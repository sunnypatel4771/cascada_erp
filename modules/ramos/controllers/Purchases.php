<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Purchases extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        if (!staff_can('view', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        $this->load->model('ramos/order_items_model', 'order_items_model');
        $this->load->model('ramos/inventory_model', 'inventory_model');
        $this->load->model('ramos/suppliers_model', 'suppliers_model');
        $this->load->model('ramos/purchase_model', 'ramos_purchase_model');
    }

    public function index(): void
    {
        $requiredQuantities     = $this->order_items_model->get_required_quantities([RAMOS_ORDER_STATUS_NEW, RAMOS_ORDER_STATUS_PROCESSING]);
        $inventoryList          = $this->inventory_model->get();
        $openPurchaseQuantities = $this->ramos_purchase_model->get_open_purchase_quantities(['draft', 'sent', 'partial']);
        $suppliers             = $this->suppliers_model->get();

        $inventoryMap = [];
        foreach ($inventoryList as $inventory) {
            $inventoryMap[$inventory['id']] = $inventory;
        }

        $supplierMap = [];
        foreach ($suppliers as $supplier) {
            $supplierMap[$supplier['id']] = $supplier;
        }

        $deficitGroups = $this->ramos_purchase_model->build_supplier_deficits($requiredQuantities, $inventoryMap, $openPurchaseQuantities);

        foreach ($deficitGroups as $supplierId => &$group) {
            $group['supplier'] = $supplierId ? ($supplierMap[$supplierId] ?? null) : null;
            $group['total_items'] = isset($group['items']) ? count($group['items']) : 0;
        }
        unset($group);

        $data['title']          = _l('ramos_purchases_title');
        $data['subtitle']       = _l('ramos_purchases_subtitle');
        $data['deficit_groups'] = $deficitGroups;
        $data['suppliers']      = $supplierMap;
        $data['recent_batches'] = $this->ramos_purchase_model->get_recent_batches();
        $data['all_batches']    = $this->ramos_purchase_model->get_batches();

        $this->load->view('purchases/planner', $data);
    }

    public function approve(): void
    {
        if (!staff_can('create', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        $supplierId = $this->input->post('supplier_id');
        $items      = $this->input->post('items');

        if (empty($items) || !is_array($items)) {
            set_alert('warning', _l('ramos_purchases_nothing_selected'));
            redirect(admin_url('ramos/purchases'));
        }

        $normalized = [];

        foreach ($items as $item) {
            if (!isset($item['inventory_item_id'], $item['requested_qty'])) {
                continue;
            }

            $inventoryItemId = (int) $item['inventory_item_id'];
            $requestedQty    = (float) $item['requested_qty'];

            if ($inventoryItemId <= 0 || $requestedQty <= 0) {
                continue;
            }

            $normalized[] = [
                'inventory_item_id' => $inventoryItemId,
                'requested_qty'     => $requestedQty,
                'current_stock'     => isset($item['current_stock']) ? (float) $item['current_stock'] : 0,
                'safety_stock'      => isset($item['safety_stock']) ? (float) $item['safety_stock'] : 0,
            ];
        }

        if (empty($normalized)) {
            set_alert('warning', _l('ramos_purchases_nothing_selected'));
            redirect(admin_url('ramos/purchases'));
        }

        $batchId = $this->ramos_purchase_model->create_batch($supplierId ? (int) $supplierId : null, $normalized);

        if ($batchId) {
            set_alert('success', _l('ramos_purchases_batch_created'));
            redirect(admin_url('ramos/purchases/batch/' . $batchId));
        } else {
            set_alert('danger', _l('ramos_purchases_batch_failed'));
            redirect(admin_url('ramos/purchases'));
        }
    }

    public function batch($batchId): void
    {
        $batch = $this->ramos_purchase_model->get_batch($batchId);

        if (empty($batch)) {
            show_404();
        }

        $items = $this->ramos_purchase_model->get_batch_items($batchId);

        $data['title']     = _l('ramos_purchases_batch_title', html_escape($batch['batch_code']));
        $data['batch']     = $batch;
        $data['items']     = $items;
        $data['statuses']  = ramos_purchase_statuses();

        $this->load->view('purchases/batch', $data);
    }

    public function send($batchId): void
    {
        if (!staff_can('edit', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        $batch = $this->ramos_purchase_model->get_batch($batchId);
        if (empty($batch)) {
            show_404();
        }

        if ($batch['status'] !== 'draft') {
            set_alert('warning', _l('ramos_purchases_send_invalid'));
            redirect(admin_url('ramos/purchases/batch/' . $batchId));
        }

        $notes = $this->input->post('notes');
        $this->ramos_purchase_model->mark_sent($batchId, $notes);

        set_alert('success', _l('ramos_purchases_marked_sent'));

        redirect(admin_url('ramos/purchases/batch/' . $batchId));
    }

    public function receive($batchId): void
    {
        if (!staff_can('edit', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        $batch = $this->ramos_purchase_model->get_batch($batchId);
        if (empty($batch)) {
            show_404();
        }

        if (!in_array($batch['status'], ['draft', 'sent', 'partial'], true)) {
            set_alert('warning', _l('ramos_purchases_receive_closed'));
            redirect(admin_url('ramos/purchases/batch/' . $batchId));
        }

        $itemIds   = $this->input->post('item_id');
        $quantities = $this->input->post('receive_qty');

        if (empty($itemIds) || empty($quantities)) {
            redirect(admin_url('ramos/purchases/batch/' . $batchId));
        }

        $receipts = [];
        foreach ($itemIds as $index => $itemId) {
            $itemId = (int) $itemId;
            $qty    = isset($quantities[$index]) ? (float) $quantities[$index] : 0;

            if ($itemId <= 0 || $qty <= 0) {
                continue;
            }

            $receipts[$itemId] = ($receipts[$itemId] ?? 0) + $qty;
        }

        if (empty($receipts)) {
            set_alert('warning', _l('ramos_purchases_receive_nothing'));
            redirect(admin_url('ramos/purchases/batch/' . $batchId));
        }

        $applied = $this->ramos_purchase_model->record_receipts($batchId, $receipts);

        if (empty($applied)) {
            set_alert('warning', _l('ramos_purchases_receive_nothing'));
            redirect(admin_url('ramos/purchases/batch/' . $batchId));
        }

        if (!empty($applied)) {
            $items = $this->ramos_purchase_model->get_batch_items($batchId);
            $itemMap = [];
            foreach ($items as $item) {
                $itemMap[$item['id']] = $item;
            }

            foreach ($applied as $itemId => $delta) {
                if ($delta <= 0) {
                    continue;
                }

                if (isset($itemMap[$itemId]) && $itemMap[$itemId]['inventory_item_id']) {
                    $this->inventory_model->adjust_quantity($itemMap[$itemId]['inventory_item_id'], $delta);
                }
            }
        }

        $status = $this->ramos_purchase_model->refresh_batch_status($batchId);

        if ($status === 'received') {
            set_alert('success', _l('ramos_purchases_batch_completed'));
        } else {
            set_alert('success', _l('ramos_purchases_receive_recorded'));
        }

        redirect(admin_url('ramos/purchases/batch/' . $batchId));
    }
}
