<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Importar clientes (Ramos) desde XLS/XLSX con encabezados.
 *
 * - Deduplica por CUSTOMER_EMAIL (archivo + base de datos tblcontacts.email)
 * - Inserta en tblclients
 * - Crea contacto primario con login en tblcontacts (password hasheada via Clients_model::add_contact)
 */
class Importar_clientes_ramos extends AdminController
{
    private string $tmpDir;

    public function __construct()
    {
        parent::__construct();

        if (!has_permission('customers', '', 'view')) {
            access_denied('customers');
        }

        $this->tmpDir = FCPATH . 'uploads/importar_clientes_ramos/';
        if (!is_dir($this->tmpDir)) {
            @mkdir($this->tmpDir, 0755, true);
        }
    }

    public function index()
    {
        $data = [];
        $data['title'] = 'Importar Clientes (Ramos XLSX)';
        $data['step']  = $this->input->get('step') ?: 'upload';

        $sess = $this->session->userdata('importar_clientes_ramos');
        if (is_array($sess)) {
            foreach (['file_path','file_ext','headers','preview_rows','errors','last_result'] as $k) {
                if (array_key_exists($k, $sess)) {
                    $data[$k] = $sess[$k];
                }
            }
        }

        $this->load->view('importar_clientes/importer_ramos', $data);
    }

    public function upload()
    {
        if (!has_permission('customers', '', 'create')) {
            access_denied('customers');
        }

        if (!isset($_FILES['file']) || empty($_FILES['file']['name'])) {
            set_alert('danger', 'Selecciona un archivo.');
            redirect(admin_url('importar_clientes/importar_clientes_ramos'));
        }

        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv','xls','xlsx'], true)) {
            set_alert('danger', 'Solo CSV, XLS o XLSX.');
            redirect(admin_url('importar_clientes/importar_clientes_ramos'));
        }

        $token = bin2hex(random_bytes(16));
        $dest  = $this->tmpDir . 'import_' . $token . '.' . $ext;

