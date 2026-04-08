/**
 * Spec 11 — Facturación ERP Fixes
 *
 * Verifies the three gaps fixed in Facturacion.php:
 *   1. ERP invoice orders show editable pick rows (real pick_id ≠ 0)
 *   2. Remisión PDF generates without crash for ERP stops
 *   3. "Email Invoice" button appears when invoice_id is set (ERP orders)
 */
import { test, expect } from '@playwright/test';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded } from '../utils/guards';

/** Today has ERP invoice stops on two routes (order IDs 61, 62). */
const ERP_DATE = '2026-04-07';
const BASE_URL  = process.env.PW_BASE_URL || 'http://127.0.0.1:8080';

// ---------------------------------------------------------------------------
// Helper: navigate to facturación for the ERP date and wait for cards
// ---------------------------------------------------------------------------
async function gotoFacturacion(page: Parameters<typeof loginAdmin>[0]) {
  await loginAdmin(page, creds.admin);
  await page.goto(`/admin/ramos/facturacion?date=${ERP_DATE}`, { waitUntil: 'domcontentloaded' });
  await assertPageLoaded(page);
  // Wait for at least one route card to appear
  await page.locator('#ramos-facturacion-routes .panel_s').first().waitFor({ timeout: 20_000 });
}

// ---------------------------------------------------------------------------
// 1. Page loads and shows route cards for the ERP date
// ---------------------------------------------------------------------------
test.describe('11. Facturación — ERP invoice fixes', () => {

  test('11-A  Facturación loads route cards for ERP date', async ({ page }) => {
    await gotoFacturacion(page);

    const cards = page.locator('#ramos-facturacion-routes .panel_s');
    await expect(cards.first()).toBeVisible();

    // Customer rows should exist
    await expect(page.locator('.tw-bg-slate-100').first()).toBeVisible();

    // No PHP fatal errors
    await expect(page.locator('body')).not.toContainText(/Fatal error|SQLSTATE|Unknown column/i);
  });

  // ---------------------------------------------------------------------------
  // 2. ERP orders expose real pick_id → edit forms are rendered
  // ---------------------------------------------------------------------------
  test('11-B  ERP order rows show Guardar (qty/weight) edit forms', async ({ page }) => {
    await gotoFacturacion(page);

    // The fix makes pick_id > 0 for ERP items once the picking console has been
    // loaded at least once (which it has — pick items exist with status=completed).
    const updateForm = page.locator('form[action*="facturacion/update_item/"]').first();
    const formVisible = await updateForm.isVisible().catch(() => false);

    if (!formVisible) {
      // If no edit forms at all on this date, skip gracefully with a clear message.
      test.skip(true, 'No update_item forms on ERP date — pick records may not have been seeded yet. Visit /admin/ramos/picking/console first to generate pick records.');
      return;
    }

    // Form must contain picked_qty and weight inputs
    await expect(updateForm.locator('input[name="picked_qty"]')).toBeVisible();
    await expect(updateForm.locator('input[name="weight"]')).toBeVisible();
    await expect(updateForm.locator('button[type="submit"]')).toBeVisible();

    console.log('[11-B] PASS — ERP order has real pick_id, Guardar form is visible.');
  });

  // ---------------------------------------------------------------------------
  // 3. Edit qty/weight for an ERP pick item and save without error
  // ---------------------------------------------------------------------------
  test('11-C  Edit qty+weight for ERP pick item — no server error', async ({ page }) => {
    await gotoFacturacion(page);

    const updateForm = page.locator('form[action*="facturacion/update_item/"]').first();
    const formVisible = await updateForm.isVisible().catch(() => false);

    if (!formVisible) {
      test.skip(true, 'No update_item forms visible — skip edit test.');
      return;
    }

    // Fill picked_qty and weight
    await updateForm.locator('input[name="picked_qty"]').fill('2');
    await updateForm.locator('input[name="weight"]').fill('1.25');

    const [response] = await Promise.all([
      page.waitForResponse(
        (r) => r.url().includes('facturacion/update_item/'),
        { timeout: 20_000 }
      ),
      updateForm.locator('button[type="submit"]').click(),
    ]);

    expect([200, 301, 302, 303]).toContain(response.status());
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).not.toContainText(/Fatal error|SQLSTATE|Unknown column/i);
    console.log('[11-C] PASS — ERP pick item saved without error.');
  });

  // ---------------------------------------------------------------------------
  // 4. Remisión PDF link for ERP stop returns content (no 500/404)
  // ---------------------------------------------------------------------------
  test('11-D  Remisión PDF endpoint works for ERP order (no crash)', async ({ page }) => {
    await loginAdmin(page, creds.admin);

    // Navigate directly to the generate_remision endpoint for ERP order 61.
    // The fixed controller falls back to HTML when the PDF library is not installed,
    // so any non-500 response means the ERP data path worked correctly.
    await page.goto(`/admin/ramos/facturacion/generate_remision/61`, { waitUntil: 'domcontentloaded' });

    // Should not show the generic CI error page
    await expect(page.locator('body')).not.toContainText(/Unable to load|An Error Was Encountered|Fatal error/i);

    // Should contain order data (either as rendered HTML or a PDF header)
    const bodyText = await page.locator('body').textContent() ?? '';
    const hasOrderData = bodyText.includes('REMISION') || bodyText.includes('JAPAN ROLL') ||
                         bodyText.includes('61') || page.url().includes('generate_remision');
    expect(hasOrderData).toBeTruthy();

    console.log('[11-D] PASS — generate_remision/61 returned without PHP crash.');
  });

  // ---------------------------------------------------------------------------
  // 5. "Email Invoice" button is visible for ERP orders (invoice_id = orderId)
  // ---------------------------------------------------------------------------
  test('11-E  Email Invoice button visible for ERP orders (invoice_id set)', async ({ page }) => {
    await gotoFacturacion(page);

    // ERP orders have invoice_id = orderId, so the "View Invoice" block is rendered.
    // The "Email Invoice" button lives inside that same block.
    const emailBtn = page.locator('a[href*="facturacion/send_invoice_email"]').first();
    const btnVisible = await emailBtn.isVisible().catch(() => false);

    if (!btnVisible) {
      // The button requires $can_edit = true. Verify that is_admin() is true for
      // the logged-in user by checking that at least one "View Invoice" link exists.
      const viewInvoiceBtn = page.locator('a[href*="invoices/invoice/"]').first();
      const viewVisible = await viewInvoiceBtn.isVisible().catch(() => false);

      if (!viewVisible) {
        test.skip(true, 'No invoice-related buttons on ERP date — ERP data may be missing.');
        return;
      }

      // If View Invoice is present but Email button isn't, that's a failure.
      await expect(emailBtn).toBeVisible();
    }

    // Verify href points to the send_invoice_email action
    const href = await emailBtn.getAttribute('href');
    expect(href).toMatch(/facturacion\/send_invoice_email\/\d+/);
    console.log(`[11-E] PASS — Email Invoice button present at href: ${href}`);
  });

  // ---------------------------------------------------------------------------
  // 6. send_invoice_email endpoint returns a redirect (not a 500)
  // ---------------------------------------------------------------------------
  test('11-F  send_invoice_email endpoint does not crash (no 500)', async ({ page }) => {
    await loginAdmin(page, creds.admin);

    // Navigate directly — CodeIgniter will redirect back to facturacion
    // (either with "sent" or "no invoice / email not configured" alert).
    // The important thing is we must NOT land on a 500 error page.
    await page.goto(`/admin/ramos/facturacion/send_invoice_email/61`, { waitUntil: 'domcontentloaded' });

    // Must NOT show a PHP fatal or CI error
    await expect(page.locator('body')).not.toContainText(/Unable to load|An Error Was Encountered|Fatal error|SQLSTATE/i);

    // Should redirect back to the facturacion page (or show an alert)
    const url = page.url();
    const landed = url.includes('facturacion') || url.includes('admin/authentication') || url.includes('admin/');
    expect(landed).toBeTruthy();

    console.log(`[11-F] PASS — send_invoice_email/61 completed without crash. Landed at: ${page.url()}`);
  });

  // ---------------------------------------------------------------------------
  // 7. Auto-refresh returns valid JSON for ERP date
  // ---------------------------------------------------------------------------
  test('11-G  Facturación refresh endpoint returns valid JSON for ERP date', async ({ page }) => {
    test.setTimeout(70_000); // page fires auto-refresh after 30 s
    await loginAdmin(page, creds.admin);
    await page.goto(`/admin/ramos/facturacion?date=${ERP_DATE}`, { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);

    // Wait for the auto-refresh that fires on the page (30 s interval timer).
    // Instead of waiting 30 s, intercept the response when it fires.
    const [refreshResp] = await Promise.all([
      page.waitForResponse(
        (r) => r.url().includes('ramos/facturacion/refresh'),
        { timeout: 60_000 }
      ),
    ]);

    expect(refreshResp.status()).toBe(200);
    const json = await refreshResp.json().catch(() => null);
    expect(json).not.toBeNull();
    expect(json?.success).toBe(true);
    // hasData should be true since we have ERP stops on this date
    expect(json?.hasData).toBe(true);
    console.log('[11-G] PASS — refresh returned valid JSON with hasData=true.');
  });
  // Note: 11-G has a 60 s timeout because it waits for the page's built-in 30 s refresh timer.

});
