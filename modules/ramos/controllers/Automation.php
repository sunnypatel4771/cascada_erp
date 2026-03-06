<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Automation Controller
 *
 * Handles the purchase order automation process:
 * 1. Analyzes inventory vs order demand
 * 2. Calculates purchase requirements
 * 3. Generates draft purchase orders grouped by supplier priority
 * 4. Tracks which orders have been processed
 */

if (!defined('RAMOS_MODULE_NAME')) {
    define('RAMOS_MODULE_NAME', 'ramos');
}

class Automation extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        if (!staff_can('view', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        // COMMENTED: Ramos module's own orders - replaced with omni_sales orders
        // $this->load->model('ramos/orders_model', 'orders_model');
        // $this->load->model('ramos/order_items_model', 'order_items_model');

        // NEW: Load omni_sales model to access orders from omni_sales module
        $this->load->model('omni_sales/omni_sales_model', 'omni_sales_model');

        $this->load->model('ramos/inventory_model', 'inventory_model');
        $this->load->model('ramos/suppliers_model', 'suppliers_model');
        $this->load->model('ramos/purchase_model', 'ramos_purchase_model');
        $this->load->model('ramos/automation_model', 'automation_model');
    }

    /**
     * Main automation dashboard
     */
    public function index(): void
    {
        $data['title']    = _l('ramos_automation_title');
        $data['subtitle'] = _l('ramos_automation_subtitle');

        // Get recent automation runs
        $data['recent_runs'] = $this->automation_model->get_recent_runs(10);

        // Get count of unprocessed orders from BOTH sources
        $omniCount = $this->automation_model->count_unprocessed_omni_orders();
        $erpCount = $this->automation_model->count_unprocessed_erp_orders();
        
        $data['unprocessed_count'] = $omniCount + $erpCount;
        $data['omni_count'] = $omniCount;
        $data['erp_count'] = $erpCount;

        $this->load->view('automation/index', $data);
    }

    /**
     * Automation settings page
     */
    public function settings(): void
    {
        if (!staff_can('view', RAMOS_MODULE_NAME)) {
            access_denied();
        }
        
        // Check for edit permission to allow saving
        $canEdit = staff_can('edit', RAMOS_MODULE_NAME);

        // Handle form submission
        if ($this->input->post()) {
            // Log the POST request for debugging
            $postData = $this->input->post();
            error_log('DEBUG: Settings form submitted - POST data: ' . json_encode($postData));
            log_activity('DEBUG: Settings form submitted - canEdit=' . ($canEdit ? 'true' : 'false'));
            
            if (!$canEdit) {
                error_log('DEBUG: User does not have edit permission');
                echo json_encode([
                    'success' => false,
                    'message' => _l('access_denied')
                ]);
                return;
            }
            $this->_save_automation_schedule_settings();
            return;
        }

        // Load current settings
        $data['title']    = _l('ramos_settings_automation_schedule_title');
        $data['subtitle'] = _l('ramos_settings_automation_schedule_subtitle');
        $data['can_edit'] = $canEdit;

        // Automation schedule settings
        $data['automation_enabled'] = get_option('ramos_automation_schedule_enabled') === '1';
        
        // NEW: Load individual schedule settings
        $data['schedule_run_daily'] = get_option('ramos_automation_schedule_run_daily') === '1';
        $data['schedule_date'] = get_option('ramos_automation_schedule_date', '');
        $data['schedule_hour'] = (int)get_option('ramos_automation_schedule_hour', 8);
        $data['schedule_minutes'] = (int)get_option('ramos_automation_schedule_minutes', 0);
        
        // OLD: Load array format for backward compatibility
        $hoursJson = get_option('ramos_automation_schedule_hours', json_encode([8, 14, 18]));
        $data['schedule_hours'] = json_decode($hoursJson, true);
        if (!is_array($data['schedule_hours'])) {
            $data['schedule_hours'] = [8, 14, 18];
        }

        $minutesJson = get_option('ramos_automation_schedule_minutes', json_encode([0]));
        $data['schedule_minutes_array'] = json_decode($minutesJson, true);
        if (!is_array($data['schedule_minutes_array'])) {
            $data['schedule_minutes_array'] = [0];
        }

        // Route generation settings
        $data['route_generation_auto'] = get_option('ramos_route_generate_on_success') === '1';
        $data['default_max_stops'] = (int)get_option('ramos_default_max_stops', 10);
        $data['default_route_prefix'] = get_option('ramos_default_route_prefix', 'Route');
        $data['default_route_start_time'] = get_option('ramos_default_route_start_time', '08:00:00');

        // Last run info
        $data['last_automation_run_date'] = get_option('ramos_last_automation_run_date') ?: _l('ramos_settings_never_run');

        // Available hours for selection
        $data['available_hours'] = array_combine(range(0, 23), range(0, 23));

        $this->load->view('automation/settings', $data);
    }

    /**
     * Save automation schedule settings (AJAX)
     */
    private function _save_automation_schedule_settings(): void
    {
        error_log('DEBUG: _save_automation_schedule_settings called');
        
        if (!staff_can('edit', RAMOS_MODULE_NAME)) {
            error_log('DEBUG: User does not have edit permission in save method');
            echo json_encode([
                'success' => false,
                'message' => _l('access_denied')
            ]);
            return;
        }

        try {
            error_log('DEBUG: Starting to save settings');
            
            // Automation enabled/disabled
            $automationEnabled = $this->input->post('automation_enabled') === 'on' ? '1' : '0';
            error_log('DEBUG: automationEnabled=' . $automationEnabled);
            
            $updateResult = update_option('ramos_automation_schedule_enabled', $automationEnabled);
            error_log('DEBUG: update_option result=' . ($updateResult ? 'true' : 'false'));
            error_log('DEBUG: Saved ramos_automation_schedule_enabled');

            if ($automationEnabled === '1') {
                // Get settings from new form
                $runDaily = $this->input->post('schedule_run_daily') === 'on' ? '1' : '0';
                $selectedDate = trim((string)$this->input->post('schedule_date'));
                $selectedHour = (int)$this->input->post('schedule_hour');
                $selectedMinutes = (int)$this->input->post('schedule_minutes');

                // Validate
                if ($selectedHour < 0 || $selectedHour > 23) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid hour selected'
                    ]);
                    return;
                }

                if ($selectedMinutes < 0 || $selectedMinutes > 59) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid minutes selected'
                    ]);
                    return;
                }

                if (!$runDaily && empty($selectedDate)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Please select a day or enable "Run Daily"'
                    ]);
                    return;
                }

                // Save settings
                update_option('ramos_automation_schedule_run_daily', $runDaily);
                update_option('ramos_automation_schedule_date', $selectedDate);
                update_option('ramos_automation_schedule_hour', (string)$selectedHour);
                update_option('ramos_automation_schedule_minutes', (string)$selectedMinutes);

                // For backward compatibility, also save as hours array (but NOT minutes - keep minutes as integer)
                update_option('ramos_automation_schedule_hours', json_encode([$selectedHour]));
            }

            // Route generation auto-generate on success
            $routeGenAuto = $this->input->post('route_generation_auto') === 'on' ? '1' : '0';
            update_option('ramos_route_generate_on_success', $routeGenAuto);

            // Default max stops per route
            $maxStops = (int)$this->input->post('default_max_stops');
            $maxStops = max(1, min(100, $maxStops)); // Between 1-100
            update_option('ramos_default_max_stops', (string)$maxStops);

            // Default route prefix
            $routePrefix = trim((string)$this->input->post('default_route_prefix'));
            $routePrefix = $routePrefix ?: 'Route';
            update_option('ramos_default_route_prefix', $routePrefix);

            // Default route start time
            $startTime = trim((string)$this->input->post('default_route_start_time'));
            if ($startTime && preg_match('/^\d{2}:\d{2}$/', $startTime)) {
                update_option('ramos_default_route_start_time', $startTime . ':00');
            }

            log_activity('Ramos scheduled automation settings updated');

            echo json_encode([
                'success' => true,
                'message' => _l('settings_updated_successfully')
            ]);
        } catch (Exception $e) {
            log_activity('Error saving automation settings: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Error saving settings: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Run the automation process (AJAX)
     *
     * This is the main trigger that:
     * 1. Analyzes inventory vs orders
     * 2. Generates purchase orders
     * 3. Marks orders as processed
     */
    public function run(): void
    {
        if (!staff_can('create', RAMOS_MODULE_NAME)) {
            echo json_encode([
                'success' => false,
                'message' => _l('access_denied')
            ]);
            return;
        }

        if (!$this->input->is_ajax_request()) {
            show_404();
            return;
        }

        // Load the automation helper
        $this->load->helper('ramos/ramos_automation');

        // Execute automation with current staff user ID
        $result = ramos_execute_automation(get_staff_user_id());

        // Return result as JSON
        echo json_encode($result);
    }

    /**
     * Get current analysis without creating purchase orders (AJAX)
     * Shows what would be ordered
     */
    public function analyze(): void
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
            return;
        }

        // Load the automation helper for quantity merging
        $this->load->helper('ramos/ramos_automation');

        try {
            // Get unprocessed orders from BOTH sources
            $unprocessedOrders = $this->automation_model->get_unprocessed_omni_orders();
            $erpOrders = $this->automation_model->get_unprocessed_erp_orders();

            // Check if there are ANY unprocessed orders from either source
            if (empty($unprocessedOrders) && empty($erpOrders)) {
                echo json_encode([
                    'success' => false,
                    'message' => _l('ramos_automation_no_orders')
                ]);
                return;
            }

            // Merge both order sources
            $allOrders = array_merge($unprocessedOrders, $erpOrders);
            
            // Separate order IDs by source for processing
            $omniOrderIds = array_column($unprocessedOrders, 'id');
            $erpOrderIds = array_column($erpOrders, 'id');
            
            // Get required quantities from BOTH sources
            $omniQuantities = $this->automation_model->get_required_quantities_from_omni_orders($omniOrderIds);
            $erpQuantities = $this->automation_model->get_required_quantities_from_erp_orders($erpOrderIds);
            
            // Merge quantities from both sources using helper
            $requiredQuantities = _ramos_merge_quantities($omniQuantities, $erpQuantities);

            // Get inventory
            $inventoryMap = $this->automation_model->get_warehouse_inventory_by_product();

            // Get open purchases
            $openPurchaseQuantities = $this->ramos_purchase_model->get_open_purchase_quantities(['draft', 'sent', 'partial']);

            // Build deficit report
            $deficitGroups = $this->ramos_purchase_model->build_supplier_deficits(
                $requiredQuantities,
                $inventoryMap,
                $openPurchaseQuantities
            );

            // Check if there are any items that need purchasing
            if (empty($deficitGroups)) {
                echo json_encode([
                    'success' => true,
                    'message' => _l('ramos_automation_sufficient_stock'),
                    'analysis' => [],
                    'orders_count' => count($unprocessedOrders),
                    'has_sufficient_stock' => true
                ]);
                return;
            }

            // Add supplier info
            $suppliers = $this->suppliers_model->get();
            $supplierMap = [];
            foreach ($suppliers as $supplier) {
                $supplierMap[$supplier['id']] = $supplier;
            }

            foreach ($deficitGroups as $supplierId => &$group) {
                $group['supplier'] = $supplierId ? ($supplierMap[$supplierId] ?? null) : null;
            }
            unset($group);

            // Sort by priority
            uasort($deficitGroups, function($a, $b) {
                $priorityA = isset($a['supplier']['priority']) ? (int) $a['supplier']['priority'] : 999;
                $priorityB = isset($b['supplier']['priority']) ? (int) $b['supplier']['priority'] : 999;
                return $priorityA <=> $priorityB;
            });

            // Format data for frontend display
            $analysis = [];
            foreach ($deficitGroups as $supplierId => $group) {
                if (empty($group['items'])) {
                    continue;
                }

                $supplierName = $group['supplier']['supplier_name'] ?? _l('ramos_purchases_unassigned_supplier');

                $items = [];
                foreach ($group['items'] as $item) {
                    $items[] = [
                        'item_name' => $item['item_name'],
                        'required'  => $item['required_qty'],
                        'on_hand'   => $item['current_stock'],
                        'to_buy'    => $item['required_qty']
                    ];
                }

                $analysis[] = [
                    'supplier_id'   => $supplierId,
                    'supplier_name' => $supplierName,
                    'items'         => $items
                ];
            }

            echo json_encode([
                'success'  => true,
                'analysis' => $analysis,
                'omni_orders_count' => count($omniOrderIds),
                'erp_orders_count' => count($erpOrderIds),
                'orders_count' => count($omniOrderIds) + count($erpOrderIds)
            ]);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}

