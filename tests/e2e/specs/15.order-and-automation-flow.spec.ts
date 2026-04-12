import { test, expect } from '@playwright/test';
import { loginAdmin, loginCustomer } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded } from '../utils/guards';

/**
 * End-to-end smoke: client portal loads and order UI is usable, then admin
 * manual automation run returns a structured JSON payload (orders may or may
 * not be pending depending on data — we assert API shape and no fatals).
 */
test.describe('15. Order + automation flow', () => {
  test('portal loads for customer, then admin automation run returns JSON keys', async ({ page }) => {
    test.skip(!creds.admin.password, 'Set PW_ADMIN_PASSWORD to run admin UI tests.');

    page.on('dialog', (d) => d.accept().catch(() => {}));

    // ── Customer portal ─────────────────────────────────────────────────────
    await loginCustomer(page, creds.customer);
    await page.goto('/clients', { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);
    await expect(page.locator('body')).not.toContainText(/Fatal error|SQLSTATE|419/i);
    await expect(page.locator('body')).toContainText(/order|pedido|cart|carrito/i);

    // Best-effort: mirror spec 01 — search + add if split-order UI is present
    const hasSearch = await page.locator('#product-search').isVisible().catch(() => false);
    if (hasSearch) {
      await page.locator('#product-search').fill('tom');
      await page.waitForTimeout(600);
      const productCard = page.locator('#products-catalog .product-card, #products-catalog .product-item').first();
      if (await productCard.isVisible().catch(() => false)) {
        await productCard.click();
        await page.waitForTimeout(400);
      }
      const saveBtn = page
        .locator('button:has-text("Guardar pedido"), button:has-text("Save Order"), button:has-text("Guardar Pedido")')
        .first();
      if (await saveBtn.isVisible().catch(() => false)) {
        await saveBtn.click();
        await page.waitForTimeout(2000);
      }
    }

    // ── Admin automation (new session) ────────────────────────────────────
    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/automation', { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);
    await expect(page.locator('body')).not.toContainText(/Fatal error|Call to undefined|SQLSTATE/i);
    await expect(page.locator('#ramos-automation-trigger')).toBeVisible();

    const responsePromise = page.waitForResponse(
      (r) => r.url().includes('/ramos/automation/run') && r.request().method() === 'POST',
      { timeout: 60_000 }
    );

    page.once('dialog', (d) => d.accept().catch(() => {}));
    await page.locator('#ramos-automation-trigger').click();

    const response = await responsePromise;
    expect(response.status()).toBe(200);

    const body = (await response.json()) as Record<string, unknown>;
    expect(typeof body.batches_created).toBe('number');
    expect(typeof body.routes_created).toBe('number');
    expect(typeof body.modules_assigned).toBe('number');
    expect(typeof body.success).toBe('boolean');
  });
});
