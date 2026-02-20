<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Facturacion extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if (!is_staff_logged_in()) {
            redirect(admin_url('authentication'));
        }
        $this->load->model('facturacion/Facturacion_model');
    }

    public function index()
    {
        $data['title'] = 'Facturación';
        $data['groups'] = $this->Facturacion_model->get_ready_groups();
        $this->load->view('facturacion/manage', $data);
    }

    public function settings()
    {
        $data['title'] = 'Configuración - Facturación';
        $data['tables'] = $this->Facturacion_model->env_check_tables();
        $this->load->view('facturacion/settings', $data);
    }

    public function generate_invoice()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $detail_ids = $this->input->post('detail_ids');
        if (!is_array($detail_ids) || count($detail_ids) === 0) {
            set_alert('warning', 'Selecciona al menos un producto en VERDE.');
            redirect(admin_url('facturacion'));
        }

        $detail_ids = array_values(array_unique(array_map('intval', $detail_ids)));
        $result = $this->Facturacion_model->create_invoice_from_details($detail_ids);

        if (!$result['ok']) {
            set_alert('danger', $result['message']);
            redirect(admin_url('facturacion'));
        }

        set_alert('success', 'Factura creada correctamente. ID: ' . $result['invoice_id']);
        redirect(admin_url('invoices/list_invoices/' . $result['invoice_id']));
    }
}
