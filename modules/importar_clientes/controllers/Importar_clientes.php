<?php defined('BASEPATH') or exit('No direct script access allowed');

class Importar_clientes extends AdminController
{
    private string $tmpDir;

    public function __construct()
    {
        parent::__construct();

        if (!has_permission('customers', '', 'view')) {
            access_denied('customers');
        }

        $this->tmpDir = FCPATH . 'uploads/importar_clientes/';
        if (!is_dir($this->tmpDir)) {
            @mkdir($this->tmpDir, 0755, true);
        }
    }

    public function index()
    {
        $data = [];
        $data['title'] = 'Importar Clientes';
        $data['step']  = $this->input->get('step') ?: 'upload';

        $data['tblclients_columns']  = $this->get_tblclients_columns();
        $data['tblclients_required'] = $this->get_tblclients_required_columns($data['tblclients_columns']);
        $data['custom_fields']       = $this->get_customer_custom_fields();

        // defaults
        $data['mode'] = 'insert';
        $data['upsert_key'] = 'vat';
        $data['block_duplicates'] = '1';
        $data['extra_required'] = [
            'company'     => '1',
            'email'       => '0',
            'phonenumber' => '0',
            'country'     => '0',
        ];

        $sess = $this->session->userdata('importar_clientes');
        if (is_array($sess)) {
            foreach (['file_path','file_token','file_ext','preview','errors','mapping','mode','upsert_key','block_duplicates','extra_required'] as $k) {
                if (array_key_exists($k, $sess)) {
                    $data[$k] = $sess[$k];
                }
            }
        }

        $this->load->view('importar_clientes/importer', $data);
    }

    public function upload()
    {
        if (!has_permission('customers', '', 'create')) {
            access_denied('customers');
        }

        if (!isset($_FILES['file']) || empty($_FILES['file']['name'])) {
            set_alert('danger', 'Selecciona un archivo.');
            redirect(admin_url('importar_clientes'));
        }

        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv','xls','xlsx'], true)) {
            set_alert('danger', 'Solo CSV, XLS o XLSX.');
            redirect(admin_url('importar_clientes'));
        }

        $token = bin2hex(random_bytes(16));
        $dest  = $this->tmpDir . 'import_' . $token . '.' . $ext;

