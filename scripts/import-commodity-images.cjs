'use strict';

/**
 * import-commodity-images.cjs
 *
 * Reads the XLSX file that lists items without images, extracts images from the
 * provided ZIP, and for each item that still has no image:
 *   1. Copies the matching image to modules/warehouse/uploads/item_img/{item_id}/
 *   2. Inserts a record into tblfiles (rel_type = 'commodity_item_file')
 *
 * Items that already have a tblfiles entry are skipped (never touched).
 * Generates exports/image-import-report.json with full results.
 */

const path    = require('path');
const fs      = require('fs');
const crypto  = require('crypto');
const AdmZip  = require('adm-zip');
const XLSX    = require('xlsx');
const mysql   = require('mysql2/promise');

// ── Paths ────────────────────────────────────────────────────────────────────
const ROOT         = path.resolve(__dirname, '..');
const ZIP_PATH     = '/home/usama-skakeel/Downloads/LISTA FOTOS SOFTWARE-20260423T081018Z-3-001.zip';
const XLSX_PATH    = '/home/usama-skakeel/Downloads/commodities-without-images (1).xlsx';
const EXTRACT_DIR  = '/tmp/commodity_images';
const UPLOAD_BASE  = path.join(ROOT, 'modules/warehouse/uploads/item_img');
const REPORT_PATH  = path.join(ROOT, 'exports/image-import-report.json');

// ── DB config (matches app-config.php local defaults) ────────────────────────
const DB_CONFIG = {
  host:     '127.0.0.1',
  user:     'root',
  password: '123456',
  database: 'ranos-php01',
  charset:  'utf8mb4',
};

const STAFF_ID = 4; // developer@3ware.mx

// ── Helpers ──────────────────────────────────────────────────────────────────

/** Collapse any run of whitespace to a single space, trim, uppercase. */
function normalise(str) {
  return String(str || '').replace(/\s+/g, ' ').trim().toUpperCase();
}

/** Generate a 32-char hex key (mirrors app_generate_hash in Perfex). */
function generateKey() {
  return crypto.randomBytes(16).toString('hex');
}

/**
 * Extract all images from the outer ZIP and its one nested ZIP into EXTRACT_DIR.
 * Returns a Map:  NORMALISED_NAME  →  absolute_path_on_disk
 */
function extractAndBuildLookup() {
  console.log('[1/4] Extracting images from ZIP …');

  if (fs.existsSync(EXTRACT_DIR)) {
    fs.rmSync(EXTRACT_DIR, { recursive: true });
  }
  fs.mkdirSync(EXTRACT_DIR, { recursive: true });

  const outer = new AdmZip(ZIP_PATH);
  const outerEntries = outer.getEntries();

  const nestedZipName = 'LISTA FOTOS SOFTWARE/PARTE 5 (1).zip';
  let nestedZipBuffer = null;

  // Extract all direct images; hold on to the nested zip buffer
  for (const entry of outerEntries) {
    if (entry.isDirectory) continue;
    const name = entry.entryName;

    if (name === nestedZipName) {
      nestedZipBuffer = entry.getData();
      continue;
    }

    if (/\.(png|jpg|jpeg|gif|webp)$/i.test(name)) {
      const basename = path.basename(name);
      const dest     = path.join(EXTRACT_DIR, basename);
      // Keep the first occurrence if filenames clash (shouldn't happen)
      if (!fs.existsSync(dest)) {
        fs.writeFileSync(dest, entry.getData());
      }
    }
  }

  // Extract nested zip images
  if (nestedZipBuffer) {
    const inner = new AdmZip(nestedZipBuffer);
    for (const entry of inner.getEntries()) {
      if (entry.isDirectory) continue;
      if (/\.(png|jpg|jpeg|gif|webp)$/i.test(entry.name)) {
        const dest = path.join(EXTRACT_DIR, entry.name);
        if (!fs.existsSync(dest)) {
          fs.writeFileSync(dest, entry.getData());
        }
      }
    }
  }

  // Build two-tier lookup map:
  //   tier-1 key: exact uppercase name (no extension)
  //   tier-2 key: whitespace-normalised uppercase name (no extension)
  // Both map to absolute disk path.
  const exactMap     = new Map(); // UPPER_NAME → path
  const normalisedMap = new Map(); // NORM_UPPER_NAME → path

  for (const file of fs.readdirSync(EXTRACT_DIR)) {
    const fullPath = path.join(EXTRACT_DIR, file);
    if (!fs.statSync(fullPath).isFile()) continue;

    const noExt  = file.replace(/\.(png|jpg|jpeg|gif|webp)$/i, '');
    const upper  = noExt.toUpperCase().trim();
    const normed = normalise(noExt);

    if (!exactMap.has(upper)) {
      exactMap.set(upper, fullPath);
    }
    if (!normalisedMap.has(normed)) {
      normalisedMap.set(normed, fullPath);
    }
  }

  const totalImages = fs.readdirSync(EXTRACT_DIR).length;
  console.log(`    Extracted ${totalImages} images. Lookup map ready.`);
  return { exactMap, normalisedMap };
}

