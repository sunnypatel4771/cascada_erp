import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { loginCustomer, loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded } from '../utils/guards';

async function placeSimplePortalOrder(page: any) {
  await page.goto('/clients', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await assertPageLoaded(page);

  const row = page.locator('#order-items-body tr').first();
  await expect(row).toBeVisible();

  const productSelect = row.locator('select.product-select').first();
  await expect(productSelect).toBeVisible();

  await productSelect.selectOption({ label: 'AGUA MINERAL' });
  await page.waitForTimeout(400);

  const eqSelect = row.locator('select.equivalencias-select').first();
  // Equivalencias are optional for this test; select one only if present.
  const eqVisible = await eqSelect.isVisible().catch(() => false);
  if (eqVisible) {
    const ok = await expect
      .poll(async () => await eqSelect.locator('option').count().catch(() => 0), { timeout: 20000 })
      .toBeGreaterThan(1)
      .then(() => true)
      .catch(() => false);
    if (ok) {
      await eqSelect.selectOption({ label: 'Caja' }).catch(() => {});
      await page.waitForTimeout(150);
    }
  }

  const qtyInput = row.locator('input[name*="[qty]"]').first();
  await qtyInput.fill('1');

  const saveUrlRe = /\/clients\/save_new_order/i;
  const saveResponsePromise = page
    .waitForResponse((r: any) => saveUrlRe.test(r.url()) && r.request().method() === 'POST', { timeout: 60000 })
    .catch(() => null);
  const navPromise = page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 60000 }).catch(() => null);

  await page.evaluate(() => (window as any).saveNewOrder());
  await Promise.race([saveResponsePromise, navPromise]);
  await page.waitForLoadState('domcontentloaded', { timeout: 60000 });
}

test.describe('34. Portal auto-merge invoices (same customer)', () => {
  test.skip(!creds.admin.password, 'PW_ADMIN_PASSWORD not set');

  test('placing two portal orders results in one invoice (server-side merge)', async ({ page, context }) => {
    test.setTimeout(180000);

    const clientId = process.env.PW_TEST_CLIENT_ID || '3';
    const dbHost = process.env.PW_DB_HOST || '127.0.0.1';
    const dbUser = process.env.PW_DB_USER || 'root';
    const dbPass = process.env.PW_DB_PASSWORD || '123456';
    const dbName = process.env.PW_DB_NAME || 'ranos-php01';

    function mysqlExec(sql: string): string {
      return execFileSync('mysql', ['-h', dbHost, '-u', dbUser, `-p${dbPass}`, dbName, '-N', '-e', sql], {
        encoding: 'utf8',
      }).trim();
    }

    function eligibleInvoiceCount(): number {
      const sql = `SELECT COUNT(*) FROM tblinvoices WHERE clientid=${Number(
        clientId
      )} AND status IN (1,4,6)`; // unpaid, overdue, draft
      return Number(mysqlExec(sql) || '0') || 0;
    }

    function latestEligibleInvoiceId(): number {
      const sql = `SELECT id FROM tblinvoices WHERE clientid=${Number(
        clientId
      )} AND status IN (1,4,6) ORDER BY id DESC LIMIT 1`;
      return Number(mysqlExec(sql) || '0') || 0;
    }

    function aguaMineralLineCount(invoiceId: number): number {
      const sql = `SELECT COUNT(*) FROM tblitemable WHERE rel_type='invoice' AND rel_id=${Number(
        invoiceId
      )} AND description LIKE '%AGUA MINERAL%'`;
      return Number(mysqlExec(sql) || '0') || 0;
    }

    const beforeEligible = eligibleInvoiceCount();

    // Place first order as customer
    await loginCustomer(page, creds.customer);
    await placeSimplePortalOrder(page);

    // Place second order as customer (should merge into latest invoice)
    await placeSimplePortalOrder(page);

    // Wait for auto-merge to settle: eligible invoices should remain 1 (or return to 1 if it temporarily became 2).
    const deadline = Date.now() + 45000;
    while (Date.now() < deadline) {
      if (eligibleInvoiceCount() === Math.max(1, beforeEligible)) {
        break;
      }
      await page.waitForTimeout(400);
    }

    const eligibleAfter = eligibleInvoiceCount();
    expect(eligibleAfter).toBe(Math.max(1, beforeEligible));

    const latestId = latestEligibleInvoiceId();
    expect(latestId).toBeGreaterThan(0);

    // After placing 2 orders, the remaining invoice should contain multiple lines for the same product.
    expect(aguaMineralLineCount(latestId)).toBeGreaterThanOrEqual(2);

    // Optional UI sanity in admin (ensures invoice page still loads).
    const adminPage = await context.newPage();
    await loginAdmin(adminPage, creds.admin);
    await adminPage.goto(`/admin/invoices/list_invoices/${latestId}`, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await assertPageLoaded(adminPage);
    await expect(adminPage.locator('body')).toContainText(/AGUA MINERAL/i);
  });
});

