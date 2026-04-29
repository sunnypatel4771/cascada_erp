/**
 * Export active warehouse commodities that display without a real image
 * (same rules as modules/warehouse/views/table_commodity_list.php → nul_image.jpg).
 *
 * Usage (headed browser):
 *   HEADED=1 ADMIN_EMAIL='you@example.com' ADMIN_PASSWORD='secret' node scripts/export-commodities-without-images.cjs
 *
 * Defaults:
 *   BASE_URL=http://127.0.0.1:8080
 *   OUT=exports/commodities-without-images.xlsx (default; stable path in repo)
 *
 * Pagination: uses the largest non-"All" page size (usually 100), then clicks Next
 * until disabled so every server-side DataTables page is included (not only the first).
 *
 * Requires: npm install (uses @playwright/test + xlsx from repo root).
 */

const fs = require('fs');
const path = require('path');
const { chromium } = require('@playwright/test');
const XLSX = require('xlsx');

const BASE_URL = (process.env.BASE_URL || 'http://127.0.0.1:8080').replace(/\/$/, '');
const ADMIN_EMAIL = process.env.ADMIN_EMAIL || '';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || '';
const headed =
  process.env.HEADED === '1' ||
  process.env.HEADED === 'true' ||
  process.argv.includes('--headed');

function isPlaceholderImage(src, alt) {
  if (!src) {
    return true;
  }
  const s = String(src).toLowerCase();
  if (s.includes('nul_image.jpg')) {
    return true;
  }
  if (String(alt || '').toLowerCase() === 'nul_image.jpg') {
    return true;
  }
  return false;
}

/** Wait until server-side DataTables finishes the current draw. */
async function waitForDrawComplete(page) {
  await page.waitForFunction(
    () => {
      const wrap = document.querySelector('#table-table_commodity_list_wrapper');
      if (!wrap) {
        return false;
      }
      const proc =
        wrap.querySelector('#table-table_commodity_list_processing') ||
        wrap.querySelector('.dataTables_processing');
      if (!proc) {
        return true;
      }
      const st = window.getComputedStyle(proc);
      return st.display === 'none' || st.visibility === 'hidden' || st.opacity === '0';
    },
    { timeout: 120000 }
  );
  await new Promise((r) => setTimeout(r, 400));
}

/**
 * Pick largest page size (not "All") so each request stays small enough for PHP,
 * then walk every page with Next until disabled.
 */
async function collectAllWithoutImagesAcrossPages(page) {
  const lengthSelect = page.locator('#table-table_commodity_list_wrapper .dataTables_length select');
  await lengthSelect.waitFor({ state: 'visible', timeout: 60000 });
  await waitForDrawComplete(page);

  const bestLen = await lengthSelect.evaluate((sel) => {
    let max = 25;
    for (const opt of sel.querySelectorAll('option')) {
      const v = parseInt(opt.value, 10);
      if (v > 0 && v !== -1 && !Number.isNaN(v)) {
        max = Math.max(max, v);
      }
    }
    return String(max);
  });
  await lengthSelect.selectOption(bestLen);
  await waitForDrawComplete(page);

  const mergedById = new Map();

  for (let guard = 0; guard < 600; guard++) {
    await waitForDrawComplete(page);
    const raw = await collectFromCurrentPage(page);
    let added = 0;
    for (const r of raw) {
      if (!r.itemId || !isPlaceholderImage(r.imageSrc, r.imageAlt)) {
        continue;
      }
      if (!mergedById.has(r.itemId)) {
        mergedById.set(r.itemId, r);
        added++;
      }
    }

    const info = await page
      .locator('#table-table_commodity_list_info')
      .innerText()
      .catch(() => '');
    console.log(`Table page ${guard + 1} (${info.trim()}): +${added} without image (unique total ${mergedById.size})`);

    const clicked = await page.evaluate(() => {
      const wrap = document.querySelector('#table-table_commodity_list_wrapper');
      if (!wrap) {
        return false;
      }
      const li =
        wrap.querySelector('li#table-table_commodity_list_next') ||
        wrap.querySelector('li.paginate_button.next');
      if (!li || li.classList.contains('disabled')) {
        return false;
      }
      const a = li.querySelector('a');
      if (!a) {
        return false;
      }
      a.click();
      return true;
    });
    if (!clicked) {
      break;
    }
    await waitForDrawComplete(page);
  }

  return [...mergedById.values()];
}

