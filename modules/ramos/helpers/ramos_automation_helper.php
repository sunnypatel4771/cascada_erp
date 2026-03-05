<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Ramos Automation Helper Functions
 *
 * Provides reusable functions for running automation and generating routes,
 * callable from both AJAX (Automation controller) and scheduled cron jobs.
 */

/**
 * Execute automation process: analyze inventory, generate purchase orders
 *
 * @param int $run_by_id Staff user ID (0 = system/cron)
 * @return array Result array with keys: success, run_id, orders_processed, batches_created
 */
function ramos_execute_automation($run_by_id = 0)
{
    $CI = &get_instance();
    $CI->load->model('ramos/automation_model');
    $CI->load->model('ramos/suppliers_model');
    $CI->load->model('ramos/purchase_model');

    try {
        // Step 1: Get unprocessed orders from BOTH sources
        $unprocessedOrders = $CI->automation_model->get_unprocessed_omni_orders();
        $erpOrders = $CI->automation_model->get_unprocessed_erp_orders();

        // Check if there are ANY unprocessed orders from either source
        if (empty($unprocessedOrders) && empty($erpOrders)) {
            return [
                'success'               => false,
                'run_id'                => null,
                'orders_processed'      => 0,
                'batches_created'       => 0,
                'message'               => _l('ramos_automation_no_orders')
            ];
        }

        // Step 2: Create automation run record
        $runData = [
            'run_type' => 'purchase_generation',
            'run_by'   => $run_by_id,
            'status'   => 'running'
        ];

        // Mark as cron-scheduled if run_by_id is 0 (system)
        if ($run_by_id === 0) {
            $runData['cron_scheduled'] = 1;
        }

        $runId = $CI->automation_model->create_run($runData);

        // Step 3: Calculate required quantities from all unprocessed orders (BOTH omni_sales AND ERP)
        $omniOrderIds = array_column($unprocessedOrders, 'id');
        $erpOrderIds = array_column($erpOrders, 'id');

        // Get required quantities from BOTH sources
        $omniQuantities = $CI->automation_model->get_required_quantities_from_omni_orders($omniOrderIds);
        $erpQuantities = $CI->automation_model->get_required_quantities_from_erp_orders($erpOrderIds);

        // Merge quantities from both sources
        $requiredQuantities = _ramos_merge_quantities($omniQuantities, $erpQuantities);

        // Step 4: Get current inventory from warehouse module
        $inventoryMap = $CI->automation_model->get_warehouse_inventory_by_product();

        // Step 5: Get open purchase quantities (items already ordered but not received)
        $openPurchaseQuantities = $CI->purchase_model->get_open_purchase_quantities(['draft', 'sent', 'partial']);

        // Step 6: Build deficit report grouped by supplier
        $deficitGroups = $CI->purchase_model->build_supplier_deficits(
            $requiredQuantities,
            $inventoryMap,
            $openPurchaseQuantities
        );

        // Step 7: Get suppliers and add to groups
        $suppliers = $CI->suppliers_model->get();
        $supplierMap = [];
        foreach ($suppliers as $supplier) {
            $supplierMap[$supplier['id']] = $supplier;
        }

        foreach ($deficitGroups as $supplierId => &$group) {
            $group['supplier'] = $supplierId ? ($supplierMap[$supplierId] ?? null) : null;
        }
        unset($group);

        // Step 8: Sort groups by supplier priority (lower number = higher priority)
        uasort($deficitGroups, function ($a, $b) {
            $priorityA = isset($a['supplier']['priority']) ? (int)$a['supplier']['priority'] : 999;
            $priorityB = isset($b['supplier']['priority']) ? (int)$b['supplier']['priority'] : 999;
            return $priorityA <=> $priorityB;
        });

        // Step 9: Create draft purchase batches
        $createdBatches = [];
        foreach ($deficitGroups as $supplierId => $group) {
            if (empty($group['items'])) {
                continue;
            }

            // Transform items to match create_batch expected format
            $batchItems = [];
            foreach ($group['items'] as $item) {
                $batchItems[] = [
                    'inventory_item_id' => $item['inventory_item_id'],
                    'requested_qty'     => $item['required_qty'],
                    'current_stock'     => $item['current_stock'],
                    'safety_stock'      => $item['safety_stock'],
                ];
            }

            $batchId = $CI->purchase_model->create_batch(
                $supplierId ?: null,
                $batchItems
            );

            if ($batchId) {
                $createdBatches[] = $batchId;
            }
        }

        // Step 10: Mark orders as processed (BOTH omni_sales AND ERP)
        if (!empty($omniOrderIds)) {
            $CI->automation_model->mark_omni_orders_as_processed($omniOrderIds, $runId);
        }

        if (!empty($erpOrderIds)) {
            $CI->automation_model->mark_erp_orders_as_processed($erpOrderIds, $runId);
        }

        // Step 11: Complete automation run
        $totalOrdersProcessed = count($omniOrderIds) + count($erpOrderIds);
        $CI->automation_model->complete_run($runId, [
            'status'  => 'completed',
            'total_orders_processed' => $totalOrdersProcessed,
            'total_purchase_orders_created' => count($createdBatches),
            'summary' => json_encode([
                'omni_orders_processed' => count($omniOrderIds),
                'erp_orders_processed' => count($erpOrderIds),
                'total_orders_processed' => $totalOrdersProcessed,
                'purchase_orders_created' => count($createdBatches),
                'batch_ids' => $createdBatches
            ])
        ]);

        return [
            'success'               => true,
            'run_id'                => $runId,
            'orders_processed'      => $totalOrdersProcessed,
            'batches_created'       => count($createdBatches),
            'batch_ids'             => $createdBatches
        ];

    } catch (Exception $e) {
        // Mark run as failed
        if (isset($runId)) {
            $CI->automation_model->complete_run($runId, [
                'status' => 'failed',
                'notes'  => $e->getMessage()
            ]);
        }

        log_activity('Ramos Automation Error: ' . $e->getMessage());

        return [
            'success'               => false,
            'run_id'                => $runId ?? null,
            'orders_processed'      => 0,
            'batches_created'       => 0,
            'message'               => _l('ramos_automation_failed'),
            'error'                 => $e->getMessage()
        ];
    }
}

