<?php
/**
 * Inspect an XLSX file (headers + sample rows) using Purchase module XLSXReader.
 *
 * Usage:
 *   php scripts/import/inspect_xlsx.php "scripts/import/file.xlsx" [sheetIndex]
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run via CLI.\n");
    exit(1);
}

$file = $argv[1] ?? '';
$sheetIndex = isset($argv[2]) ? (int) $argv[2] : null;

if ($file === '' || !file_exists($file)) {
    fwrite(STDERR, "File not found. Usage: php scripts/import/inspect_xlsx.php \"path.xlsx\" [sheetIndex]\n");
    exit(1);
}

require_once __DIR__ . '/../../modules/purchase/assets/plugins/XLSXReader/XLSXReader.php';

$xlsx = new XLSXReader_fin($file);
$sheetNames = $xlsx->getSheetNames();

echo "Sheets:\n";
foreach ($sheetNames as $i => $name) {
    echo "  [$i] $name\n";
}

if ($sheetIndex === null) {
    // Default to the first non-empty sheet (or sheet 0).
    $sheetIndex = 0;
    foreach ($sheetNames as $i => $name) {
        $data = $xlsx->getSheetData($name);
        if (is_array($data) && count($data) > 1) {
            $sheetIndex = $i;
            break;
        }
    }
}

if (!isset($sheetNames[$sheetIndex])) {
    fwrite(STDERR, "Invalid sheetIndex $sheetIndex\n");
    exit(1);
}

$sheetName = $sheetNames[$sheetIndex];
$data = $xlsx->getSheetData($sheetName);

echo "\nUsing sheet [$sheetIndex] $sheetName\n";
echo "Total rows: " . (is_array($data) ? count($data) : 0) . "\n";

if (!is_array($data) || count($data) === 0) {
    echo "No data.\n";
    exit(0);
}

$header = $data[0];
echo "\nHeaders (" . count($header) . "):\n";
foreach ($header as $idx => $h) {
    $h = is_string($h) ? trim($h) : $h;
    echo "  [$idx] " . (string) $h . "\n";
}

echo "\nSample rows (up to 5):\n";
for ($r = 1; $r < min(count($data), 6); $r++) {
    echo "Row $r:\n";
    foreach ($header as $c => $h) {
        $key = is_string($h) ? trim($h) : (string) $h;
        $val = $data[$r][$c] ?? '';
        if (is_string($val)) {
            $val = trim($val);
        }
        echo "  - $key: " . (string) $val . "\n";
    }
}