        if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
            set_alert('danger', 'No se pudo guardar el archivo.');
            redirect(admin_url('importar_clientes'));
        }

        $mode = $this->input->post('mode') === 'upsert' ? 'upsert' : 'insert';
        $upsert_key = in_array($this->input->post('upsert_key'), ['vat','email'], true) ? $this->input->post('upsert_key') : 'vat';
        $block_duplicates = $this->input->post('block_duplicates') === '1' ? '1' : '0';

        $extra_required = $this->input->post('extra_required');
        if (!is_array($extra_required)) { $extra_required = []; }
        $extra_required = [
            'company'     => isset($extra_required['company']) ? '1' : '0',
            'email'       => isset($extra_required['email']) ? '1' : '0',
            'phonenumber' => isset($extra_required['phonenumber']) ? '1' : '0',
            'country'     => isset($extra_required['country']) ? '1' : '0',
        ];

        $columns = $this->get_tblclients_columns();
        $expectedCount = count($columns);

        $preview = $this->parse_file_preview($dest, $ext, $expectedCount, 20);

        $mapping = [];
        for ($i=0; $i < (int)($preview['extra_columns'] ?? 0); $i++) {
            $mapping[$i] = '';
        }

        $required = $this->get_tblclients_required_columns($columns);
        $validation = $this->validate_file($dest, $ext, $columns, $required, $extra_required, $mode, $upsert_key, $block_duplicates);

        $this->session->set_userdata('importar_clientes', [
            'file_path'        => $dest,
            'file_token'       => $token,
            'file_ext'         => $ext,
            'preview'          => $preview,
            'errors'           => $validation['errors'],
            'mapping'          => $mapping,
            'mode'             => $mode,
            'upsert_key'       => $upsert_key,
            'block_duplicates' => $block_duplicates,
            'extra_required'   => $extra_required,
        ]);

        redirect(admin_url('importar_clientes?step=preview'));
    }

    public function save_mapping()
    {
        if (!has_permission('customers', '', 'create')) {
            access_denied('customers');
        }

        $sess = $this->session->userdata('importar_clientes');
        if (!is_array($sess) || empty($sess['file_path']) || !file_exists($sess['file_path'])) {
            set_alert('danger', 'Sesión inválida. Vuelve a subir el archivo.');
            redirect(admin_url('importar_clientes'));
        }

        $mapping = $this->input->post('mapping');
        if (!is_array($mapping)) { $mapping = []; }

        $clean = [];
        foreach ($mapping as $k => $v) {
            $k = (int)$k;
            $v = trim((string)$v);
            if ($v !== '' && !ctype_digit($v)) { $v = ''; }
            $clean[$k] = $v;
        }

        $sess['mapping'] = $clean;
        $this->session->set_userdata('importar_clientes', $sess);

        set_alert('success', 'Mapeo guardado.');
        redirect(admin_url('importar_clientes?step=preview'));
    }

    public function confirm_import()
    {
        if (!has_permission('customers', '', 'create')) {
            access_denied('customers');
        }

        $sess = $this->session->userdata('importar_clientes');
        if (!is_array($sess) || empty($sess['file_path']) || !file_exists($sess['file_path'])) {
            set_alert('danger', 'Sesión inválida. Vuelve a subir el archivo.');
            redirect(admin_url('importar_clientes'));
        }

        $ext = $sess['file_ext'] ?? 'csv';

        $columns  = $this->get_tblclients_columns();
        $required = $this->get_tblclients_required_columns($columns);

        $mode = ($sess['mode'] ?? 'insert') === 'upsert' ? 'upsert' : 'insert';
        $upsert_key = in_array(($sess['upsert_key'] ?? 'vat'), ['vat','email'], true) ? $sess['upsert_key'] : 'vat';
        $block_duplicates = ($sess['block_duplicates'] ?? '1') === '1' ? '1' : '0';
        $extra_required = is_array($sess['extra_required'] ?? null) ? $sess['extra_required'] : [];

        $validation = $this->validate_file($sess['file_path'], $ext, $columns, $required, $extra_required, $mode, $upsert_key, $block_duplicates);
        if (!empty($validation['errors'])) {
            $sess['errors'] = $validation['errors'];
            $sess['preview'] = $this->parse_file_preview($sess['file_path'], $ext, count($columns), 20);
            $this->session->set_userdata('importar_clientes', $sess);

            set_alert('danger', 'La importación está bloqueada: corrige los errores.');
            redirect(admin_url('importar_clientes?step=preview'));
        }

        $rows = $this->read_all_rows($sess['file_path'], $ext);
        if ($rows === null) {
            set_alert('danger', 'No se pudo leer XLS/XLSX (requiere PhpSpreadsheet). Usa CSV o instala la librería.');
            redirect(admin_url('importar_clientes'));
        }

        $expectedCount = count($columns);
        $mapping = is_array($sess['mapping'] ?? null) ? $sess['mapping'] : [];

        $imported = 0;
        $updated  = 0;

        $this->db->trans_start();

        foreach ($rows as $idx => $row) {
            if ($this->is_row_empty($row)) { continue; }
            if (count($row) < $expectedCount) { continue; }

            $assoc = [];
            for ($i=0; $i<$expectedCount; $i++) {
                $assoc[$columns[$i]] = isset($row[$i]) ? trim((string)$row[$i]) : '';
            }

            $clientId = 0;

            if ($mode === 'upsert') {
                $keyVal = trim((string)($assoc[$upsert_key] ?? ''));
                if ($keyVal !== '') {
                    $this->db->where($upsert_key, $keyVal);
                    $existing = $this->db->get(db_prefix().'clients')->row_array();
                    if ($existing && isset($existing['userid'])) {
                        $clientId = (int)$existing['userid'];
                    }
                }
            }

            $payload = [];
            for ($i=0; $i<$expectedCount; $i++) {
                $col = $columns[$i];
                if ($col === 'userid') { continue; }
                $payload[$col] = $this->normalize_value($col, $row[$i]);
            }

            if ($clientId > 0) {
                $this->db->where('userid', $clientId);
                $this->db->update(db_prefix().'clients', $payload);
                $updated++;
            } else {
                $this->db->insert(db_prefix().'clients', $payload);
                $clientId = (int)$this->db->insert_id();
                if ($clientId > 0) { $imported++; }
            }

            if ($clientId > 0) {
                // custom fields
                $extraStart = $expectedCount;
                foreach ($mapping as $extraIndex => $fieldId) {
                    $fieldId = trim((string)$fieldId);
                    if ($fieldId === '') { continue; }

                    $csvIndex = $extraStart + (int)$extraIndex;
                    if (!isset($row[$csvIndex])) { continue; }
                    $val = trim((string)$row[$csvIndex]);
                    if ($val === '') { continue; }

                    $fieldIdInt = (int)$fieldId;

                    $this->db->where('relid', $clientId);
                    $this->db->where('fieldid', $fieldIdInt);
                    $this->db->where('fieldto', 'customers');
                    $existingCf = $this->db->get(db_prefix().'customfieldsvalues')->row_array();

                    if ($existingCf && isset($existingCf['id'])) {
                        $this->db->where('id', (int)$existingCf['id']);
                        $this->db->update(db_prefix().'customfieldsvalues', ['value' => $val]);
                    } else {
                        $this->db->insert(db_prefix().'customfieldsvalues', [
                            'relid'   => $clientId,
                            'fieldid' => $fieldIdInt,
                            'fieldto' => 'customers',
                            'value'   => $val,
                        ]);
                    }
                }
            }
        }

        $this->db->trans_complete();

        @unlink($sess['file_path']);
        $this->session->unset_userdata('importar_clientes');

        if ($this->db->trans_status() === false) {
            set_alert('danger', 'Error en base de datos. No se completó.');
            redirect(admin_url('importar_clientes'));
        }

        set_alert('success', "Importación finalizada. Insertados: {$imported}. Actualizados: {$updated}.");
        redirect(admin_url('importar_clientes'));
    }

    // ---------- helpers ----------

    private function get_tblclients_columns(): array
    {
        $table = db_prefix() . 'clients';
        $cols = [];
        $query = $this->db->query("SHOW COLUMNS FROM `{$table}`");
        foreach ($query->result_array() as $row) {
            $cols[] = $row['Field'];
        }
        return $cols;
    }

    private function get_tblclients_required_columns(array $columns): array
    {
        $table = db_prefix() . 'clients';
        $required = [];
        $query = $this->db->query("SHOW COLUMNS FROM `{$table}`");
        foreach ($query->result_array() as $row) {
            $field = $row['Field'];
            if (!in_array($field, $columns, true)) { continue; }
            if ($field === 'userid') { continue; }
            $null = strtoupper((string)$row['Null']);
            $default = $row['Default'];
            $extra = strtolower((string)$row['Extra']);
            if ($null === 'NO' && $default === null && strpos($extra, 'auto_increment') === false) {
                $required[] = $field;
            }
        }
        return $required;
    }

    private function get_customer_custom_fields(): array
    {
        $this->db->where('fieldto', 'customers');
        $this->db->order_by('name', 'ASC');
        return $this->db->get(db_prefix() . 'customfields')->result_array();
    }

    private function parse_file_preview(string $path, string $ext, int $expectedCount, int $maxRows): array
    {
        $rows = $this->read_preview_rows($path, $ext, $maxRows);
        if ($rows === null) {
            return [
                'rows' => [],
                'extra_columns' => 0,
                'max_cols' => 0,
                'expected_cols' => $expectedCount,
                'read_error' => 'No se pudo leer XLS/XLSX. Instala PhpSpreadsheet o usa CSV.',
            ];
        }

        $maxCols = 0;
        foreach ($rows as $r) {
            $maxCols = max($maxCols, count($r));
        }
        $extra = max(0, $maxCols - $expectedCount);

        return [
            'rows' => $rows,
            'extra_columns' => $extra,
            'max_cols' => $maxCols,
            'expected_cols' => $expectedCount,
            'read_error' => '',
        ];
    }

    private function validate_file(string $path, string $ext, array $columns, array $required, array $extra_required, string $mode, string $upsert_key, string $block_duplicates): array
    {
        $rows = $this->read_all_rows($path, $ext);
        if ($rows === null) {
            return ['errors' => ['No se pudo leer XLS/XLSX. Instala PhpSpreadsheet en el servidor o usa CSV.']];
        }

        $errors = [];
        $expectedCount = count($columns);
        $seen = [];

        $rowIndex = 0;
        foreach ($rows as $row) {
            $rowIndex++;
            if ($this->is_row_empty($row)) { continue; }

            if (count($row) < $expectedCount) {
                $errors[] = "Fila {$rowIndex}: columnas insuficientes (" . count($row) . "/{$expectedCount}).";
                continue;
            }

            $assoc = [];
            for ($i=0; $i<$expectedCount; $i++) {
                $assoc[$columns[$i]] = isset($row[$i]) ? trim((string)$row[$i]) : '';
            }

            foreach ($required as $col) {
                if (!isset($assoc[$col]) || trim((string)$assoc[$col]) === '') {
                    $errors[] = "Fila {$rowIndex}: falta campo obligatorio '{$col}'.";
                }
            }

            foreach (['company','email','phonenumber','country'] as $k) {
                if (($extra_required[$k] ?? '0') === '1') {
                    if (!isset($assoc[$k]) || trim((string)$assoc[$k]) === '') {
                        $errors[] = "Fila {$rowIndex}: falta campo requerido '{$k}' (configurado).";
                    }
                }
            }

            if (isset($assoc['email']) && trim((string)$assoc['email']) !== '' && !filter_var($assoc['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Fila {$rowIndex}: email inválido ('{$assoc['email']}').";
            }

            if (isset($assoc['active']) && trim((string)$assoc['active']) !== '') {
                $a = trim((string)$assoc['active']);
                if (!in_array($a, ['0','1'], true)) {
                    $errors[] = "Fila {$rowIndex}: active debe ser 0 o 1 ('{$a}').";
                }
            }

            if (isset($assoc['datecreated']) && trim((string)$assoc['datecreated']) !== '') {
                $d = trim((string)$assoc['datecreated']);
                if (!$this->is_valid_datetime($d)) {
                    $errors[] = "Fila {$rowIndex}: datecreated inválido ('{$d}'). Usa YYYY-MM-DD o YYYY-MM-DD HH:MM:SS.";
                }
            }

            if ($block_duplicates === '1') {
                if ($mode === 'upsert') {
                    $kv = trim((string)($assoc[$upsert_key] ?? ''));
                    if ($kv === '') {
                        $errors[] = "Fila {$rowIndex}: en modo ACTUALIZAR, '{$upsert_key}' no puede ir vacío.";
                    } else {
                        $key = $upsert_key . ':' . strtolower($kv);
                        if (isset($seen[$key])) {
                            $errors[] = "Fila {$rowIndex}: duplicado en archivo para '{$upsert_key}'='{$kv}' (fila {$seen[$key]}).";
                        } else {
                            $seen[$key] = $rowIndex;
                        }
                    }
                } else {
                    foreach (['vat','email'] as $k) {
                        $kv = trim((string)($assoc[$k] ?? ''));
                        if ($kv === '') { continue; }
                        $key = $k . ':' . strtolower($kv);
                        if (isset($seen[$key])) {
                            $errors[] = "Fila {$rowIndex}: duplicado en archivo para '{$k}'='{$kv}' (fila {$seen[$key]}).";
                        } else {
                            $seen[$key] = $rowIndex;
                        }
                    }
                }
            }
        }

        return ['errors' => $errors];
    }

    private function read_preview_rows(string $path, string $ext, int $maxRows): ?array
    {
        $rows = $this->read_all_rows($path, $ext);
        if ($rows === null) { return null; }
        return array_slice($rows, 0, $maxRows);
    }

    private function read_all_rows(string $path, string $ext): ?array
    {
        $ext = strtolower($ext);

        if ($ext === 'csv') {
            $rows = [];
            $fh = fopen($path, 'r');
            if ($fh === false) { return null; }
            while (($row = fgetcsv($fh, 0, ',')) !== false) {
                if ($this->is_row_empty($row)) { continue; }
                $rows[] = $row;
            }
            fclose($fh);
            return $rows;
        }

        if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory')) {
            return null;
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = [];
            foreach ($sheet->toArray(null, true, true, false) as $row) {
                if ($this->is_row_empty($row)) { continue; }
                $rows[] = $row;
            }
            return $rows;
        } catch (Exception $e) {
            return null;
        }
    }

    private function is_valid_datetime(string $s): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) { return true; }
        if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/', $s)) { return true; }
        return false;
    }

    private function is_row_empty(array $row): bool
    {
        foreach ($row as $v) {
            if (trim((string)$v) !== '') { return false; }
        }
        return true;
    }

    private function normalize_value(string $col, $val)
    {
        $val = is_string($val) ? trim($val) : $val;
        if ($col === 'active' && $val !== '') {
            return ($val === '1' || $val === 1) ? 1 : 0;
        }
        return $val;
    }
}
