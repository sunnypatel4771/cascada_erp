<?php
/**
 * Import Purchase Items from a RAMOS XLSX into Perfex/Purchase tables.
 *
 * - Upserts into `tblitems` by `commodity_code`
 * - Upserts custom fields into `tblcustomfieldsvalues` with `fieldto='items_pr'`
 *
 * Usage:
 *   php scripts/import/import_purchase_items.php "scripts/import/RAMOS_PRODUCTOS_ABRIL_21_2026.xlsx"
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$xlsxPath = $argv[1] ?? '';
if ($xlsxPath === '' || !file_exists($xlsxPath)) {
    fwrite(STDERR, "File not found. Usage: php scripts/import/import_purchase_items.php \"path.xlsx\"\n");
    exit(1);
}

// Perfex/CI configs guard against direct access; define BASEPATH for CLI usage.
if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require_once __DIR__ . '/../../application/config/app-config.php';
require_once __DIR__ . '/../../modules/purchase/assets/plugins/XLSXReader/XLSXReader.php';

$db = new mysqli(APP_DB_HOSTNAME, APP_DB_USERNAME, APP_DB_PASSWORD, APP_DB_NAME);
if ($db->connect_error) {
    fwrite(STDERR, "DB connect error: {$db->connect_error}\n");
    exit(1);
}
$db->set_charset('utf8mb4');

function norm($v): string
{
    if ($v === null) {
        return '';
    }
    if (is_float($v) || is_int($v)) {
        return (string) $v;
    }
    return trim((string) $v);
}

function norm_key($v): string
{
    return mb_strtolower(trim((string) $v));
}

function to_int($v, $default = 0): int
{
    $s = norm($v);
    if ($s === '') {
        return (int) $default;
    }
    return (int) $s;
}

function to_decimal($v, $default = 0.0): float
{
    $s = norm($v);
    if ($s === '') {
        return (float) $default;
    }
    // Remove currency/group separators in a permissive way.
    $s = str_replace([',', ' '], ['', ''], $s);
    return (float) $s;
}

function db_one_assoc(mysqli $db, string $sql, array $params): ?array
{
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException("Prepare failed: {$db->error}");
    }
    if (!empty($params)) {
        $types = '';
        $vals = [];
        foreach ($params as $p) {
            if (is_int($p)) {
                $types .= 'i';
            } elseif (is_float($p)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
            $vals[] = $p;
        }
        $stmt->bind_param($types, ...$vals);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function db_exec(mysqli $db, string $sql, array $params): void
{
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException("Prepare failed: {$db->error}");
    }
    if (!empty($params)) {
        $types = '';
        $vals = [];
        foreach ($params as $p) {
            if (is_int($p)) {
                $types .= 'i';
            } elseif (is_float($p)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
            $vals[] = $p;
        }
        $stmt->bind_param($types, ...$vals);
    }
    $stmt->execute();
    if ($stmt->error) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException("DB exec error: $err");
    }
    $stmt->close();
}

function resolve_unit_id(mysqli $db, string $unit): ?int
{
    $unit = trim($unit);
    if ($unit === '') {
        return null;
    }
    // Try match by unit_code first, then by unit_name.
    $row = db_one_assoc($db, "SELECT unit_type_id FROM tblware_unit_type WHERE unit_code = ? LIMIT 1", [$unit]);
    if ($row) {
        return (int) $row['unit_type_id'];
    }
    $row = db_one_assoc($db, "SELECT unit_type_id FROM tblware_unit_type WHERE unit_name = ? LIMIT 1", [$unit]);
    if ($row) {
        return (int) $row['unit_type_id'];
    }
    return null;
}

// Map custom field names -> ids (fieldto='items', values stored into items_pr)
$cfNames = ['proveedor', 'modulo', 'maduracion', 'stock_seguridad'];
$cfIds = [];
foreach ($cfNames as $n) {
    $row = db_one_assoc($db, "SELECT id FROM tblcustomfields WHERE name = ? AND fieldto = 'items' LIMIT 1", [$n]);
    if ($row) {
        $cfIds[$n] = (int) $row['id'];
    }
}

$xlsx = new XLSXReader_fin($xlsxPath);
$sheetNames = $xlsx->getSheetNames();
$sheetName = $sheetNames[1] ?? ($sheetNames[0] ?? null);
if (!$sheetName) {
    fwrite(STDERR, "No sheets found.\n");
    exit(1);
}
$data = $xlsx->getSheetData($sheetName);
if (!is_array($data) || count($data) < 2) {
    fwrite(STDERR, "No rows found.\n");
    exit(1);
}

// Build header map
$headerMap = [];
foreach (($data[0] ?? []) as $i => $h) {
    $key = norm_key($h);
    if ($key !== '') {
        $headerMap[$key] = $i;
    }
}

$get = function(int $r, string $key) use ($data, $headerMap) {
    if (!isset($headerMap[$key])) {
        return '';
    }
    return $data[$r][$headerMap[$key]] ?? '';
};

$total = 0;
$ok = 0;
$err = 0;
$inserted = 0;
$updated = 0;
$errors = [];
$seenCodes = [];
$generatedCodes = [];

function generate_unique_5_digit_code(mysqli $db, array &$generatedCodes): string
{
    // 5-digit numeric code, avoid collisions with DB and this import run.
    for ($i = 0; $i < 5000; $i++) {
        $code = (string) random_int(10000, 99999);
        if (isset($generatedCodes[$code])) {
            continue;
        }
        $row = db_one_assoc($db, "SELECT id FROM tblitems WHERE commodity_code = ? LIMIT 1", [$code]);
        if ($row) {
            continue;
        }
        $generatedCodes[$code] = 1;
        return $code;
    }
    throw new RuntimeException('Unable to generate unique 5-digit commodity_code');
}

for ($r = 1; $r < count($data); $r++) {
    $total++;

    $commodity_code = norm($get($r, 'commodity_code'));
    $commodity_name = norm($get($r, 'commodity_name'));
    $desc = norm($get($r, 'description'));
    $long_description = norm($get($r, 'long_description'));

    if ($commodity_name === '') {
        $err++;
        $errors[] = ['row' => $r + 1, 'commodity_code' => $commodity_code, 'error' => 'Missing commodity_name'];
        continue;
    }

    $clave_sat = norm($get($r, 'clave_sat'));
    $group_id = to_int($get($r, 'group_id'), 0);
    $unit = norm($get($r, 'unit'));
    $unit_id = resolve_unit_id($db, $unit);
    if ($unit_id === null && $unit !== '') {
        // unit missing in master table, reject (no-skip: goes to error report)
        $err++;
        $errors[] = ['row' => $r + 1, 'commodity_code' => $commodity_code, 'error' => "Unknown unit '$unit'"];
        continue;
    }

    $warehouse_id = to_int($get($r, 'warehouse_id'), null);
    $active = to_int($get($r, 'active'), 1);
    $without_checking_warehouse = to_int($get($r, 'without_checking_warehouse'), 0);

    $can_be_sold = norm($get($r, 'can_be_sold'));
    $can_be_inventory = norm($get($r, 'can_be_inventory'));
    $can_be_purchased = norm($get($r, 'can_be_purchased'));

    $commodity_barcode = norm($get($r, 'commodity_barcode'));
    $sku_code = norm($get($r, 'sku_code'));
    $sku_name = norm($get($r, 'sku_name'));

    $purchase_price = to_decimal($get($r, 'purchase_price'), 0.0);
    $rate = to_decimal($get($r, 'rate'), 0.0);

    // If commodity_code is missing OR duplicated in the XLSX, generate a new unique 5-digit code.
    $seenCodes[$commodity_code] = ($seenCodes[$commodity_code] ?? 0) + 1;
    $isDuplicateInFile = ($commodity_code !== '' && $seenCodes[$commodity_code] > 1);
    $isMissingCode = ($commodity_code === '');

    if ($isMissingCode || $isDuplicateInFile) {
        $original = $commodity_code;
        $commodity_code = generate_unique_5_digit_code($db, $generatedCodes);
        // Preserve original commodity_code in clave_sat if empty.
        if ($clave_sat === '' && $original !== '') {
            $clave_sat = $original;
        }
    }

    // For duplicates, always INSERT a new item row (do not upsert).
    $existing = null;
    if (!$isDuplicateInFile && !$isMissingCode) {
        $existing = db_one_assoc($db, "SELECT id FROM tblitems WHERE commodity_code = ? LIMIT 1", [$commodity_code]);
    }

    if ($existing) {
        $item_id = (int) $existing['id'];
        db_exec(
            $db,
            "UPDATE tblitems
             SET commodity_name = ?, description = ?, long_description = ?, clave_sat = ?,
                 group_id = ?, unit = ?, unit_id = ?, warehouse_id = ?,
                 commodity_barcode = ?, sku_code = ?, sku_name = ?,
                 purchase_price = ?, rate = ?, active = ?, without_checking_warehouse = ?,
                 can_be_sold = ?, can_be_inventory = ?, can_be_purchased = ?
             WHERE id = ?",
            [
                $commodity_name,
                $desc !== '' ? $desc : $commodity_name,
                $long_description !== '' ? $long_description : $commodity_name,
                $clave_sat,
                $group_id,
                $unit,
                (int) ($unit_id ?? 0),
                (int) ($warehouse_id ?? 0),
                $commodity_barcode !== '' ? $commodity_barcode : $commodity_code,
                $sku_code !== '' ? $sku_code : $commodity_code,
                $sku_name !== '' ? $sku_name : $commodity_code,
                $purchase_price,
                $rate,
                $active,
                $without_checking_warehouse,
                $can_be_sold !== '' ? $can_be_sold : 'can_be_sold',
                $can_be_inventory !== '' ? $can_be_inventory : 'can_be_inventory',
                $can_be_purchased !== '' ? $can_be_purchased : 'can_be_purchased',
                $item_id,
            ]
        );
        $updated++;
    } else {
        // Ensure barcode + sku are unique-ish; default to the chosen commodity_code.
        if ($commodity_barcode === '') {
            $commodity_barcode = $commodity_code;
        }
        if ($sku_code === '') {
            $sku_code = $commodity_code;
        }
        if ($sku_name === '') {
            $sku_name = $commodity_code;
        }

        db_exec(
            $db,
            "INSERT INTO tblitems
             (commodity_name, description, long_description, clave_sat, group_id, unit, unit_id, warehouse_id,
              commodity_code, commodity_barcode, sku_code, sku_name, purchase_price, rate, tax, tax2, active,
              without_checking_warehouse, can_be_sold, can_be_inventory, can_be_purchased)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $commodity_name,
                $desc !== '' ? $desc : $commodity_name,
                $long_description !== '' ? $long_description : $commodity_name,
                $clave_sat,
                $group_id,
                $unit,
                (int) ($unit_id ?? 0),
                (int) ($warehouse_id ?? 0),
                $commodity_code,
                $commodity_barcode !== '' ? $commodity_barcode : $commodity_code,
                $sku_code !== '' ? $sku_code : $commodity_code,
                $sku_name !== '' ? $sku_name : $commodity_code,
                $purchase_price,
                $rate,
                0,
                0,
                $active,
                $without_checking_warehouse,
                $can_be_sold !== '' ? $can_be_sold : 'can_be_sold',
                $can_be_inventory !== '' ? $can_be_inventory : 'can_be_inventory',
                $can_be_purchased !== '' ? $can_be_purchased : 'can_be_purchased',
            ]
        );
        $item_id = (int) $db->insert_id;
        $inserted++;
    }

    // Custom fields upsert
    $cfVals = [
        'proveedor' => norm($get($r, 'proveedor')),
        'modulo' => norm($get($r, 'modulo')),
        'maduracion' => norm($get($r, 'maduracion')),
        'stock_seguridad' => norm($get($r, 'stock_seguridad')),
    ];

    foreach ($cfVals as $name => $val) {
        if ($val === '' || !isset($cfIds[$name])) {
            continue;
        }
        $fieldId = $cfIds[$name];
        $existingCf = db_one_assoc(
            $db,
            "SELECT id FROM tblcustomfieldsvalues WHERE relid = ? AND fieldid = ? AND fieldto = 'items_pr' LIMIT 1",
            [$item_id, $fieldId]
        );
        if ($existingCf) {
            db_exec($db, "UPDATE tblcustomfieldsvalues SET value = ? WHERE id = ?", [$val, (int) $existingCf['id']]);
        } else {
            db_exec(
                $db,
                "INSERT INTO tblcustomfieldsvalues (relid, fieldid, fieldto, value) VALUES (?,?, 'items_pr', ?)",
                [$item_id, $fieldId, $val]
            );
        }
    }

    $ok++;
}

echo "Imported rows: $ok\n";
echo "Errors: $err\n";
echo "Inserted: $inserted\n";
echo "Updated: $updated\n";
echo "Total processed: $total\n";

if ($err > 0) {
    $out = __DIR__ . '/import_purchase_items_errors_' . date('Ymd_His') . '.csv';
    $fp = fopen($out, 'w');
    fputcsv($fp, ['row', 'commodity_code', 'error']);
    foreach ($errors as $e) {
        fputcsv($fp, [$e['row'], $e['commodity_code'], $e['error']]);
    }
    fclose($fp);
    echo "Error report: $out\n";
}

