<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Routes_model extends App_Model
{
    protected $routesTable;
    protected $stopsTable;

    public function __construct()
    {
        parent::__construct();
        $this->routesTable = db_prefix() . 'ramos_routes';
        $this->stopsTable  = db_prefix() . 'ramos_route_stops';
    }

    public function get_routes(?string $date = null): array
    {
        if ($date) {
            $this->db->where('route_date', $date);
        }

        $routes = $this->db
            ->order_by('route_date', 'ASC')
            ->order_by('start_time', 'ASC')
            ->get($this->routesTable)
            ->result_array();

        if (empty($routes)) {
            return [];
        }

        $routeIds = array_column($routes, 'id');

        $counts = $this->db
            ->select('route_id, COUNT(*) as total_stops, SUM(CASE WHEN status = \'completed\' THEN 1 ELSE 0 END) as completed_stops', false)
            ->from($this->stopsTable)
            ->where_in('route_id', $routeIds)
            ->group_by('route_id')
            ->get()
            ->result_array();

        $countMap = [];
        foreach ($counts as $row) {
            $routeId = (int) $row['route_id'];
            $countMap[$routeId] = [
                'total'     => (int) $row['total_stops'],
                'completed' => (int) ($row['completed_stops'] ?? 0),
            ];
        }

        foreach ($routes as &$route) {
            $metrics = $countMap[(int) $route['id']] ?? ['total' => 0, 'completed' => 0];
            $route['total_stops']     = $metrics['total'];
            $route['completed_stops'] = $metrics['completed'];
            $route['pending_stops']   = max(0, $metrics['total'] - $metrics['completed']);

            if (!empty($route['start_time'])) {
                $route['start_time'] = substr((string) $route['start_time'], 0, 5);
            }
        }
        unset($route);

        return $routes;
    }

    public function get_route($routeId): array
    {
        $route = $this->db->get_where($this->routesTable, ['id' => (int) $routeId])->row_array();
        return $route ?: [];
    }

    public function get_route_stops($routeId): array
    {
        // COMMENTED: Ramos orders - replaced with omni_sales orders
        // return $this->db
        //     ->select('rs.*, o.order_number, o.customer_name, o.delivery_address, o.priority, o.delivery_datetime')
        //     ->from($this->stopsTable . ' rs')
        //     ->join(db_prefix() . 'ramos_orders o', 'o.id = rs.order_id', 'left')
        //     ->where('rs.route_id', (int) $routeId)
        //     ->order_by('rs.stop_number', 'ASC')
        //     ->get()
        //     ->result_array();

        // NEW: Get route stops from both omni_sales (tblcart) and ERP portal (tblinvoices) orders
        $stops = $this->db
            ->select('rs.*')
            ->from($this->stopsTable . ' rs')
            ->where('rs.route_id', (int) $routeId)
            ->order_by('rs.stop_number', 'ASC')
            ->get()
            ->result_array();

        // For each stop, get the order details from the appropriate source
        foreach ($stops as &$stop) {
            // Try to get from omni_sales (tblcart) first
            $omni_order = $this->db
                ->select('c.order_number, c.phonenumber as customer_name, c.address as delivery_address, c.duedate as delivery_datetime')
                ->from(db_prefix() . 'cart c')
                ->where('c.id', (int) $stop['order_id'])
                ->get()
                ->row_array();

            if ($omni_order) {
                $stop = array_merge($stop, $omni_order);
            } else {
                // Fall back to ERP invoice (tblinvoices)
                $erp_order = $this->db
                    ->select('i.number as order_number, cl.company as customer_name, cl.shipping_street as delivery_address, i.duedate as delivery_datetime')
                    ->from(db_prefix() . 'invoices i')
                    ->join(db_prefix() . 'clients cl', 'cl.userid = i.clientid', 'left')
                    ->where('i.id', (int) $stop['order_id'])
                    ->get()
                    ->row_array();

                if ($erp_order) {
                    $stop = array_merge($stop, $erp_order);
                }
            }
        }
        unset($stop);

        return $stops;
    }

    public function update_route_status($routeId, string $status): bool
    {
        $routeId = (int) $routeId;
        if ($routeId <= 0) {
            return false;
        }

        $statuses = ramos_route_statuses();
        if (!array_key_exists($status, $statuses)) {
            return false;
        }

        $data = [
            'status' => $status,
        ];

        if ($status === 'dispatched') {
            $data['notes'] = $this->append_note($routeId, _l('ramos_routes_note_dispatched')); 
        } elseif ($status === 'completed') {
            $data['notes'] = $this->append_note($routeId, _l('ramos_routes_note_completed'));
        }

        $this->db->where('id', $routeId);
        $updated = $this->db->update($this->routesTable, $data);

        if ($updated && $status === 'completed') {
            $this->db->where('route_id', $routeId);
            $this->db->update($this->stopsTable, ['status' => 'completed']);
        }

        return $updated;
    }

    protected function append_note(int $routeId, string $note): string
    {
        $route = $this->get_route($routeId);
        $existing = $route['notes'] ?? '';
        $entry = '[' . date('Y-m-d H:i') . '] ' . $note;
        if ($existing) {
            return $existing . PHP_EOL . $entry;
        }
        return $entry;
    }

    public function generate_routes(string $date, string $startTime, int $maxStops, string $prefix = 'Route'): array
    {
        $date = $date ?: date('Y-m-d');
        $maxStops = max(1, (int) $maxStops);

        // COMMENTED: Ramos orders - replaced with omni_sales orders
        // $orders = $this->db
        //     ->select('o.id, o.order_number, o.customer_name, o.delivery_address, o.priority, o.delivery_datetime')
        //     ->from(db_prefix() . 'ramos_orders o')
        //     ->join($this->stopsTable . ' rs', 'rs.order_id = o.id', 'left')
        //     ->where('o.status', RAMOS_ORDER_STATUS_READY)
        //     ->where('rs.id IS NULL', null, false)
        //     ->group_start()
        //         ->where('o.delivery_datetime IS NULL', null, false)
        //         ->or_where('DATE(o.delivery_datetime) = ' . $this->db->escape($date), null, false)
        //     ->group_end()
        //     ->order_by("FIELD(o.priority, 'high','normal','low')", '', false)
        //     ->order_by('o.delivery_datetime IS NULL', 'ASC', false)
        //     ->order_by('o.delivery_datetime', 'ASC')
        //     ->order_by('o.id', 'ASC')
        //     ->get()
        //     ->result_array();

        // NEW: Combined query for both omni_sales (tblcart) and ERP portal (tblinvoices) orders
        // Get orders that are not yet in routes and match the delivery date
        // Also get customer Zona for grouping routes by zone
        
        // Query 1: Omni_sales orders (tblcart)
        $this->db->select('c.id, c.order_number, c.phonenumber as customer_name, c.address as delivery_address, c.duedate as delivery_datetime, c.userid, "omni_sales" as order_source');
        $this->db->select('cfv.value as zona', false);
        $this->db->from(db_prefix() . 'cart c');
        $this->db->join($this->stopsTable . ' rs', 'rs.order_id = c.id', 'left');
        $this->db->join(db_prefix() . 'customfieldsvalues cfv', 'cfv.relid = c.userid AND cfv.fieldid = 3', 'left'); // fieldid 3 = Zona
        $this->db->where('c.status !=', 5); // Exclude cancelled orders
        $this->db->where('c.channel_id IN (1,2,4,6)', null, false); // Valid sales channels
        $this->db->where('c.original_order_id IS NULL', null, false); // Exclude return orders
        $this->db->where('rs.id IS NULL', null, false); // Not already in routes
        $this->db->group_start();
            $this->db->where('c.duedate IS NULL', null, false);
            $this->db->or_where('DATE(c.duedate) = ' . $this->db->escape($date), null, false);
        $this->db->group_end();
        
        $omni_orders = $this->db->get()->result_array();

        // Query 2: ERP portal orders (tblinvoices)
        // Only include invoices that are unpaid/partially paid and have a due date matching the specified date
        $this->db->select('i.id, i.number as order_number, cl.company as customer_name, cl.shipping_street as address, i.duedate as delivery_datetime, i.clientid as userid, "erp_invoice" as order_source');
        $this->db->select('COALESCE(i.zone, "' . _l('ramos_routes_no_zone') . '") as zona', false); // Use ERP zone if set, otherwise "No Zone"
        $this->db->select('COALESCE(i.priority, 5) as priority', false); // Use ERP priority if set, otherwise 5 (normal)
        $this->db->from(db_prefix() . 'invoices i');
        $this->db->join(db_prefix() . 'clients cl', 'cl.userid = i.clientid', 'left');
        $this->db->join($this->stopsTable . ' rs', 'rs.order_id = i.id', 'left');
        $this->db->where('i.status', 1); // Unpaid invoices (status = 1 is unpaid)
        $this->db->where('rs.id IS NULL', null, false); // Not already in routes
        $this->db->where("i.clientnote LIKE '%portal%' OR i.clientnote LIKE '%customer%'", null, false); // Only portal-created invoices
        $this->db->group_start();
            $this->db->where('i.duedate IS NULL', null, false);
            $this->db->or_where('DATE(i.duedate) = ' . $this->db->escape($date), null, false);
        $this->db->group_end();
        
        $erp_orders = $this->db->get()->result_array();

        // Combine both order sources
        $orders = array_merge($omni_orders, $erp_orders);

        // Sort combined orders by zona, priority, duedate, and id
        if (!empty($orders)) {
            usort($orders, function($a, $b) {
                // Sort by zona first
                $zonaCompare = strcmp($a['zona'] ?? '', $b['zona'] ?? '');
                if ($zonaCompare !== 0) {
                    return $zonaCompare;
                }
                
                // Then by priority (lower number = higher priority)
                $aPriority = (int) ($a['priority'] ?? 5);
                $bPriority = (int) ($b['priority'] ?? 5);
                if ($aPriority !== $bPriority) {
                    return $aPriority <=> $bPriority;
                }
                
                // Then by duedate (nulls first)
                $aHasDate = !empty($a['delivery_datetime']);
                $bHasDate = !empty($b['delivery_datetime']);
                if ($aHasDate && $bHasDate) {
                    return strtotime($a['delivery_datetime']) <=> strtotime($b['delivery_datetime']);
                }
                if ($aHasDate !== $bHasDate) {
                    return $bHasDate ? 1 : -1;
                }
                
                // Finally by ID
                return $a['id'] <=> $b['id'];
            });
        }

        if (empty($orders)) {
            return [];
        }

        // Group orders by zone first, then chunk each zone's orders
        $ordersByZone = [];
        foreach ($orders as $order) {
            $zone = trim((string) ($order['zona'] ?? ''));
            if ($zone === '') {
                $zone = _l('ramos_routes_no_zone');
            }
            $ordersByZone[$zone][] = $order;
        }

        $routesCreated = [];
        $startTimestamp = strtotime($date . ' ' . ($startTime ?: '08:00:00'));
        $routeIndex = 0;

        $this->db->trans_start();

        // Process each zone separately
        foreach ($ordersByZone as $zoneName => $zoneOrders) {
            // Chunk orders within this zone
            $chunks = array_chunk($zoneOrders, $maxStops);

            foreach ($chunks as $chunkIndex => $chunk) {
                $baseStartTimestamp = $startTimestamp ? strtotime('+' . $routeIndex . ' hour', $startTimestamp) : null;
                $earliestDelivery   = null;

                foreach ($chunk as $order) {
                    if (!empty($order['delivery_datetime'])) {
                        $deliveryTs = strtotime($order['delivery_datetime']);
                        if ($deliveryTs !== false && date('Y-m-d', $deliveryTs) === $date) {
                            if ($earliestDelivery === null || $deliveryTs < $earliestDelivery) {
                                $earliestDelivery = $deliveryTs;
                            }
                        }
                    }
                }

                if ($earliestDelivery !== null && $baseStartTimestamp !== null) {
                    $routeStartTimestamp = min($earliestDelivery, $baseStartTimestamp);
                } else {
                    $routeStartTimestamp = $earliestDelivery ?? $baseStartTimestamp;
                }

                $routeStart = $routeStartTimestamp ? date('H:i:s', $routeStartTimestamp) : null;

                // Include zone name in route label if more than one chunk for this zone
                $zoneLabel = count($chunks) > 1
                    ? $zoneName . ' ' . ($chunkIndex + 1)
                    : $zoneName;

                $routeData = [
                    'route_date'    => $date,
                    'start_time'    => $routeStart,
                    'vehicle_label' => trim($prefix . ' - ' . $zoneLabel),
                    'capacity'      => $maxStops,
                    'status'        => 'draft',
                    'created_by'    => get_staff_user_id(),
                    'created_at'    => date('Y-m-d H:i:s'),
                ];

                $this->db->insert($this->routesTable, $routeData);
                $routeId = (int) $this->db->insert_id();

                if ($routeId <= 0) {
                    continue;
                }

                $baseEtaTimestamp = $routeStartTimestamp;

                foreach ($chunk as $position => $order) {
                    $etaTimestamp = null;

                    if (!empty($order['delivery_datetime'])) {
                        $deliveryTs = strtotime($order['delivery_datetime']);
                        if ($deliveryTs !== false && date('Y-m-d', $deliveryTs) === $date) {
                            $etaTimestamp = $deliveryTs;
                        }
                    }

                    if ($etaTimestamp === null && $baseEtaTimestamp !== null) {
                        $etaTimestamp = strtotime('+' . ($position * 20) . ' minutes', $baseEtaTimestamp);
                    }

                    $etaValue = $etaTimestamp ? date('Y-m-d H:i:s', $etaTimestamp) : null;

                    $this->db->insert($this->stopsTable, [
                        'route_id'    => $routeId,
                        'order_id'    => (int) $order['id'],
                        'stop_number' => $position + 1,
                        'eta'         => $etaValue,
                        'status'      => 'pending',
                    ]);

                    // Link pick items to this route stop for route-priority picking
                    $stopId = (int) $this->db->insert_id();
                    if ($stopId > 0) {
                        $this->db->where('order_id', (int) $order['id']);
                        $this->db->update(db_prefix() . 'ramos_pick_items', [
                            'route_stop_id' => $stopId
                        ]);
                    }
                }

                $routesCreated[] = $routeId;
                $routeIndex++;
            }
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            return [];
        }

        return $routesCreated;
    }

    public function get_routes_for_board(?string $date = null): array
    {
        $routes = $this->get_routes($date);

        if (empty($routes)) {
            return [];
        }

        foreach ($routes as &$route) {
            $route['board_state'] = $this->determine_board_state(
                (int) ($route['total_stops'] ?? 0),
                (int) ($route['completed_stops'] ?? 0)
            );
            $route['stops'] = $this->get_route_stops((int) $route['id']);
        }
        unset($route);

        return $routes;
    }

    public function move_stop(int $stopId, int $originRouteId, int $destinationRouteId, array $orderedStopIds): bool
    {
        $stopId             = (int) $stopId;
        $destinationRouteId = (int) $destinationRouteId;
        $originRouteId      = (int) $originRouteId;

        if ($stopId <= 0 || $destinationRouteId <= 0 || empty($orderedStopIds)) {
            return false;
        }

        $orderedStopIds = array_values(array_unique(array_map('intval', $orderedStopIds)));

        if (!in_array($stopId, $orderedStopIds, true)) {
            return false;
        }

        $current = $this->db->get_where($this->stopsTable, ['id' => $stopId])->row_array();
        if (!$current) {
            return false;
        }

        $currentRouteId = (int) $current['route_id'];

        $this->db->trans_start();

        if ($currentRouteId !== $destinationRouteId) {
            $this->db->where('id', $stopId);
            $this->db->update($this->stopsTable, ['route_id' => $destinationRouteId]);
        }

        foreach ($orderedStopIds as $index => $id) {
            $this->db->where('id', $id);
            $this->db->update($this->stopsTable, [
                'stop_number' => $index + 1,
                'route_id'    => $destinationRouteId,
            ]);

            // NEW: Update pick items to link to this route stop
            // This enables route-priority picking where items are picked in delivery order
            $stop = $this->db->get_where($this->stopsTable, ['id' => $id])->row_array();
            if ($stop) {
                $this->db->where('order_id', (int) $stop['order_id']);
                $this->db->update(db_prefix() . 'ramos_pick_items', [
                    'route_stop_id' => $id
                ]);
            }
        }

        if ($currentRouteId > 0 && $currentRouteId !== $destinationRouteId) {
            $this->reindex_route_stops($currentRouteId);
        }

        $this->reindex_route_stops($destinationRouteId);

        $this->db->trans_complete();

        return $this->db->trans_status();
    }

    public function get_route_summary(int $routeId): array
    {
        $routeId = (int) $routeId;
        if ($routeId <= 0) {
            return [];
        }

        $route = $this->get_route($routeId);
        if (empty($route)) {
            return [];
        }

        if (!empty($route['start_time'])) {
            $route['start_time'] = substr((string) $route['start_time'], 0, 5);
        }

        $counts = $this->db
            ->select('COUNT(*) as total_stops, SUM(CASE WHEN status = \'completed\' THEN 1 ELSE 0 END) as completed_stops', false)
            ->from($this->stopsTable)
            ->where('route_id', $routeId)
            ->get()
            ->row_array();

        $total     = (int) ($counts['total_stops'] ?? 0);
        $completed = (int) ($counts['completed_stops'] ?? 0);
        $pending   = max(0, $total - $completed);

        $route['total_stops']     = $total;
        $route['completed_stops'] = $completed;
        $route['pending_stops']   = $pending;
        $route['board_state']     = $this->determine_board_state($total, $completed);

        return [
            'id'               => (int) $route['id'],
            'vehicle_label'    => $route['vehicle_label'],
            'start_time'       => $route['start_time'],
            'route_date'       => $route['route_date'],
            'capacity'         => (int) $route['capacity'],
            'total_stops'      => $route['total_stops'],
            'completed_stops'  => $route['completed_stops'],
            'pending_stops'    => $route['pending_stops'],
            'board_state'      => $route['board_state'],
        ];
    }

    protected function reindex_route_stops(int $routeId): void
    {
        $routeId = (int) $routeId;
        if ($routeId <= 0) {
            return;
        }

        $stops = $this->db
            ->select('id')
            ->from($this->stopsTable)
            ->where('route_id', $routeId)
            ->order_by('stop_number', 'ASC')
            ->order_by('id', 'ASC')
            ->get()
            ->result_array();

        $position = 1;
        foreach ($stops as $stop) {
            $this->db->where('id', (int) $stop['id']);
            $this->db->update($this->stopsTable, ['stop_number' => $position++]);
        }
    }

    public function determine_board_state(int $totalStops, int $completedStops): array
    {
        $pending = max(0, $totalStops - $completedStops);

        if ($totalStops === 0) {
            $key   = 'empty';
            $label = _l('ramos_routes_board_state_empty');
            $badge = 'label-default';
        } elseif ($pending === 0) {
            $key   = 'ready';
            $label = _l('ramos_routes_board_state_ready');
            $badge = 'label-success';
        } elseif ($completedStops > 0) {
            $key   = 'partial';
            $label = _l('ramos_routes_board_state_partial');
            $badge = 'label-warning';
        } else {
            $key   = 'delayed';
            $label = _l('ramos_routes_board_state_delayed');
            $badge = 'label-danger';
        }

        return [
            'key'         => $key,
            'label'       => $label,
            'badge_class' => $badge,
        ];
    }

    /**
     * Get route delivery sheet with product details for each stop
     *
     * @param int $routeId
     * @return array
     */
    public function get_route_delivery_sheet(int $routeId): array
    {
        $routeId = (int) $routeId;

        if ($routeId <= 0) {
            return [];
        }

        // COMMENTED: Ramos orders - replaced with omni_sales orders
        // Get route stops with order information
        // $this->db->select('rs.id, rs.stop_number, rs.eta, o.id as order_id, o.order_number, o.customer_name, o.delivery_address');
        // $this->db->from($this->stopsTable . ' rs');
        // $this->db->join(db_prefix() . 'ramos_orders o', 'o.id = rs.order_id', 'inner');
        // $this->db->where('rs.route_id', $routeId);
        // $this->db->order_by('rs.stop_number', 'ASC');

        // NEW: Using omni_sales orders (tblcart)
        // Include customer Zona for grouping in delivery sheet
        $this->db->select('rs.id, rs.stop_number, rs.eta, c.id as order_id, c.order_number, c.phonenumber as customer_name, c.address as delivery_address, c.userid');
        $this->db->select('cfv.value as zona', false);
        $this->db->from($this->stopsTable . ' rs');
        $this->db->join(db_prefix() . 'cart c', 'c.id = rs.order_id', 'inner');
        $this->db->join(db_prefix() . 'customfieldsvalues cfv', 'cfv.relid = c.userid AND cfv.fieldid = 3', 'left'); // fieldid 3 = Zona
        $this->db->where('rs.route_id', $routeId);
        $this->db->order_by('cfv.value', 'ASC'); // Group by zona first
        $this->db->order_by('rs.stop_number', 'ASC');

        $stops = $this->db->get()->result_array();

        if (empty($stops)) {
            return [];
        }

        // COMMENTED: Ramos order items - replaced with omni_sales cart items
        // For each stop, get the order items (products)
        // foreach ($stops as &$stop) {
        //     $orderId = (int) $stop['order_id'];
        //
        //     $this->db->select('oi.quantity, ii.item_name, ii.unit');
        //     $this->db->from(db_prefix() . 'ramos_order_items oi');
        //     $this->db->join(db_prefix() . 'ramos_inventory_items ii', 'ii.id = oi.inventory_item_id', 'left');
        //     $this->db->where('oi.order_id', $orderId);
        //     $this->db->order_by('oi.id', 'ASC');
        //
        //     $products = $this->db->get()->result_array();
        //
        //     $stop['products'] = [];
        //     foreach ($products as $product) {
        //         $stop['products'][] = [
        //             'item_name' => $product['item_name'] ?? 'Unknown Item',
        //             'quantity'  => (float) $product['quantity'],
        //             'unit'      => $product['unit'] ?? 'pz'
        //         ];
        //     }
        // }

        // NEW: Using omni_sales cart items (tblcart_detailt)
        foreach ($stops as &$stop) {
            $orderId = (int) $stop['order_id'];

            $this->db->select('cd.quantity, i.description as item_name, u.unit_name as unit');
            $this->db->from(db_prefix() . 'cart_detailt cd');
            $this->db->join(db_prefix() . 'items i', 'i.id = cd.product_id', 'left');
            $this->db->join(db_prefix() . 'ware_unit_type u', 'u.unit_type_id = i.unit_id', 'left');
            $this->db->where('cd.cart_id', $orderId);
            $this->db->order_by('cd.id', 'ASC');

            $products = $this->db->get()->result_array();

            $stop['products'] = [];
            foreach ($products as $product) {
                $stop['products'][] = [
                    'item_name' => $product['item_name'] ?? 'Unknown Item',
                    'quantity'  => (float) $product['quantity'],
                    'unit'      => $product['unit'] ?? 'pz'
                ];
            }
        }
        unset($stop);

        return $stops;
    }
}
