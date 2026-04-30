/**
 * Export items that currently have no usable image in the UI.
 *
 * Rule matches modules/warehouse/views/table_commodity_list.php:
 * - UI reads tblfiles (rel_type = commodity_item_file) ordered by dateadded desc
 * - It displays the FIRST row per item (latest)
 * - It checks file_exists in these locations (in order):
 *   1) modules/warehouse/uploads/item_img/{rel_id}/{file_name}
 *   2) modules/purchase/uploads/item_img/{rel_id}/{file_name}
 *   3) modules/manufacturing/uploads/products/{rel_id}/{file_name}
 * - If none exist, the table shows nul_image placeholder.
 *
 * Output: /var/www/ramos-php/exports/items-without-images-now.xlsx
 */

'use strict';

const fs = require('fs');
const path = require('path');
const XLSX = require('xlsx');
const mysql = require('mysql2/promise');

const ROOT = path.resolve(__dirname, '..');
const OUT_PATH = path.join(ROOT, 'exports/items-without-images-now.xlsx');

const DB_CONFIG = {
  host: '127.0.0.1',
  user: 'root',
  password: '123456',
  database: 'ranos-php01',
  charset: 'utf8mb4',
};

function existingImagePath(relId, fileName) {
  const rel = String(relId);
  const candidates = [
    path.join(ROOT, 'modules/warehouse/uploads/item_img', rel, fileName),
    path.join(ROOT, 'modules/purchase/uploads/item_img', rel, fileName),
    path.join(ROOT, 'modules/manufacturing/uploads/products', rel, fileName),
  ];
  for (const p of candidates) {
    if (fs.existsSync(p)) return p;
  }
  return null;
}

async function main() {
  const db = await mysql.createConnection(DB_CONFIG);

  // Latest attachment per item (same ordering used by UI).
  // Note: if multiple rows share the same dateadded, we still only need one of them.
  const [latestRows] = await db.query(`
    SELECT f.rel_id, f.file_name, f.dateadded
    FROM tblfiles f
    JOIN (
      SELECT rel_id, MAX(dateadded) AS max_date
      FROM tblfiles
      WHERE rel_type = 'commodity_item_file'
      GROUP BY rel_id
    ) m ON m.rel_id = f.rel_id AND m.max_date = f.dateadded
    WHERE f.rel_type = 'commodity_item_file'
  `);

  const latestById = new Map();
  for (const r of latestRows) {
    if (!latestById.has(r.rel_id)) latestById.set(r.rel_id, r);
  }

  const [items] = await db.query(`
    SELECT id, commodity_code, commodity_name, sku_code, group_id, unit_id
    FROM tblitems
    WHERE active = 1
    ORDER BY id
  `);

  const [groups] = await db.query(`SELECT id, name FROM tblitems_groups`);
  const groupNameById = new Map(groups.map((g) => [Number(g.id), g.name]));

  const [units] = await db.query(`SELECT unit_type_id, unit_name FROM tblware_unit_type`);
  const unitNameById = new Map(units.map((u) => [Number(u.unit_type_id), u.unit_name]));

  const without = [];
  const noRecord = [];

  for (const item of items) {
    const latest = latestById.get(item.id);
    if (!latest) {
      noRecord.push({
        itemId: item.id,
        commodityCode: item.commodity_code,
        commodityName: item.commodity_name,
        skuCode: item.sku_code,
        groupName: groupNameById.get(Number(item.group_id)) || '',
        unitName: unitNameById.get(Number(item.unit_id)) || '',
        reason: 'no_tblfiles_record',
      });
      continue;
    }

    const existing = existingImagePath(latest.rel_id, latest.file_name);
    if (!existing) {
      without.push({
        itemId: item.id,
        commodityCode: item.commodity_code,
        commodityName: item.commodity_name,
        skuCode: item.sku_code,
        groupName: groupNameById.get(Number(item.group_id)) || '',
        unitName: unitNameById.get(Number(item.unit_id)) || '',
        latestFileName: latest.file_name,
        latestDateAdded: latest.dateadded,
        reason: 'db_points_to_missing_file',
      });
    }
  }

  await db.end();

  const wb = XLSX.utils.book_new();
  const ws1 = XLSX.utils.json_to_sheet(without);
  XLSX.utils.book_append_sheet(wb, ws1, 'Missing file on disk');

  const ws2 = XLSX.utils.json_to_sheet(noRecord);
  XLSX.utils.book_append_sheet(wb, ws2, 'No tblfiles record');

  fs.mkdirSync(path.dirname(OUT_PATH), { recursive: true });
  XLSX.writeFile(wb, OUT_PATH);

  console.log('Saved:', OUT_PATH);
  console.log('Missing file on disk:', without.length);
  console.log('No tblfiles record:', noRecord.length);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

