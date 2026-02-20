<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Pricing extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        if (!staff_can('view', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        $this->load->model('ramos/pricing_model', 'pricing_model');
        $this->load->model('ramos/inventory_model', 'inventory_model');
        $this->load->model('clients_model');
        $this->load->model('currencies_model');
    }

    public function index(): void
    {
        $inventoryItems = $this->inventory_model->get(null, ['active' => 1]);
        $customers      = $this->clients_model->get('', [db_prefix() . 'clients.active' => 1]);
        $currencies     = $this->currencies_model->get();

        $filters = [];
        $selectedInventory = (int) $this->input->get('inventory_item_id');
        $selectedCustomer  = $this->input->get('customer_id');

        if ($selectedInventory > 0) {
            $filters['inventory_item_id'] = $selectedInventory;
        }

        if ($selectedCustomer !== null && $selectedCustomer !== '') {
            $filters['customer_id'] = (int) $selectedCustomer;
        }

        $filters['active'] = $this->input->get('active') !== null ? (int) $this->input->get('active') : null;

        $filters = array_filter($filters, static function ($value) {
            return $value !== null;
        });

        $priceRules = $this->pricing_model->get(null, $filters);

        $inventoryMap = [];
        foreach ($inventoryItems as $item) {
            $inventoryMap[(int) $item['id']] = $item;
        }

        $customerMap = [];
        foreach ($customers as $customer) {
            $customerMap[(int) $customer['userid']] = $customer;
        }

        $currencyMap = [];
        foreach ($currencies as $currency) {
            $currencyMap[(int) $currency['id']] = $currency;
        }

        $data['title']             = _l('ramos_pricing_title');
        $data['subtitle']          = _l('ramos_pricing_subtitle');
        $data['price_rules']       = $priceRules;
        $data['inventory_items']   = $inventoryItems;
        $data['customers']         = $customers;
        $data['currencies']        = $currencies;
        $data['inventory_map']     = $inventoryMap;
        $data['customer_map']      = $customerMap;
        $data['currency_map']      = $currencyMap;
        $data['selected_inventory']= $selectedInventory;
        $data['selected_customer'] = $selectedCustomer;

        $this->load->view('pricing/manage', $data);
    }

    public function store(): void
    {
        if (!staff_can('create', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        if (!$this->input->post()) {
            redirect(admin_url('ramos/pricing'));
        }

        $payload = [
            'inventory_item_id' => (int) $this->input->post('inventory_item_id'),
            'customer_id'       => $this->input->post('customer_id') !== '' ? (int) $this->input->post('customer_id') : null,
            'price'             => (float) $this->input->post('price'),
            'discount_percent'  => (float) $this->input->post('discount_percent'),
            'currency'          => $this->input->post('currency') !== '' ? (int) $this->input->post('currency') : null,
            'notes'             => $this->input->post('notes'),
            'active'            => $this->input->post('active') ? 1 : 0,
        ];

        try {
            $id = $this->pricing_model->create($payload);
            if ($id) {
                set_alert('success', _l('ramos_pricing_created'));
            } else {
                set_alert('warning', _l('ramos_pricing_create_failed'));
            }
        } catch (Throwable $exception) {
            log_message('error', 'Ramos pricing create failed: ' . $exception->getMessage());
            set_alert('danger', _l('ramos_pricing_create_failed'));
        }

        redirect(admin_url('ramos/pricing'));
    }

    public function update($id): void
    {
        if (!staff_can('edit', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        if (!$this->input->post()) {
            redirect(admin_url('ramos/pricing'));
        }

        $payload = [
            'price'            => (float) $this->input->post('price'),
            'discount_percent' => (float) $this->input->post('discount_percent'),
            'currency'         => $this->input->post('currency') !== '' ? (int) $this->input->post('currency') : null,
            'notes'            => $this->input->post('notes'),
            'active'           => $this->input->post('active') ? 1 : 0,
        ];

        $success = $this->pricing_model->update($id, $payload);

        if ($success) {
            set_alert('success', _l('ramos_pricing_updated'));
        } else {
            set_alert('warning', _l('ramos_pricing_update_failed'));
        }

        redirect(admin_url('ramos/pricing'));
    }

    public function delete($id): void
    {
        if (!staff_can('delete', RAMOS_MODULE_NAME)) {
            access_denied();
        }

        $success = $this->pricing_model->delete($id);

        if ($success) {
            set_alert('success', _l('ramos_pricing_deleted'));
        } else {
            set_alert('warning', _l('ramos_pricing_delete_failed'));
        }

        redirect(admin_url('ramos/pricing'));
    }
}
