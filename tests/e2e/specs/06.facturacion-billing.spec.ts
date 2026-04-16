import { test, expect } from '@playwright/test';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded } from '../utils/guards';

/** A date known to have routes and customer stops in the seeded DB. */
const SEEDED_DATE = '2026-03-27';

test.describe('6. Facturacion (Billing)', () => {
  test.skip(!creds.admin.password, 'PW_ADMIN_PASSWORD not set – skipping facturación tests');

  test('facturacion page loads and shows date picker + console link', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/facturacion');
    await assertPageLoaded(page);

    await expect(page).toHaveURL(/ramos\/facturacion/i);
    await expect(page.locator('body')).toContainText(/facturaci[oó]n|billing|factura/i);

    // Date picker for filtering by route date
    await expect(page.locator('input[name="date"]')).toBeVisible();

    // Link to Picking Console (may appear in sidebar and as a button - use first match)
    await expect(page.locator('a[href*="ramos/picking/console"]').first()).toBeVisible();
  });

  test('facturacion with seeded date shows routes grouped by customer with status badges', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto(`/admin/ramos/facturacion?date=${SEEDED_DATE}`);
    await assertPageLoaded(page);

    // Route cards are rendered (not empty-state)
    const routeCards = page.locator('#ramos-facturacion-routes .panel_s');
    await expect(routeCards.first()).toBeVisible({ timeout: 15000 });

    // Each card has a route name heading
    await expect(
      page.locator('#ramos-facturacion-routes .panel-heading h5').first()
    ).toBeVisible();

    // Customer rows with order numbers exist
    await expect(page.locator('.tw-bg-slate-100').first()).toBeVisible();

    // Status label (red / yellow / green) present for at least one customer
    const statusLabel = page.locator('.label-danger, .label-warning, .label-success').first();
    await expect(statusLabel).toBeVisible();

    // Table shows product lines
    await expect(page.locator('#ramos-facturacion-routes table').first()).toBeVisible();
  });

  test('facturacion: edit price for a line item and save without error', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto(`/admin/ramos/facturacion?date=${SEEDED_DATE}`);
    await assertPageLoaded(page);

    const priceForm = page.locator('form[action*="facturacion/update_item_price"]').first();
    const hasPriceForm = await priceForm.isVisible().catch(() => false);

    if (!hasPriceForm) {
      test.skip(true, 'No price edit forms visible (no pick items on seeded date).');
      return;
    }

    const priceInput = priceForm.locator('input[name="new_price"]');
    const currentVal = await priceInput.inputValue();
    const newVal = String(Math.max(0.01, Number(currentVal) + 1));
    await priceInput.fill(newVal);

    const [response] = await Promise.all([
      // The server may return a 200 (AJAX) or 3xx redirect (normal form POST) — both are valid
      page.waitForResponse((r) => r.url().includes('facturacion/update_item_price'), { timeout: 15000 }),
      priceForm.locator('button[type="submit"]').click(),
    ]);
    expect([200, 301, 302, 303]).toContain(response.status());
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).not.toContainText(/Fatal error|SQLSTATE|Unknown column/i);
  });

  test('facturacion: edit qty + weight for a pick item and save without error', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto(`/admin/ramos/facturacion?date=${SEEDED_DATE}`);
    await assertPageLoaded(page);

    const updateForm = page.locator('form[action*="facturacion/update_item/"]').first();
    const hasForm = await updateForm.isVisible().catch(() => false);

    if (!hasForm) {
      test.skip(true, 'No qty/weight edit forms visible (no pick items on seeded date).');
      return;
    }

    await updateForm.locator('input[name="picked_qty"]').fill('1');
    await updateForm.locator('input[name="weight"]').fill('0.5');

    const [response] = await Promise.all([
      page.waitForResponse((r) => r.url().includes('facturacion/update_item/'), { timeout: 15000 }),
      updateForm.locator('button[type="submit"]').click(),
    ]);
    expect([200, 301, 302, 303]).toContain(response.status());
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).not.toContainText(/Fatal error|SQLSTATE|Unknown column/i);
  });

  test('facturacion: invoice and remision generation buttons present for pending orders', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto(`/admin/ramos/facturacion?date=${SEEDED_DATE}`);
    await assertPageLoaded(page);

    const routeCards = page.locator('#ramos-facturacion-routes .panel_s');
    const hasCards = await routeCards.first().isVisible().catch(() => false);
    if (!hasCards) {
      test.skip(true, 'No billing cards for seeded date.');
      return;
    }

    // Either a "View Invoice" button or the generate buttons (Factura / Remisión) must be present
    const invoiceBtn    = page.locator('a[href*="facturacion/generate_invoice"]').first();
    const remisionBtn   = page.locator('a[href*="facturacion/generate_remision"]').first();
    const viewInvoiceBtn = page.locator('a[href*="invoices/invoice/"]').first();

    const anyBtnVisible =
      (await invoiceBtn.isVisible().catch(() => false)) ||
      (await remisionBtn.isVisible().catch(() => false)) ||
      (await viewInvoiceBtn.isVisible().catch(() => false));

    expect(anyBtnVisible).toBeTruthy();
  });

  test('facturacion: auto-refresh endpoint returns valid JSON', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto(`/admin/ramos/facturacion?date=${SEEDED_DATE}`);
    await assertPageLoaded(page);

    // Call refresh endpoint directly (avoid relying on browser timers).
    const baseUrl = process.env.PW_BASE_URL || 'http://127.0.0.1:8080';
    const refreshData = await page.evaluate(async (url: string) => {
      const resp = await fetch(`${url}/admin/ramos/facturacion/refresh`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'content-type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: 'date=' + encodeURIComponent(new Date().toISOString().slice(0, 10)),
      });
      let data: unknown = null;
      try { data = await resp.json(); } catch { /* non-JSON */ }
      return { status: resp.status, data };
    }, baseUrl);

    expect(refreshData.status).toBe(200);
    expect(refreshData.data).toBeTruthy();
    expect(refreshData.data as any).toHaveProperty('success', true);
  });
});
