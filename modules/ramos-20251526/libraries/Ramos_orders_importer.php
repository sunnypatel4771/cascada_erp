<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Ramos_orders_importer
{
    /**
     * @var CI_Controller
     */
    protected $ci;

    /**
     * @var Orders_model
     */
    protected $ordersModel;

    public function __construct()
    {
        $this->ci = &get_instance();

        if (!isset($this->ci->orders_model)) {
            $this->ci->load->model('ramos/orders_model');
        }

        $this->ordersModel = $this->ci->orders_model;
    }

    /**
     * Parse uploaded file into array rows.
     *
     * @param  string $path
     * @param  string $extension
     * @return array
     *
     * @throws Exception
     */
    public function parseFile(string $path, string $extension): array
    {
        $extension = strtolower($extension);

        if ($extension === 'csv') {
            return $this->parseCsv($path);
        }

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            return $this->parseSpreadsheet($path);
        }

        throw new Exception(_l('ramos_orders_import_invalid_type'));
    }

    /**
     * Validate and normalise a single row.
     *
     * @param  array $row
     * @return array
     */
    public function transformRow(array $row): array
    {
        $customerName = trim((string) ($row['customer_name'] ?? ''));
        $address      = trim((string) ($row['delivery_address'] ?? $row['address'] ?? ''));

        if ($customerName === '' || $address === '') {
            return [
                'valid' => false,
                'error' => _l('ramos_orders_import_missing_required'),
            ];
        }

        $orderNumber = trim((string) ($row['order_number'] ?? ''));
        $notes       = trim((string) ($row['notes'] ?? ''));

        $priority = $row['priority'] ?? RAMOS_PRIORITY_NORMAL;
        $status   = $row['status'] ?? RAMOS_ORDER_STATUS_NEW;

        $deliveryDatetime = $row['delivery_datetime'] ?? '';

        if ($deliveryDatetime === '' && (!empty($row['delivery_date']) || !empty($row['delivery_time']))) {
            $deliveryDatetime = trim((string) ($row['delivery_date'] ?? '')) . ' ' . trim((string) ($row['delivery_time'] ?? '00:00'));
        }

        $deliveryDatetime = trim((string) $deliveryDatetime);
        $sqlDatetime      = null;

        if ($deliveryDatetime !== '') {
            $timestamp = strtotime($deliveryDatetime);
            if ($timestamp === false) {
                return [
                    'valid' => false,
                    'error' => _l('ramos_orders_import_invalid_date'),
                ];
            }
            $sqlDatetime = date('Y-m-d H:i:s', $timestamp);
        }

        $data = [
            'customer_name'     => $customerName,
            'delivery_address'  => $address,
            'priority'          => $this->ordersModel->sanitizePriority($priority),
            'status'            => $this->ordersModel->sanitizeStatus($status),
            'notes'             => $notes !== '' ? $notes : null,
            'delivery_datetime' => $sqlDatetime,
        ];

        if ($orderNumber !== '') {
            $data['order_number'] = $orderNumber;
        }

        if (!empty($row['client_id'])) {
            $data['client_id'] = (int) $row['client_id'];
        }

        if (!empty($row['customer_reference'])) {
            $data['customer_reference'] = (int) $row['customer_reference'];
        }

        return [
            'valid' => true,
            'data'  => $data,
        ];
    }

    /**
     * Parse CSV file.
     *
     * @param  string $path
     * @return array
     *
     * @throws Exception
     */
    protected function parseCsv(string $path): array
    {
        if (!is_readable($path)) {
            throw new Exception(_l('ramos_orders_import_unreadable'));
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new Exception(_l('ramos_orders_import_unreadable'));
        }

        $rows    = [];
        $headers = [];
        $rowIdx  = 0;

        while (($data = fgetcsv($handle, 0, ',')) !== false) {
            $data = array_map('trim', $data);

            if ($rowIdx === 0) {
                if (isset($data[0])) {
                    $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', $data[0]);
                }
                $headers = array_map(fn($value) => strtolower(str_replace(' ', '_', $value)), $data);
                $rowIdx++;
                continue;
            }

            if ($this->rowIsEmpty($data)) {
                $rowIdx++;
                continue;
            }

            $rows[] = $this->combineRow($headers, $data);
            $rowIdx++;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Parse Excel file.
     *
     * @param  string $path
     * @return array
     *
     * @throws Exception
     */
    protected function parseSpreadsheet(string $path): array
    {
        $autoloaders = [
            APPPATH . 'vendor/autoload.php',
            FCPATH . 'vendor/autoload.php',
        ];

        if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            foreach ($autoloaders as $autoload) {
                if (file_exists($autoload)) {
                    require_once $autoload;
                    break;
                }
            }
        }

        if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            throw new Exception(_l('ramos_orders_import_excel_library_missing'));
        }

        $rows    = [];
        $headers = [];

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $worksheet   = $spreadsheet->getActiveSheet();
        $rowIterator = $worksheet->getRowIterator();

        $rowIndex = 0;

        foreach ($rowIterator as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $dataRow = [];
            foreach ($cellIterator as $cell) {
                $dataRow[] = trim((string) $cell->getValue());
            }

            if ($rowIndex === 0) {
                $headers = array_map(fn($value) => strtolower(str_replace(' ', '_', $value)), $dataRow);
                $rowIndex++;
                continue;
            }

            if ($this->rowIsEmpty($dataRow)) {
                $rowIndex++;
                continue;
            }

            $rows[] = $this->combineRow($headers, $dataRow);
            $rowIndex++;
        }

        return $rows;
    }

    /**
     * Combine header row with values.
     *
     * @param  array $headers
     * @param  array $row
     * @return array
     */
    protected function combineRow(array $headers, array $row): array
    {
        $assoc = [];
        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }
            $assoc[$header] = $row[$index] ?? null;
        }

        return $assoc;
    }

    /**
     * Skip blank rows.
     *
     * @param  array $row
     * @return bool
     */
    protected function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
