<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Admin controller for managing item unit equivalencias.
 * URL: /admin/ramos/equivalencias
 */
class Equivalencias extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        if (!staff_can('edit', RAMOS_MODULE_NAME)) {
            access_denied(RAMOS_MODULE_NAME);
        }

        $this->load->model('ramos/inventory_model', 'inventory_model');
    }

    /**
     * Main equivalencias management page.
     */
    public function index(): void
    {
        $data['title']   = _l('ramos_equivalencias_title');
        $data['items']   = $this->inventory_model->get_items_with_equivalences();

        $this->load->view('ramos/equivalencias/manage', $data);
    }

    /**
     * AJAX: Get equivalencias for a specific item.
     * GET /admin/ramos/equivalencias/get/{item_id}
     */
    public function get(int $itemId = 0): void
    {
        if (!$itemId) {
            $this->output->set_content_type('application/json')
                ->set_output(json_encode(['success' => false]));
            return;
        }

        $equivs = $this->inventory_model->get_equivalences($itemId);

        $this->output->set_content_type('application/json')
            ->set_output(json_encode(['success' => true, 'data' => $equivs]));
    }

    /**
     * AJAX POST: Save (add/update) an equivalencia.
     */
    public function save(): void
    {
        if (!$this->input->post()) {
            show_error('Invalid request', 400);
        }

        $data = [
            'id'                => (int) $this->input->post('id'),
            'item_id'           => (int) $this->input->post('item_id'),
            'unit_name'         => trim($this->input->post('unit_name', true)),
            'conversion_factor' => (float) $this->input->post('conversion_factor'),
            'sort_order'        => (int) $this->input->post('sort_order'),
        ];

        if (!$data['item_id'] || $data['unit_name'] === '') {
            $this->output->set_content_type('application/json')
                ->set_output(json_encode(['success' => false, 'message' => 'Missing fields']));
            return;
        }

        $id = $this->inventory_model->save_equivalence($data);

        $this->output->set_content_type('application/json')
            ->set_output(json_encode(['success' => (bool) $id, 'id' => $id]));
    }

    /**
     * AJAX POST: Delete an equivalencia by ID.
     */
    public function delete(int $id = 0): void
    {
        if (!$id) {
            $this->output->set_content_type('application/json')
                ->set_output(json_encode(['success' => false]));
            return;
        }

        $result = $this->inventory_model->delete_equivalence($id);

        $this->output->set_content_type('application/json')
            ->set_output(json_encode(['success' => $result]));
    }
}
