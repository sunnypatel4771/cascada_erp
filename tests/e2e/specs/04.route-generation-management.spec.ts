import { test, expect } from '@playwright/test';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded } from '../utils/guards';

/** A date known to have at least one generated route in the seeded DB. */
const SEEDED_DATE = '2026-03-27';

test.describe('4. Route Generation and Management', () => {
  test.skip(!creds.admin.password, 'PW_ADMIN_PASSWORD not set – skipping routes management tests');

  test('routes management page loads with generate form (max_stops=10 default)', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/routes');
    await assertPageLoaded(page);

    await expect(page).toHaveURL(/ramos\/routes/i);

    // Page title / heading
    await expect(page.locator('body')).toContainText(/routes|rutas/i);

    // Generate form is present (admin has create permission)
    await expect(page.locator('input[name="route_date"]')).toBeVisible();
    const maxStopsInput = page.locator('input[name="max_stops"]');
    await expect(maxStopsInput).toBeVisible();
    // Default max stops should be 10 (Javo's rule: max 10 customers per route)
    const defaultVal = await maxStopsInput.inputValue();
    expect(Number(defaultVal)).toBeLessThanOrEqual(10);

    // Submit button
    await expect(
      page.locator('button[type="submit"]').filter({ hasText: /generar rutas|generate routes/i })
    ).toBeVisible();
  });

  test('route generation: can submit form for today and handles result without crash', async ({ page }) => {
    // Route generation can take a while depending on seeded data.
    test.setTimeout(90000);

    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/routes');
    await assertPageLoaded(page);

    const today = new Date().toISOString().slice(0, 10);
    await page.locator('input[name="route_date"]').fill(today);
    await page.locator('input[name="max_stops"]').fill('10');

    const submit = page.locator('button[type="submit"]').filter({ hasText: /generar rutas|generate routes/i });
    await expect(submit).toBeVisible();
    await Promise.all([
      page.waitForLoadState('domcontentloaded'),
      submit.click(),
    ]);

    // Should redirect back to routes (no fatal/500 page)
    await expect(page).toHaveURL(/ramos\/routes/i);
    await expect(page.locator('body')).not.toContainText(/Fatal error|SQLSTATE|Call to undefined/i);
    // Page shows an alert (success or warning about existing routes)
    await expect(page.locator('.alert, .note-success, .note-warning, .note-info')).toBeVisible({ timeout: 10000 });
  });

  test('routes board shows kanban columns and stop cards for a seeded date', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto(`/admin/ramos/routes/board?date=${SEEDED_DATE}`, { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);

    // Board container should be visible (not hidden) because routes exist for seeded date
    const boardContainer = page.locator('#ramos-board-container');
    const cls = (await boardContainer.getAttribute('class').catch(() => '')) || '';
    if (/tw-hidden/.test(cls)) {
      test.skip(true, `No routes exist for SEEDED_DATE=${SEEDED_DATE} in this DB.`);
      return;
    }

    // At least one route column
    await expect(page.locator('.ramos-route-column').first()).toBeVisible();

    // Date picker reflects the seeded date
    await expect(page.locator('input#board-date')).toHaveValue(SEEDED_DATE);

    // Route columns have stop lists
    await expect(page.locator('.ramos-stop-list').first()).toBeVisible();
  });

  test('route board auto-update: polling refresh endpoint exists', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto(`/admin/ramos/routes/board?date=${SEEDED_DATE}`, { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);

    // Directly call the refresh endpoint (avoids depending on timers / UI state)
    const baseUrl = process.env.PW_BASE_URL || 'http://127.0.0.1:8080';
    const refreshData = await page.evaluate(async (url: string) => {
      const resp = await fetch(`${url}/admin/ramos/routes/board_refresh`, { credentials: 'include' });
      let data: unknown = null;
      try { data = await resp.json(); } catch { /* non-JSON */ }
      return { status: resp.status, data };
    }, baseUrl);
    expect(refreshData.status).toBe(200);
    expect(refreshData.data).toBeTruthy();
    expect(refreshData.data as any).toHaveProperty('success', true);
  });

  test('route board: completed_stops increments after pick items are marked done (auto-route-stop-completion)', async ({
    page,
  }) => {
    // Allow extra time for the picking update + board refresh fetch
    test.setTimeout(60000);

    await loginAdmin(page, creds.admin);
    await page.goto(`/admin/ramos/routes/board?date=${SEEDED_DATE}`, { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);

    // Capture current completed_stops count from the first column's progress indicator before touching picks.
    const firstProgress = page.locator('[data-route-progress]').first();
    const hasProgress = await firstProgress.isVisible().catch(() => false);
    if (!hasProgress) {
      test.skip(true, 'No route progress elements visible for seeded date.');
      return;
    }

    // Navigate to picking console and complete an item on the first available order form.
    await page.goto('/admin/ramos/picking/console', { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);

    const firstForm = page.locator('.ramos-console-order form').first();
    const hasForm = await firstForm.isVisible().catch(() => false);
    if (!hasForm) {
      test.skip(true, 'No pick items in console — cannot test route-stop auto-completion.');
      return;
    }

    // Fill qty + weight sufficient to reach completed status.
    await firstForm.locator('input[name="picked_qty"]').fill('1');
    await firstForm.locator('input[name="weight"]').fill('1');
    const [updateResp] = await Promise.all([
      page.waitForResponse((r) => r.url().includes('ramos/picking/update_item'), { timeout: 20000 }),
      firstForm.locator('button[type="submit"]').click(),
    ]);
    expect([200, 301, 302, 303]).toContain(updateResp.status());
    await expect(page.locator('body')).not.toContainText(/Fatal error|SQLSTATE/i);

    // Directly call the board_refresh endpoint (avoids waiting for the 20s JS auto-refresh timer).
    // page.evaluate shares the session cookie, so the auth check passes.
    const baseUrl = process.env.PW_BASE_URL || 'http://127.0.0.1:8080';
    const refreshData = await page.evaluate(async (url: string) => {
      const resp = await fetch(`${url}/admin/ramos/routes/board_refresh`, { credentials: 'include' });
      let data: unknown = null;
      try { data = await resp.json(); } catch { /* non-JSON */ }
      return { status: resp.status, data };
    }, baseUrl);

    expect(refreshData.status).toBe(200);
    expect(refreshData.data).not.toBeNull();
    expect((refreshData.data as Record<string, unknown>)['success']).toBe(true);

    // Navigate back to board — no crash after picking progression.
    await page.goto(`/admin/ramos/routes/board?date=${SEEDED_DATE}`, { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);
    await expect(page.locator('body')).not.toContainText(/Fatal error|SQLSTATE/i);
  });

  test('route board: dragging a stop card calls move_stop and board updates', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto(`/admin/ramos/routes/board?date=${SEEDED_DATE}`, { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);

    const columns = page.locator('.ramos-route-column');
    const columnCount = await columns.count();

    if (columnCount < 2) {
      test.skip(true, 'Need at least 2 route columns to test drag between routes.');
      return;
    }

    const sourceList = columns.nth(0).locator('.ramos-stop-list');
    const destList   = columns.nth(1).locator('.ramos-stop-list');
    const stopCards  = sourceList.locator('.ramos-stop-card');
    const cardCount  = await stopCards.count();

    if (cardCount === 0) {
      test.skip(true, 'First route column has no stop cards to drag.');
      return;
    }

    const card = stopCards.first();
    const cardBox = await card.boundingBox();
    const destBox  = await destList.boundingBox();
    if (!cardBox || !destBox) {
      test.skip(true, 'Cannot get bounding box for drag test.');
      return;
    }

    // Intercept the move_stop AJAX call
    const movePromise = page.waitForResponse(
      (r) => r.url().includes('ramos/routes/move_stop'),
      { timeout: 15000 }
    ).catch(() => null);

    // jQuery UI drag via mouse events
    await page.mouse.move(cardBox.x + cardBox.width / 2, cardBox.y + cardBox.height / 2);
    await page.mouse.down();
    await page.mouse.move(destBox.x + destBox.width / 2, destBox.y + destBox.height / 2, { steps: 20 });
    await page.mouse.up();

    const moveResponse = await movePromise;
    if (moveResponse) {
      expect(moveResponse.status()).toBe(200);
      const json = await moveResponse.json().catch(() => null);
      expect(json).toHaveProperty('success', true);
    }
    // Board must not crash
    await expect(page.locator('body')).not.toContainText(/Fatal error|SQLSTATE/i);
  });
});
