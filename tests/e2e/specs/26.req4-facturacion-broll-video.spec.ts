import { test, expect } from '@playwright/test';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded } from '../utils/guards';

// Force video for every run in this file (must be top-level — not inside describe).
test.use({ video: 'on' });

/**
 * B-roll / smoke recording for Requirement 4 (Facturación → invoice → invoices list).
 * Run headed with video always saved:
 *
 *   PW_ADMIN_PASSWORD='your-password' npx playwright test tests/e2e/specs/26.req4-facturacion-broll-video.spec.ts
 *
 * Video: test-results/playwright-artifacts (folder per run, video.webm inside).
 */
test.describe('26. Req4 — Facturación B-roll (video always on)', () => {
  test('Facturación → optional Generate invoice → Invoices list', async ({ page }) => {
    test.skip(!creds.admin.password, 'Set PW_ADMIN_PASSWORD to record this flow');
    test.setTimeout(120000);

    const today = new Date().toISOString().slice(0, 10);

    await loginAdmin(page, creds.admin);

    // 1) Facturación (today, then try a common seeded date if empty)
    await page.goto(`/admin/ramos/facturacion?date=${today}`, { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);
    await expect(page).toHaveURL(/ramos\/facturacion/i);
    await expect(page.locator('body')).not.toContainText(/403|access denied/i);

    await page.waitForTimeout(1500);

    const hasRouteContent = await page
      .locator('#ramos-facturacion-routes, .panel_s, [class*="facturacion"]')
      .first()
      .isVisible()
      .catch(() => false);

    if (!hasRouteContent) {
      await page.goto('/admin/ramos/facturacion?date=2026-03-27', { waitUntil: 'domcontentloaded' });
      await assertPageLoaded(page);
      await page.waitForTimeout(1500);
    }

    // 2) Optional: Generate invoice (confirm dialog)
    page.once('dialog', (d) => d.accept().catch(() => {}));

    const genInvoice = page.locator('a[href*="facturacion/generate_invoice"]').first();

    if (await genInvoice.isVisible({ timeout: 8000 }).catch(() => false)) {
      await genInvoice.click();
      await page.waitForLoadState('domcontentloaded');
      await page.waitForTimeout(2000);
    } else {
      // Still useful B-roll: scroll the page a bit
      await page.evaluate(() => window.scrollBy(0, 400));
      await page.waitForTimeout(800);
    }

    // 3) Perfex invoices list
    await page.goto('/admin/invoices', { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);
    await expect(page).toHaveURL(/admin\/invoices/i);
    await expect(page.locator('body')).toContainText(/invoice|factura/i);

    await page.waitForTimeout(2000);
    await page.evaluate(() => window.scrollBy(0, 300));
    await page.waitForTimeout(1000);
  });
});