        if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
            set_alert('danger', 'No se pudo guardar el archivo.');
            redirect(admin_url('importar_clientes/importar_clientes_ramos'));
        }

        // For preview we only need headers + a small sample; the XLSX can contain many formatted empty rows.
        $parsed = $this->parseFileWithHeaders($dest, $ext, 25);
        if ($parsed === null || empty($parsed['headers'])) {
            @unlink($dest);
            set_alert('danger', 'No se pudo leer el archivo.');
            redirect(admin_url('importar_clientes/importar_clientes_ramos'));
        }

        $errors = $this->validateParsed($parsed);

        $this->session->set_userdata('importar_clientes_ramos', [
            'file_path'    => $dest,
            'file_ext'     => $ext,
            'headers'      => $parsed['headers'],
            'preview_rows' => array_slice($parsed['data_rows'], 0, 15),
            'errors'       => $errors,
            'last_result'  => null,
        ]);

        redirect(admin_url('importar_clientes/importar_clientes_ramos?step=preview'));
    }

    public function confirm_import()
    {
        if (!has_permission('customers', '', 'create')) {
            access_denied('customers');
        }

        $sess = $this->session->userdata('importar_clientes_ramos');
        if (!is_array($sess) || empty($sess['file_path']) || !is_file($sess['file_path'])) {
            set_alert('danger', 'Sesión inválida. Vuelve a subir el archivo.');
            redirect(admin_url('importar_clientes/importar_clientes_ramos'));
        }

        $parsed = $this->parseFileWithHeaders($sess['file_path'], $sess['file_ext'] ?? 'xlsx', null);
        if ($parsed === null) {
            set_alert('danger', 'No se pudo leer el archivo.');
            redirect(admin_url('importar_clientes/importar_clientes_ramos?step=preview'));
        }

        $errors = $this->validateParsed($parsed);
        if (!empty($errors)) {
            $sess['errors'] = $errors;
            $sess['headers'] = $parsed['headers'];
            $sess['preview_rows'] = array_slice($parsed['data_rows'], 0, 15);
            $this->session->set_userdata('importar_clientes_ramos', $sess);
            set_alert('danger', 'La importación está bloqueada: corrige los errores.');
            redirect(admin_url('importar_clientes/importar_clientes_ramos?step=preview'));
        }

        $this->load->model('clients_model');

        $importedClients = 0;
        $createdContacts = 0;
        $skippedEmailDupFile = 0;
        $skippedEmailDupDb = 0;
        $skippedEmpty = 0;
        $rowErrors = [];

        $headerIndex = $this->buildHeaderIndex($parsed['headers']);
        $seenEmails = [];

        $this->db->trans_start();

        $excelRowBase = 2;
        foreach ($parsed['data_rows'] as $i => $row) {
            $sheetRow = $excelRowBase + $i;
            if ($this->isRowEmpty($row)) {
                $skippedEmpty++;
                continue;
            }

            $email = $this->getCell($row, $headerIndex, 'CUSTOMER_EMAIL');
            $email = trim((string) $email);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $rowErrors[] = "Fila {$sheetRow}: CUSTOMER_EMAIL inválido o vacío.";
                continue;
            }

            $emailKey = strtolower($email);
            if (isset($seenEmails[$emailKey])) {
                $skippedEmailDupFile++;
                continue;
            }
            $seenEmails[$emailKey] = $sheetRow;

            // Skip duplicates by email against DB (tblcontacts.email)
            // Use LOWER() to avoid collation/case edge cases.
            $existingContact = $this->db
                ->query(
                    'SELECT id FROM `' . db_prefix() . 'contacts` WHERE LOWER(email)=LOWER(?) LIMIT 1',
                    [$email]
                )
                ->row_array();
            if (!empty($existingContact)) {
                $skippedEmailDupDb++;
                if (count($rowErrors) < 5) {
                    $rowErrors[] = "Fila {$sheetRow}: email ya existe según sistema ({$email}), contact_id=" . ($existingContact['id'] ?? '?');
                }
                continue;
            }

            $company = trim((string) $this->getCell($row, $headerIndex, 'company'));
            if ($company === '') {
                $rowErrors[] = "Fila {$sheetRow}: company vacío.";
                continue;
            }

            $clientPayload = $this->buildClientPayload($row, $headerIndex);
            $this->db->insert(db_prefix() . 'clients', $clientPayload);
            $clientId = (int) $this->db->insert_id();
            if ($clientId <= 0) {
                $dbErr = $this->db->error();
                $msg = is_array($dbErr) && !empty($dbErr['message']) ? $dbErr['message'] : 'unknown db error';
                $rowErrors[] = "Fila {$sheetRow}: no se pudo insertar cliente. DB: {$msg}";
                continue;
            }
            $importedClients++;

            $contactName = trim((string) $this->getCell($row, $headerIndex, 'CUSTOMER_CONTACTO'));
            if ($contactName === '') {
                $contactName = 'Contacto';
            }

            $username = trim((string) $this->getCell($row, $headerIndex, 'CUSTOMER_USER'));
            $password = (string) $this->getCell($row, $headerIndex, 'Customer_password');
            $password = trim($password);
            if ($password === '') {
                $password = 'Ramos123';
            }

            // Create primary contact login. Clients_model will hash the password.
            $contactData = [
                'firstname' => $contactName,
                'lastname'  => '',
                'email'     => $email,
                'phonenumber' => (string) ($clientPayload['phonenumber'] ?? ''),
                'password'  => $password,
                'is_primary' => 1,
                // Do not send welcome email during bulk import
                'donotsendwelcomeemail' => 1,
            ];

            $contactId = (int) $this->clients_model->add_contact($contactData, $clientId, true);
            if ($contactId <= 0) {
                $rowErrors[] = "Fila {$sheetRow}: cliente insertado (ID {$clientId}) pero no se pudo crear contacto/login.";
                continue;
            }
            $createdContacts++;

            // Store username in contacts.title if provided (no dedicated username field in tblcontacts)
            if ($username !== '') {
                $this->db->where('id', $contactId);
                $this->db->update(db_prefix() . 'contacts', ['title' => $username]);
            }
        }

        $this->db->trans_complete();

        @unlink($sess['file_path']);

        $sess['last_result'] = [
            'imported_clients'       => $importedClients,
            'created_contacts'       => $createdContacts,
            'skipped_email_dup_file' => $skippedEmailDupFile,
            'skipped_email_dup_db'   => $skippedEmailDupDb,
            'skipped_empty'          => $skippedEmpty,
            'row_errors'             => array_slice($rowErrors, 0, 200),
        ];
        $sess['errors'] = [];
        unset($sess['file_path'], $sess['file_ext']);
        $this->session->set_userdata('importar_clientes_ramos', $sess);

        if ($this->db->trans_status() === false) {
            set_alert('danger', 'Error en base de datos. No se completó.');
            redirect(admin_url('importar_clientes/importar_clientes_ramos'));
        }

        set_alert('success', 'Importación completada.');
        redirect(admin_url('importar_clientes/importar_clientes_ramos?step=results'));
    }

    public function cancel()
    {
        $sess = $this->session->userdata('importar_clientes_ramos');
        if (is_array($sess) && !empty($sess['file_path']) && is_file($sess['file_path'])) {
            @unlink($sess['file_path']);
        }
        $this->session->unset_userdata('importar_clientes_ramos');
        redirect(admin_url('importar_clientes/importar_clientes_ramos'));
    }

    // ---------------------------------------------------------------------
    // Parsing helpers (PHPExcel, same as products importer)
    // ---------------------------------------------------------------------

    /**
     * @return array{headers: string[], data_rows: array<int,array<int,string>>}|null
     */
    private function parseFileWithHeaders(string $path, string $ext, ?int $maxDataRows): ?array
    {
        $ext = strtolower($ext);
        if ($ext === 'csv') {
            return $this->parseCsvWithHeaders($path, $maxDataRows);
        }
        return $this->parseExcelWithHeaders($path, $maxDataRows);
    }

    /**
     * @return array{headers: string[], data_rows: array<int,array<int,string>>}|null
     */
    private function parseCsvWithHeaders(string $path, ?int $maxDataRows): ?array
    {
        $fh = fopen($path, 'r');
        if ($fh === false) {
            return null;
        }
        $headerLine = fgetcsv($fh, 0, ',');
        if ($headerLine === false) {
            fclose($fh);
            return null;
        }
        $headers = array_map(static fn($h) => trim((string) $h), $headerLine);
        $dataRows = [];
        while (($row = fgetcsv($fh, 0, ',')) !== false) {
            if ($this->isRowEmpty($row)) {
                continue;
            }
            $dataRows[] = array_map(static fn($v) => trim((string) $v), $row);
            if ($maxDataRows !== null && count($dataRows) >= $maxDataRows) {
                break;
            }
        }
        fclose($fh);
        return ['headers' => $headers, 'data_rows' => $dataRows];
    }

    /**
     * @return array{headers: string[], data_rows: array<int,array<int,string>>}|null
     */
    private function parseExcelWithHeaders(string $path, ?int $maxDataRows): ?array
    {
        try {
            require_once module_dir_path('warehouse', 'third_party/excel/PHPExcel/IOFactory.php');
            $excel = PHPExcel_IOFactory::load($path);
        } catch (Throwable $e) {
            return null;
        }

        $sheet = $excel->getSheet(0);
        $highestRow = (int) $sheet->getHighestDataRow();
        if ($highestRow < 1) {
            return null;
        }

        $highestColLetter = $sheet->getHighestDataColumn(1);
        try {
            $colCount = PHPExcel_Cell::columnIndexFromString($highestColLetter);
        } catch (Throwable $e) {
            return null;
        }

        $headers = [];
        for ($c = 0; $c < $colCount; $c++) {
            $headers[] = trim((string) $sheet->getCellByColumnAndRow($c, 1)->getValue());
        }

        $rows = [];
        $emptyStreak = 0;
        $foundData = false;
        for ($r = 2; $r <= $highestRow; $r++) {
            $row = [];
            for ($c = 0; $c < $colCount; $c++) {
                $cell = $sheet->getCellByColumnAndRow($c, $r);
                $v = $cell ? $cell->getValue() : '';
                if (is_object($v) && method_exists($v, 'getPlainText')) {
                    $v = $v->getPlainText();
                }
                $row[] = trim((string) $v);
            }
            if ($this->isRowEmpty($row)) {
                if ($foundData) {
                    $emptyStreak++;
                }
            } else {
                $foundData = true;
                $emptyStreak = 0;
                $rows[] = $row;
            }

            // Many Excel exports have a huge formatted empty tail. Once we saw real data,
            // break after a large empty streak.
            if ($foundData && $emptyStreak >= 300) {
                break;
            }

            if ($maxDataRows !== null && count($rows) >= $maxDataRows) {
                break;
            }
        }

        return ['headers' => $headers, 'data_rows' => $rows];
    }

    private function buildHeaderIndex(array $headers): array
    {
        $idx = [];
        foreach ($headers as $i => $h) {
            $k = trim((string) $h);
            if ($k !== '') {
                $idx[$k] = (int) $i;
            }
        }
        return $idx;
    }

    private function getCell(array $row, array $headerIndex, string $header)
    {
        $i = $headerIndex[$header] ?? null;
        if ($i === null) {
            return '';
        }
        return $row[$i] ?? '';
    }

    private function validateParsed(array $parsed): array
    {
        $errors = [];
        $headers = $parsed['headers'] ?? [];
        $headerIndex = $this->buildHeaderIndex($headers);

        foreach (['company','vat','active','CUSTOMER_EMAIL','Customer_password','CUSTOMER_USER','CUSTOMER_CONTACTO'] as $h) {
            if (!isset($headerIndex[$h])) {
                $errors[] = "Falta columna requerida: {$h}";
            }
        }

        return $errors;
    }

    private function isRowEmpty(array $row): bool
    {
        foreach ($row as $v) {
            if (trim((string) $v) !== '') {
                return false;
            }
        }
        return true;
    }

    private function buildClientPayload(array $row, array $headerIndex): array
    {
        $now = date('Y-m-d H:i:s');

        $country = (int) $this->getCell($row, $headerIndex, 'country');
        $billingCountry = (int) $this->getCell($row, $headerIndex, 'billing_country');

        return [
            'company' => (string) $this->getCell($row, $headerIndex, 'company'),
            'vat' => (string) $this->getCell($row, $headerIndex, 'vat'),
            'phonenumber' => (string) $this->getCell($row, $headerIndex, 'phonenumber'),
            'country' => $country > 0 ? $country : 0,
            'plist' => (string) $this->getCell($row, $headerIndex, 'plist') !== '' ? (int) $this->getCell($row, $headerIndex, 'plist') : null,
            'city' => (string) $this->getCell($row, $headerIndex, 'city'),
            'zip' => (string) $this->getCell($row, $headerIndex, 'zip'),
            'state' => (string) $this->getCell($row, $headerIndex, 'state'),
            'address' => (string) $this->getCell($row, $headerIndex, 'address'),
            'datecreated' => $now,
            'active' => ((string) $this->getCell($row, $headerIndex, 'active')) === '0' ? 0 : 1,

            // Billing mapping (tblclients uses billing_street)
            'billing_street' => (string) $this->getCell($row, $headerIndex, 'billing_address'),
            'billing_city' => (string) $this->getCell($row, $headerIndex, 'billing_city'),
            'billing_state' => (string) $this->getCell($row, $headerIndex, 'billing_state'),
            'billing_zip' => (string) $this->getCell($row, $headerIndex, 'billing_zip'),
            'billing_country' => $billingCountry > 0 ? $billingCountry : 0,
        ];
    }
}

