<?php

defined('BASEPATH') or exit('No direct script access allowed');


class Ramos_client extends ClientsController
{
    public function __construct()
    {
        parent::__construct();

        if (!is_client_logged_in()) {
            redirect(site_url());
        }

        $this->load->model('ramos/orders_model', 'orders_model');
        $this->load->library('ramos/ramos_orders_importer', null, 'orders_importer');
        $this->load->helper(['form']);
    }

    /**
     * Redirect to the main Perfex client portal home page.
     *
     * The legacy Ramos order form (ramos_orders schema) is retired.
     * The full-featured split-screen order interface (last order + new order,
     * pricing markup, Maduración) is on the standard clients home page.
     *
     * @return void
     */
    public function orders(): void
    {
        redirect(site_url('clients'));
    }

    /**
     * Store manual order submission.
     *
     * @return void
     */
    public function store_order(): void
    {
        $contactId = get_client_user_id();
        $clientId  = $this->resolveClientIdFromContact($contactId);

        $customerName    = trim((string) $this->input->post('customer_name'));
        $deliveryAddress = trim((string) $this->input->post('delivery_address'));
        $priority        = $this->input->post('priority') ?: RAMOS_PRIORITY_NORMAL;
        $deliveryDate    = $this->input->post('delivery_date');
        $deliveryTime    = $this->input->post('delivery_time');
        $notes           = trim((string) $this->input->post('notes'));

        if ($customerName === '' || $deliveryAddress === '') {
            set_alert('warning', _l('ramos_client_orders_validation_required'));
            redirect(site_url('clients/ramos_client/orders'));
        }

        $deliveryDateTime = $this->mergeDateAndTime($deliveryDate, $deliveryTime);
        if ($deliveryDate && $deliveryDateTime === null) {
            set_alert('warning', _l('ramos_client_orders_validation_delivery_time'));
            redirect(site_url('clients/ramos_client/orders'));
        }

        $payload = [
            'customer_name'     => $customerName,
            'delivery_address'  => $deliveryAddress,
            'priority'          => $priority,
            'status'            => RAMOS_ORDER_STATUS_NEW,
            'notes'             => $notes !== '' ? $notes : null,
            'delivery_datetime' => $deliveryDateTime,
            'customer_reference'=> $contactId,
        ];

        if ($clientId) {
            $payload['client_id'] = $clientId;
        }

        try {
            $this->orders_model->create($payload);
            set_alert('success', _l('ramos_client_orders_created'));
        } catch (Throwable $exception) {
            log_message('error', 'Ramos portal order create failed: ' . $exception->getMessage());
            set_alert('danger', _l('ramos_client_orders_create_failed'));
        }

        redirect(site_url('clients/ramos_client/orders'));
    }

    /**
     * Import orders from uploaded file.
     *
     * @return void
     */
    public function upload_orders(): void
    {
        $contactId = get_client_user_id();
        $clientId  = $this->resolveClientIdFromContact($contactId);

        if (empty($_FILES['orders_file']['name'])) {
            set_alert('warning', _l('ramos_client_orders_upload_no_file'));
            redirect(site_url('clients/ramos_client/orders'));
        }

        $file      = $_FILES['orders_file'];
        $extension = strtolower((string) pathinfo($file['name'], PATHINFO_EXTENSION));
        $tempFile  = get_temp_dir() . 'ramos_client_orders_' . app_generate_hash() . '.' . $extension;

        if (!@move_uploaded_file($file['tmp_name'], $tempFile)) {
            set_alert('danger', _l('ramos_client_orders_upload_move_failed'));
            redirect(site_url('clients/ramos_client/orders'));
        }

        try {
            $rows = $this->orders_importer->parseFile($tempFile, $extension);
        } catch (Exception $exception) {
            @unlink($tempFile);
            set_alert('danger', $exception->getMessage());
            redirect(site_url('clients/ramos_client/orders'));
        }

        @unlink($tempFile);

        if (empty($rows)) {
            set_alert('warning', _l('ramos_client_orders_upload_no_rows'));
            redirect(site_url('clients/ramos_client/orders'));
        }

        $imported = 0;
        $errors   = [];
        $batchId  = app_generate_hash();

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2;
            $transformed = $this->orders_importer->transformRow($row);

            if (!$transformed['valid']) {
                $errors[] = _l('ramos_orders_import_row_error', $lineNumber) . ' - ' . $transformed['error'];
                continue;
            }

            $payload = $transformed['data'];
            $payload['status']             = RAMOS_ORDER_STATUS_NEW;
            $payload['import_batch']       = $batchId;
            $payload['customer_reference'] = $contactId;
            if ($clientId) {
                $payload['client_id'] = $clientId;
            }

            try {
                $this->orders_model->create($payload);
                $imported++;
            } catch (Throwable $exception) {
                $errors[] = _l('ramos_orders_import_row_error', $lineNumber) . ' - ' . $exception->getMessage();
            }
        }

        if ($imported > 0) {
            set_alert('success', _l('ramos_client_orders_upload_success', $imported));
        }

        if (!empty($errors)) {
            set_alert('warning', implode('<br>', $errors));
        }

        if ($imported === 0 && empty($errors)) {
            set_alert('warning', _l('ramos_orders_import_nothing_imported'));
        }

        redirect(site_url('clients/ramos_client/orders'));
    }

    protected function mergeDateAndTime(?string $date, ?string $time): ?string
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

    protected function resolveDefaultCustomerName($contact, $company): string
    {
        if ($contact) {
            $fullName = trim($contact->firstname . ' ' . $contact->lastname);
            if ($fullName !== '') {
                return $fullName;
            }
        }

        if ($company && !empty($company->company)) {
            return $company->company;
        }

        return '';
    }

    protected function resolveDefaultAddress($company): string
    {
        if (!$company) {
            return '';
        }

        $parts = array_filter([
            $company->billing_street ?? '',
            trim(($company->billing_city ?? '') . ' ' . ($company->billing_state ?? '')),
            trim(($company->billing_zip ?? '') . ' ' . ($company->billing_country ? get_country($company->billing_country)->short_name : '')),
        ]);

        return trim(implode(', ', array_filter($parts)));
    }

    protected function resolveClientIdFromContact($contactId): ?int
    {
        if (!$contactId) {
            return null;
        }

        $row = $this->db
            ->select('userid')
            ->from(db_prefix() . 'contacts')
            ->where('id', $contactId)
            ->get()
            ->row();

        if ($row && !empty($row->userid)) {
            return (int) $row->userid;
        }

        return null;
    }

    protected function getContact($contactId)
    {
        if (!$contactId) {
            return null;
        }

        return $this->db
            ->select('id, firstname, lastname, email, phonenumber, userid')
            ->from(db_prefix() . 'contacts')
            ->where('id', $contactId)
            ->get()
            ->row();
    }

    protected function getCompany(?int $clientId)
    {
        if (!$clientId) {
            return null;
        }

        return $this->db
            ->where('userid', $clientId)
            ->get(db_prefix() . 'clients')
            ->row();
    }
}
