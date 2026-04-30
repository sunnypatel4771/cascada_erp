/**
 * Import missing custom-field data from the client Excel sheet into the Perfex CRM DB.
 *
 * Fields updated per client (all stored in tblcustomfieldsvalues):
 *   customers_horario        (field id 2)   ← customers_horario
 *   customers_zona           (field id 3)   ← customers_zona
 *   customers_prioridad      (field id 4)   ← customers_prioridad
 *   customers_descuento      (field id 6)   ← customers_descuento
 *   customers_forma_de_pago  (field id 7)   ← customers_forma_de_pago
 *   customers_regimen_fiscal (field id 8)   ← customers_regimen_fiscal
 *   customers_uso_cfdi       (field id 9)   ← customers_uso_cfdi
 *   customers_direccion_fiscal (field id 10) ← billing_address (Dirección Fiscal)
 *   customers_metodo_de_pago (field id 19)  ← customers_metodo_de_pago
 *
 * Clients are matched by company name (case-insensitive, trimmed).
 * For select-type fields, the options column is expanded to include any new values.
 *
 * Usage:
 *   node scripts/import-client-fields.cjs
 *
 * DB credentials are read from application/config/app-config.php defaults.
 */

const fs   = require('fs');
const path = require('path');
const mysql = require('mysql2/promise');
const XLSX = require('xlsx');

// ── DB config (mirrors app-config.php local defaults) ────────────────────────
const DB = {
  host:     process.env.DB_HOST     || '127.0.0.1',
  port:     parseInt(process.env.DB_PORT || '3306', 10),
  user:     process.env.DB_USER     || 'root',
  password: process.env.DB_PASS     || '123456',
  database: process.env.DB_NAME     || 'ranos-php01',
  charset:  'utf8mb4',
};

// ── Custom field IDs (from tblcustomfields) ───────────────────────────────────
const FIELD = {
  horario:        2,
  zona:           3,
  prioridad:      4,
  descuento:      6,
  forma_de_pago:  7,
  regimen_fiscal: 8,
  uso_cfdi:       9,
  direccion_fiscal: 10,
  metodo_de_pago: 19,
};

// ── Mapping: field_id → Excel column ─────────────────────────────────────────
const FIELD_TO_COL = {
  [FIELD.horario]:          'customers_horario',
  [FIELD.zona]:             'customers_zona',
  [FIELD.prioridad]:        'customers_prioridad',
  [FIELD.descuento]:        'customers_descuento',
  [FIELD.forma_de_pago]:    'customers_forma_de_pago',
  [FIELD.regimen_fiscal]:   'customers_regimen_fiscal',
  [FIELD.uso_cfdi]:         'customers_uso_cfdi',
  [FIELD.direccion_fiscal]: 'billing_address',   // Dirección Fiscal comes from billing_address col
  [FIELD.metodo_de_pago]:   'customers_metodo_de_pago',
};

// Normalize company name for fuzzy matching
function normalize(s) {
  return String(s || '')
    .trim()
    .toUpperCase()
    .replace(/\s+/g, ' ')
    .replace(/[,\.]/g, '');
}

async function main() {
  // 1. Read Excel
  const xlsxPath = '/home/usama-skakeel/Downloads/RAMOS CLIENTES ABRIL 2026 (3).xlsx';
  if (!fs.existsSync(xlsxPath)) {
    console.error('Excel file not found:', xlsxPath);
    process.exit(1);
  }
  const wb   = XLSX.readFile(xlsxPath);
  const rows = XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]], { defval: '' });
  console.log(`Read ${rows.length} rows from Excel.`);

  // 2. Connect to DB
  const conn = await mysql.createConnection(DB);
  console.log('Connected to DB:', DB.database);

  try {
    // 3. Load all clients from DB → map normalized-name → userid
    const [clients] = await conn.query('SELECT userid, company FROM tblclients');
    const nameToId = new Map();
    for (const c of clients) {
      nameToId.set(normalize(c.company), c.userid);
    }
    console.log(`Loaded ${clients.length} clients from DB.`);

    // 4. Expand select-field options to include all values from the sheet
    const [cfRows] = await conn.query(
      'SELECT id, options, type FROM tblcustomfields WHERE id IN (?)',
      [Object.values(FIELD)]
    );
    const cfMap = new Map(cfRows.map((r) => [r.id, r]));

    for (const [fid, col] of Object.entries(FIELD_TO_COL)) {
      const cf = cfMap.get(Number(fid));
      if (!cf || cf.type !== 'select') continue;

      const sheetVals = [
        ...new Set(
          rows
            .map((r) => String(r[col] ?? '').trim())
            .filter((v) => v !== '' && v !== '0')
        ),
      ];

      const existingOpts = String(cf.options || '')
        .split(',')
        .map((o) => o.trim())
        .filter(Boolean);

      const existingSet = new Set(existingOpts.map((o) => o.toLowerCase()));
      const toAdd = sheetVals.filter((v) => !existingSet.has(v.toLowerCase()));

      if (toAdd.length > 0) {
        const newOptions = [...existingOpts, ...toAdd].join(',');
        await conn.query('UPDATE tblcustomfields SET options = ? WHERE id = ?', [newOptions, fid]);
        console.log(`  Field ${fid}: added options [${toAdd.join(', ')}]`);
      }
    }

    // 5. For each Excel row, find the matching DB client and upsert custom field values
    let matched = 0, skipped = 0, inserted = 0, updated = 0;

    for (const row of rows) {
      const key    = normalize(row.company);
      const userid = nameToId.get(key);

      if (!userid) {
        console.warn(`  SKIP (no DB match): "${row.company}"`);
        skipped++;
        continue;
      }
      matched++;

      for (const [fidStr, col] of Object.entries(FIELD_TO_COL)) {
        const fid   = Number(fidStr);
        const value = String(row[col] ?? '').trim();
        if (value === '' || value === '0') continue;

        // Check if a value already exists
        const [[existing]] = await conn.query(
          'SELECT id FROM tblcustomfieldsvalues WHERE relid = ? AND fieldid = ? AND fieldto = ?',
          [userid, fid, 'customers']
        );

        if (existing) {
          await conn.query(
            'UPDATE tblcustomfieldsvalues SET value = ? WHERE relid = ? AND fieldid = ? AND fieldto = ?',
            [value, userid, fid, 'customers']
          );
          updated++;
        } else {
          await conn.query(
            'INSERT INTO tblcustomfieldsvalues (relid, fieldid, fieldto, value) VALUES (?, ?, ?, ?)',
            [userid, fid, 'customers', value]
          );
          inserted++;
        }
      }
    }

    console.log('\n── Summary ──────────────────────────────────');
    console.log(`Excel rows processed : ${rows.length}`);
    console.log(`Matched to DB client : ${matched}`);
    console.log(`Skipped (no match)   : ${skipped}`);
    console.log(`CF values inserted   : ${inserted}`);
    console.log(`CF values updated    : ${updated}`);

    // 6. Quick verification
    const [[{ cnt }]] = await conn.query(
      'SELECT COUNT(*) AS cnt FROM tblcustomfieldsvalues WHERE fieldid IN (?)',
      [Object.values(FIELD)]
    );
    console.log(`Total CF rows in DB  : ${cnt}`);

  } finally {
    await conn.end();
  }
}

main().catch((e) => { console.error(e); process.exit(1); });
