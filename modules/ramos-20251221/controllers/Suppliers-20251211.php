<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Suppliers extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        if (!staff_can('view', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        $this->load->model('ramos/suppliers_model', 'suppliers_model');
        $this->load->helper('form');
    }

    public function index(): void
    {
        $suppliers = $this->suppliers_model->get();

        $data['title']     = _l('ramos_suppliers_title');
        $data['subtitle']  = _l('ramos_suppliers_subtitle');
        $data['suppliers'] = $suppliers;

        $this->load->view('suppliers/manage', $data);
    }

    public function store(): void
    {
        if (!staff_can('create', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        if (!$this->input->post()) {
            redirect(admin_url('ramos/suppliers'));
        }

        $payload = $this->getPayloadFromRequest();

        if ($payload['supplier_name'] === '') {
            set_alert('danger', _l('ramos_suppliers_validation_required'));
            redirect(admin_url('ramos/suppliers'));
        }

        $id = $this->suppliers_model->create($payload);

        if ($id) {
            set_alert('success', _l('ramos_suppliers_created'));
        } else {
            set_alert('danger', _l('ramos_suppliers_create_failed'));
        }

        redirect(admin_url('ramos/suppliers'));
    }

    public function update($id): void
    {
        if (!staff_can('edit', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        if (!$this->input->post()) {
            redirect(admin_url('ramos/suppliers'));
        }

        $payload = $this->getPayloadFromRequest();

        if (isset($payload['supplier_name']) && $payload['supplier_name'] === '') {
            set_alert('danger', _l('ramos_suppliers_validation_required'));
            redirect(admin_url('ramos/suppliers'));
        }

        $payload['active'] = $this->input->post('active') ? 1 : 0;

        $success = $this->suppliers_model->update($id, $payload);

        if ($success) {
            set_alert('success', _l('updated_successfully', _l('ramos_suppliers_label')));
        } else {
            set_alert('danger', _l('problem_updating', _l('ramos_suppliers_label')));
        }

        redirect(admin_url('ramos/suppliers'));
    }

    public function delete($id): void
    {
        if (!staff_can('delete', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        $success = $this->suppliers_model->delete($id);

        if ($success) {
            set_alert('success', _l('deleted', _l('ramos_suppliers_label')));
        } else {
            set_alert('danger', _l('problem_deleting', _l('ramos_suppliers_label')));
        }

        redirect(admin_url('ramos/suppliers'));
    }

    private function getPayloadFromRequest(): array
    {
        $name = $this->input->post('supplier_name', true);

        return [
            'supplier_name' => $name === null ? '' : trim((string) $name),
            'contact_name'  => $this->input->post('contact_name', true),
            'phone'         => $this->input->post('phone', true),
            'email'         => $this->input->post('email', true),
            'notes'         => $this->input->post('notes', true),
        ];
    }
}