/**
 * Generate routes for today based on automation success
 *
 * @return array Result array with keys: success, route_ids, routes_count
 */
function ramos_generate_routes_for_today()
{
    $CI = &get_instance();
    $CI->load->model('ramos/routes_model');

    try {
        $today = date('Y-m-d');
        $startTime = get_option('ramos_default_route_start_time', '08:00:00');
        $maxStops = (int)get_option('ramos_default_max_stops', 10);
        $prefix = get_option('ramos_default_route_prefix', 'Route');

        $routes = $CI->routes_model->generate_routes($today, $startTime, $maxStops, $prefix);

        log_activity('[RAMOS CRON] Generated ' . count($routes) . ' routes for ' . $today);

        return [
            'success'       => true,
            'route_ids'     => $routes,
            'routes_count'  => count($routes)
        ];

    } catch (Exception $e) {
        log_activity('[RAMOS CRON] Route generation error: ' . $e->getMessage());

        return [
            'success'       => false,
            'route_ids'     => [],
            'routes_count'  => 0,
            'error'         => $e->getMessage()
        ];
    }
}

/**
 * Check if scheduled automation should run at this time
 *
 * @return bool True if should run, false otherwise
 */
function ramos_should_run_scheduled_automation()
{
    $currentTime = date('H:i:s');
    
    // Check if automation is enabled
    $enabledValue = get_option('ramos_automation_schedule_enabled');
    if ($enabledValue !== '1') {
        log_activity('[RAMOS DEBUG] Automation disabled. enabled=' . $enabledValue);
        return false;
    }

    // Check if already ran today
    $lastRunDate = get_option('ramos_last_automation_run_date');
    if ($lastRunDate === date('Y-m-d')) {
        log_activity('[RAMOS DEBUG] Already ran today at ' . $lastRunDate);
        return false;
    }

    // Get configured hours and minutes
    $hoursJson = get_option('ramos_automation_schedule_hours', json_encode([8, 14, 18]));
    $hours = json_decode($hoursJson, true);
    if (!is_array($hours)) {
        $hours = [8, 14, 18];
    }

    $minutesJson = get_option('ramos_automation_schedule_minutes', json_encode([0]));
    $minutes = json_decode($minutesJson, true);
    if (!is_array($minutes)) {
        $minutes = [0];
    }

    // Get current time
    $currentHour = (int)date('H');
    $currentMinute = (int)date('i');

    log_activity('[RAMOS DEBUG] Time check - Current: ' . $currentHour . ':' . sprintf('%02d', $currentMinute) . ', Configured hours: [' . implode(',', $hours) . '], minutes: [' . implode(',', $minutes) . ']');

    // Check each configured hour:minute pair
    foreach ($hours as $scheduledHour) {
        // Only check if current hour matches
        if ($currentHour !== $scheduledHour) {
            continue;
        }

        // For this hour, check if current minute is within tolerance of any scheduled minute
        foreach ($minutes as $scheduledMinute) {
            $timeDiff = abs($currentMinute - $scheduledMinute);
            
            log_activity('[RAMOS DEBUG] Hour match! Checking minute: current=' . $currentMinute . ', scheduled=' . $scheduledMinute . ', diff=' . $timeDiff);
            
            // Allow 2-minute tolerance window (e.g., if scheduled for :00, run between :00-:02)
            if ($timeDiff <= 2) {
                log_activity('[RAMOS DEBUG] MATCH FOUND! Will run automation');
                return true;
            }
        }
    }

    log_activity('[RAMOS DEBUG] No time match found');
    return false;
}

