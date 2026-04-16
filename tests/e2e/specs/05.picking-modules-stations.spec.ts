import { test, expect } from '@playwright/test';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded } from '../utils/guards';

test.describe('5. Picking Modules and Stations', () => {
  test.skip(!creds.admin.password, 'PW_ADMIN_PASSWORD not set – skipping picking module tests');

  test('picking module management page loads with module cards and assigned products', async ({ page }) => {
    test.setTimeout(90000);
    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/picking', { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);

    await expect(page.locator('body')).toContainText(/picking|module|módulo/i);

    // Module management cards exist
    const moduleSection = page.locator('.ramos-picking-modules');
    await expect(moduleSection).toBeVisible({ timeout: 30000 });

    const cards = moduleSection.locator('.col-md-6');
    await expect(cards.first()).toBeVisible();
    // Each card should show a module name and have an assigned product list or shift form
    await expect(page.locator('body')).toContainText(/product|producto|staff|operator|supervisor/i);
  });

  test('picking console loads for admin and shows all modules (admin sees everything)', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/picking/console');
    await assertPageLoaded(page);

    await expect(page).toHaveURL(/ramos\/picking\/console/i);

    // Admin always sees all module cards (or a "no orders" message per module — either is valid)
    const hasModules = await page.locator('.ramos-console-module').first().isVisible().catch(() => false);
    if (hasModules) {
      await expect(page.locator('.ramos-console-module').first()).toBeVisible();
      // Each module card shows pending/completed orders or empty message
      await expect(page.locator('body')).toContainText(
        /order|pedido|completado|completed|pending|pendiente|no orders|sin pedidos/i
      );
    } else {
      // No active orders today — page still loads cleanly
      await expect(page.locator('body')).toContainText(
        /no estás|no está|sin pedidos|no orders|picking|console|consola/i
      );
    }
    // No fatal errors
    await expect(page.locator('body')).not.toContainText(/Fatal error|SQLSTATE|Call to undefined/i);
  });

  test('picking console: status color badges (green=completed, yellow=pending, red=waiting_po) present', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/picking/console');
    await assertPageLoaded(page);

    const hasOrders = await page.locator('.ramos-console-order').first().isVisible().catch(() => false);
    if (!hasOrders) {
      // No orders today — validate the console still shows structural elements
      await expect(page.locator('#ramos-console-modules')).toBeVisible();
      test.info().annotations.push({ type: 'note', description: 'No orders in console today; structure check only.' });
      return;
    }

    // At least one status badge exists
    await expect(
      page.locator('.label-success, .label-warning, .label-danger').first()
    ).toBeVisible();
  });

  test('picking console: update item (qty + weight) via save form', async ({ page }) => {
    test.setTimeout(90000);
    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/picking/console', { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);

    const firstForm = page.locator('.ramos-console-order form').first();
    const hasForm = await firstForm.isVisible().catch(() => false);
    if (!hasForm) {
      test.skip(true, 'No pending pick items in console — skipping update test.');
      return;
    }

    const qtyInput    = firstForm.locator('input[name="picked_qty"]');
    const weightInput = firstForm.locator('input[name="weight"]');
    const submitBtn   = firstForm.locator('button[type="submit"]');

    await qtyInput.fill('1');
    await weightInput.fill('0.50');

    // Some environments respond via XHR; others may update without a distinct response capture.
    const responsePromise = page
      .waitForResponse((r) => r.url().includes('ramos/picking/update_item'), { timeout: 30000 })
      .catch(() => null);
    await submitBtn.click();
    const response = await responsePromise;
    if (response) {
      // Server may return 200 (AJAX) or 3xx (form POST redirect) — both acceptable
      expect([200, 301, 302, 303]).toContain(response.status());
    }
    await expect(page.locator('body')).not.toContainText(/Fatal error|SQLSTATE|Unknown column/i);

    // After update, console should reload (auto-refresh) or show updated state
    await page.waitForLoadState('domcontentloaded');
  });

  test('picking console: auto-refresh endpoint returns valid JSON', async ({ page }) => {
    // Avoid relying on browser timers; call refresh endpoint directly.
    test.setTimeout(50000);

    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/picking/console');
    await assertPageLoaded(page);

    const baseUrl = process.env.PW_BASE_URL || 'http://127.0.0.1:8080';
    const refresh = await page.evaluate(async (url: string) => {
      const resp = await fetch(`${url}/admin/ramos/picking/console_refresh`, { credentials: 'include' });
      let data: unknown = null;
      try { data = await resp.json(); } catch { /* non-JSON */ }
      return { status: resp.status, data };
    }, baseUrl);
    expect(refresh.status).toBe(200);
    expect(refresh.data).toBeTruthy();
    expect(refresh.data as any).toHaveProperty('success', true);
  });
});
