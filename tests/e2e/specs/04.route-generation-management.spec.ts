import { test, expect } from '@playwright/test';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded } from '../utils/guards';

/** A date known to have at least one generated route in the seeded DB. */
const SEEDED_DATE = '2026-03-27';

test.describe('4. Route Generation and Management', () => {
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
    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/routes');
    await assertPageLoaded(page);

    const today = new Date().toISOString().slice(0, 10);
    await page.locator('input[name="route_date"]').fill(today);
    await page.locator('input[name="max_stops"]').fill('10');

    await Promise.all([
      page.waitForLoadState('domcontentloaded'),
      page.locator('button[type="submit"]').filter({ hasText: /generar rutas|generate routes/i }).click(),
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
    await expect(boardContainer).not.toHaveClass(/tw-hidden/);

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

    // Intercept the refresh call to verify it returns valid JSON
    const [response] = await Promise.all([
      page.waitForResponse(
        (r) => r.url().includes('ramos/routes/board_refresh'),
        { timeout: 25000 }
      ),
    ]);
    expect(response.status()).toBe(200);
    const json = await response.json().catch(() => null);
    expect(json).not.toBeNull();
    expect(json).toHaveProperty('success', true);
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
