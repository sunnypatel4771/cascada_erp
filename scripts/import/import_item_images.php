<?php
/**
 * Import item images from extracted folder into Purchase item images storage.
 *
 * - Copies images into `modules/purchase/uploads/item_img/<item_id>/<file_name>`
 * - Creates `tblfiles` rows with `rel_type='commodity_item_file'`
 *
 * Mapping:
 * - Matches image basename (without extension) to `tblitems.commodity_name` and `tblitems.description`
 *   using a normalization function (uppercase, remove accents, remove punctuation, collapse spaces).
 *
 * Usage:
 *   php scripts/import/import_item_images.php "scripts/import/item_images_src/LISTA FOTOS SOFTWARE"
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$srcDir = $argv[1] ?? '';
if ($srcDir === '' || !is_dir($srcDir)) {
    fwrite(STDERR, "Source dir not found. Usage: php scripts/import/import_item_images.php \"path/to/images\"\n");
    exit(1);
}

// Perfex/CI configs guard against direct access; define BASEPATH for CLI usage.
if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require_once __DIR__ . '/../../application/config/app-config.php';

$db = new mysqli(APP_DB_HOSTNAME, APP_DB_USERNAME, APP_DB_PASSWORD, APP_DB_NAME);
if ($db->connect_error) {
    fwrite(STDERR, "DB connect error: {$db->connect_error}\n");
    exit(1);
}
$db->set_charset('utf8mb4');

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

function normalize_name(string $s): string
{
    $s = trim($s);
    if ($s === '') {
        return '';
    }
    $s = mb_strtoupper($s);
    // Transliterate accents if possible.
    if (class_exists('Transliterator')) {
        $tr = Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');
        if ($tr) {
            $s = $tr->transliterate($s);
        }
    } else {
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        $s = mb_strtoupper($s);
    }
    // Replace separators with space.
    $s = str_replace(['_', '-', "\t", "\r", "\n"], ' ', $s);
    // Remove most punctuation.
    $s = preg_replace('/[^A-Z0-9 ]+/u', ' ', $s) ?? $s;
    // Collapse whitespace.
    $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
    return trim($s);
}

function normalize_tokens(string $s): array
{
    $s = normalize_name($s);
    if ($s === '') {
        return [];
    }
    $parts = explode(' ', $s);
    $out = [];
    $stop = ['DE', 'DEL', 'LA', 'EL', 'Y', 'A', 'AL'];
    $map = [
        'LT' => 'LITRO',
        'LTS' => 'LITRO',
        'L' => 'LITRO',
        'LTR' => 'LITRO',
        'LTRS' => 'LITRO',
        'CJ' => 'CAJA',
        'CAJ' => 'CAJA',
        'ARP' => 'ARPILLA',
        'ARPI' => 'ARPILLA',
        'PQ' => 'PAQUETE',
        'PQTE' => 'PAQUETE',
        'PQT' => 'PAQUETE',
        'P' => 'PAQUETE',
        'GDE' => 'GRANDE',
        'CH' => 'CHICO',
        'MED' => 'MEDIANO',
    ];
    foreach ($parts as $p) {
        if ($p === '' || in_array($p, $stop, true)) {
            continue;
        }
        // Drop pure numbers
        if (preg_match('/^[0-9]+$/', $p)) {
            continue;
        }
        // Normalize fractions like 1/4, 1_4 already removed to spaces; skip lone small numbers like 1, 2
        if (preg_match('/^[0-9]+(\\.[0-9]+)?$/', $p)) {
            continue;
        }
        $p = $map[$p] ?? $p;
        $out[$p] = 1;
    }
    return array_keys($out);
}

function jaccard(array $a, array $b): float
{
    if (empty($a) || empty($b)) {
        return 0.0;
    }
    $sa = array_fill_keys($a, true);
    $sb = array_fill_keys($b, true);
    $inter = 0;
    foreach ($sa as $k => $_) {
        if (isset($sb[$k])) {
            $inter++;
        }
    }
    $union = count($sa) + count($sb) - $inter;
    return $union > 0 ? ($inter / $union) : 0.0;
}

function safe_filename(string $base, string $ext): string
{
    $base = trim($base);
    $base = preg_replace('/\s+/u', ' ', $base) ?? $base;
    $base = preg_replace('/[^A-Za-z0-9 _.-]+/u', '', $base) ?? $base;
    $base = trim($base);
    if ($base === '') {
        $base = 'item';
    }
    return $base . '.' . $ext;
}

// Determine staffid to associate with file records (pick first staff id, else 1).
$staffRow = db_one_assoc($db, "SELECT staffid FROM tblstaff ORDER BY staffid ASC LIMIT 1", []);
$staffId = $staffRow ? (int) $staffRow['staffid'] : 1;

// Build normalized name -> item ids map from tblitems.
$nameToIds = [];
$idToTokens = [];
$tokenToIds = [];
$res = $db->query("SELECT id, commodity_name, description FROM tblitems");
while ($row = $res->fetch_assoc()) {
    $id = (int) $row['id'];
    $n1 = normalize_name((string) ($row['commodity_name'] ?? ''));
    $n2 = normalize_name((string) ($row['description'] ?? ''));
    foreach ([$n1, $n2] as $n) {
        if ($n === '') {
            continue;
        }
        if (!isset($nameToIds[$n])) {
            $nameToIds[$n] = [];
        }
        $nameToIds[$n][$id] = 1;
    }

    // Token index for fuzzy matching
    $tokens = normalize_tokens((string) ($row['commodity_name'] ?? ''));
    if (empty($tokens)) {
        $tokens = normalize_tokens((string) ($row['description'] ?? ''));
    }
    $idToTokens[$id] = $tokens;
    foreach ($tokens as $t) {
        $tokenToIds[$t][$id] = 1;
    }
}
$res->free();

$images = [];
$it = new DirectoryIterator($srcDir);
foreach ($it as $f) {
    if ($f->isDot() || !$f->isFile()) {
        continue;
    }
    $ext = strtolower($f->getExtension());
    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
        continue;
    }
    $images[] = $f->getPathname();
}

sort($images);

$totalImages = count($images);
$matchedImages = 0;
$unmatchedImages = 0;
$attachedRecords = 0;
$skippedExisting = 0;
$errors = [];
$fuzzyMatchedImages = 0;
$fuzzyLowConfidence = 0;
$fuzzyReport = [];

$uploadRoot = __DIR__ . '/../../modules/purchase/uploads/item_img';
if (!is_dir($uploadRoot)) {
    // Should exist, but create if missing.
    mkdir($uploadRoot, 0775, true);
}

foreach ($images as $path) {
    $base = pathinfo($path, PATHINFO_FILENAME);
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $norm = normalize_name($base);

    $ids = [];
    $matchType = 'exact';
    $score = 1.0;

    if ($norm !== '' && isset($nameToIds[$norm])) {
        $matchedImages++;
        $ids = array_keys($nameToIds[$norm]);
    } else {
        // Fuzzy match by token similarity
        $imgTokens = normalize_tokens($base);
        // Special-case: single-token names (e.g. "ZARZAMORA") should attach to all items containing that token,
        // as long as the candidate set is small (to avoid blasting a generic image everywhere).
        if (count($imgTokens) === 1) {
            $t = $imgTokens[0];
            $candIds = isset($tokenToIds[$t]) ? array_keys($tokenToIds[$t]) : [];
            if (count($candIds) > 0 && count($candIds) <= 6) {
                $matchType = 'fuzzy';
                $score = 0.65;
                $fuzzyMatchedImages++;
                $ids = $candIds;
            }
        }

        if (!empty($ids)) {
            // already matched via single-token rule
        } else {
        $candidates = [];
        foreach ($imgTokens as $t) {
            if (!isset($tokenToIds[$t])) {
                continue;
            }
            foreach ($tokenToIds[$t] as $id => $_) {
                $candidates[$id] = 1;
            }
        }

        $bestId = null;
        $bestScore = 0.0;
        foreach ($candidates as $cid => $_) {
            $s = jaccard($imgTokens, $idToTokens[(int) $cid] ?? []);
            if ($s > $bestScore) {
                $bestScore = $s;
                $bestId = (int) $cid;
            }
        }

        if ($bestId !== null && $bestScore >= 0.55) {
            $matchType = 'fuzzy';
            $score = $bestScore;
            $fuzzyMatchedImages++;
            $ids = [$bestId];
        } else {
            $unmatchedImages++;
            $errors[] = ['file' => basename($path), 'reason' => 'No matching item by name/description'];
            continue;
        }

        // Track low-confidence fuzzy matches for review
        if ($matchType === 'fuzzy' && $score < 0.70) {
            $fuzzyLowConfidence++;
            $fuzzyReport[] = ['file' => basename($path), 'matched_item_id' => (string) $bestId, 'score' => (string) $score];
        }
        }
    }

    // Use a stable filename in destination (keep original base as much as possible).
    $destFile = safe_filename($base, $ext);
    $filetype = $ext === 'png' ? 'image/png' : ($ext === 'jpg' || $ext === 'jpeg' ? 'image/jpeg' : 'image/' . $ext);

    foreach ($ids as $itemId) {
        $itemDir = $uploadRoot . '/' . $itemId;
        if (!is_dir($itemDir)) {
            mkdir($itemDir, 0775, true);
        }
        $destPath = $itemDir . '/' . $destFile;

        // Check DB for existing attachment with same filename for this item.
        $existing = db_one_assoc(
            $db,
            "SELECT id FROM tblfiles WHERE rel_id = ? AND rel_type = 'commodity_item_file' AND file_name = ? LIMIT 1",
            [(int) $itemId, $destFile]
        );
        if ($existing) {
            $skippedExisting++;
            // Ensure file exists on disk; if not, copy it.
            if (!file_exists($destPath)) {
                copy($path, $destPath);
            }
            continue;
        }

        if (!file_exists($destPath)) {
            copy($path, $destPath);
        }

        $attachmentKey = bin2hex(random_bytes(16)); // 32 chars

        db_exec(
            $db,
            "INSERT INTO tblfiles (rel_id, rel_type, file_name, filetype, visible_to_customer, attachment_key, staffid, contact_id, task_comment_id, dateadded)
             VALUES (?, 'commodity_item_file', ?, ?, 0, ?, ?, 0, 0, NOW())",
            [(int) $itemId, $destFile, $filetype, $attachmentKey, (int) $staffId]
        );
        $attachedRecords++;
    }
}

echo "Total images scanned: $totalImages\n";
echo "Matched images: $matchedImages\n";
echo "Fuzzy matched images: $fuzzyMatchedImages\n";
echo "Fuzzy low-confidence: $fuzzyLowConfidence\n";
echo "Unmatched images: $unmatchedImages\n";
echo "Attachments created: $attachedRecords\n";
echo "Skipped existing attachments: $skippedExisting\n";

if ($unmatchedImages > 0) {
    $out = __DIR__ . '/import_item_images_unmatched_' . date('Ymd_His') . '.csv';
    $fp = fopen($out, 'w');
    fputcsv($fp, ['file', 'reason']);
    foreach ($errors as $e) {
        fputcsv($fp, [$e['file'], $e['reason']]);
    }
    fclose($fp);
    echo "Unmatched report: $out\n";
}

if ($fuzzyLowConfidence > 0) {
    $out = __DIR__ . '/import_item_images_fuzzy_review_' . date('Ymd_His') . '.csv';
    $fp = fopen($out, 'w');
    fputcsv($fp, ['file', 'matched_item_id', 'score']);
    foreach ($fuzzyReport as $e) {
        fputcsv($fp, [$e['file'], $e['matched_item_id'], $e['score']]);
    }
    fclose($fp);
    echo "Fuzzy review report: $out\n";
}

