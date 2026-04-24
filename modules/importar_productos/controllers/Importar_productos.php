<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Importar_productos extends AdminController
{
    private string $tmpDir;

    /** @var string customfieldsvalues.fieldto for item custom fields (matches core import) */
    private const ITEMS_CF_REL = 'items_pr';

    /** @var array<int,string> */
    private array $notImportableFields = ['id'];

    public function __construct()
    {
        parent::__construct();

        if (!staff_can('create', 'items')) {
            access_denied('Items');
        }

        $this->tmpDir = FCPATH . 'uploads/importar_productos/';
        if (!is_dir($this->tmpDir)) {
            @mkdir($this->tmpDir, 0755, true);
        }
    }

    public function index()
    {
        $data = [];
        $data['title'] = _l('importar_productos_title');
        $data['step'] = $this->input->get('step') ?: 'upload';

        $data['items_fields'] = $this->getImportableItemFields();
        $data['custom_fields'] = $this->getItemCustomFields();

        $sess = $this->session->userdata('importar_productos');
        if (is_array($sess)) {
            foreach ([
                'file_path', 'file_ext', 'headers', 'header_count', 'preview_rows',
                'column_map', 'errors', 'last_result', 'duplicate_check', 'duplicate_check_db', 'duplicate_by',
            ] as $k) {
                if (array_key_exists($k, $sess)) {
                    $data[$k] = $sess[$k];
                }
            }
        }

        if (($data['step'] === 'map') && (!is_array($sess) || empty($sess['headers']))) {
            set_alert('warning', _l('importar_productos_session_invalid'));
            redirect(admin_url('importar_productos'));
        }

        $this->load->view('importer', $data);
    }

    public function upload()
    {
        if (!isset($_FILES['file']) || empty($_FILES['file']['name'])) {
            set_alert('danger', _l('importar_productos_session_invalid'));
            redirect(admin_url('importar_productos'));
        }

        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xls', 'xlsx'], true)) {
            set_alert('danger', 'Solo CSV, XLS o XLSX.');
            redirect(admin_url('importar_productos'));
        }

        $token = bin2hex(random_bytes(16));
        $dest  = $this->tmpDir . 'import_' . $token . '.' . $ext;

        if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
            set_alert('danger', 'No se pudo guardar el archivo.');
            redirect(admin_url('importar_productos'));
        }

        $parsed = $this->parseFileWithHeaders($dest, $ext);
        if ($parsed === null || empty($parsed['headers'])) {
            @unlink($dest);
            set_alert('danger', _l('importar_productos_read_error'));
            redirect(admin_url('importar_productos'));
        }

        $column_map = [];
        foreach ($parsed['headers'] as $idx => $headerLabel) {
            $column_map[$idx] = $this->guessMapping((string) $headerLabel);
        }

        $duplicate_check = $this->input->post('duplicate_check') === '1' ? '1' : '0';
        $duplicate_check_db = $this->input->post('duplicate_check_db') === '1' ? '1' : '0';
        $duplicate_by    = $this->input->post('duplicate_by') === 'sku_code' ? 'sku_code' : 'description';

        $this->session->set_userdata('importar_productos', [
            'file_path'        => $dest,
            'file_ext'         => $ext,
            'headers'          => $parsed['headers'],
            'header_count'     => count($parsed['headers']),
            'preview_rows'     => array_slice($parsed['data_rows'], 0, 15),
            'column_map'       => $column_map,
            'errors'           => [],
            'last_result'      => null,
            'duplicate_check'  => $duplicate_check,
            'duplicate_check_db' => $duplicate_check_db,
            'duplicate_by'     => $duplicate_by,
        ]);

        redirect(admin_url('importar_productos?step=map'));
    }

    public function save_mapping()
    {
        $sess = $this->session->userdata('importar_productos');
        if (!is_array($sess) || empty($sess['file_path']) || !is_file($sess['file_path'])) {
            set_alert('danger', _l('importar_productos_session_invalid'));
            redirect(admin_url('importar_productos'));
        }

        $posted = $this->input->post('column_map');
        if (!is_array($posted)) {
            $posted = [];
        }

        $clean = [];
        foreach ($posted as $k => $v) {
            $clean[(int) $k] = trim((string) $v);
        }

        $sess['column_map'] = $clean;
        $sess['duplicate_check'] = $this->input->post('duplicate_check') === '1' ? '1' : '0';
        $sess['duplicate_check_db'] = $this->input->post('duplicate_check_db') === '1' ? '1' : '0';
        $sess['duplicate_by']     = $this->input->post('duplicate_by') === 'sku_code' ? 'sku_code' : 'description';

        $mapErrors = $this->validateMapping($clean, (int) $sess['header_count']);
        $mapErrors = array_merge($mapErrors, $this->validateDuplicateRules($clean, $sess));
        $sess['errors'] = $mapErrors;

        $this->session->set_userdata('importar_productos', $sess);

        if (!empty($mapErrors)) {
            set_alert('danger', 'Corrige el mapeo: faltan campos obligatorios.');
        } else {
            set_alert('success', 'Mapeo guardado.');
        }

        redirect(admin_url('importar_productos?step=map'));
    }

    public function run()
    {
        $sess = $this->session->userdata('importar_productos');
        if (!is_array($sess) || empty($sess['file_path']) || !is_file($sess['file_path'])) {
            set_alert('danger', _l('importar_productos_session_invalid'));
            redirect(admin_url('importar_productos'));
        }

        $simulate = $this->input->post('simulate') === '1';

        $parsed = $this->parseFileWithHeaders($sess['file_path'], $sess['file_ext']);
        if ($parsed === null) {
            set_alert('danger', _l('importar_productos_read_error'));
            redirect(admin_url('importar_productos?step=map'));
        }

        $column_map = is_array($sess['column_map'] ?? null) ? $sess['column_map'] : [];
        $mapErrors  = $this->validateMapping($column_map, count($parsed['headers']));
        $mapErrors = array_merge($mapErrors, $this->validateDuplicateRules($column_map, $sess));
        if (!empty($mapErrors)) {
            $sess['errors'] = $mapErrors;
            $this->session->set_userdata('importar_productos', $sess);
            set_alert('danger', 'No se puede importar: mapeo incompleto.');
            redirect(admin_url('importar_productos?step=map'));
        }

        $customFields = $this->getItemCustomFields();
        $cfById = [];
        foreach ($customFields as $cf) {
            $cfById[(int) $cf['id']] = $cf;
        }

        $rowErrors   = [];
        $simRows     = [];
        $imported    = 0;
        $skipped     = 0;

        $dupCheck = ($sess['duplicate_check'] ?? '0') === '1';
        $dupCheckDb = ($sess['duplicate_check_db'] ?? '0') === '1';
        $dupBy    = ($sess['duplicate_by'] ?? 'description') === 'sku_code' ? 'sku_code' : 'description';
        $seenDup  = [];

        if (!$simulate) {
            $this->db->trans_start();
        }

        $excelRowBase = 2;
        foreach ($parsed['data_rows'] as $i => $row) {
            $sheetRow = $excelRowBase + $i;
            if ($this->isRowEmpty($row)) {
                continue;
            }

            $build = $this->buildItemPayload($row, $column_map, $cfById);
            if (!empty($build['errors'])) {
                foreach ($build['errors'] as $er) {
                    $rowErrors[] = "Fila {$sheetRow}: {$er}";
                }
                $skipped++;
                continue;
            }

            /** @var array<string,mixed> $insert */
            $insert = $build['insert'];
            $cfVals = $build['custom_field_values'];

            if ($dupCheck) {
                $keyField = $dupBy;
                $keyVal = isset($insert[$keyField]) ? trim((string) $insert[$keyField]) : '';
                if ($keyVal === '') {
                    $rowErrors[] = "Fila {$sheetRow}: duplicados activados pero '{$keyField}' está vacío.";
                    $skipped++;
                    continue;
                }
                $dkey = $keyField . '|' . strtolower($keyVal);
                if (isset($seenDup[$dkey])) {
                    $rowErrors[] = "Fila {$sheetRow}: duplicado en archivo ({$keyField}) igual a fila {$seenDup[$dkey]}.";
                    $skipped++;
                    continue;
                }
                $seenDup[$dkey] = $sheetRow;
            }

            if ($simulate) {
                if (count($simRows) < 80) {
                    $simRows[] = [
                        'row'     => $sheetRow,
                        'insert'  => $insert,
                        'customs' => $cfVals,
                        'ramos'   => $build['ramos_sync'] ?? ['has_maduracion' => null],
                    ];
                }
                $imported++;
                continue;
            }

            if ($dupCheckDb) {
                $keyField = $dupBy;
                $keyVal = isset($insert[$keyField]) ? trim((string) $insert[$keyField]) : '';
                if ($keyVal !== '' && $this->dbItemExists($keyField, $keyVal)) {
                    $rowErrors[] = "Fila {$sheetRow}: ya existe en la base de datos ({$keyField}='{$keyVal}').";
                    $skipped++;
                    continue;
                }
            }

            $insert = $this->filterItemInsert($insert);

            $this->db->insert(db_prefix() . 'items', $insert);
            $id = (int) $this->db->insert_id();
            if ($id < 1) {
                $rowErrors[] = "Fila {$sheetRow}: no se pudo insertar el artículo.";
                $skipped++;
                continue;
            }

            $this->syncRamosMaduracionFromImport($insert, $build['ramos_sync']['has_maduracion'] ?? null);

            foreach ($cfVals as $fid => $val) {
                $fid = (int) $fid;
                if ($fid < 1 || $val === '' || $val === null) {
                    continue;
                }
                $cfRow = $cfById[$fid] ?? null;
                if (!$cfRow) {
                    continue;
                }
                $storeVal = (string) $val;
                if (($cfRow['type'] ?? '') === 'link' && class_exists('\\app\\services\\utilities\\Str') && !\app\services\utilities\Str::isHtml($storeVal)) {
                    $storeVal = sprintf('<a href="%s" target="_blank">%s</a>', $storeVal, $storeVal);
                }
                $this->db->insert(db_prefix() . 'customfieldsvalues', [
                    'relid'   => $id,
                    'fieldid' => $fid,
                    'fieldto' => self::ITEMS_CF_REL,
                    'value'   => trim($storeVal),
                ]);
            }

            $imported++;
        }

        if (!$simulate) {
            $this->db->trans_complete();
            if ($this->db->trans_status() === false) {
                @unlink($sess['file_path']);
                $this->session->unset_userdata('importar_productos');
                set_alert('danger', 'Error en base de datos; se revirtió la transacción.');
                redirect(admin_url('importar_productos'));
            }
        }

        @unlink($sess['file_path']);

        $sess['last_result'] = [
            'simulate'   => $simulate,
            'imported'   => $imported,
            'skipped'    => $skipped,
            'row_errors' => array_slice($rowErrors, 0, 300),
            'sim_rows'   => $simRows,
        ];
        $sess['errors'] = [];
        unset($sess['file_path'], $sess['file_ext'], $sess['preview_rows'], $sess['headers'], $sess['header_count'], $sess['column_map']);

        $this->session->set_userdata('importar_productos', $sess);

        if ($simulate) {
            set_alert('info', _l('importar_productos_results_simulated'));
        } else {
            set_alert('success', _l('importar_productos_results_imported') . " Insertados: {$imported}. Omitidos: {$skipped}.");
        }

        redirect(admin_url('importar_productos?step=results'));
    }

    public function cancel()
    {
        $sess = $this->session->userdata('importar_productos');
        if (is_array($sess) && !empty($sess['file_path']) && is_file($sess['file_path'])) {
            @unlink($sess['file_path']);
        }
        $this->session->unset_userdata('importar_productos');
        redirect(admin_url('importar_productos'));
    }

    // ---------------------------------------------------------------------
    // Parsing
    // ---------------------------------------------------------------------

    /**
     * @return array{headers: string[], data_rows: array<int,array<int,string>>}|null
     */
    private function parseFileWithHeaders(string $path, string $ext): ?array
    {
        $ext = strtolower($ext);
        if ($ext === 'csv') {
            return $this->parseCsvWithHeaders($path);
        }

        return $this->parseExcelWithHeaders($path);
    }

    /**
     * @return array{headers: string[], data_rows: array<int,array<int,string>>}|null
     */
    private function parseCsvWithHeaders(string $path): ?array
    {
        $fh = fopen($path, 'r');
        if ($fh === false) {
            return null;
        }
        $first = fgets($fh);
        if ($first === false) {
            fclose($fh);
            return null;
        }
        $delimiter = $this->detectCsvDelimiter($first);
        rewind($fh);
        $headerLine = fgetcsv($fh, 0, $delimiter);
        if ($headerLine === false) {
            fclose($fh);
            return null;
        }
        $headers = $this->normalizeHeaderRow($headerLine);
        $dataRows = [];
        while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
            if ($this->isRowEmpty($row)) {
                continue;
            }
            $dataRows[] = array_map(static function ($v) {
                return trim((string) $v);
            }, $row);
        }
        fclose($fh);

        return ['headers' => $headers, 'data_rows' => $this->trimRowsToHeaderCount($dataRows, count($headers))];
    }

    private function detectCsvDelimiter(string $firstLine): string
    {
        $comma = substr_count($firstLine, ',');
        $semi  = substr_count($firstLine, ';');
        return $semi > $comma ? ';' : ',';
    }

    /**
     * @return array{headers: string[], data_rows: array<int,array<int,string>>}|null
     */
    private function parseExcelWithHeaders(string $path): ?array
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
            $colCount = PHPExcel_Cell::columnIndexFromString($highestColLetter); // 1-based count
        } catch (Throwable $e) {
            return null;
        }

        $headers = [];
        for ($c = 0; $c < $colCount; $c++) {
            $cell = $sheet->getCellByColumnAndRow($c, 1);
            $headers[] = trim($this->cellToString($cell));
        }

        $dataRows = [];
        for ($r = 2; $r <= $highestRow; $r++) {
            $row = [];
            for ($c = 0; $c < $colCount; $c++) {
                $cell = $sheet->getCellByColumnAndRow($c, $r);
                $row[] = trim($this->cellToString($cell));
            }
            if ($this->isRowEmpty($row)) {
                continue;
            }
            $dataRows[] = $row;
        }

        return ['headers' => $headers, 'data_rows' => $this->trimRowsToHeaderCount($dataRows, count($headers))];
    }

    private function cellToString($cell): string
    {
        if ($cell === null) {
            return '';
        }
        $v = $cell->getValue();
        if (is_object($v) && method_exists($v, 'getPlainText')) {
            return $v->getPlainText();
        }
        if ($v === null) {
            return '';
        }
        return (string) $v;
    }

    /**
     * @param array<int,array<int,string>> $rows
     * @return array<int,array<int,string>>
     */
    private function trimRowsToHeaderCount(array $rows, int $headerCount): array
    {
        $out = [];
        foreach ($rows as $row) {
            $row = array_values($row);
            if ($headerCount <= 0) {
                $out[] = [];
                continue;
            }
            if (count($row) < $headerCount) {
                $row = array_pad($row, $headerCount, '');
            } elseif (count($row) > $headerCount) {
                $row = array_slice($row, 0, $headerCount);
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param array<int,string> $headerLine
     * @return array<int,string>
     */
    private function normalizeHeaderRow(array $headerLine): array
    {
        $headers = [];
        foreach ($headerLine as $h) {
            $headers[] = trim((string) $h);
        }

        return $headers;
    }

    // ---------------------------------------------------------------------
    // Mapping
    // ---------------------------------------------------------------------

    /**
     * @param array<int,string> $column_map
     * @return array<int,string> errors (empty if ok)
     */
    private function validateMapping(array $column_map, int $headerCount): array
    {
        $errors = [];
        $hasDesc = false;
        for ($i = 0; $i < $headerCount; $i++) {
            $v = $column_map[$i] ?? '';
            if ($v === 'db:description') {
                $hasDesc = true;
            }
        }
        if (!$hasDesc) {
            $errors[] = "Debes mapear la columna 'description' (descripción / producto).";
        }

        return $errors;
    }

    private function guessMapping(string $header): string
    {
        $h = strtolower(trim($header));
        $h = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $h);

        // Exact headers first so we do not mis-map (e.g. "commodity_name" contains "nombre").
        $exact = [
            'commodity_name' => 'db:commodity_name',
            'commodity_code' => 'db:commodity_code',
            'commodity_barcode' => 'db:commodity_barcode',
            'maduracion' => 'ramos_sync:has_maduracion',
        ];
        if (isset($exact[$h])) {
            return $exact[$h];
        }

        $aliases = [
            'db:description' => ['description', 'descripcion', 'descripción', 'producto', 'nombre', 'articulo', 'artículo', 'item'],
            'db:rate'        => ['rate', 'precio', 'precio venta', 'p. venta', 'pvp', 'price'],
            'db:purchase_price' => ['purchase_price', 'purchase price', 'precio compra', 'precio de compra', 'costo', 'cost'],
            'db:unit'        => ['unit', 'unidad', 'u.m.', 'um'],
            'db:group_id'    => ['group', 'group_id', 'grupo', 'categoria', 'categoría', 'familia'],
            'db:tax'         => ['tax', 'impuesto', 'iva', 'tax1'],
            'db:tax2'        => ['tax2', 'impuesto2', 'impuesto 2', 'iva2'],
            'db:sku_code'    => ['sku', 'sku_code', 'codigo sku', 'código sku', 'clave sku'],
            'db:long_description' => ['long_description', 'detalle', 'descripcion larga', 'notas'],
        ];

        foreach ($aliases as $target => $names) {
            foreach ($names as $n) {
                if ($h === $n || strpos($h, $n) !== false) {
                    return $target;
                }
            }
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function getImportableItemFields(): array
    {
        $fields = $this->db->list_fields(db_prefix() . 'items');
        $out = [];
        foreach ($fields as $f) {
            if (in_array($f, $this->notImportableFields, true)) {
                continue;
            }
            $out[] = $f;
        }

        return $out;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function getItemCustomFields(): array
    {
        $this->db->where('fieldto', 'items');
        $this->db->order_by('name', 'ASC');

        return $this->db->get(db_prefix() . 'customfields')->result_array();
    }

    /**
     * @param array<int,string> $row
     * @param array<int,string> $column_map
     * @param array<int,array<string,mixed>> $cfById
     * @return array{insert: array<string,mixed>, custom_field_values: array<int,string>, errors: string[], ramos_sync: array{has_maduracion: int|null}}
     */
    private function buildItemPayload(array $row, array $column_map, array $cfById): array
    {
        $errors = [];
        $insert = [];
        $cfVals = [];
        $ramosSync = ['has_maduracion' => null];

        foreach ($column_map as $idx => $target) {
            $target = trim((string) $target);
            if ($target === '') {
                continue;
            }
            $val = isset($row[$idx]) ? $row[$idx] : '';
            $val = trim((string) $val);

            if (strpos($target, 'db:') === 0) {
                $field = substr($target, 3);
                if ($field === '') {
                    continue;
                }
                if (in_array($field, $this->notImportableFields, true)) {
                    continue;
                }
                $insert[$field] = $val;
            } elseif (strpos($target, 'cf:') === 0) {
                $fid = (int) substr($target, 3);
                if ($fid > 0) {
                    $cfVals[$fid] = $val;
                }
            } elseif (strpos($target, 'ramos_sync:') === 0) {
                $ramosKey = substr($target, strlen('ramos_sync:'));
                if ($ramosKey === 'has_maduracion') {
                    $parsed = $this->parseMaduracionFlag($val);
                    if ($parsed !== null) {
                        $ramosSync['has_maduracion'] = $parsed;
                    }
                }
            }
        }

        if (!isset($insert['description'])) {
            $insert['description'] = '';
        }
        $insert['description'] = trim((string) $insert['description']);
        if ($insert['description'] === '') {
            $insert['description'] = '/';
        }

        // Normalize rate
        if (!isset($insert['rate']) || trim((string) $insert['rate']) === '') {
            $insert['rate'] = 0.0;
        } else {
            $rateRaw = str_replace([' ', ','], ['', '.'], (string) $insert['rate']);
            if (!is_numeric($rateRaw)) {
                $insert['rate'] = 0.0;
            } else {
                $insert['rate'] = (float) $rateRaw;
            }
        }

        foreach (['group_id', 'tax', 'tax2'] as $f) {
            if (array_key_exists($f, $insert)) {
                $insert[$f] = $this->normalizeTaxOrGroupField($f, (string) $insert[$f]);
            }
        }

        if (!empty($insert['tax2']) && empty($insert['tax'])) {
            $insert['tax']  = $insert['tax2'];
            $insert['tax2'] = 0;
        }

        // Defaults for common numeric fields if present in schema but empty
        foreach (['group_id', 'tax', 'tax2'] as $f) {
            if (!array_key_exists($f, $insert)) {
                continue;
            }
            if ($insert[$f] === '' || $insert[$f] === null) {
                $insert[$f] = 0;
            }
            $insert[$f] = (int) $insert[$f];
        }

        $insert = $this->filterItemInsert($insert);

        foreach ($insert as $k => $v) {
            if ($k === 'description' || $k === 'rate') {
                continue;
            }
            if ($v === '' || $v === null) {
                unset($insert[$k]);
            }
        }

        return [
            'insert'               => $insert,
            'custom_field_values'  => $cfVals,
            'errors'               => [],
            'ramos_sync'           => $ramosSync,
        ];
    }

    /**
     * @param array<int,string> $column_map
     * @param array<string,mixed> $sess
     * @return list<string>
     */
    private function validateDuplicateRules(array $column_map, array $sess): array
    {
        $errors = [];
        if (($sess['duplicate_check'] ?? '0') !== '1') {
            return $errors;
        }
        if (($sess['duplicate_by'] ?? 'description') !== 'sku_code') {
            return $errors;
        }
        $hasSku = false;
        foreach ($column_map as $v) {
            if ($v === 'db:sku_code') {
                $hasSku = true;
                break;
            }
        }
        if (!$hasSku) {
            $errors[] = 'Duplicados por SKU: debes mapear la columna sku_code (db:sku_code).';
        }

        return $errors;
    }

    /**
     * @param array<string,mixed> $insert
     * @return array<string,mixed>
     */
    private function filterItemInsert(array $insert): array
    {
        $allowed = array_flip($this->getImportableItemFields());
        $out = [];
        foreach ($insert as $k => $v) {
            if (isset($allowed[$k])) {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    private function normalizeTaxOrGroupField(string $field, string $value): int|string
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        if ($field === 'group_id') {
            return $this->resolveGroupId($value);
        }
        if ($field === 'tax' || $field === 'tax2') {
            return $this->resolveTaxId($value);
        }

        return $value;
    }

    private function resolveTaxId(string $value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }
        $this->db->where('name', $value);
        $row = $this->db->get(db_prefix() . 'taxes')->row();

        return $row ? (int) $row->id : 0;
    }

    private function resolveGroupId(string $value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }
        $this->db->where('name', $value);
        $row = $this->db->get(db_prefix() . 'items_groups')->row();

        return $row ? (int) $row->id : 0;
    }

    /**
     * @param array<int,mixed> $row
     */
    private function isRowEmpty(array $row): bool
    {
        foreach ($row as $v) {
            if (trim((string) $v) !== '') {
                return false;
            }
        }

        return true;
    }

    private function dbItemExists(string $field, string $value): bool
    {
        $field = $field === 'sku_code' ? 'sku_code' : 'description';
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        $this->db->where($field, $value);
        $this->db->limit(1);
        $row = $this->db->get(db_prefix() . 'items')->row();

        return (bool) $row;
    }

    /**
     * Parse Sí/No style spreadsheet values into 0|1; unknown/blank returns null (skip Ramos sync).
     */
    private function parseMaduracionFlag(string $raw): ?int
    {
        $t = function_exists('mb_strtolower') ? mb_strtolower(trim($raw), 'UTF-8') : strtolower(trim($raw));
        $t = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $t);
        if ($t === '' || $t === '-' || $t === 'n/a') {
            return null;
        }
        if (in_array($t, ['si', 'yes', 'y', '1', 'true', 'verdadero', 'x'], true)) {
            return 1;
        }
        if (in_array($t, ['no', '0', 'false', 'falso'], true)) {
            return 0;
        }

        return null;
    }

    /**
     * Align tblitems.description with ramos_inventory_items (item_name) and set has_maduracion.
     * Runs only when the import column is mapped to ramos_sync:has_maduracion and the cell parses to 0|1.
     *
     * @param array<string,mixed> $insert  Payload inserted into tblitems
     * @param int|null            $hasMaduracion  null = do nothing
     */
    private function syncRamosMaduracionFromImport(array $insert, ?int $hasMaduracion): void
    {
        if ($hasMaduracion === null) {
            return;
        }
        $table = db_prefix() . 'ramos_inventory_items';
        if (!$this->db->table_exists($table)) {
            return;
        }

        $desc = isset($insert['description']) ? trim((string) $insert['description']) : '';
        if ($desc === '' || $desc === '/') {
            return;
        }

        $unit = isset($insert['unit']) ? trim((string) $insert['unit']) : '';
        if ($unit === '') {
            $unit = 'unit';
        }
        $skuRaw = isset($insert['sku_code']) ? trim((string) $insert['sku_code']) : '';
        $sku = $skuRaw === '' ? null : $skuRaw;

        $now = date('Y-m-d H:i:s');
        $staffId = get_staff_user_id();
        $flag = $hasMaduracion ? 1 : 0;

        $existing = $this->db->where('item_name', $desc)->get($table)->row_array();
        if (!empty($existing)) {
            $this->db->where('id', (int) $existing['id']);
            $this->db->update($table, [
                'has_maduracion' => $flag,
                'updated_at'     => $now,
                'updated_by'     => $staffId ?: null,
            ]);

            return;
        }

        if ($sku !== null) {
            $bySku = $this->db->where('sku', $sku)->get($table)->row_array();
            if (!empty($bySku)) {
                $this->db->where('id', (int) $bySku['id']);
                $this->db->update($table, [
                    'item_name'      => $desc,
                    'has_maduracion' => $flag,
                    'updated_at'     => $now,
                    'updated_by'     => $staffId ?: null,
                ]);

                return;
            }
        }

        $this->db->insert($table, [
            'item_name'      => $desc,
            'sku'            => $sku,
            'unit'           => $unit,
            'quantity'       => 0,
            'safety_stock'   => 0,
            'buffer_percent' => 25.00,
            'has_maduracion' => $flag,
            'active'         => 1,
            'created_at'     => $now,
            'created_by'     => $staffId ?: null,
        ]);
    }
}
