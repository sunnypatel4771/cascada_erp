<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Ramos Automation Helper
 *
 * Provides standalone functions for scheduled and manual automation execution.
 * These are called by:
 *   - The after_cron_run hook in ramos.php  (scheduled runs)
 *   - Automation::run() controller           (manual AJAX runs)
 *   - Scheduler::_execute_automation()       (legacy scheduler controller)
 */

/**
 * Check if scheduled automation should run right now.
 *
 * Conditions:
 *  1. Automation is enabled
 *  2. Current hour & minute match configured schedule
 *  3. Has not already run today
 *  4. If not "run daily", today's weekday must match configured day
 *
 * @return bool
 */
function ramos_should_run_scheduled_automation(): bool
{
    if (get_option('ramos_automation_schedule_enabled') !== '1') {
        return false;
    }

    $startHour    = (int) get_option('ramos_automation_schedule_hour', 8);
    $startMinutes = (int) get_option('ramos_automation_schedule_minutes', 0);
    $endHour      = (int) get_option('ramos_automation_schedule_end_hour', 18);
    $endMinutes   = (int) get_option('ramos_automation_schedule_end_minutes', 0);
    $runDaily     = get_option('ramos_automation_schedule_run_daily') === '1';
    $scheduledDate = strtolower(trim((string) get_option('ramos_automation_schedule_date', '')));

    // Use the app's configured timezone
    $appTimezone = get_option('default_timezone');
    $now = !empty($appTimezone)
        ? new DateTime('now', new DateTimeZone($appTimezone))
        : new DateTime('now');
    $currentHour   = (int) $now->format('G');
    $currentMinute = (int) $now->format('i');

    $startTotal   = $startHour   * 60 + $startMinutes;
    $endTotal     = $endHour     * 60 + $endMinutes;
    $currentTotal = $currentHour * 60 + $currentMinute;

    // Build the list of trigger minutes: start, start+30, start+60, … up to and including end
    $triggerMinutes = [];
    for ($t = $startTotal; $t <= $endTotal; $t += 30) {
        $triggerMinutes[] = $t;
    }

    // Check if current time falls within a 6-minute window of any trigger point
    $inWindow = false;
    foreach ($triggerMinutes as $trigger) {
        if ($currentTotal >= $trigger && $currentTotal <= $trigger + 5) {
            $inWindow = true;
            break;
        }
    }
    if (!$inWindow) {
        return false;
    }

    // If not daily, check day of week
    if (!$runDaily && !empty($scheduledDate)) {
        $todayName = strtolower(date('l')); // e.g. "friday"
        if ($todayName !== $scheduledDate) {
            return false;
        }
    }

    return true;
}

/**
 * Execute the full automation process.
 *
 * Loads all required models via the CI instance, runs the purchase order
 * generation pipeline, and returns a result array.
 *
 * @param  int $runById  Staff ID triggering the run; 0 for cron/system.
 * @return array{success: bool, run_id: int|null, orders_processed: int, batches_created: int, message: string}
 */
