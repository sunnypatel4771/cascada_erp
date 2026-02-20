<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Fe_sat_model extends App_Model
{
    protected $docsTable;
    protected $logsTable;

    public function __construct()
    {
        parent::__construct();
        $this->docsTable = db_prefix() . 'fe_sat_docs';
        $this->logsTable = db_prefix() . 'fe_sat_logs';
        $this->load->library('perfex_fesat/Fe_sat_cfdi_builder', null, 'cfdi_builder');
        $this->load->library('perfex_fesat/Fe_sat_digibox_client', null, 'digibox_client');
        $this->load->helper(['security', 'string', 'clients']);
        $this->load->model('clients_model');
    }

    public function find_by_invoice(int $invoiceId)
    {
        return $this->db
            ->where('invoice_id', $invoiceId)
            ->get($this->docsTable)
            ->row();
    }

    public function find(int $id)
    {
        return $this->db
            ->where('id', $id)
            ->get($this->docsTable)
            ->row();
    }

    public function calculate_totals($invoice): array
    {
        return $this->cfdi_builder->calculateTotals($invoice);
    }

    public function get_form_defaults($invoice, $client, $document = null): array
    {
        $serieDefault   = get_option('perfex_fesat_series_default');
        $folioDefault   = get_option('perfex_fesat_folio_next');
        $cfdiDefault    = get_option('perfex_fesat_cfdi_use_default');
        $paymentMethod  = get_option('perfex_fesat_payment_method');
        $paymentForm    = get_option('perfex_fesat_payment_form');
        $currency       = get_option('perfex_fesat_currency') ?: ($invoice->currency_name ?? 'MXN');
        $sandbox        = get_option('perfex_fesat_sandbox_mode') === '1';

        return [
            'serie'          => $document->serie ?? $serieDefault,
            'folio'          => $document->folio ?? $folioDefault,
            'uso_cfdi'       => $document->uso_cfdi ?? $cfdiDefault,
            'payment_method' => $document->payment_method ?? $paymentMethod,
            'payment_form'   => $document->payment_form ?? $paymentForm,
            'currency'       => $document->currency ?? $currency,
            'customer_rfc'   => $document->customer_rfc ?? ($client->vat ?? ''),
            'customer_regimen' => $document->customer_regimen ?? '601',
            'customer_name'  => $client->company ?? '',
            'sandbox'        => $sandbox,
        ];
    }

    public function timbrar($invoice, array $formData, int $staffId): array
    {
        $document              = $this->find_by_invoice((int) $invoice->id);
        $regenerationRequested = !empty($formData['force_regenerate']);

        if ($document && $document->status === 'success' && !$regenerationRequested) {
            return [
                'success' => false,
                'message' => _l('fe_sat_already_stamped'),
                'invoice_id' => $invoice->id,
            ];
        }

        $buildResult = $this->cfdi_builder->build($invoice, $formData);
        if (!$buildResult['success']) {
            return [
                'success'    => false,
                'message'    => implode('<br>', $buildResult['errors']),
                'invoice_id' => $invoice->id,
            ];
        }

        if ($document) {
            $docId = (int) $document->id;
            $this->db->where('id', $docId)->update($this->docsTable, [
                'status'           => 'pending',
                'message'          => '',
                'subtotal'         => $buildResult['totals']['subtotal'],
                'iva'              => $buildResult['totals']['iva'],
                'total'            => $buildResult['totals']['total'],
                'amount_in_words'  => $buildResult['totals']['amount_in_words'],
                'customer_rfc'     => $buildResult['customer']['rfc'],
                'customer_regimen' => $buildResult['customer']['regimen'],
                'serie'            => $buildResult['serie'],
                'folio'            => $buildResult['folio'],
                'uso_cfdi'         => $buildResult['uso_cfdi'],
                'payment_method'   => $buildResult['payment_method'],
                'payment_form'     => $buildResult['payment_form'],
                'currency'         => $buildResult['currency'],
                'updated_at'       => date('Y-m-d H:i:s'),
            ]);
        } else {
            $docId = $this->create_document_record($invoice, $buildResult['totals'], [
                'status'           => 'pending',
                'customer_rfc'     => $buildResult['customer']['rfc'],
                'customer_regimen' => $buildResult['customer']['regimen'],
                'serie'            => $buildResult['serie'],
                'folio'            => $buildResult['folio'],
                'uso_cfdi'         => $buildResult['uso_cfdi'],
                'payment_method'   => $buildResult['payment_method'],
                'payment_form'     => $buildResult['payment_form'],
                'currency'         => $buildResult['currency'],
            ]);
        }

        $requestMeta = [
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->clientid,
            'serie' => $buildResult['serie'],
            'folio' => $buildResult['folio'],
            'sandbox' => $buildResult['sandbox'],
        ];

        $apiResponse = $this->digibox_client->timbrar($buildResult['xml'], $requestMeta);

        $this->record_log($docId, 'request', $apiResponse['endpoint'], $apiResponse['http_code'] ?? null, $apiResponse['request_excerpt'] ?? '');

        if ($apiResponse['success']) {
            $paths = $this->store_files($invoice->id, $apiResponse['uuid'], $apiResponse['xml'], $apiResponse['pdf']);

            $update = [
                'digibox_uuid'    => $apiResponse['uuid'],
                'status'          => 'success',
                'message'         => _l('fe_sat_timbrado_success'),
                'xml_path'        => $paths['xml'],
                'pdf_path'        => $paths['pdf'],
                'subtotal'        => $buildResult['totals']['subtotal'],
                'iva'             => $buildResult['totals']['iva'],
                'total'           => $buildResult['totals']['total'],
                'amount_in_words' => $buildResult['totals']['amount_in_words'],
                'updated_at'      => date('Y-m-d H:i:s'),
            ];
            $this->db->where('id', $docId)->update($this->docsTable, $update);

            $this->record_log($docId, 'response', $apiResponse['endpoint'], $apiResponse['http_code'], $apiResponse['body_excerpt'] ?? '');

            $this->increment_folio_counter($buildResult['folio']);
            log_activity('FE-SAT timbrado exitoso para factura #' . $invoice->id . ' por staff #' . $staffId);

            return [
                'success'    => true,
                'message'    => _l('fe_sat_timbrado_success'),
                'document_id'=> $docId,
                'invoice_id' => $invoice->id,
            ];
        }

        $this->db->where('id', $docId)->update($this->docsTable, [
            'status'     => 'error',
            'message'    => $apiResponse['message'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->record_log($docId, 'response', $apiResponse['endpoint'], $apiResponse['http_code'], $apiResponse['body_excerpt'] ?? $apiResponse['message']);

        return [
            'success'    => false,
            'message'    => $apiResponse['message'],
            'invoice_id' => $invoice->id,
        ];
    }

    public function save_settings(array $payload, int $staffId): array
    {
        $fields = [
            'perfex_fesat_base_url'       => trim((string) ($payload['base_url'] ?? '')),
            'perfex_fesat_series_default' => trim((string) ($payload['series_default'] ?? 'A')),
            'perfex_fesat_folio_next'     => trim((string) ($payload['folio_next'] ?? '1')),
            'perfex_fesat_cfdi_use_default' => trim((string) ($payload['cfdi_use_default'] ?? 'G03')),
            'perfex_fesat_payment_method' => trim((string) ($payload['payment_method'] ?? 'PPD')),
            'perfex_fesat_payment_form'   => trim((string) ($payload['payment_form'] ?? '99')),
            'perfex_fesat_currency'       => trim((string) ($payload['currency'] ?? 'MXN')),
            'perfex_fesat_storage_driver' => trim((string) ($payload['storage_driver'] ?? 'local')),
            'perfex_fesat_sandbox_mode'   => !empty($payload['sandbox_mode']) ? '1' : '0',
            'perfex_fesat_company_regimen' => trim((string) ($payload['company_regimen'] ?? '601')),
        ];

        foreach ($fields as $option => $value) {
            update_option($option, $value);
        }

        $secretFields = [
            'perfex_fesat_username' => $payload['username'] ?? '',
            'perfex_fesat_password' => $payload['password'] ?? '',
            'perfex_fesat_api_key'  => $payload['api_key'] ?? '',
        ];

        foreach ($secretFields as $option => $value) {
            if ($value === '') {
                continue;
            }
            update_option($option, app_encrypt(trim((string) $value)));
        }

        log_activity('FE-SAT settings updated by staff #' . $staffId);

        return [
            'success' => true,
            'message' => _l('fe_sat_settings_saved'),
        ];
    }

    public function get_settings(): array
    {
        $passwordEncrypted = get_option('perfex_fesat_password');
        $apiEncrypted      = get_option('perfex_fesat_api_key');

        return [
            'base_url'        => get_option('perfex_fesat_base_url'),
            'series_default'  => get_option('perfex_fesat_series_default'),
            'folio_next'      => get_option('perfex_fesat_folio_next'),
            'cfdi_use_default'=> get_option('perfex_fesat_cfdi_use_default'),
            'payment_method'  => get_option('perfex_fesat_payment_method'),
            'payment_form'    => get_option('perfex_fesat_payment_form'),
            'currency'        => get_option('perfex_fesat_currency'),
            'storage_driver'  => get_option('perfex_fesat_storage_driver'),
            'sandbox_mode'    => get_option('perfex_fesat_sandbox_mode') === '1',
            'company_regimen' => get_option('perfex_fesat_company_regimen'),
            'username'        => $this->maybeDecryptOption('perfex_fesat_username'),
            'password'        => '',
            'api_key'         => '',
            'has_password'    => !empty($passwordEncrypted),
            'has_api_key'     => !empty($apiEncrypted),
        ];
    }

    public function get_recent_logs(int $limit = 15): array
    {
        $this->db->select($this->logsTable . '.*, ' . $this->docsTable . '.invoice_id');
        $this->db->join($this->docsTable, $this->docsTable . '.id = ' . $this->logsTable . '.fe_id', 'left');
        $this->db->order_by($this->logsTable . '.created_at', 'DESC');
        $this->db->limit($limit);

        return $this->db->get($this->logsTable)->result_array();
    }

    public function get_last_diagnostics(): ?array
    {
        $raw = get_option('perfex_fesat_last_settings_test');
        if (!$raw) {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function test_connection(): array
    {
        $response = $this->digibox_client->ping();

        update_option('perfex_fesat_last_settings_test', json_encode([
            'time'      => date('c'),
            'success'   => $response['success'],
            'message'   => $response['message'],
            'http_code' => $response['http_code'] ?? null,
        ]));

        return $response;
    }

    public function send_email(int $documentId, int $staffId): array
    {
        $document = $this->find($documentId);
        if (!$document) {
            return ['success' => false, 'message' => _l('fe_sat_document_not_found')];
        }

        if ($document->status !== 'success') {
            return ['success' => false, 'message' => _l('fe_sat_cannot_email_non_success')];
        }

        $invoice = $this->load_invoice($document->invoice_id);
        if (!$invoice) {
            return ['success' => false, 'message' => _l('fe_sat_invoice_not_found')];
        }

        $contact = $this->clients_model->get_contact(get_primary_contact_user_id($invoice->clientid));
        if (!$contact) {
            return ['success' => false, 'message' => _l('fe_sat_primary_contact_missing')];
        }

        if (!is_file($document->xml_path) || !is_file($document->pdf_path)) {
            return ['success' => false, 'message' => _l('fe_sat_file_missing')];
        }

        $templateSlug = get_option('perfex_fesat_email_template') ?: 'fe_sat_send_email';

        $mailResult = send_mail_template(
            $templateSlug,
            PERFEX_FESAT_MODULE_NAME,
            $document,
            $invoice,
            $contact,
            [
                $document->xml_path,
                $document->pdf_path,
            ]
        );

        if ($mailResult) {
            log_activity('FE-SAT files emailed for invoice #' . $invoice->id . ' by staff #' . $staffId);
            return ['success' => true, 'message' => _l('fe_sat_email_sent'), 'invoice_id' => $invoice->id];
        }

        return ['success' => false, 'message' => _l('fe_sat_email_failed'), 'invoice_id' => $invoice->id];
    }

    public function handle_webhook(string $payload, string $signature): array
    {
        if ($payload === '') {
            return ['success' => false, 'message' => _l('fe_sat_webhook_payload_missing')];
        }

        $data = json_decode($payload, true);
        if (!is_array($data)) {
            return ['success' => false, 'message' => _l('fe_sat_webhook_invalid_payload')];
        }

        $invoiceId = (int) ($data['invoice_id'] ?? 0);
        if ($invoiceId <= 0) {
            return ['success' => false, 'message' => _l('fe_sat_webhook_invoice_missing')];
        }

        $document = $this->find_by_invoice($invoiceId);
        if (!$document) {
            return ['success' => false, 'message' => _l('fe_sat_document_not_found')];
        }

        $this->record_log((int) $document->id, 'response', 'webhook', null, substr($payload, 0, 65535));

        if (!empty($data['status']) && $data['status'] === 'success') {
            $this->db->where('id', $document->id)->update($this->docsTable, [
                'status'     => 'success',
                'message'    => $data['message'] ?? _l('fe_sat_timbrado_success'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } elseif (!empty($data['status'])) {
            $this->db->where('id', $document->id)->update($this->docsTable, [
                'status'     => $data['status'],
                'message'    => $data['message'] ?? '',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return ['success' => true, 'message' => 'OK'];
    }

    protected function load_invoice(int $invoiceId)
    {
        if (!class_exists('Invoices_model', false)) {
            $this->load->model('invoices_model');
        }

        return $this->invoices_model->get($invoiceId);
    }

    protected function create_document_record($invoice, array $totals, array $meta): int
    {
        $data = [
            'invoice_id'       => $invoice->id,
            'customer_id'      => $invoice->clientid,
            'status'           => $meta['status'] ?? 'pending',
            'message'          => '',
            'subtotal'         => $totals['subtotal'],
            'iva'              => $totals['iva'],
            'total'            => $totals['total'],
            'amount_in_words'  => $totals['amount_in_words'],
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ];

        foreach (['customer_rfc', 'customer_regimen', 'serie', 'folio', 'uso_cfdi', 'payment_method', 'payment_form', 'currency'] as $field) {
            if (isset($meta[$field])) {
                $data[$field] = $meta[$field];
            }
        }

        $this->db->insert($this->docsTable, $data);

        return (int) $this->db->insert_id();
    }

    protected function store_files(int $invoiceId, string $uuid, ?string $xml, ?string $pdf): array
    {
        $baseDir = PERFEX_FESAT_UPLOAD_DIR . $invoiceId . '/';
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }

        $timestamp = date('Ymd_His');

        $paths = ['xml' => null, 'pdf' => null];

        if ($xml) {
            $xmlPath = $baseDir . $timestamp . '_' . $uuid . '.xml';
            file_put_contents($xmlPath, $xml);
            $paths['xml'] = $xmlPath;
        }

        if ($pdf) {
            $pdfData = $this->maybeDecodeBase64($pdf);
            $pdfPath = $baseDir . $timestamp . '_' . $uuid . '.pdf';
            file_put_contents($pdfPath, $pdfData);
            $paths['pdf'] = $pdfPath;
        }

        return $paths;
    }

    protected function maybeDecodeBase64(string $data): string
    {
        $decoded = base64_decode($data, true);
        return $decoded !== false ? $decoded : $data;
    }

    protected function record_log(int $documentId, string $direction, string $endpoint, $httpCode, string $payload): void
    {
        $this->db->insert($this->logsTable, [
            'fe_id'           => $documentId,
            'direction'       => $direction,
            'endpoint'        => $endpoint,
            'http_code'       => $httpCode,
            'payload_excerpt' => mb_substr($payload, 0, 65535),
            'created_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    protected function increment_folio_counter($folio): void
    {
        $current = (int) get_option('perfex_fesat_folio_next');
        $folio   = (int) $folio;
        if ($folio >= $current) {
            update_option('perfex_fesat_folio_next', (string) ($folio + 1));
        }
    }

    protected function maybeDecryptOption(string $key): string
    {
        $value = get_option($key);
        if (!$value) {
            return '';
        }

        return app_decrypt($value);
    }
}
