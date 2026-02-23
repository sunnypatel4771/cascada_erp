<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Inventory extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        if (!staff_can('view', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        $this->load->model('ramos/inventory_model', 'inventory_model');
        $this->load->model('ramos/suppliers_model', 'suppliers_model');
        $this->load->helper('form');
    }

    /**
     * Inventory listing.
     *
     * @return void
     */
    public function index(): void
    {
        $search = trim((string) $this->input->get('search'));
        $status = $this->input->get('status');

        $filters = [];

        if ($search !== '') {
            $filters['search'] = $search;
        }

        if ($status !== null && $status !== '') {
            $filters['active'] = (int) $status;
        }

        $items = $this->inventory_model->get(null, $filters);
        $suppliers = $this->suppliers_model->get(null, true);

        $data['title']       = _l('ramos_inventory_title');
        $data['subtitle']    = _l('ramos_inventory_subtitle');
        $data['items']       = $items;
        $data['search_term'] = $search;
        $data['status']      = $status;
        $data['suppliers']   = $suppliers;

        $this->load->view('inventory/manage', $data);
    }

    /**
     * Store inventory item.
     *
     * @return void
     */
    public function store(): void
    {
        if (!staff_can('create', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        if (!$this->input->post()) {
            redirect(admin_url('ramos/inventory'));
        }

        $payload = $this->getItemPayloadFromRequest();

        if ($payload['item_name'] === '' || $payload['item_name'] === null) {
            set_alert('danger', _l('ramos_inventory_validation_required'));
            redirect(admin_url('ramos/inventory'));
        }

        $itemId = $this->inventory_model->create($payload);

        if ($itemId) {
            set_alert('success', _l('ramos_inventory_created'));
        } else {
            set_alert('danger', _l('ramos_inventory_create_failed'));
        }

        redirect(admin_url('ramos/inventory'));
    }

    /**
     * Update inventory item.
     *
     * @param  int $id
     * @return void
     */
    public function update($id): void
    {
        if (!staff_can('edit', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        if (!$this->input->post()) {
            redirect(admin_url('ramos/inventory'));
        }

        $payload = $this->getItemPayloadFromRequest();
        $payload['active'] = $this->input->post('active') ? 1 : 0;

        if (isset($payload['item_name']) && $payload['item_name'] === '') {
            set_alert('danger', _l('ramos_inventory_validation_required'));
            redirect(admin_url('ramos/inventory'));
        }

        $success = $this->inventory_model->update($id, $payload);

        if ($success) {
            set_alert('success', _l('updated_successfully', _l('ramos_inventory_label')));
        } else {
            set_alert('danger', _l('problem_updating', _l('ramos_inventory_label')));
        }

        redirect(admin_url('ramos/inventory'));
    }

    /**
     * Adjust inventory quantity.
     *
     * @param  int $id
     * @return void
     */
    public function adjust($id): void
    {
        if (!staff_can('edit', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        if (!$this->input->post()) {
            redirect(admin_url('ramos/inventory'));
        }

        $delta = (float) $this->input->post('adjustment');

        if ($delta === 0.0) {
            set_alert('warning', _l('ramos_inventory_adjustment_zero'));
            redirect(admin_url('ramos/inventory'));
        }

        $success = $this->inventory_model->adjust_quantity($id, $delta);

        if ($success) {
            set_alert('success', _l('ramos_inventory_adjusted'));
        } else {
            set_alert('danger', _l('ramos_inventory_adjust_failed'));
        }

        redirect(admin_url('ramos/inventory'));
    }

    /**
     * Delete inventory item.
     *
     * @param  int $id
     * @return void
     */
    public function delete($id): void
    {
        if (!staff_can('delete', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        $success = $this->inventory_model->delete($id);

        if ($success) {
            set_alert('success', _l('deleted', _l('ramos_inventory_label')));
        } else {
            set_alert('danger', _l('problem_deleting', _l('ramos_inventory_label')));
        }

        redirect(admin_url('ramos/inventory'));
    }

    /**
     * Upload product image for an inventory item (AJAX / POST).
     *
     * @param  int $id
     * @return void
     */
    public function upload_image($id): void
    {
        if (!staff_can('edit', RAMOS_MODULE_NAME)) {
            $this->output->set_status_header(403)
                ->set_content_type('application/json')
                ->set_output(json_encode(['success' => false, 'message' => _l('access_denied')]));
            return;
        }

        $item = $this->inventory_model->get((int) $id);
        if (empty($item)) {
            $this->output->set_status_header(404)
                ->set_content_type('application/json')
                ->set_output(json_encode(['success' => false, 'message' => _l('ramos_inventory_not_found')]));
            return;
        }

        if (empty($_FILES['image']['name'])) {
            set_alert('danger', _l('ramos_inventory_image_no_file'));
            redirect(admin_url('ramos/inventory'));
            return;
        }

        $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo       = finfo_open(FILEINFO_MIME_TYPE);
        $mime        = finfo_file($finfo, $_FILES['image']['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowedMime, true)) {
            set_alert('danger', _l('ramos_inventory_image_invalid_type'));
            redirect(admin_url('ramos/inventory'));
            return;
        }

        $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $dir     = FCPATH . 'modules/ramos/uploads/item_img/' . (int) $id . '/';
        $newName = 'product_' . (int) $id . '_' . time() . '.' . $ext;

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (!@move_uploaded_file($_FILES['image']['tmp_name'], $dir . $newName)) {
            set_alert('danger', _l('ramos_inventory_image_upload_failed'));
            redirect(admin_url('ramos/inventory'));
            return;
        }

        $relativePath = 'modules/ramos/uploads/item_img/' . (int) $id . '/' . $newName;
        $this->inventory_model->save_image((int) $id, $relativePath);

        set_alert('success', _l('ramos_inventory_image_saved'));
        redirect(admin_url('ramos/inventory'));
    }

    /**
     * Remove product image for an inventory item.
     *
     * @param  int $id
     * @return void
     */
    public function remove_image($id): void
    {
        if (!staff_can('edit', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        $item = $this->inventory_model->get((int) $id);
        if (empty($item)) {
            show_404();
        }

        $this->inventory_model->remove_image((int) $id);

        set_alert('success', _l('ramos_inventory_image_removed'));
        redirect(admin_url('ramos/inventory'));
    }

    /**
     * Prepare payload from request.
     *
     * @return array
     */
    private function getItemPayloadFromRequest(): array
    {
        $rawName = $this->input->post('item_name', true);

        $payload = [
            'item_name'    => $rawName === null ? null : trim((string) $rawName),
            'sku'          => $this->input->post('sku', true),
            'unit'         => $this->input->post('unit', true),
            'quantity'     => (float) $this->input->post('quantity'),
            'safety_stock' => (float) $this->input->post('safety_stock'),
            'notes'        => $this->input->post('notes', true),
        ];

        $buffer = $this->input->post('buffer_percent');
        if ($buffer !== null && $buffer !== '') {
            $payload['buffer_percent'] = (float) $buffer;
        }

        $supplierId = $this->input->post('supplier_id');
        if ($supplierId !== null && $supplierId !== '') {
            $payload['supplier_id'] = (int) $supplierId > 0 ? (int) $supplierId : null;
        }

        return $payload;
    }
}
