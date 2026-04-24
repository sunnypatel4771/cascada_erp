import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { loginAdmin, loginCustomer } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded } from '../utils/guards';
import { logoutAdmin } from '../utils/staff';

/**
 * End-to-end (headed): admin `week` field + portal order pricing.
 *
 * Requires:
 * - PW_ADMIN_PASSWORD
 * - MySQL reachable with defaults (override with PW_DB_HOST, PW_DB_USER, PW_DB_PASSWORD, PW_DB_NAME)
 * - creds.customer can log in (userid 3 in seed DB)
 * - Ramos inventory `PLATANO` + active price rule for customer 3 (price 50)
 * - customers_descuento = 10 → weekly line ~55, cost line ~16.5 (purchase 15)
 */
test.describe('19. Week field + weekly pricing (full headed flow)', () => {
  test.skip(!creds.admin.password, 'PW_ADMIN_PASSWORD not set – skipping full week/pricing e2e');

  test('week=1 uses list price+markup; week=0 uses cost+markup', async ({ page }) => {
    test.setTimeout(120000);
    page.on('dialog', (dialog) => {
      void dialog.accept().catch(() => {});
    });

    const clientId = process.env.PW_TEST_CLIENT_ID || '3';
    const dbHost = process.env.PW_DB_HOST || '127.0.0.1';
    const dbUser = process.env.PW_DB_USER || 'root';
    const dbPass = process.env.PW_DB_PASSWORD || '123456';
    const dbName = process.env.PW_DB_NAME || 'ranos-php01';

    const expectedWeeklyLine = /55[.,]00|55\.0\b|55,00/;
    const expectedCostLine = /16[.,]50|16\.5\b|16,50/;

    function mysqlExec(sql: string): string {
      return execFileSync(
        'mysql',
        ['-h', dbHost, '-u', dbUser, `-p${dbPass}`, dbName, '-N', '-e', sql],
        { encoding: 'utf8' }
      ).trim();
    }

    function platanoLineRate(invoiceId: number): string {
      const sql = `SELECT rate FROM tblitemable WHERE rel_type='invoice' AND rel_id=${Number(
        invoiceId
      )} AND description LIKE '%PLATANO%' ORDER BY id DESC LIMIT 1`;
      return mysqlExec(sql);
    }

    function latestInvoiceId(): number {
      const sql = `SELECT id FROM tblinvoices WHERE clientid=${Number(clientId)} ORDER BY id DESC LIMIT 1`;
      const out = mysqlExec(sql);
      const n = Number(out);
      return Number.isFinite(n) ? n : 0;
    }

    async function waitForNewInvoiceId(previous: number): Promise<number> {
      const deadline = Date.now() + 45000;
      while (Date.now() < deadline) {
        const id = latestInvoiceId();
        if (id > previous) return id;
        await page.waitForTimeout(400);
      }
      throw new Error(`Timeout waiting for new invoice (client ${clientId}), last id was ${previous}`);
    }

    async function placePlatanoOrderQty1(): Promise<number> {
      const before = latestInvoiceId();
      await page
        .locator(
          'button:has-text("Guardar pedido"), button:has-text("Save Order"), button:has-text("Guardar Pedido")'
        )
        .first()
        .click();
      return waitForNewInvoiceId(before);
    }

    // --- Admin: enable week ---
    await loginAdmin(page, creds.admin);
    await page.goto(`/admin/clients/client/${clientId}?group=profile`, { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);
    await expect(page).toHaveURL(new RegExp(`/admin/clients/client/${clientId}`, 'i'));

    const weekSelect = page.locator('select#week, select[name="week"]').first();
    await weekSelect.waitFor({ state: 'attached', timeout: 15000 });
    await weekSelect.selectOption('1');

    const saveProfile = page.locator('#profile-save-section button.only-save').first();
    await expect(saveProfile).toBeVisible();
    await saveProfile.click();
    await page.waitForLoadState('networkidle').catch(() => {});
    await page.goto(`/admin/clients/client/${clientId}?group=profile`, { waitUntil: 'domcontentloaded' });
    await expect(weekSelect).toHaveValue('1');

    await logoutAdmin(page);

    // --- Customer: order PLATANO (week on) ---
    await loginCustomer(page, creds.customer);
    await page.goto('/clients', { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);

    const hasSearch = await page.locator('#product-search').isVisible().catch(() => false);
    test.skip(!hasSearch, 'Split-order portal UI not present – skipping PLATANO order');

    await page.locator('#product-search').fill('PLATANO');
    await page.waitForTimeout(800);
    const platanoCard = page
      .locator('#products-catalog .product-card, #products-catalog .product-item')
      .filter({ hasText: /platano/i })
      .first();
    await expect(platanoCard).toBeVisible();
    await platanoCard.click();
    await page.waitForTimeout(500);

    const row = page
      .locator('#order-items-body tr, .order-items-table tr, table.order-table tbody tr')
      .filter({ hasText: /platano/i })
      .first();
    await expect(row).toBeVisible();
    const qtyInput = row.locator('input[name*="[qty]"], input[name*="qty"], input[name*="quantity"]').first();
    if (await qtyInput.isVisible().catch(() => false)) {
      await qtyInput.fill('1');
    }

    const invoiceIdWeekOn = await placePlatanoOrderQty1();
    expect(invoiceIdWeekOn).toBeGreaterThan(0);

    await page.goto('/authentication/logout', { waitUntil: 'domcontentloaded' }).catch(() => {});
    await page.context().clearCookies().catch(() => {});

    // --- Admin: verify invoice line (weekly path) ---
    await loginAdmin(page, creds.admin);
    await page.goto(`/admin/invoices/invoice/${invoiceIdWeekOn}`, { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);
    await expect(page.locator('body')).toContainText('PLATANO');
    // Invoice item rates live in <input> cells — body innerText often omits them; assert DB + row inputs.
    await expect.poll(() => platanoLineRate(invoiceIdWeekOn), { timeout: 15000 }).toMatch(expectedWeeklyLine);
    const weeklyRow = page
      .locator('table.invoice-items-table tbody tr')
      .filter({ hasText: /PLATANO/i })
      .first();
    await expect(weeklyRow).toBeVisible();
    await expect
      .poll(async () => {
        const vals = await weeklyRow.locator('input').evaluateAll((els) =>
          els.map((e) => (e as HTMLInputElement).value)
        );
        return vals.join('|');
      })
      .toMatch(expectedWeeklyLine);

    // --- Admin: disable week ---
    await page.goto(`/admin/clients/client/${clientId}?group=profile`, { waitUntil: 'domcontentloaded' });
    await weekSelect.waitFor({ state: 'attached', timeout: 15000 });
    await weekSelect.selectOption('0');
    await saveProfile.click();
    await page.waitForLoadState('networkidle').catch(() => {});
    await page.goto(`/admin/clients/client/${clientId}?group=profile`, { waitUntil: 'domcontentloaded' });
    await expect(weekSelect).toHaveValue('0');

    await logoutAdmin(page);

    // --- Customer: order again (cost path) ---
    await loginCustomer(page, creds.customer);
    await page.goto('/clients', { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);

    await page.locator('#product-search').fill('PLATANO');
    await page.waitForTimeout(800);
    await platanoCard.click();
    await page.waitForTimeout(500);

    const row2 = page
      .locator('#order-items-body tr, .order-items-table tr, table.order-table tbody tr')
      .filter({ hasText: /platano/i })
      .first();
    await expect(row2).toBeVisible();
    const qty2 = row2.locator('input[name*="[qty]"], input[name*="qty"], input[name*="quantity"]').first();
    if (await qty2.isVisible().catch(() => false)) {
      await qty2.fill('1');
    }

    const invoiceIdWeekOff = await placePlatanoOrderQty1();
    expect(invoiceIdWeekOff).toBeGreaterThan(0);

    await page.goto('/authentication/logout', { waitUntil: 'domcontentloaded' }).catch(() => {});
    await page.context().clearCookies().catch(() => {});

    await loginAdmin(page, creds.admin);
    await page.goto(`/admin/invoices/invoice/${invoiceIdWeekOff}`, { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);
    await expect(page.locator('body')).toContainText('PLATANO');
    await expect.poll(() => platanoLineRate(invoiceIdWeekOff), { timeout: 15000 }).toMatch(expectedCostLine);
    const costRow = page
      .locator('table.invoice-items-table tbody tr')
      .filter({ hasText: /PLATANO/i })
      .first();
    await expect(costRow).toBeVisible();
    await expect
      .poll(async () => {
        const vals = await costRow.locator('input').evaluateAll((els) =>
          els.map((e) => (e as HTMLInputElement).value)
        );
        return vals.join('|');
      })
      .toMatch(expectedCostLine);

    await expect(page.locator('body')).not.toContainText(/Fatal error|Uncaught|SQLSTATE/i);
  });
});