async function collectFromCurrentPage(page) {
  const table = page.locator('table.table-table_commodity_list');
  await table.waitFor({ state: 'visible', timeout: 60000 });

  return table.locator('tbody tr').evaluateAll((trs) => {
    const out = [];
    for (const tr of trs) {
      const tds = tr.querySelectorAll('td');
      if (tds.length < 4) {
        continue;
      }
      const cb = tr.querySelector('input[type="checkbox"][value]');
      const itemId = cb ? cb.value.trim() : '';
      const img = tr.querySelector('td img.images_w_table, td img');
      const src = img ? img.getAttribute('src') || '' : '';
      const alt = img ? img.getAttribute('alt') || '' : '';
      const textCol = (i) => (tds[i] ? tds[i].innerText.replace(/\s+/g, ' ').trim() : '');
      // Columns: 0 checkbox, 1 image, 2 code, 3 name, 4 sku, 5 group, 6 warehouse, 7 tags, 8 inventory, 9 unit, ...
      out.push({
        itemId,
        imageSrc: src,
        imageAlt: alt,
        commodityCode: textCol(2),
        commodityName: textCol(3),
        skuCode: textCol(4),
        groupName: textCol(5),
        warehouseName: textCol(6),
        tags: textCol(7),
        inventory: textCol(8),
        unitName: textCol(9),
      });
    }
    return out;
  });
}

async function main() {
  if (!ADMIN_EMAIL || !ADMIN_PASSWORD) {
    console.error('Set ADMIN_EMAIL and ADMIN_PASSWORD in the environment.');
    process.exit(1);
  }

  const browser = await chromium.launch({ headless: !headed });
  const context = await browser.newContext({ baseURL: BASE_URL });
  const page = await context.newPage();

  try {
    await page.goto('/admin/authentication', { waitUntil: 'domcontentloaded' });
    await page.locator('input[name="email"]').fill(ADMIN_EMAIL);
    await page.locator('input[name="password"]').fill(ADMIN_PASSWORD);
    await Promise.all([
      page.waitForURL(/\/admin(\/|$)/, { timeout: 60000 }),
      page.locator('button[type="submit"]').click(),
    ]);

    await page.goto('/admin/warehouse/commodity_list', { waitUntil: 'domcontentloaded' });
    await page.locator('#table-table_commodity_list_wrapper').waitFor({ state: 'visible', timeout: 120000 });

    const withoutImage = await collectAllWithoutImagesAcrossPages(page);

    const rows = withoutImage.map((r) => ({
      'Item ID': r.itemId,
      'Commodity code': r.commodityCode,
      'Commodity name': r.commodityName,
      'SKU code': r.skuCode,
      'Group name': r.groupName,
      'Warehouse name': r.warehouseName,
      Tags: r.tags,
      Inventory: r.inventory,
      'Unit name': r.unitName,
    }));

    const defaultOut = path.join(process.cwd(), 'exports', 'commodities-without-images.xlsx');
    const outPath = process.env.OUT || defaultOut;
    fs.mkdirSync(path.dirname(outPath), { recursive: true });

    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.json_to_sheet(rows.length ? rows : [{ Note: 'No commodities without images found.' }]);
    XLSX.utils.book_append_sheet(wb, ws, 'No image');
    XLSX.writeFile(wb, outPath);

    console.log(`Wrote ${rows.length} rows to ${outPath}`);
  } finally {
    await browser.close();
  }
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