/** Resolve the best matching image path for a given IMAGE_FILE reference. */
function resolveImage(ref, exactMap, normalisedMap) {
  if (!ref || !ref.trim()) return null;

  const upper  = ref.toUpperCase().trim();
  const normed = normalise(ref);

  if (exactMap.has(upper))      return { filePath: exactMap.get(upper), matchType: 'exact' };
  if (normalisedMap.has(normed)) return { filePath: normalisedMap.get(normed), matchType: 'whitespace_normalised' };
  return null;
}

// ── Main ─────────────────────────────────────────────────────────────────────

async function main() {
  // ── Step 1: Extract ZIP and build lookup ─────────────────────────────────
  const { exactMap, normalisedMap } = extractAndBuildLookup();

  // ── Step 2: Read XLSX ─────────────────────────────────────────────────────
  console.log('[2/4] Reading XLSX …');
  const wb   = XLSX.readFile(XLSX_PATH);
  const rows = XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]], { defval: '' });
  console.log(`    ${rows.length} rows loaded.`);

  // ── Step 3: Query DB for items that already have images ───────────────────
  console.log('[3/4] Querying database for existing image records …');
  const db = await mysql.createConnection(DB_CONFIG);

  const [existingRows] = await db.query(
    "SELECT DISTINCT rel_id FROM tblfiles WHERE rel_type = 'commodity_item_file'"
  );
  const existingIds = new Set(existingRows.map(r => Number(r.rel_id)));
  console.log(`    ${existingIds.size} items already have image records in tblfiles.`);

  // ── Step 4: Process each XLSX row ─────────────────────────────────────────
  console.log('[4/4] Processing items …');

  const report = {
    success:                  [],
    skipped_already_has_image: [],
    no_ref:                   [],
    unmatched:                [],
  };

  const now = new Date().toISOString().slice(0, 19).replace('T', ' ');

  for (const row of rows) {
    const itemId   = parseInt(row['Item ID'], 10);
    const imageRef = String(row['IMAGE_FILE'] || '').trim();
    const itemCode = String(row['Commodity code'] || '').trim();
    const itemName = String(row['Commodity name'] || '').trim();

    if (!itemId) continue;

    // Guard: already has image
    if (existingIds.has(itemId)) {
      report.skipped_already_has_image.push({ itemId, itemCode, itemName, imageRef });
      continue;
    }

    // No IMAGE_FILE reference provided
    if (!imageRef) {
      report.no_ref.push({ itemId, itemCode, itemName });
      continue;
    }

    // Try to find matching image in zip
    const match = resolveImage(imageRef, exactMap, normalisedMap);

    if (!match) {
      report.unmatched.push({ itemId, itemCode, itemName, imageRef });
      continue;
    }

    const srcPath  = match.filePath;
    const fileName = path.basename(srcPath); // keep original filename incl. extension
    const destDir  = path.join(UPLOAD_BASE, String(itemId));
    const destPath = path.join(destDir, fileName);

    // Create destination directory if needed
    fs.mkdirSync(destDir, { recursive: true });

    // Copy image file
    fs.copyFileSync(srcPath, destPath);

    // Insert into tblfiles
    await db.execute(
      `INSERT INTO tblfiles
         (rel_id, rel_type, file_name, filetype, visible_to_customer,
          attachment_key, staffid, contact_id, task_comment_id, dateadded)
       VALUES (?, 'commodity_item_file', ?, 'image/png', 0, ?, ?, 0, 0, ?)`,
      [itemId, fileName, generateKey(), STAFF_ID, now]
    );

    report.success.push({
      itemId,
      itemCode,
      itemName,
      imageRef,
      fileName,
      matchType: match.matchType,
    });

    process.stdout.write(`    ✓ [${itemId}] ${itemName} → ${fileName} (${match.matchType})\n`);
  }

  await db.end();

  // ── Write report ──────────────────────────────────────────────────────────
  fs.mkdirSync(path.dirname(REPORT_PATH), { recursive: true });
  fs.writeFileSync(REPORT_PATH, JSON.stringify(report, null, 2));

  console.log('\n── Summary ──────────────────────────────────────────────────');
  console.log(`  Imported successfully    : ${report.success.length}`);
  console.log(`  Skipped (had image)      : ${report.skipped_already_has_image.length}`);
  console.log(`  Skipped (no IMAGE_FILE)  : ${report.no_ref.length}`);
  console.log(`  Unmatched (no zip file)  : ${report.unmatched.length}`);
  console.log(`\n  Report saved to: ${REPORT_PATH}`);
}

main().catch(err => {
  console.error('Fatal error:', err);
  process.exit(1);
});