/**
 * Merge quantities from multiple sources (omni_sales and ERP)
 *
 * @param array $omniQuantities Quantities from omni_sales orders
 * @param array $erpQuantities Quantities from ERP orders
 * @return array Merged quantities array
 */
function _ramos_merge_quantities($omniQuantities, $erpQuantities)
{
    $merged = $omniQuantities;

    foreach ($erpQuantities as $itemId => $quantity) {
        if (isset($merged[$itemId])) {
            // Add to existing quantity
            $merged[$itemId] += $quantity;
        } else {
            // Add new item
            $merged[$itemId] = $quantity;
        }
    }

    return $merged;
}

/**
 * Get the last automation run date formatted for display
 *
 * @return string Formatted last run date or message if never run
 */
function ramos_get_last_run_display()
{
    $lastRunDate = get_option('ramos_last_automation_run_date');
    
    if (!$lastRunDate) {
        return '<span class="text-danger"><i class="fa fa-times-circle"></i> ' . _l('ramos_settings_never_run') . '</span>';
    }
    
    // Parse the date and show how long ago
    $lastRunDateTime = strtotime($lastRunDate);
    $now = time();
    $diffSeconds = $now - $lastRunDateTime;
    
    if ($diffSeconds < 60) {
        $ago = _l('ramos_settings_just_now');
    } elseif ($diffSeconds < 3600) {
        $minutes = floor($diffSeconds / 60);
        $ago = sprintf(_l('ramos_settings_minutes_ago'), $minutes);
    } elseif ($diffSeconds < 86400) {
        $hours = floor($diffSeconds / 3600);
        $ago = sprintf(_l('ramos_settings_hours_ago'), $hours);
    } else {
        $days = floor($diffSeconds / 86400);
        $ago = sprintf(_l('ramos_settings_days_ago'), $days);
    }
    
    return '<span class="text-success"><i class="fa fa-check-circle"></i> ' . date('Y-m-d H:i:s', $lastRunDateTime) . '</span> <small class="text-muted">(' . $ago . ')</small>';
}
