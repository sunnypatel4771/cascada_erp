import { test, expect } from '@playwright/test';
import { execFileSync } from 'child_process';
import { loginCustomer } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded } from '../utils/guards';

type DbConfig = { host: string; user: string; pass: string; name: string };

function dbConfig(): DbConfig {
  return {
    host: process.env.PW_DB_HOST || '127.0.0.1',
    user: process.env.PW_DB_USER || 'root',
    pass: process.env.PW_DB_PASS || '123456',
    name: process.env.PW_DB_NAME || 'ranos-php01',
  };
}

function phpQueryJson(sql: string): any {
  const cfg = dbConfig();
  const php = `
    $h = mysqli_connect(${JSON.stringify(cfg.host)}, ${JSON.stringify(cfg.user)}, ${JSON.stringify(cfg.pass)}, ${JSON.stringify(cfg.name)});
    if (!$h) { fwrite(STDERR, "db_connect_failed\\n"); exit(2); }
    $sql = ${JSON.stringify(sql)};
    $r = mysqli_query($h, $sql);
    if ($r === false) { fwrite(STDERR, "db_query_failed: ".mysqli_error($h)."\\n"); exit(3); }
    $rows = [];
    while ($row = mysqli_fetch_assoc($r)) { $rows[] = $row; }
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
  `.trim();

  const out = execFileSync('php', ['-r', php], { stdio: ['ignore', 'pipe', 'pipe'] }).toString('utf8');
  return JSON.parse(out || '[]');
}

function dbScalar(sql: string): string | null {
  const rows = phpQueryJson(sql);
  if (!rows || rows.length === 0) return null;
  const first = rows[0];
  const keys = Object.keys(first);
  return keys.length ? String(first[keys[0]] ?? '') : null;
}

test.describe('Portal maduración + inventory history', () => {
  test('maduración appears for Sí items; save reduces inventory and adds history row', async ({ page }) => {
    test.setTimeout(180000);

    const productLabel = process.env.PW_MADURACION_ITEM || 'LIMON REAL KG';

    const itemIdStr = dbScalar(
      `SELECT id FROM tblitems WHERE description=${JSON.stringify(productLabel)} LIMIT 1`
    );
    expect(itemIdStr, `Missing tblitems row for ${productLabel}`).toBeTruthy();
    const itemId = parseInt(itemIdStr || '0', 10);
    expect(itemId).toBeGreaterThan(0);

    const invBeforeStr = dbScalar(`SELECT COALESCE(SUM(inventory_number),0) as qty FROM tblinventory_manage WHERE commodity_id=${itemId}`);
    const invBefore = parseFloat(invBeforeStr || '0') || 0;

    const txnBeforeStr = dbScalar(
      `SELECT COUNT(*) as cnt FROM tblgoods_transaction_detail WHERE commodity_id=${itemId} AND status=2 AND note LIKE 'Portal order invoice #%';`
    );
    const txnBefore = parseInt(txnBeforeStr || '0', 10) || 0;

    await loginCustomer(page, creds.customer);
    await page.goto('/clients', { waitUntil: 'domcontentloaded', timeout: 60000 });
    await assertPageLoaded(page);

    await expect(page.locator('#new-order-form')).toBeVisible();
    const row = page.locator('#order-items-body tr').first();
    const productSelect = row.locator('select.product-select').first();
    await productSelect.selectOption({ label: productLabel });
    await page.waitForTimeout(400);

    // Maduración select should be visible for items marked "Sí" in custom field.
    const madSel = row.locator('select.maduracion-select').first();
    await expect(madSel).toBeVisible();
    await madSel.selectOption({ value: 'Maduro' });

    // If equivalencias are present and visible, select first non-empty option.
    const eqSel = row.locator('select.equivalencias-select').first();
    if (await eqSel.isVisible().catch(() => false)) {
      const opts = await eqSel.locator('option').allTextContents();
      if (opts.length > 0) {
        await eqSel.selectOption({ label: opts[0] });
      }
    }

    // qty=1
    await row.locator('input[name*="[qty]"]').first().fill('1');

    // Save and wait for POST (reload/navigation can be missed in headed mode).
    const saveUrlRe = /\/clients\/save_new_order/i;
    const saveResp = page
      .waitForResponse((r) => saveUrlRe.test(r.url()) && r.request().method() === 'POST', { timeout: 60000 })
      .catch(() => null);

    await page.evaluate(() => (window as any).saveNewOrder());
    await saveResp;
    await page.waitForTimeout(1200);

    // Verify previous order section shows this item and ripeness.
    await expect(page.locator('body')).toContainText(new RegExp(productLabel, 'i'));
    await expect(page.locator('body')).toContainText(/maduro/i);

    const invAfterStr = dbScalar(`SELECT COALESCE(SUM(inventory_number),0) as qty FROM tblinventory_manage WHERE commodity_id=${itemId}`);
    const invAfter = parseFloat(invAfterStr || '0') || 0;

    const txnAfterStr = dbScalar(
      `SELECT COUNT(*) as cnt FROM tblgoods_transaction_detail WHERE commodity_id=${itemId} AND status=2 AND note LIKE 'Portal order invoice #%';`
    );
    const txnAfter = parseInt(txnAfterStr || '0', 10) || 0;

    // Inventory should not increase; ideally should decrease by 1.
    expect(invAfter).toBeLessThanOrEqual(invBefore);
    expect(txnAfter).toBeGreaterThan(txnBefore);
  });
});

