<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Orders extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        if (!staff_can('view', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        $this->load->model('ramos/orders_model', 'orders_model');
        $this->load->model('ramos/order_items_model', 'order_items_model');
        $this->load->model('ramos/inventory_model', 'inventory_model');
        $this->load->model('ramos/picking_model', 'picking_model');
        $this->load->model('clients_model');
        $this->load->helper(['form', 'string']);
    }

    /**
     * List orders with optional filters.
     *
     * @return void
     */
    public function index(): void
    {
        $statusFilter = $this->input->get('status');
        $searchTerm   = trim((string) $this->input->get('search'));

        $filters = [];

        if ($statusFilter && array_key_exists($statusFilter, ramos_order_statuses())) {
            $filters['status'] = $statusFilter;
        }

        if ($searchTerm !== '') {
            $filters['search'] = $searchTerm;
        }

        $data['title']          = _l('ramos_orders_title');
        $data['subtitle']       = _l('ramos_orders_subtitle');
        $data['orders']         = $this->orders_model->get(null, $filters);
        $data['statuses']       = ramos_order_statuses();
        $data['priorities']     = ramos_order_priorities();
        $data['status_filter']  = $statusFilter;
        $data['search_term']    = $searchTerm;
        $data['clients']        = $this->clients_model->get('', [db_prefix() . 'clients.active' => 1]);

        $this->load->view('orders/manage', $data);
    }

    /**
     * Store a new order from manual entry.
     *
     * @return void
     */
    public function store(): void
    {
        if (!staff_can('create', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        if (!$this->input->post()) {
            redirect(admin_url('ramos/orders'));
        }

        $customerName    = trim((string) $this->input->post('customer_name'));
        $deliveryAddress = trim((string) $this->input->post('delivery_address'));
        $priority        = $this->input->post('priority');
        $status          = $this->input->post('status');
        $orderNumber     = trim((string) $this->input->post('order_number'));
        $notes           = $this->input->post('notes');
        $deliveryDate    = $this->input->post('delivery_date');
        $deliveryTime    = $this->input->post('delivery_time');
        $clientId        = (int) $this->input->post('client_id');

        if ($customerName === '' || $deliveryAddress === '') {
            set_alert('danger', _l('ramos_orders_validation_required'));
            redirect(admin_url('ramos/orders'));
        }

        $deliveryDateTime = $this->mergeDateAndTime($deliveryDate, $deliveryTime);
        if ($deliveryDate && !$deliveryDateTime) {
            set_alert('danger', _l('ramos_orders_validation_delivery_time'));
            redirect(admin_url('ramos/orders'));
        }

        $payload = [
            'customer_name'     => $customerName,
            'delivery_address'  => $deliveryAddress,
            'priority'          => $priority,
            'status'            => $status,
            'notes'             => $notes,
            'delivery_datetime' => $deliveryDateTime,
        ];

        if ($orderNumber !== '') {
            $payload['order_number'] = $orderNumber;
        }

        if ($clientId > 0) {
            $payload['client_id'] = $clientId;
        }

        $result = $this->orders_model->create($payload);

        if ($result) {
            set_alert('success', _l('ramos_orders_created_successfully'));
        } else {
            set_alert('danger', _l('ramos_orders_create_failed'));
        }

        redirect(admin_url('ramos/orders'));
    }

    /**
     * Manage order items.
     *
     * @param  int $orderId
     * @return void
     */
    public function items($orderId): void
    {
        $order = $this->orders_model->get($orderId);

        if (empty($order)) {
            show_404();
        }

        $this->picking_model->ensure_pick_records_for_order($orderId);

        $items          = $this->order_items_model->get(null, ['order_id' => $orderId]);
        $inventoryList  = $this->inventory_model->get(null, ['active' => 1]);

        $inventoryOptions = [];
        $inventoryMap     = [];
        foreach ($inventoryList as $inventoryItem) {
            $inventoryOptions[] = [
                'id'             => $inventoryItem['id'],
                'name'           => trim($inventoryItem['item_name'] . ' (' . $inventoryItem['unit'] . ')'),
                'unit'           => $inventoryItem['unit'],
                'has_maduracion' => (int) ($inventoryItem['has_maduracion'] ?? 0),
            ];
            $inventoryMap[$inventoryItem['id']] = $inventoryItem;
        }

        $data['title']             = _l('ramos_orders_items_title');
        $data['order']             = $order;
        $data['order_items']       = $items;
        $data['inventory_options'] = $inventoryOptions;
        $data['inventory_map']     = $inventoryMap;

        $this->load->view('orders/items', $data);
    }

    /**
     * Store order item.
     *
     * @param  int $orderId
     * @return void
     */
    public function store_item($orderId): void
    {
        if (!staff_can('edit', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        if (!$this->input->post()) {
            redirect(admin_url('ramos/orders/items/' . $orderId));
        }

        $order = $this->orders_model->get($orderId);
        if (empty($order)) {
            show_404();
        }

        $inventoryItemId = (int) $this->input->post('inventory_item_id');
        $itemName        = trim((string) $this->input->post('item_name'));
        $quantity        = (float) $this->input->post('quantity');

        if ($inventoryItemId > 0 && $itemName === '') {
            $inventoryItem = $this->inventory_model->get($inventoryItemId);
            if (!empty($inventoryItem)) {
                $itemName = $inventoryItem['item_name'];
            }
        }

        if ($itemName === '' || $quantity <= 0) {
            set_alert('danger', _l('ramos_orders_items_validation'));
            redirect(admin_url('ramos/orders/items/' . $orderId));
        }

        $payload = [
            'order_id'          => (int) $orderId,
            'inventory_item_id' => $inventoryItemId > 0 ? $inventoryItemId : null,
            'item_name'         => $itemName,
            'quantity'          => $quantity,
            'ripeness'          => $this->input->post('ripeness'),
        ];

        $insertId = $this->order_items_model->create($payload);

        if ($insertId) {
            $this->picking_model->ensure_pick_record_for_item($insertId);
        }

        set_alert('success', _l('ramos_orders_items_created'));

        redirect(admin_url('ramos/orders/items/' . $orderId));
    }

    /**
     * Delete order item.
     *
     * @param  int $orderId
     * @param  int $itemId
     * @return void
     */
    public function delete_item($orderId, $itemId): void
    {
        if (!staff_can('delete', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        $order = $this->orders_model->get($orderId);
        if (empty($order)) {
            show_404();
        }

        $item = $this->order_items_model->get($itemId);
        if (empty($item) || (int) $item['order_id'] !== (int) $orderId) {
            show_404();
        }

        $this->picking_model->remove_pick_records_for_item($itemId);

        $success = $this->order_items_model->delete($itemId);

        if ($success) {
            set_alert('success', _l('deleted', _l('ramos_orders_items_label')));
        } else {
            set_alert('danger', _l('problem_deleting', _l('ramos_orders_items_label')));
        }

        redirect(admin_url('ramos/orders/items/' . $orderId));
    }

    /**
     * Update order status (AJAX).
     *
     * @param  int $id
     * @return void
     */
    public function update_status($id): void
    {
        if (!staff_can('edit', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        if (!$this->input->is_ajax_request()) {
            show_error('Invalid request', 400);
        }

        $status   = $this->input->post('status');
        $statuses = ramos_order_statuses();

        if (!array_key_exists($status, $statuses)) {
            $this->output
                ->set_content_type('application/json')
                ->set_status_header(422)
                ->set_output(json_encode([
                    'success' => false,
                    'message' => _l('ramos_orders_invalid_status'),
                ]));

            return;
        }

        $success = $this->orders_model->update_status((int) $id, $status);

        $additionalMessages = [];

        if ($success && $status === RAMOS_ORDER_STATUS_READY) {
            $this->load->library('ramos/ramos_invoice_generator', null, 'ramos_invoice_generator');
            $invoiceResult = $this->ramos_invoice_generator->generate_for_order((int) $id);

            if (!empty($invoiceResult['message'])) {
                $additionalMessages[] = $invoiceResult['message'];
            }

            if (!empty($invoiceResult['warnings'])) {
                $additionalMessages = array_merge($additionalMessages, $invoiceResult['warnings']);
            }
        }

        $responseMessage = $success
            ? _l('updated_successfully', _l('ramos_orders_label'))
            : _l('problem_updating', _l('ramos_orders_label'));

        if (!empty($additionalMessages)) {
            $responseMessage .= ' ' . implode(' ', $additionalMessages);
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'success' => $success,
                'message' => $responseMessage,
            ]));
    }

    /**
     * Delete an order.
     *
     * @param  int $id
     * @return void
     */
    public function delete($id): void
    {
        if (!staff_can('delete', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        $order = $this->orders_model->get($id);
        if (!$order) {
            set_alert('warning', _l('ramos_orders_not_found'));
            redirect(admin_url('ramos/orders'));
        }

        $success = $this->orders_model->delete($id);

        if ($success) {
            set_alert('success', _l('deleted', _l('ramos_orders_label')));
        } else {
            set_alert('danger', _l('problem_deleting', _l('ramos_orders_label')));
        }

        redirect(admin_url('ramos/orders'));
    }

    /**
     * Import orders via CSV/XLSX.
     *
     * @return void
     */
    public function import(): void
    {
        if (!staff_can('create', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        if (empty($_FILES['orders_file']['name'])) {
            set_alert('warning', _l('ramos_orders_import_no_file'));
            redirect(admin_url('ramos/orders'));
        }

        $file      = $_FILES['orders_file'];
        $extension = strtolower((string) pathinfo($file['name'], PATHINFO_EXTENSION));

        $tempFile = get_temp_dir() . 'ramos_orders_' . app_generate_hash() . '.' . $extension;

        if (!@move_uploaded_file($file['tmp_name'], $tempFile)) {
            set_alert('danger', _l('ramos_orders_import_move_failed'));
            redirect(admin_url('ramos/orders'));
        }

        $this->load->library('ramos/ramos_orders_importer', null, 'orders_importer');

        try {
            $rows = $this->orders_importer->parseFile($tempFile, $extension);
        } catch (Exception $exception) {
            @unlink($tempFile);
            set_alert('danger', $exception->getMessage());
            redirect(admin_url('ramos/orders'));
        }

        @unlink($tempFile);

        if (empty($rows)) {
            set_alert('warning', _l('ramos_orders_import_no_rows'));
            redirect(admin_url('ramos/orders'));
        }

        $batchId  = app_generate_hash();
        $imported = 0;
        $errors   = [];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2; // +2 because header is row 1
            $transformed = $this->orders_importer->transformRow($row);

            if (!$transformed['valid']) {
                $errors[] = _l('ramos_orders_import_row_error', $lineNumber) . ' - ' . $transformed['error'];
                continue;
            }

            $transformed['data']['import_batch'] = $batchId;

            try {
                $this->orders_model->create($transformed['data']);
                $imported++;
            } catch (Exception $exception) {
                $errors[] = _l('ramos_orders_import_row_error', $lineNumber) . ' - ' . $exception->getMessage();
            }
        }

        if ($imported > 0) {
            set_alert('success', _l('ramos_orders_import_success', $imported));
        }

        if (!empty($errors)) {
            set_alert('warning', implode('<br>', $errors));
        }

        if ($imported === 0 && empty($errors)) {
            set_alert('warning', _l('ramos_orders_import_nothing_imported'));
        }

        redirect(admin_url('ramos/orders'));
    }

    /**
     * Combine form date and time into SQL datetime.
     *
     * @param  string|null $date
     * @param  string|null $time
     * @return string|null
     */
    private function mergeDateAndTime(?string $date, ?string $time): ?string
    {
        $date = trim((string) $date);
        $time = trim((string) $time);

        if ($date === '') {
            return null;
        }

        $time = $time === '' ? '00:00' : $time;

        $timestamp = strtotime($date . ' ' . $time);

        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

}
