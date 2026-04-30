'use strict';

/**
 * Reads the client's "commodities-without-images" XLSX, compares IMAGE_FILE
 * to LISTA FOTOS SOFTWARE-20260423T081018Z-3-001.zip (including nested zip),
 * checks DB + disk for current image state, and writes column "Reason":
 *   missing image | file not found | add
 *
 * Input (default paths, override with env):
 *   CLIENT_XLSX=/path/to/commodities-without-images\ (2).xlsx
 *   CLIENT_ZIP=/path/to/LISTA FOTOS SOFTWARE-20260423T081018Z-3-001.zip
 *   OUT_XLSX=/path/to/output.xlsx
 */

const fs = require('fs');
const path = require('path');
const AdmZip = require('adm-zip');
const XLSX = require('xlsx');
const mysql = require('mysql2/promise');

const ROOT = path.resolve(__dirname, '..');

const CLIENT_XLSX =
  process.env.CLIENT_XLSX ||
  '/home/usama-skakeel/Downloads/commodities-without-images (2).xlsx';
const CLIENT_ZIP =
  process.env.CLIENT_ZIP ||
  '/home/usama-skakeel/Downloads/LISTA FOTOS SOFTWARE-20260423T081018Z-3-001.zip';
const OUT_XLSX =
  process.env.OUT_XLSX ||
  '/home/usama-skakeel/Downloads/commodities-without-images (2) - with-reasons.xlsx';

const DB_CONFIG = {
  host: '127.0.0.1',
  user: 'root',
  password: '123456',
  database: 'ranos-php01',
  charset: 'utf8mb4',
};

function normalise(str) {
  return String(str || '')
    .replace(/\s+/g, ' ')
    .trim()
    .toUpperCase();
}

/** Build sets of image basenames (no extension) from outer + nested ZIP. */
function buildZipNameIndex(zipPath) {
  const exact = new Set();
  const norm = new Set();

  const addFileName = (fn) => {
    if (!/\.(png|jpg|jpeg|gif|webp)$/i.test(fn)) return;
    const base = fn.replace(/\.(png|jpg|jpeg|gif|webp)$/i, '');
    exact.add(base.toUpperCase().trim());
    norm.add(normalise(base));
  };

  const outer = new AdmZip(zipPath);
  const nestedName = 'LISTA FOTOS SOFTWARE/PARTE 5 (1).zip';

  for (const entry of outer.getEntries()) {
    if (entry.isDirectory) continue;
    const name = entry.entryName;
    if (name === nestedName) {
      const inner = new AdmZip(entry.getData());
      for (const e2 of inner.getEntries()) {
        if (e2.isDirectory) continue;
        addFileName(e2.name);
      }
      continue;
    }
    if (/\.(png|jpg|jpeg|gif|webp)$/i.test(name) && !name.toLowerCase().endsWith('.zip')) {
      addFileName(path.basename(name));
    }
  }

  return { exact, norm };
}

function zipMatch(imageRef, exact, norm) {
  const ref = String(imageRef || '').trim();
  if (!ref) return { matched: false, how: null };
  const u = ref.toUpperCase().trim();
  if (exact.has(u)) return { matched: true, how: 'exact name (case-insensitive)' };
  const n = normalise(ref);
  if (norm.has(n)) return { matched: true, how: 'normalized whitespace match' };
  return { matched: false, how: null };
}

function physicalExists(relId, fileName) {
  const rel = String(relId);
  const candidates = [
    path.join(ROOT, 'modules/warehouse/uploads/item_img', rel, fileName),
    path.join(ROOT, 'modules/warehouse/uploads/item_img_2', rel, fileName),
    path.join(ROOT, 'modules/purchase/uploads/item_img', rel, fileName),
    path.join(ROOT, 'modules/manufacturing/uploads/products', rel, fileName),
  ];
  for (const p of candidates) {
    if (fs.existsSync(p)) return p;
  }
  return null;
}

async function loadLatestFilesByItemId(db, itemIds) {
  if (!itemIds.length) return new Map();
  const placeholders = itemIds.map(() => '?').join(',');
  const [rows] = await db.query(
    `SELECT rel_id, file_name, dateadded
     FROM tblfiles
     WHERE rel_type = 'commodity_item_file' AND rel_id IN (${placeholders})
     ORDER BY rel_id, dateadded DESC`,
    itemIds
  );
  const map = new Map();
  for (const r of rows) {
    if (!map.has(r.rel_id)) map.set(r.rel_id, r);
  }
  return map;
}

/** Short labels for the Reason column (keep simple). */
function buildReason({ imageRef, zipMatchResult, itemId, latestRow }) {
  const ref = String(imageRef || '').trim();

  // No filename in sheet → cannot look up in ZIP
  if (!ref) {
    return 'missing image';
  }

  // ZIP has no file matching IMAGE_FILE
  if (!zipMatchResult.matched) {
    return 'file not found';
  }

  // Sheet matches ZIP, but app has no usable file on disk (no DB row, or row points to missing file)
  if (!latestRow || !physicalExists(itemId, latestRow.file_name)) {
    return 'file not found';
  }

  return 'add';
}

async function main() {
  if (!fs.existsSync(CLIENT_XLSX)) {
    console.error('Missing XLSX:', CLIENT_XLSX);
    process.exit(1);
  }
  if (!fs.existsSync(CLIENT_ZIP)) {
    console.error('Missing ZIP:', CLIENT_ZIP);
    process.exit(1);
  }

  console.log('Loading ZIP index…');
  const { exact, norm } = buildZipNameIndex(CLIENT_ZIP);
  console.log('  ZIP unique name keys (exact):', exact.size);

  const wb = XLSX.readFile(CLIENT_XLSX);
  const sheetName = wb.SheetNames[0];
  const ws = wb.Sheets[sheetName];
  const rows = XLSX.utils.sheet_to_json(ws, { defval: '' });

  const itemIds = rows
    .map((r) => parseInt(r['Item ID'], 10))
    .filter((id) => !Number.isNaN(id));

  console.log('Loading DB latest attachments for', itemIds.length, 'items…');
  const db = await mysql.createConnection(DB_CONFIG);
  const latestById = await loadLatestFilesByItemId(db, itemIds);
  await db.end();

  const outRows = rows.map((r) => {
    const itemId = parseInt(r['Item ID'], 10);
    const imageRef = r['IMAGE_FILE'];
    const zm = zipMatch(imageRef, exact, norm);
    const latest = Number.isNaN(itemId) ? null : latestById.get(itemId) || null;
    const reason = buildReason({
      imageRef,
      zipMatchResult: zm,
      itemId,
      latestRow: latest,
    });
    return { ...r, Reason: reason };
  });

  const outWs = XLSX.utils.json_to_sheet(outRows);
  const outWb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(outWb, outWs, sheetName);
  XLSX.writeFile(outWb, OUT_XLSX);

  console.log('Wrote:', OUT_XLSX);

  const counts = { 'missing image': 0, 'file not found': 0, add: 0 };
  for (const r of outRows) {
    counts[r.Reason] = (counts[r.Reason] || 0) + 1;
  }

  console.log('\nSummary:');
  console.log(counts);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
