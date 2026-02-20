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
        redirect(admin_url('fe_sat/settings'));
    }

    public function settings()
    {
        if (staff_cant('manage_settings', 'fe_sat')) {
            access_denied('fe_sat');
        }

        if ($this->input->post()) {
            $payload = $this->input->post(null, false) ?? [];

            // Handle CSD file uploads
            $uploadResult = $this->handle_csd_file_uploads();
            if (!$uploadResult['success'] && !empty($uploadResult['message'])) {
                set_alert('danger', $uploadResult['message']);
                redirect(admin_url('fe_sat/settings'));
                return;
            }

            // Merge uploaded file paths into payload
            if (!empty($uploadResult['csd_cer_path'])) {
                $payload['csd_cer_path'] = $uploadResult['csd_cer_path'];
                $payload['csd_cer_filename'] = $uploadResult['csd_cer_filename'];
                $payload['csd_cer_uploaded_at'] = date('Y-m-d H:i:s');
            }
            if (!empty($uploadResult['csd_key_path'])) {
                $payload['csd_key_path'] = $uploadResult['csd_key_path'];
                $payload['csd_key_filename'] = $uploadResult['csd_key_filename'];
                $payload['csd_key_uploaded_at'] = date('Y-m-d H:i:s');
            }

            $result  = $this->feSatModel->save_settings($payload, get_staff_user_id());
            set_alert($result['success'] ? 'success' : 'danger', $result['message']);
            redirect(admin_url('fe_sat/settings'));
            return;
        }

        $data['title']        = _l('fe_sat_settings_title');
        $data['options']      = $this->feSatModel->get_settings();
        $data['recent_logs']  = $this->feSatModel->get_recent_logs(15);
        $data['diagnostics']  = $this->feSatModel->get_last_diagnostics();

        $this->load->view('perfex_fesat/settings', $data);
    }

    /**
     * Handle CSD certificate and key file uploads
     */
    private function handle_csd_file_uploads(): array
    {
        $result = [
            'success' => true,
            'message' => '',
            'csd_cer_path' => null,
            'csd_cer_filename' => null,
            'csd_key_path' => null,
            'csd_key_filename' => null,
        ];

        // Ensure CSD upload directory exists
        $csdDir = PERFEX_FESAT_UPLOAD_DIR . 'csd/';
        if (!is_dir($csdDir)) {
            if (!mkdir($csdDir, 0755, true) && !is_dir($csdDir)) {
                return [
                    'success' => false,
                    'message' => 'Failed to create CSD upload directory',
                ];
            }

            // Create .htaccess to protect directory
            $htaccess = $csdDir . '.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "Deny from all\n");
            }
        }

        // Handle .cer file upload
        if (!empty($_FILES['csd_cer_file']['name'])) {
            $fileExt = pathinfo($_FILES['csd_cer_file']['name'], PATHINFO_EXTENSION);

            if (strtolower($fileExt) !== 'cer') {
                return [
                    'success' => false,
                    'message' => 'CSD certificate must be a .cer file',
                ];
            }

            if ($_FILES['csd_cer_file']['error'] !== UPLOAD_ERR_OK) {
                return [
                    'success' => false,
                    'message' => 'Failed to upload CSD certificate file',
                ];
            }

            $filename = 'csd_certificate_' . time() . '.cer';
            $uploadPath = $csdDir . $filename;

            if (move_uploaded_file($_FILES['csd_cer_file']['tmp_name'], $uploadPath)) {
                $result['csd_cer_path'] = $uploadPath;
                $result['csd_cer_filename'] = $_FILES['csd_cer_file']['name'];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to save CSD certificate file',
                ];
            }
        }

        // Handle .key file upload
        if (!empty($_FILES['csd_key_file']['name'])) {
            $fileExt = pathinfo($_FILES['csd_key_file']['name'], PATHINFO_EXTENSION);

            if (strtolower($fileExt) !== 'key') {
                return [
                    'success' => false,
                    'message' => 'CSD private key must be a .key file',
                ];
            }

            if ($_FILES['csd_key_file']['error'] !== UPLOAD_ERR_OK) {
                return [
                    'success' => false,
                    'message' => 'Failed to upload CSD private key file',
                ];
            }

            $filename = 'csd_private_key_' . time() . '.key';
            $uploadPath = $csdDir . $filename;

            if (move_uploaded_file($_FILES['csd_key_file']['tmp_name'], $uploadPath)) {
                $result['csd_key_path'] = $uploadPath;
                $result['csd_key_filename'] = $_FILES['csd_key_file']['name'];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to save CSD private key file',
                ];
            }
        }

        return $result;
    }

    public function test_connection()
    {
        if (!is_admin() && staff_cant('manage_settings', 'fe_sat')) {
            access_denied('fe_sat');
        }

        $result = $this->feSatModel->test_connection();
        set_alert($result['success'] ? 'success' : 'danger', $result['message']);
        redirect(admin_url('fe_sat/settings'));
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

    /**
     * Show cancellation form modal
     */
    public function cancel_form($invoiceId)
    {
        if (staff_cant('generate', 'fe_sat')) {
            access_denied('fe_sat');
        }

        $invoiceId = (int) $invoiceId;
        $invoice = $this->invoices_model->get($invoiceId);
        if (!$invoice) {
            show_404();
        }

        $document = $this->feSatModel->find_by_invoice($invoiceId);
        if (!$document || $document->status !== 'success' || empty($document->digibox_uuid)) {
            set_alert('danger', _l('fe_sat_cannot_cancel_invoice'));
            redirect(admin_url('invoices/list_invoices/' . $invoiceId));
            return;
        }

        $data = [
            'title' => _l('fe_sat_cancel_invoice_title'),
            'invoice' => $invoice,
            'document' => $document,
        ];

        $this->load->view('perfex_fesat/cancel_form', $data);
    }

    /**
     * Process cancellation request
     */
    public function cancel($invoiceId)
    {
        if (staff_cant('generate', 'fe_sat')) {
            access_denied('fe_sat');
        }

        $invoiceId = (int) $invoiceId;
        $invoice = $this->invoices_model->get($invoiceId);
        if (!$invoice) {
            show_404();
        }

        $document = $this->feSatModel->find_by_invoice($invoiceId);
        if (!$document || $document->status !== 'success' || empty($document->digibox_uuid)) {
            set_alert('danger', _l('fe_sat_cannot_cancel_invoice'));
            redirect(admin_url('invoices/list_invoices/' . $invoiceId));
            return;
        }

        $payload = $this->input->post(null, false) ?? [];
        $result = $this->feSatModel->cancelar($document, $payload, get_staff_user_id());

        // Prepare data for debug view
        $data = [
            'title' => _l('fe_sat_cancel_invoice_title'),
            'invoice' => $invoice,
            'document' => $document,
            'result' => $result,
            'payload' => $payload,
        ];

        // Load the cancellation result view with debug output
        $this->load->view('perfex_fesat/cancel_result', $data);
    }
}
