<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Dashboard_model extends App_Model
{
    protected $ordersTable;
    protected $inventoryTable;
    protected $purchaseTable;

    public function __construct()
    {
        parent::__construct();

        $this->ordersTable    = db_prefix() . 'ramos_orders';
        $this->inventoryTable = db_prefix() . 'ramos_inventory_items';
        $this->purchaseTable  = db_prefix() . 'ramos_purchase_batches';

        $this->load->model('ramos/notifications_model', 'ramos_notifications');
        $this->load->model('ramos/routes_model', 'routes_model');

        if (!function_exists('time_ago')) {
            $this->load->helper('func');
        }
    }

    public function build_snapshot(?string $routeDate = null): array
    {
        $routeDate = $this->sanitizeDate($routeDate) ?? date('Y-m-d');

        $orders        = $this->summarize_orders();
        $inventory     = $this->summarize_inventory();
        $purchases     = $this->summarize_purchases();
        $routes        = $this->summarize_routes($routeDate);
        $notifications = $this->summarize_notifications();

        return [
            'generated_at'  => date('c'),
            'orders'        => $orders,
            'inventory'     => $inventory,
            'purchases'     => $purchases,
            'routes'        => $routes,
            'notifications' => $notifications,
        ];
    }

    protected function summarize_orders(): array
    {
        $statusCounts = [];

        foreach (array_keys(ramos_order_statuses()) as $status) {
            $statusCounts[$status] = 0;
        }

        $results = $this->db
            ->select('status, COUNT(*) as total')
            ->from($this->ordersTable)
            ->group_by('status')
            ->get()
            ->result_array();

        $grandTotal = 0;
        foreach ($results as $row) {
            $status                        = $row['status'];
            $count                         = (int) $row['total'];
            if (!array_key_exists($status, $statusCounts)) {
                $statusCounts[$status] = 0;
            }
            $statusCounts[$status]         = $count + ($statusCounts[$status] ?? 0);
            $grandTotal                   += $count;
        }

        $todayCount = (int) $this->db
            ->where('DATE(created_at)', date('Y-m-d'))
            ->count_all_results($this->ordersTable);

        return [
            'total'         => $grandTotal,
            'statuses'      => $statusCounts,
            'today'         => $todayCount,
            'ready_percent' => $grandTotal > 0 ? round(($statusCounts[RAMOS_ORDER_STATUS_READY] / $grandTotal) * 100, 1) : 0,
        ];
    }

    protected function summarize_inventory(): array
    {
        $items = $this->db
            ->select('id, item_name, sku, quantity, safety_stock, buffer_percent, unit')
            ->from($this->inventoryTable)
            ->where('active', 1)
            ->get()
            ->result_array();

        $alerts = [];

        foreach ($items as $item) {
            $status = ramos_inventory_status(
                (float) $item['quantity'],
                (float) $item['safety_stock'],
                (float) $item['buffer_percent']
            );

            if (!in_array($status, ['yellow', 'red'], true)) {
                continue;
            }

            $alerts[] = [
                'id'           => (int) $item['id'],
                'item_name'    => $item['item_name'],
                'quantity'     => (float) $item['quantity'],
                'unit'         => $item['unit'],
                'safety_stock' => (float) $item['safety_stock'],
                'status'       => $status,
                'status_label' => ramos_inventory_status_label($status),
                'status_class' => ramos_inventory_status_badge_class($status),
            ];
        }

        usort($alerts, static function ($a, $b) {
            $weight = ['red' => 1, 'yellow' => 2];
            return ($weight[$a['status']] ?? 3) <=> ($weight[$b['status']] ?? 3);
        });

        return [
            'low_count' => count($alerts),
            'alerts'    => array_slice($alerts, 0, 6),
        ];
    }

    protected function summarize_purchases(): array
    {
        $pendingStatuses = ['draft', 'sent', 'partial'];

        $pendingTotal = (int) $this->db
            ->where_in('status', $pendingStatuses)
            ->count_all_results($this->purchaseTable);

        $threshold = date('Y-m-d H:i:s', time() - (RAMOS_PURCHASE_DELAY_THRESHOLD_HOURS * 3600));

        $delayed = $this->db
            ->select('b.id, b.batch_code, b.status, b.sent_at, b.created_at, s.supplier_name')
            ->from($this->purchaseTable . ' b')
            ->join(db_prefix() . 'ramos_suppliers s', 's.id = b.supplier_id', 'left')
            ->where_in('b.status', ['sent', 'partial'])
            ->where('b.sent_at IS NOT NULL', null, false)
            ->where('b.sent_at <', $threshold)
            ->order_by('b.sent_at', 'ASC')
            ->limit(10)
            ->get()
            ->result_array();

        $activeDelayed = [];
        foreach ($delayed as $batch) {
            $activeDelayed[] = (int) $batch['id'];

            $title = _l('ramos_notifications_po_delayed_title', $batch['batch_code']);
            $message = _l('ramos_notifications_po_delayed_body', time_ago($batch['sent_at']));

            $this->ramos_notifications->ensure('purchase_delayed', $title, $message, [
                'severity'     => 'warning',
                'context_type' => 'purchase_batch',
                'context_id'   => (int) $batch['id'],
                'metadata'     => [
                    'status' => $batch['status'],
                    'sent_at'=> $batch['sent_at'],
                ],
            ]);
        }

        $this->ramos_notifications->resolve_missing_contexts('purchase_delayed', $activeDelayed);

        $recent = $this->db
            ->select('b.id, b.batch_code, b.status, b.created_at, s.supplier_name')
            ->from($this->purchaseTable . ' b')
            ->join(db_prefix() . 'ramos_suppliers s', 's.id = b.supplier_id', 'left')
            ->order_by('b.created_at', 'DESC')
            ->limit(5)
            ->get()
            ->result_array();

        return [
            'pending_total' => $pendingTotal,
            'delayed'       => $delayed,
            'recent'        => $recent,
        ];
    }

    protected function summarize_routes(string $routeDate): array
    {
        $routes = $this->routes_model->get_routes($routeDate);

        $stateCounts = [
            'ready'   => 0,
            'partial' => 0,
            'delayed' => 0,
            'empty'   => 0,
        ];

        $activeDelayed = [];

        foreach ($routes as &$route) {
            $state = $this->routes_model->determine_board_state(
                (int) ($route['total_stops'] ?? 0),
                (int) ($route['completed_stops'] ?? 0)
            );

            $route['board_state'] = $state;

            $key = $state['key'] ?? 'empty';
            if (!isset($stateCounts[$key])) {
                $stateCounts[$key] = 0;
            }
            $stateCounts[$key] = ($stateCounts[$key] ?? 0) + 1;

            if ($this->should_trigger_route_alert($route, $state)) {
                $activeDelayed[] = (int) $route['id'];

                $title = _l('ramos_notifications_route_delayed_title', $route['vehicle_label'] ?? '#' . $route['id']);
                $message = _l('ramos_notifications_route_delayed_body', $routeDate);

                $this->ramos_notifications->ensure('route_delayed', $title, $message, [
                    'severity'     => 'danger',
                    'context_type' => 'route',
                    'context_id'   => (int) $route['id'],
                    'metadata'     => [
                        'pending'  => (int) $route['pending_stops'],
                        'start_at' => $route['start_time'],
                    ],
                ]);
            }
        }
        unset($route);

        $this->ramos_notifications->resolve_missing_contexts('route_delayed', $activeDelayed);

        return [
            'date'    => $routeDate,
            'counts'  => $stateCounts,
            'total'   => count($routes),
            'routes'  => $routes,
        ];
    }

    protected function summarize_notifications(): array
    {
        $records = $this->ramos_notifications->get_recent(15);

        $formatted = [];

        foreach ($records as $record) {
            $formatted[] = [
                'id'             => (int) $record['id'],
                'type'           => $record['type'],
                'title'          => $record['title'],
                'message'        => $record['message'],
                'severity'       => $record['severity'],
                'time_ago'       => time_ago($record['created_at']),
                'acknowledged_at'=> $record['acknowledged_at'],
                'resolved_at'    => $record['resolved_at'],
            ];
        }

        return $formatted;
    }

    protected function should_trigger_route_alert(array $route, array $state): bool
    {
        if (($state['key'] ?? '') !== 'delayed') {
            return false;
        }

        $totalStops = (int) ($route['total_stops'] ?? 0);
        if ($totalStops === 0) {
            return false;
        }

        $routeDate = $route['route_date'] ?? null;
        if (!$routeDate) {
            return false;
        }

        $startTime = $route['start_time'] ?? '00:00:00';
        $startTimestamp = strtotime($routeDate . ' ' . $startTime);

        if ($routeDate < date('Y-m-d')) {
            return true;
        }

        if ($startTimestamp === false) {
            return false;
        }

        $grace = RAMOS_ROUTE_DELAY_THRESHOLD_MINUTES * 60;

        return $startTimestamp <= (time() - $grace);
    }

    protected function sanitizeDate(?string $date): ?string
    {
        $date = trim((string) $date);
        if ($date === '') {
            return null;
        }

        $dt = DateTime::createFromFormat('Y-m-d', $date);
        if ($dt && $dt->format('Y-m-d') === $date) {
            return $date;
        }

        return null;
    }
}
