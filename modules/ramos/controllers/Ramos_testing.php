<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * CLI utilities for Ramos Module 14 regression/testing.
 *
 * Usage examples:
 *  php index.php ramos/ramos_testing snapshot
 *  php index.php ramos/ramos_testing snapshot 2024-05-20
 */
class Ramos_testing extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        if (!is_cli()) {
            echo 'Ramos testing tools are CLI-only.' . PHP_EOL;
            exit(1);
        }

        $this->load->model('ramos/dashboard_model', 'dashboard_model');
        $this->load->model('ramos/orders_model', 'orders_model');
        $this->load->model('ramos/purchase_model', 'ramos_purchase_model');
        $this->load->model('ramos/routes_model', 'routes_model');
    }

    /**
     * Snapshot health-check that inspects dashboard metrics and returns warnings.
     *
     * @param string|null $routeDate
     * @return void
     */
    public function snapshot($routeDate = null): void
    {
        $snapshot = $this->dashboard_model->build_snapshot($routeDate);
        $issues   = $this->evaluate_snapshot($snapshot);

        $payload = [
            'status'      => empty($issues) ? 'ok' : 'warnings',
            'generated_at'=> $snapshot['generated_at'] ?? date('c'),
            'route_date'  => $snapshot['routes']['date'] ?? ($routeDate ?: date('Y-m-d')),
            'issues'      => $issues,
            'orders'      => $snapshot['orders'] ?? [],
            'inventory'   => [
                'low_count' => $snapshot['inventory']['low_count'] ?? 0,
                'alerts'    => $snapshot['inventory']['alerts'] ?? [],
            ],
            'purchases'   => [
                'pending_total' => $snapshot['purchases']['pending_total'] ?? 0,
                'delayed'       => $snapshot['purchases']['delayed'] ?? [],
            ],
            'routes'      => [
                'total'  => $snapshot['routes']['total'] ?? 0,
                'counts' => $snapshot['routes']['counts'] ?? [],
            ],
        ];

        log_message('info', 'Ramos snapshot QA: ' . json_encode([
            'status' => $payload['status'],
            'issues' => array_column($issues, 'code'),
        ]));

        $this->output_json($payload, empty($issues) ? 0 : 2);
    }

    /**
     * Evaluate snapshot data and surface warnings.
     *
     * @param  array $snapshot
     * @return array
     */
    protected function evaluate_snapshot(array $snapshot): array
    {
        $issues = [];

        $ordersTotal = (int) ($snapshot['orders']['total'] ?? 0);
        if ($ordersTotal === 0) {
            $issues[] = [
                'code'    => 'orders_empty',
                'message' => 'No Ramos orders detected. Seed sample data before regression.',
            ];
        }

        $lowStock = (int) ($snapshot['inventory']['low_count'] ?? 0);
        if ($lowStock > 0) {
            $issues[] = [
                'code'    => 'inventory_low_stock',
                'message' => sprintf('%d inventory items below safety stock.', $lowStock),
                'details' => array_column($snapshot['inventory']['alerts'] ?? [], 'item_name'),
            ];
        }

        $delayedPurchases = $snapshot['purchases']['delayed'] ?? [];
        if (!empty($delayedPurchases)) {
            $issues[] = [
                'code'    => 'purchase_delays',
                'message' => sprintf('%d purchase batches delayed.', count($delayedPurchases)),
                'details' => array_column($delayedPurchases, 'batch_code'),
            ];
        }

        $routeCounts = $snapshot['routes']['counts'] ?? [];
        $delayedRoutes = (int) ($routeCounts['delayed'] ?? 0);
        if ($delayedRoutes > 0) {
            $issues[] = [
                'code'    => 'route_delays',
                'message' => sprintf('%d routes delayed.', $delayedRoutes),
            ];
        }

        $notifications = $snapshot['notifications'] ?? [];
        foreach ($notifications as $notification) {
            if (!empty($notification['severity']) && in_array($notification['severity'], ['danger', 'warning'], true)) {
                $issues[] = [
                    'code'    => 'notification_' . $notification['severity'],
                    'message' => sprintf('Notification pending: %s', $notification['title']),
                ];
            }
        }

        return $issues;
    }

    protected function output_json(array $payload, int $exitCode = 0): void
    {
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit($exitCode);
    }
}