function ramos_execute_automation(int $runById = 0): array
{
    $CI = &get_instance();

    // Load required models
    $CI->load->model('ramos/automation_model', 'ramos_automation_model');
    $CI->load->model('ramos/ramos_purchase_model', 'ramos_purchase_model');
    $CI->load->model('ramos/suppliers_model',  'ramos_suppliers_model');

    // Create the run record
    $runId = $CI->ramos_automation_model->create_run([
        'run_type' => 'purchase_generation',
        'run_by'   => $runById,
        'status'   => 'running',
    ]);

    try {
        // ── 1. Collect unprocessed orders from both sources ──────────────
        $omniOrders = $CI->ramos_automation_model->get_unprocessed_omni_orders();
        $erpOrders  = $CI->ramos_automation_model->get_unprocessed_erp_orders();

        if (empty($omniOrders) && empty($erpOrders)) {
            $CI->ramos_automation_model->complete_run($runId, [
                'status'                       => 'completed',
                'total_orders_processed'       => 0,
                'total_purchase_orders_created'=> 0,
                'notes'                        => 'No unprocessed orders found.',
            ]);

            return [
                'success'          => false,
                'run_id'           => $runId,
                'orders_processed' => 0,
                'batches_created'  => 0,
                'message'          => 'No unprocessed orders found.',
            ];
        }

        $omniOrderIds = array_column($omniOrders, 'id');
        $erpOrderIds  = array_column($erpOrders,  'id');

        // ── 2. Aggregate required quantities from both sources ────────────
        $omniQuantities = $CI->ramos_automation_model->get_required_quantities_from_omni_orders($omniOrderIds);
        $erpQuantities  = $CI->ramos_automation_model->get_required_quantities_from_erp_orders($erpOrderIds);
        $requiredQtys   = _ramos_merge_quantities($omniQuantities, $erpQuantities);

        if (empty($requiredQtys)) {
            $CI->ramos_automation_model->complete_run($runId, [
                'status'                       => 'completed',
                'total_orders_processed'       => count($omniOrderIds) + count($erpOrderIds),
                'total_purchase_orders_created'=> 0,
                'notes'                        => 'Orders found but no matching inventory items.',
            ]);

            return [
                'success'          => false,
                'run_id'           => $runId,
                'orders_processed' => count($omniOrderIds) + count($erpOrderIds),
                'batches_created'  => 0,
                'message'          => 'Orders found but no matching inventory items.',
            ];
        }

        // ── 3. Get current inventory & open purchase orders ───────────────
        $inventoryMap          = $CI->ramos_automation_model->get_warehouse_inventory_by_product();
        $openPurchaseQuantities = $CI->ramos_purchase_model->get_open_purchase_quantities(['draft', 'sent', 'partial']);

        // ── 4. Calculate deficits per supplier ────────────────────────────
        $deficitGroups = $CI->ramos_purchase_model->build_supplier_deficits(
            $requiredQtys,
            $inventoryMap,
            $openPurchaseQuantities
        );

        // ── 5. Mark all orders as processed (regardless of stock) ─────────
        if (!empty($omniOrderIds)) {
            $CI->ramos_automation_model->mark_omni_orders_as_processed($omniOrderIds, $runId);
        }
        if (!empty($erpOrderIds)) {
            $CI->ramos_automation_model->mark_erp_orders_as_processed($erpOrderIds, $runId);
        }

        $totalOrdersProcessed = count($omniOrderIds) + count($erpOrderIds);

        // ── 6. Create purchase batch for each supplier with deficits ──────
        $batchesCreated = 0;

        if (!empty($deficitGroups)) {
            // Get supplier map for enriching groups
            $suppliers   = $CI->ramos_suppliers_model->get();
            $supplierMap = [];
            foreach ($suppliers as $supplier) {
                $supplierMap[$supplier['id']] = $supplier;
            }

            // Sort groups by supplier priority (lower number = higher priority)
            uasort($deficitGroups, function ($a, $b) use ($supplierMap) {
                $suppA = isset($a['supplier_id']) ? ($supplierMap[$a['supplier_id']] ?? null) : null;
                $suppB = isset($b['supplier_id']) ? ($supplierMap[$b['supplier_id']] ?? null) : null;
                $prioA = isset($suppA['priority']) ? (int) $suppA['priority'] : 999;
                $prioB = isset($suppB['priority']) ? (int) $suppB['priority'] : 999;
                return $prioA <=> $prioB;
            });

            foreach ($deficitGroups as $supplierId => $group) {
                if (empty($group['items'])) {
                    continue;
                }

                // Map items to the format expected by create_batch
                $batchItems = [];
                foreach ($group['items'] as $item) {
                    $batchItems[] = [
                        'inventory_item_id' => $item['inventory_item_id'],
                        'requested_qty'     => $item['required_qty'],
                        'current_stock'     => $item['current_stock'],
                        'safety_stock'      => $item['safety_stock'],
                    ];
                }

                $CI->ramos_purchase_model->create_batch($supplierId ?: null, $batchItems);
                $batchesCreated++;
            }
        }

        // ── 7. Complete the run record ─────────────────────────────────────
        $CI->ramos_automation_model->complete_run($runId, [
            'status'                       => 'completed',
            'total_orders_processed'       => $totalOrdersProcessed,
            'total_purchase_orders_created'=> $batchesCreated,
            'notes'                        => $batchesCreated > 0
                ? "Created {$batchesCreated} purchase batch(es) from {$totalOrdersProcessed} order(s)."
                : "Sufficient stock — no purchase orders needed. Processed {$totalOrdersProcessed} order(s).",
        ]);

        return [
            'success'          => true,
            'run_id'           => $runId,
            'orders_processed' => $totalOrdersProcessed,
            'batches_created'  => $batchesCreated,
            'message'          => "Processed {$totalOrdersProcessed} order(s), created {$batchesCreated} purchase batch(es).",
        ];

    } catch (Exception $e) {
        $CI->ramos_automation_model->complete_run($runId, [
            'status' => 'failed',
            'notes'  => $e->getMessage(),
        ]);

        return [
            'success'          => false,
            'run_id'           => $runId,
            'orders_processed' => 0,
            'batches_created'  => 0,
            'message'          => $e->getMessage(),
        ];
    }
}

/**
 * Generate delivery routes for today using configured defaults.
 *
 * @return array{success: bool, route_ids: array, routes_count: int, message: string}
 */
function ramos_generate_routes_for_today(): array
{
    $CI = &get_instance();
    $CI->load->model('ramos/routes_model', 'routes_model');

    $appTimezone = get_option('default_timezone');
    $tz          = !empty($appTimezone) ? new DateTimeZone($appTimezone) : null;
    $date        = $tz ? (new DateTime('now', $tz))->format('Y-m-d') : date('Y-m-d');
    $startTime = get_option('ramos_default_route_start_time') ?: '08:00:00';
    $maxStops  = (int) get_option('ramos_default_max_stops') ?: 10;
    $prefix    = get_option('ramos_default_route_prefix') ?: 'Route';

    try {
        $routeIds = $CI->routes_model->generate_routes($date, $startTime, $maxStops, $prefix);

        $count = is_array($routeIds) ? count($routeIds) : 0;

        return [
            'success'      => true,
            'route_ids'    => is_array($routeIds) ? $routeIds : [],
            'routes_count' => $count,
            'message'      => "Generated {$count} route(s) for {$date}.",
        ];
    } catch (Exception $e) {
        return [
            'success'      => false,
            'route_ids'    => [],
            'routes_count' => 0,
            'message'      => $e->getMessage(),
        ];
    }
}

/**
 * Merge two quantity arrays, summing values for matching item IDs.
 *
 * @param  array $a  Keyed by inventory_item_id => qty
 * @param  array $b  Keyed by inventory_item_id => qty
 * @return array
 */
function _ramos_merge_quantities(array $a, array $b): array
{
    $merged = $a;
    foreach ($b as $itemId => $qty) {
        if (isset($merged[$itemId])) {
            $merged[$itemId] += (float) $qty;
        } else {
            $merged[$itemId] = (float) $qty;
        }
    }
    return $merged;
}
