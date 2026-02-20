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
        $this->load->model('ramos/purchase_model', 'purchase_model');
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

        // Get count of unprocessed orders
        // COMMENTED: Ramos orders - replaced with omni_sales orders
        // $data['unprocessed_count'] = $this->orders_model->count_unprocessed_orders();

        // NEW: Count unprocessed orders from omni_sales (tblcart)
        $data['unprocessed_count'] = $this->automation_model->count_unprocessed_omni_orders();

        $this->load->view('automation/index', $data);
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

        try {
            // Step 1: Get unprocessed orders
            // COMMENTED: Ramos orders - replaced with omni_sales orders
            // $unprocessedOrders = $this->orders_model->get_unprocessed_orders();

            // NEW: Get unprocessed orders from omni_sales (tblcart)
            // Includes all orders except cancelled (status 5) and return orders
            $unprocessedOrders = $this->automation_model->get_unprocessed_omni_orders();

            if (empty($unprocessedOrders)) {
                echo json_encode([
                    'success' => false,
                    'message' => _l('ramos_automation_no_orders')
                ]);
                return;
            }

            // Step 2: Create automation run record
            $runId = $this->automation_model->create_run([
                'run_type' => 'purchase_generation',
                'run_by'   => get_staff_user_id(),
                'status'   => 'running'
            ]);

            // Step 3: Calculate required quantities from all unprocessed orders
            $orderIds = array_column($unprocessedOrders, 'id');

            // COMMENTED: Ramos order items - replaced with omni_sales cart_detailt
            // $requiredQuantities = $this->order_items_model->get_required_quantities_by_orders($orderIds);

            // NEW: Get required quantities from omni_sales cart detail items (tblcart_detailt)
            $requiredQuantities = $this->automation_model->get_required_quantities_from_omni_orders($orderIds);

            // Step 4: Get current inventory
            // COMMENTED: Ramos inventory - replaced with warehouse module inventory
            // $inventoryItems = $this->inventory_model->get();
            // $inventoryMap = [];
            // foreach ($inventoryItems as $item) {
            //     $inventoryMap[$item['id']] = $item;
            // }

            // NEW: Get inventory from warehouse module (tblinventory_manage)
            // Aggregate stock quantities by commodity_id (product_id)
            $inventoryMap = $this->automation_model->get_warehouse_inventory_by_product();

            // Step 5: Get open purchase quantities (items already ordered but not received)
            $openPurchaseQuantities = $this->purchase_model->get_open_purchase_quantities(['draft', 'sent', 'partial']);

            // Step 6: Build deficit report grouped by supplier
            $deficitGroups = $this->purchase_model->build_supplier_deficits(
                $requiredQuantities,
                $inventoryMap,
                $openPurchaseQuantities
            );

            // Step 7: Get suppliers and add to groups
            $suppliers = $this->suppliers_model->get();
            $supplierMap = [];
            foreach ($suppliers as $supplier) {
                $supplierMap[$supplier['id']] = $supplier;
            }

            foreach ($deficitGroups as $supplierId => &$group) {
                $group['supplier'] = $supplierId ? ($supplierMap[$supplierId] ?? null) : null;
            }
            unset($group);

            // Step 8: Sort groups by supplier priority (lower number = higher priority)
            uasort($deficitGroups, function($a, $b) {
                $priorityA = isset($a['supplier']['priority']) ? (int) $a['supplier']['priority'] : 999;
                $priorityB = isset($b['supplier']['priority']) ? (int) $b['supplier']['priority'] : 999;
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

                $batchId = $this->purchase_model->create_batch(
                    $supplierId ?: null,
                    $batchItems
                );

                if ($batchId) {
                    $createdBatches[] = $batchId;
                }
            }

            // Step 10: Mark orders as processed
            // COMMENTED: Ramos orders - replaced with omni_sales orders
            // $this->orders_model->mark_orders_as_processed($orderIds, $runId);

            // NEW: Mark omni_sales orders (tblcart) as processed
            $this->automation_model->mark_omni_orders_as_processed($orderIds, $runId);

            // Step 11: Complete automation run
            $this->automation_model->complete_run($runId, [
                'status'  => 'completed',
                'total_orders_processed' => count($orderIds),
                'total_purchase_orders_created' => count($createdBatches),
                'summary' => json_encode([
                    'orders_processed' => count($orderIds),
                    'purchase_orders_created' => count($createdBatches),
                    'batch_ids' => $createdBatches
                ])
            ]);

            echo json_encode([
                'success' => true,
                'message' => _l('ramos_automation_success_message', count($orderIds), count($createdBatches)),
                'run_id' => $runId,
                'orders_processed' => count($orderIds),
                'purchase_orders_created' => count($createdBatches),
                'batch_ids' => $createdBatches
            ]);

        } catch (Exception $e) {
            // Mark run as failed
            if (isset($runId)) {
                $this->automation_model->complete_run($runId, [
                    'status' => 'failed',
                    'notes'  => $e->getMessage()
                ]);
            }

            log_activity('Ramos Automation Error: ' . $e->getMessage());

            echo json_encode([
                'success' => false,
                'message' => _l('ramos_automation_failed'),
                'error'   => $e->getMessage()
            ]);
        }
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

        try {
            // Get unprocessed orders
            // COMMENTED: Ramos orders - replaced with omni_sales orders
            // $unprocessedOrders = $this->orders_model->get_unprocessed_orders();

            // NEW: Get unprocessed orders from omni_sales (tblcart)
            $unprocessedOrders = $this->automation_model->get_unprocessed_omni_orders();

            if (empty($unprocessedOrders)) {
                echo json_encode([
                    'success' => false,
                    'message' => _l('ramos_automation_no_orders')
                ]);
                return;
            }

            // Calculate requirements
            $orderIds = array_column($unprocessedOrders, 'id');

            // COMMENTED: Ramos order items - replaced with omni_sales cart_detailt
            // $requiredQuantities = $this->order_items_model->get_required_quantities_by_orders($orderIds);

            // NEW: Get required quantities from omni_sales cart detail items (tblcart_detailt)
            $requiredQuantities = $this->automation_model->get_required_quantities_from_omni_orders($orderIds);

            // Get inventory
            // COMMENTED: Ramos inventory - replaced with warehouse module inventory
            // $inventoryItems = $this->inventory_model->get();

            // NEW: Get inventory from warehouse module (tblinventory_manage)
            // Aggregate stock quantities by commodity_id (product_id)
            $inventoryMap = $this->automation_model->get_warehouse_inventory_by_product();

            // Get open purchases
            $openPurchaseQuantities = $this->purchase_model->get_open_purchase_quantities(['draft', 'sent', 'partial']);

            // Build deficit report
            $deficitGroups = $this->purchase_model->build_supplier_deficits(
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
                'orders_count' => count($unprocessedOrders)
            ]);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
