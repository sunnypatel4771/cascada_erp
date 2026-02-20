<?php

defined('BASEPATH') or exit('No direct script access allowed');

class FeSat extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('perfex_fesat/fe_sat_model', 'feSatModel');
        $this->load->model('invoices_model');
        $this->load->model('clients_model');
        $this->load->helper(['url', 'form', 'invoices']);
    }

    public function index()
    {
        redirect(admin_url('perfex_fesat/settings'));
    }

    public function settings()
    {
        if (staff_cant('manage_settings', 'fe_sat')) {
            access_denied('fe_sat');
        }

        if ($this->input->post()) {
            $payload = $this->input->post(null, false) ?? [];
            $result  = $this->feSatModel->save_settings($payload, get_staff_user_id());
            set_alert($result['success'] ? 'success' : 'danger', $result['message']);
            redirect(admin_url('perfex_fesat/settings'));
            return;
        }

        $data['title']        = _l('fe_sat_settings_title');
        $data['options']      = $this->feSatModel->get_settings();
        $data['recent_logs']  = $this->feSatModel->get_recent_logs(15);
        $data['diagnostics']  = $this->feSatModel->get_last_diagnostics();

        $this->load->view('perfex_fesat/settings', $data);
    }

    public function test_connection()
    {
        if (!is_admin() && staff_cant('manage_settings', 'fe_sat')) {
            access_denied('fe_sat');
        }

        $result = $this->feSatModel->test_connection();
        set_alert($result['success'] ? 'success' : 'danger', $result['message']);
        redirect(admin_url('perfex_fesat/settings'));
    }

    public function form($invoiceId)
    {
        if (staff_cant('generate', 'fe_sat')) {
            access_denied('fe_sat');
        }

        $invoiceId = (int) $invoiceId;
        $invoice   = $this->invoices_model->get($invoiceId);

        if (!$invoice) {
            show_404();
        }

        $client = $this->clients_model->get($invoice->clientid);
        if (!$client) {
            set_alert('danger', _l('fe_sat_customer_not_found'));
            redirect(admin_url('invoices/list_invoices/' . $invoiceId));
            return;
        }

        $document = $this->feSatModel->find_by_invoice($invoiceId);
        $totals   = $this->feSatModel->calculate_totals($invoice);

        $data = [
            'title'         => _l('fe_sat_form_title'),
            'invoice'       => $invoice,
            'client'        => $client,
            'document'      => $document,
            'totals'        => $totals,
            'defaults'      => $this->feSatModel->get_form_defaults($invoice, $client, $document),
            'items'         => $invoice->items,
        ];

        $this->load->view('perfex_fesat/fe_form', $data);
    }

    public function generate($invoiceId)
    {
        if (staff_cant('generate', 'fe_sat')) {
            access_denied('fe_sat');
        }

        $invoiceId = (int) $invoiceId;
        $invoice   = $this->invoices_model->get($invoiceId);
        if (!$invoice) {
            show_404();
        }

        $payload = $this->input->post(null, false) ?? [];
        $result  = $this->feSatModel->timbrar($invoice, $payload, get_staff_user_id());

        set_alert($result['success'] ? 'success' : 'danger', $result['message']);
        redirect(admin_url('invoices/list_invoices/' . $invoiceId));
    }

    public function view_file($id, $type)
    {
        if (staff_cant('view', 'fe_sat')) {
            access_denied('fe_sat');
        }

        $id   = (int) $id;
        $type = strtolower($type);

        $document = $this->feSatModel->find($id);
        if (!$document) {
            show_404();
        }

        $path = $type === 'pdf' ? $document->pdf_path : $document->xml_path;
        if (!$path || !is_file($path)) {
            show_error(_l('fe_sat_file_missing'), 404, _l('fe_sat_file_missing_title'));
        }

        $mime = $type === 'pdf' ? 'application/pdf' : 'application/xml';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="' . basename($path) . '"');
        readfile($path);
        exit;
    }

    public function send_email($id)
    {
        if (staff_cant('permission_send_email', 'fe_sat')) {
            access_denied('fe_sat');
        }

        $id       = (int) $id;
        $response = $this->feSatModel->send_email($id, get_staff_user_id());

        set_alert($response['success'] ? 'success' : 'danger', $response['message']);
        $redirectInvoice = $response['invoice_id'] ?? null;
        $url = $redirectInvoice ? 'invoices/list_invoices/' . $redirectInvoice : 'invoices/list_invoices';
        redirect(admin_url($url));
    }
}
