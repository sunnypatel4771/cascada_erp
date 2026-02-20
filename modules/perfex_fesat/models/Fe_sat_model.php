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
        $this->load->library('encryption');
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
        $serieDefault = get_option('perfex_fesat_series_default');
        $folioDefault = get_option('perfex_fesat_folio_next');
        $cfdiDefault = get_option('perfex_fesat_cfdi_use_default');
        $paymentMethod = get_option('perfex_fesat_payment_method');
        $paymentForm = get_option('perfex_fesat_payment_form');
        $currency = get_option('perfex_fesat_currency') ?: ($invoice->currency_name ?? 'MXN');
        $sandbox = get_option('perfex_fesat_sandbox_mode') === '1';

        return [
            'serie' => $document->serie ?? $serieDefault,
            'folio' => $document->folio ?? $folioDefault,
            'uso_cfdi' => $document->uso_cfdi ?? $cfdiDefault,
            'payment_method' => $document->payment_method ?? $paymentMethod,
            'payment_form' => $document->payment_form ?? $paymentForm,
            'currency' => $document->currency ?? $currency,
            'customer_rfc' => $document->customer_rfc ?? ($client->vat ?? ''),
            'customer_regimen' => $document->customer_regimen ?? '601',
            'customer_name' => $client->company ?? '',
            'sandbox' => $sandbox,
        ];
    }

    public function timbrar($invoice, array $formData, int $staffId): array
    {
        $document = $this->find_by_invoice((int) $invoice->id);
        $regenerationRequested = !empty($formData['force_regenerate']);

        if ($document && $document->status === 'success' && !$regenerationRequested) {
            return [
                'success' => false,
                'message' => _l('fe_sat_document_already_stamped'),
            ];
        }

        // Build CFDI XML
        $buildResult = $this->cfdi_builder->build($invoice, $formData);

        if (!$buildResult['success']) {
            return [
                'success' => false,
                'message' => _l('fe_sat_xml_build_failed'),
                'errors' => $buildResult['errors'] ?? [],
            ];
        }

        if ($document) {
            $docId = (int) $document->id;
            $this->db->where('id', $docId)->update($this->docsTable, [
                'status' => 'pending',
                'message' => '',
                'subtotal' => $buildResult['totals']['subtotal'],
                'iva' => $buildResult['totals']['iva'],
                'total' => $buildResult['totals']['total'],
                'amount_in_words' => $buildResult['totals']['amount_in_words'],
                'customer_rfc' => $buildResult['customer']['rfc'],
                'customer_regimen' => $buildResult['customer']['regimen'],
                'serie' => $buildResult['serie'],
                'folio' => $buildResult['folio'],
                'uso_cfdi' => $buildResult['uso_cfdi'],
                'payment_method' => $buildResult['payment_method'],
                'payment_form' => $buildResult['payment_form'],
                'currency' => $buildResult['currency'],
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $docId = $this->create_document_record($invoice, $buildResult['totals'], [
                'status' => 'pending',
                'customer_rfc' => $buildResult['customer']['rfc'],
                'customer_regimen' => $buildResult['customer']['regimen'],
                'serie' => $buildResult['serie'],
                'folio' => $buildResult['folio'],
                'uso_cfdi' => $buildResult['uso_cfdi'],
                'payment_method' => $buildResult['payment_method'],
                'payment_form' => $buildResult['payment_form'],
                'currency' => $buildResult['currency'],
            ]);
        }

        $requestMeta = [
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->clientid,
            'serie' => $buildResult['serie'],
            'folio' => $buildResult['folio'],
            'sandbox' => $buildResult['sandbox'],
        ];

        // Call DigiBox API
        $apiResponse = $this->digibox_client->timbrar($buildResult['xml'], $requestMeta);

        // Log the request
        $this->record_log($docId, 'request', $apiResponse['endpoint'] ?? '', $apiResponse['http_code'] ?? null, $apiResponse['request_excerpt'] ?? '');

        if ($apiResponse['success']) {
            // Store files
            $paths = $this->store_files($invoice->id, $apiResponse['uuid'], $apiResponse['xml'], $apiResponse['pdf']);

            // Update database record with success
            $update = [
                'digibox_uuid' => $apiResponse['uuid'],
                'status' => 'success',
                'message' => _l('fe_sat_timbrado_success'),
                'xml_path' => $paths['xml'],
                'pdf_path' => $paths['pdf'],
                'subtotal' => $buildResult['totals']['subtotal'],
                'iva' => $buildResult['totals']['iva'],
                'total' => $buildResult['totals']['total'],
                'amount_in_words' => $buildResult['totals']['amount_in_words'],
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            $this->db->where('id', $docId)->update($this->docsTable, $update);

            // Log response
            $this->record_log($docId, 'response', $apiResponse['endpoint'], $apiResponse['http_code'], $apiResponse['body_excerpt'] ?? '');

            // Increment folio counter and log activity
            $this->increment_folio_counter($buildResult['folio']);
            log_activity('FE-SAT timbrado exitoso para factura #' . $invoice->id . ' por staff #' . $staffId);

            return [
                'success' => true,
                'message' => _l('fe_sat_timbrado_success'),
                'uuid' => $apiResponse['uuid'],
                'xml_path' => $paths['xml'],
                'pdf_path' => $paths['pdf'],
                'http_code' => $apiResponse['http_code'] ?? null,
                'endpoint' => $apiResponse['endpoint'] ?? null,
                'api_log' => $apiResponse['api_log'] ?? [],
            ];
        }

        // Handle error
        $this->db->where('id', $docId)->update($this->docsTable, [
            'status' => 'error',
            'message' => $apiResponse['message'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->record_log($docId, 'response', $apiResponse['endpoint'] ?? '', $apiResponse['http_code'] ?? null, $apiResponse['body_excerpt'] ?? $apiResponse['message']);

        return [
            'success' => false,
            'message' => $apiResponse['message'],
            'http_code' => $apiResponse['http_code'] ?? null,
            'endpoint' => $apiResponse['endpoint'] ?? null,
            'body_excerpt' => $apiResponse['body_excerpt'] ?? null,
            'api_log' => $apiResponse['api_log'] ?? [],
        ];
    }

    public function save_settings(array $payload, int $staffId): array
    {
        $fields = [
            'perfex_fesat_base_url' => trim((string) ($payload['base_url'] ?? '')),
            'perfex_fesat_series_default' => trim((string) ($payload['series_default'] ?? 'A')),
            'perfex_fesat_folio_next' => trim((string) ($payload['folio_next'] ?? '1')),
            'perfex_fesat_cfdi_use_default' => trim((string) ($payload['cfdi_use_default'] ?? 'G03')),
            'perfex_fesat_payment_method' => trim((string) ($payload['payment_method'] ?? 'PPD')),
            'perfex_fesat_payment_form' => trim((string) ($payload['payment_form'] ?? '99')),
            'perfex_fesat_currency' => trim((string) ($payload['currency'] ?? 'MXN')),
            'perfex_fesat_storage_driver' => trim((string) ($payload['storage_driver'] ?? 'local')),
            'perfex_fesat_sandbox_mode' => !empty($payload['sandbox_mode']) ? '1' : '0',
            'perfex_fesat_ssl_verify_disabled' => !empty($payload['ssl_verify_disabled']) ? '1' : '0',
            'perfex_fesat_company_regimen' => trim((string) ($payload['company_regimen'] ?? '601')),
        ];

        // Handle CSD file paths (only update if new files uploaded)
        if (!empty($payload['csd_cer_path'])) {
            $fields['perfex_fesat_csd_cer_path'] = trim((string) $payload['csd_cer_path']);
            $fields['perfex_fesat_csd_cer_filename'] = trim((string) ($payload['csd_cer_filename'] ?? ''));
            $fields['perfex_fesat_csd_cer_uploaded_at'] = trim((string) ($payload['csd_cer_uploaded_at'] ?? ''));
        }
        if (!empty($payload['csd_key_path'])) {
            $fields['perfex_fesat_csd_key_path'] = trim((string) $payload['csd_key_path']);
            $fields['perfex_fesat_csd_key_filename'] = trim((string) ($payload['csd_key_filename'] ?? ''));
            $fields['perfex_fesat_csd_key_uploaded_at'] = trim((string) ($payload['csd_key_uploaded_at'] ?? ''));
        }

        foreach ($fields as $option => $value) {
            update_option($option, $value);
        }

        $secretFields = [
            'perfex_fesat_username' => $payload['username'] ?? '',
            'perfex_fesat_password' => $payload['password'] ?? '',
            'perfex_fesat_api_key' => $payload['api_key'] ?? '',
            'perfex_fesat_csd_password' => $payload['csd_password'] ?? '',
        ];

        foreach ($secretFields as $option => $value) {
            if ($value === '') {
                continue;
            }
            // TEMPORARY: Store without encryption for testing
            // TODO: Re-enable encryption after testing
            update_option($option, trim((string) $value));
            // update_option($option, $this->encryption->encrypt(trim((string) $value)));
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
        $apiEncrypted = get_option('perfex_fesat_api_key');
        $csdPasswordEncrypted = get_option('perfex_fesat_csd_password');

        return [
            'base_url' => get_option('perfex_fesat_base_url'),
            'series_default' => get_option('perfex_fesat_series_default'),
            'folio_next' => get_option('perfex_fesat_folio_next'),
            'cfdi_use_default' => get_option('perfex_fesat_cfdi_use_default'),
            'payment_method' => get_option('perfex_fesat_payment_method'),
            'payment_form' => get_option('perfex_fesat_payment_form'),
            'currency' => get_option('perfex_fesat_currency'),
            'storage_driver' => get_option('perfex_fesat_storage_driver'),
            'sandbox_mode' => get_option('perfex_fesat_sandbox_mode') === '1',
            'ssl_verify_disabled' => get_option('perfex_fesat_ssl_verify_disabled') === '1',
            'company_regimen' => get_option('perfex_fesat_company_regimen'),
            'username' => $this->maybeDecryptOption('perfex_fesat_username'),
            'password' => '',
            'api_key' => '',
            'has_password' => !empty($passwordEncrypted),
            'has_api_key' => !empty($apiEncrypted),
            'csd_cer_path' => get_option('perfex_fesat_csd_cer_path'),
            'csd_cer_filename' => get_option('perfex_fesat_csd_cer_filename'),
            'csd_cer_uploaded_at' => get_option('perfex_fesat_csd_cer_uploaded_at'),
            'csd_key_path' => get_option('perfex_fesat_csd_key_path'),
            'csd_key_filename' => get_option('perfex_fesat_csd_key_filename'),
            'csd_key_uploaded_at' => get_option('perfex_fesat_csd_key_uploaded_at'),
            'csd_password' => '',
            'has_csd_password' => !empty($csdPasswordEncrypted),
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
            'time' => date('c'),
            'success' => $response['success'],
            'message' => $response['message'],
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

    /**
     * Cancel a stamped CFDI
     *
     * @param object $document The FE-SAT document to cancel
     * @param array $payload Form data with motivo and folioSustitucion
     * @param int $staffId Staff ID making the cancellation
     * @return array Result with success status and message
     */
    public function cancelar(object $document, array $payload, int $staffId): array
    {
        // Validate document
        if ($document->status !== 'success' || empty($document->digibox_uuid)) {
            return ['success' => false, 'message' => _l('fe_sat_cannot_cancel_invoice')];
        }

        // Validate motivo
        $motivo = trim((string) ($payload['motivo'] ?? ''));
        if (!in_array($motivo, ['01', '02', '03', '04'])) {
            return ['success' => false, 'message' => 'Motivo de cancelación inválido'];
        }

        // Validate folioSustitucion for motivo 01
        $folioSustitucion = trim((string) ($payload['folio_sustitucion'] ?? ''));
        if ($motivo === '01' && empty($folioSustitucion)) {
            return ['success' => false, 'message' => 'El folio de sustitución es requerido para el motivo 01'];
        }

        // Get company RFC
        $rfcEmisor = strtoupper(trim((string) get_option('company_vat')));
        if (empty($rfcEmisor)) {
            return ['success' => false, 'message' => _l('fe_sat_error_missing_company_rfc')];
        }

        // Get CSD certificate paths from settings
        $csdCerPath = get_option('perfex_fesat_csd_cer_path');
        $csdKeyPath = get_option('perfex_fesat_csd_key_path');
        $csdPassword = get_option('perfex_fesat_csd_password');

        // Convert files to base64 (clean - no newlines or whitespace)
        $csdCerBase64 = '';
        $csdKeyBase64 = '';

        if (!empty($csdCerPath) && is_file($csdCerPath)) {
            $fileContent = file_get_contents($csdCerPath);

            // Check if file is PEM format (starts with -----BEGIN)
            if (strpos($fileContent, '-----BEGIN') !== false) {
                // Remove PEM headers/footers and extract base64 content
                $fileContent = preg_replace('/-----BEGIN [^-]+-----/', '', $fileContent);
                $fileContent = preg_replace('/-----END [^-]+-----/', '', $fileContent);
                $fileContent = preg_replace('/\s+/', '', $fileContent); // Remove all whitespace
                $csdCerBase64 = $fileContent;
            } else {
                // File is binary DER format - encode to base64
                $csdCerBase64 = base64_encode($fileContent);
            }
        }

        if (!empty($csdKeyPath) && is_file($csdKeyPath)) {
            $fileContent = file_get_contents($csdKeyPath);

            // Check if file is PEM format (starts with -----BEGIN)
            if (strpos($fileContent, '-----BEGIN') !== false) {
                // Remove PEM headers/footers and extract base64 content
                $fileContent = preg_replace('/-----BEGIN [^-]+-----/', '', $fileContent);
                $fileContent = preg_replace('/-----END [^-]+-----/', '', $fileContent);
                $fileContent = preg_replace('/\s+/', '', $fileContent); // Remove all whitespace
                $csdKeyBase64 = $fileContent;
            } else {
                // File is binary DER format - encode to base64
                $csdKeyBase64 = base64_encode($fileContent);
            }
        }

        // Validate CSD files are configured (REQUIRED by DigiBox API documentation)
        if (empty($csdCerBase64) || empty($csdKeyBase64) || empty($csdPassword)) {
            return [
                'success' => false,
                'message' => 'CSD certificate files are required for cancellation. Please configure CSD files in module settings.',
            ];
        }

        // Validate base64 strings don't have invalid characters
        $valid = true;
        if (!preg_match('/^[A-Za-z0-9+\/]*={0,2}$/', $csdCerBase64)) {
            $valid = false;
        }
        if (!preg_match('/^[A-Za-z0-9+\/]*={0,2}$/', $csdKeyBase64)) {
            $valid = false;
        }

        if (!$valid) {
            return [
                'success' => false,
                'message' => 'CSD certificate files contain invalid characters. Please check the configuration.',
            ];
        }

        // Ensure proper base64 padding
        if (strlen($csdCerBase64) % 4 !== 0) {
            $csdCerBase64 = str_pad($csdCerBase64, strlen($csdCerBase64) + (4 - strlen($csdCerBase64) % 4), '=', STR_PAD_RIGHT);
        }
        if (strlen($csdKeyBase64) % 4 !== 0) {
            $csdKeyBase64 = str_pad($csdKeyBase64, strlen($csdKeyBase64) + (4 - strlen($csdKeyBase64) % 4), '=', STR_PAD_RIGHT);
        }

        // Initialize DigiBox client (if not already loaded)
        if (!isset($this->digibox_client)) {
            $this->load->library('perfex_fesat/Fe_sat_digibox_client', null, 'digibox_client');
        }

        // Call DigiBox CancelarCSDV2 API (as per documentation)
        $result = $this->digibox_client->cancelar(
            $document->digibox_uuid,
            $rfcEmisor,
            $motivo,
            $folioSustitucion,
            $csdCerBase64,
            $csdKeyBase64,
            $csdPassword
        );


        // Update document with cancellation status
        if ($result['success']) {
            $this->db->where('id', $document->id);
            $this->db->update(db_prefix() . 'fe_sat_docs', [
                'cancellation_status' => 'pending',
                'cancellation_date' => date('Y-m-d H:i:s'),
                'cancellation_motivo' => $motivo,
                'cancellation_folio_sustitucion' => $folioSustitucion,
                'cancellation_acuse' => $result['acuse_xml'] ?? null,
            ]);

            log_activity('FE-SAT CFDI cancelled for invoice #' . $document->invoice_id . ' by staff #' . $staffId);

            return [
                'success' => true,
                'message' => _l('fe_sat_cancel_success'),
                'acuse_xml' => $result['acuse_xml'] ?? null,
                'api_log' => $result['api_log'] ?? [],
                'http_code' => $result['http_code'] ?? null,
                'endpoint' => $result['endpoint'] ?? null,
            ];
        }

        // Log cancellation error
        log_activity('FE-SAT cancellation failed for invoice #' . $document->invoice_id . ': ' . $result['message']);

        return [
            'success' => false,
            'message' => _l('fe_sat_cancel_failed') . ': ' . $result['message'],
            'api_log' => $result['api_log'] ?? [],
            'http_code' => $result['http_code'] ?? null,
            'endpoint' => $result['endpoint'] ?? null,
            'body_excerpt' => $result['body_excerpt'] ?? null,
        ];
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
                'status' => 'success',
                'message' => $data['message'] ?? _l('fe_sat_timbrado_success'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } elseif (!empty($data['status'])) {
            $this->db->where('id', $document->id)->update($this->docsTable, [
                'status' => $data['status'],
                'message' => $data['message'] ?? '',
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
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->clientid,
            'status' => $meta['status'] ?? 'pending',
            'message' => '',
            'subtotal' => $totals['subtotal'],
            'iva' => $totals['iva'],
            'total' => $totals['total'],
            'amount_in_words' => $totals['amount_in_words'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
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
            'fe_id' => $documentId,
            'direction' => $direction,
            'endpoint' => $endpoint,
            'http_code' => $httpCode,
            'payload_excerpt' => mb_substr($payload, 0, 65535),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    protected function increment_folio_counter($folio): void
    {
        $current = (int) get_option('perfex_fesat_folio_next');
        $folio = (int) $folio;
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

        // TEMPORARY: Return plain value without decryption for testing
        // TODO: Re-enable decryption after testing
        return $value;
        // return $this->encryption->decrypt($value);
    }

    protected function printDebugSummaryTable(array $buildResult, array $apiResponse, $invoice): void
    {
        // Parse XML to extract issuer info
        $xml = $buildResult['xml'] ?? '';
        $doc = new DOMDocument();
        @$doc->loadXML($xml);
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('cfdi', 'http://www.sat.gob.mx/cfd/4');

        $emisorNode = $xpath->query('//cfdi:Emisor')->item(0);
        $receptorNode = $xpath->query('//cfdi:Receptor')->item(0);
        $comprobanteNode = $xpath->query('//cfdi:Comprobante')->item(0);

        $emisorRfc = $emisorNode ? $emisorNode->getAttribute('Rfc') : 'N/A';
        $emisorNombre = $emisorNode ? $emisorNode->getAttribute('Nombre') : 'N/A';
        $emisorRegimen = $emisorNode ? $emisorNode->getAttribute('RegimenFiscal') : 'N/A';
        $lugarExpedicion = $comprobanteNode ? $comprobanteNode->getAttribute('LugarExpedicion') : 'N/A';

        $receptorRfc = $receptorNode ? $receptorNode->getAttribute('Rfc') : 'N/A';
        $receptorNombre = $receptorNode ? $receptorNode->getAttribute('Nombre') : 'N/A';
        $receptorDomicilio = $receptorNode ? $receptorNode->getAttribute('DomicilioFiscalReceptor') : 'N/A';
        $receptorRegimen = $receptorNode ? $receptorNode->getAttribute('RegimenFiscalReceptor') : 'N/A';
        $receptorUsoCfdi = $receptorNode ? $receptorNode->getAttribute('UsoCFDI') : 'N/A';

        echo "<h2 style='color:#FF5722;border-bottom:3px solid #FF5722;padding-bottom:10px;margin-top:30px;'>📊 COMPREHENSIVE SUMMARY TABLE</h2>";
        echo "<style>
            .summary-table {width:100%;border-collapse:collapse;margin:20px 0;font-family:monospace;}
            .summary-table th {background:#2196F3;color:white;padding:12px;text-align:left;font-weight:bold;}
            .summary-table td {padding:10px;border:1px solid #ddd;}
            .summary-table tr:nth-child(even) {background:#f9f9f9;}
            .summary-table tr:hover {background:#e3f2fd;}
            .section-header {background:#4CAF50 !important;color:white !important;font-weight:bold;}
            .highlight {background:#FFF9C4 !important;font-weight:bold;}
        </style>";

        echo "<table class='summary-table'>";

        // API Endpoints Section
        echo "<tr class='section-header'><td colspan='2'>🌐 API ENDPOINTS & AUTHENTICATION</td></tr>";
        echo "<tr><td><strong>Authentication Endpoint</strong></td><td>" . htmlspecialchars(get_option('perfex_fesat_base_url')) . "</td></tr>";
        echo "<tr><td><strong>Timbrado Endpoint</strong></td><td>" . htmlspecialchars($apiResponse['endpoint'] ?? 'N/A') . "</td></tr>";
        echo "<tr><td><strong>HTTP Status Code</strong></td><td><strong>" . ($apiResponse['http_code'] ?? 'N/A') . "</strong></td></tr>";
        echo "<tr><td><strong>API Response</strong></td><td>" . ($apiResponse['success'] ? '<span class="success">✓ SUCCESS</span>' : '<span class="error">✗ FAILED</span>') . "</td></tr>";

        // Company (Emisor) Information
        echo "<tr class='section-header'><td colspan='2'>🏢 COMPANY INFORMATION (EMISOR)</td></tr>";
        echo "<tr class='highlight'><td><strong>Company RFC</strong></td><td><strong>" . htmlspecialchars($emisorRfc) . "</strong></td></tr>";
        echo "<tr class='highlight'><td><strong>Company Name</strong></td><td><strong>" . htmlspecialchars($emisorNombre) . "</strong></td></tr>";
        echo "<tr><td><strong>Regimen Fiscal</strong></td><td>" . htmlspecialchars($emisorRegimen) . "</td></tr>";
        echo "<tr><td><strong>Lugar de Expedición (ZIP)</strong></td><td>" . htmlspecialchars($lugarExpedicion) . "</td></tr>";

        // Customer (Receptor) Information
        echo "<tr class='section-header'><td colspan='2'>👤 CUSTOMER INFORMATION (RECEPTOR)</td></tr>";
        echo "<tr><td><strong>Customer RFC</strong></td><td>" . htmlspecialchars($receptorRfc) . "</td></tr>";
        echo "<tr><td><strong>Customer Name</strong></td><td>" . htmlspecialchars($receptorNombre) . "</td></tr>";
        echo "<tr><td><strong>Domicilio Fiscal (ZIP)</strong></td><td>" . htmlspecialchars($receptorDomicilio) . "</td></tr>";
        echo "<tr><td><strong>Regimen Fiscal Receptor</strong></td><td>" . htmlspecialchars($receptorRegimen) . "</td></tr>";
        echo "<tr><td><strong>Uso CFDI</strong></td><td>" . htmlspecialchars($receptorUsoCfdi) . "</td></tr>";

        // Invoice Details
        echo "<tr class='section-header'><td colspan='2'>📄 INVOICE DETAILS</td></tr>";
        echo "<tr><td><strong>Invoice ID</strong></td><td>" . $invoice->id . "</td></tr>";
        echo "<tr><td><strong>Invoice Number</strong></td><td>" . htmlspecialchars($invoice->number) . "</td></tr>";
        echo "<tr><td><strong>Serie</strong></td><td>" . htmlspecialchars($buildResult['serie'] ?? 'N/A') . "</td></tr>";
        echo "<tr><td><strong>Folio</strong></td><td>" . htmlspecialchars($buildResult['folio'] ?? 'N/A') . "</td></tr>";
        echo "<tr><td><strong>Currency</strong></td><td>" . htmlspecialchars($buildResult['currency'] ?? 'N/A') . "</td></tr>";
        echo "<tr><td><strong>Payment Method</strong></td><td>" . htmlspecialchars($buildResult['payment_method'] ?? 'N/A') . "</td></tr>";
        echo "<tr><td><strong>Payment Form</strong></td><td>" . htmlspecialchars($buildResult['payment_form'] ?? 'N/A') . "</td></tr>";

        // Financial Details
        echo "<tr class='section-header'><td colspan='2'>💰 FINANCIAL DETAILS</td></tr>";
        echo "<tr><td><strong>Subtotal</strong></td><td>$" . number_format($buildResult['totals']['subtotal'] ?? 0, 2) . "</td></tr>";
        echo "<tr><td><strong>IVA (16%)</strong></td><td>$" . number_format($buildResult['totals']['iva'] ?? 0, 2) . "</td></tr>";
        echo "<tr><td><strong>Total</strong></td><td><strong>$" . number_format($buildResult['totals']['total'] ?? 0, 2) . "</strong></td></tr>";

        // XML Preview
        echo "<tr class='section-header'><td colspan='2'>📝 XML SENT TO DIGIBOX</td></tr>";
        echo "<tr><td colspan='2'><pre style='max-height:300px;overflow:auto;background:#f5f5f5;padding:10px;'>" . htmlspecialchars(substr($xml, 0, 2000)) . "\n\n... (showing first 2000 characters)</pre></td></tr>";

        // Error Information (if failed)
        if (!$apiResponse['success']) {
            echo "<tr class='section-header'><td colspan='2'>❌ ERROR INFORMATION</td></tr>";
            echo "<tr><td><strong>Error Message</strong></td><td><span style='color:red;font-weight:bold;'>" . htmlspecialchars($apiResponse['message'] ?? 'N/A') . "</span></td></tr>";
            if (!empty($apiResponse['body_excerpt'])) {
                echo "<tr><td><strong>Full Error Response</strong></td><td><pre style='max-height:200px;overflow:auto;background:#ffebee;padding:10px;'>" . htmlspecialchars($apiResponse['body_excerpt']) . "</pre></td></tr>";
            }
        }

        echo "</table>";

        // API Call Log Section
        if (!empty($apiResponse['api_log'])) {
            echo "<tr class='section-header'><td colspan='2'>🔄 ALL API CALLS TO DIGIBOX</td></tr>";
            foreach ($apiResponse['api_log'] as $index => $call) {
                $callNum = $index + 1;
                echo "<tr><td colspan='2' style='background:#E3F2FD;padding:15px;'>";
                echo "<h4 style='margin:0 0 10px 0;color:#1976D2;'>API Call #{$callNum}: " . htmlspecialchars($call['type']) . "</h4>";
                echo "<table style='width:100%;font-size:13px;' class='summary-table'>";
                echo "<tr><td style='width:150px;'><strong>Timestamp</strong></td><td>" . htmlspecialchars($call['timestamp']) . "</td></tr>";
                echo "<tr><td><strong>Method</strong></td><td>" . htmlspecialchars($call['method']) . "</td></tr>";
                echo "<tr><td><strong>URL</strong></td><td>" . htmlspecialchars($call['url']) . "</td></tr>";
                echo "<tr><td><strong>Headers</strong></td><td><pre style='margin:0;background:#fff;padding:5px;'>" . htmlspecialchars(is_array($call['headers']) ? implode("\n", $call['headers']) : $call['headers']) . "</pre></td></tr>";
                echo "<tr><td><strong>Request Body</strong></td><td><pre style='margin:0;background:#fff;padding:5px;max-height:200px;overflow:auto;'>" . htmlspecialchars($call['request_body']) . "</pre></td></tr>";
                echo "<tr><td><strong>Response Code</strong></td><td><strong style='color:" . ($call['response_code'] >= 200 && $call['response_code'] < 300 ? 'green' : 'red') . ";'>" . $call['response_code'] . "</strong></td></tr>";
                echo "<tr><td><strong>Response Body</strong></td><td><pre style='margin:0;background:#fff;padding:5px;max-height:300px;overflow:auto;'>" . htmlspecialchars($call['response_body']) . "</pre></td></tr>";
                if ($call['error']) {
                    echo "<tr><td><strong>Error</strong></td><td><span style='color:red;'>" . htmlspecialchars($call['error']) . "</span></td></tr>";
                }
                echo "</table>";
                echo "</td></tr>";
            }
        }

        echo "</table>";

        echo "<div style='background:#FFF9C4;border-left:4px solid #FFC107;padding:15px;margin:20px 0;'>";
        echo "<h3 style='margin-top:0;color:#F57C00;'>💡 IMPORTANT: Share This Information with Client</h3>";
        echo "<p>Please verify the following with your client:</p>";
        echo "<ul>";
        echo "<li><strong>Company RFC (" . htmlspecialchars($emisorRfc) . ")</strong> - Is this correct?</li>";
        echo "<li><strong>Company Name (" . htmlspecialchars($emisorNombre) . ")</strong> - Does this EXACTLY match the SAT registered name for this RFC?</li>";
        echo "<li><strong>CSD Certificate</strong> - Has the CSD been uploaded to DigiBox portal for RFC <strong>" . htmlspecialchars($emisorRfc) . "</strong>?</li>";
        echo "<li><strong>Postal Code (" . htmlspecialchars($lugarExpedicion) . ")</strong> - Is this the correct company postal code?</li>";
        echo "</ul>";
        echo "</div>";
    }
}
