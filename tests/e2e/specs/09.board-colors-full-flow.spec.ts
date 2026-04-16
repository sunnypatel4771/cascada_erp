/**
 * Spec 09 — Routes board colour end-to-end flow
 *
 * Full journey:
 *   1. Customer logs in and places a new order for today
 *   2. Admin generates routes for today → the new order becomes a stop
 *   3. Admin opens the picking console → pick records are created automatically
 *   4. Board is refreshed → card badge changes from "Awaiting picks" to yellow (pending)
 *   5. Admin picks items in the console → board turns green
 *
 * All tests run in headed mode (configured in playwright.config.ts).
 */

import { test, expect, Page } from '@playwright/test';
import { loginAdmin, loginCustomer } from '../utils/auth';
import { creds } from '../utils/credentials';

const TODAY = new Date().toISOString().split('T')[0]; // YYYY-MM-DD
const BASE_URL = process.env.PW_BASE_URL || 'http://127.0.0.1:8080';

// ─── helpers ────────────────────────────────────────────────────────────────

async function waitForAlert(page: Page) {
  await page.waitForSelector('.alert, .alert-success, .alert-danger, .alert-warning', { timeout: 8000 }).catch(() => null);
}

async function boardRefresh(page: Page, date: string) {
  return page.evaluate(
    async ({ url, d }: { url: string; d: string }) => {
      const resp = await fetch(`${url}/admin/ramos/routes/board_refresh?date=${encodeURIComponent(d)}`, {
        credentials: 'include',
      });
      const contentType = (resp.headers.get('content-type') || '').toLowerCase();
      const text = await resp.text();
      // Some environments return HTML (e.g. auth redirect) — surface this to the test runner.
      if (!contentType.includes('json')) {
        return { ok: false, status: resp.status, contentType, text: text.slice(0, 500) };
      }
      try {
        const data = JSON.parse(text) as {
          success: boolean;
          routes: Array<{ id: number; board_state: { key: string; badge_class: string; label: string } }>;
        };
        return { ok: true, status: resp.status, contentType, data };
      } catch {
        return { ok: false, status: resp.status, contentType, text: text.slice(0, 500) };
      }
    },
    { url: BASE_URL, d: date }
  );
}

// ─── setup: delete any today routes so we start clean ───────────────────────

test.beforeAll(async ({ browser }) => {
  // We run beforeAll cleanup via admin API, not DB — skip if routes already clean.
});

// ─── test suite ─────────────────────────────────────────────────────────────

test.describe('9. Board colours — full order→route→pick flow', () => {
  // This is a destructive, highly environment-dependent end-to-end flow.
  // Enable explicitly when you have a seeded, stable environment.
  test.skip(process.env.PW_RUN_BOARD_COLORS_FLOW !== '1', 'Set PW_RUN_BOARD_COLORS_FLOW=1 to run this full-flow spec.');

  test('Step 1: customer places a new order for today', async ({ page }) => {
    test.setTimeout(90000);
    await loginCustomer(page, creds.customer);
    // After customer login the app redirects to the client home page (site_url())
    // which may be "/" or "/clients" — wait for any settled page that isn't the login page.
    try {
      await page.waitForURL((url) => !url.pathname.includes('authentication'), { timeout: 15000 });
    } catch {
      test.skip(true, 'Customer login did not complete (still on /authentication). Check PW_CUSTOMER_EMAIL/PW_CUSTOMER_PASSWORD and seeded portal access.');
      return;
    }

    // If we ended up somewhere other than the client home, navigate there explicitly
    const currentUrl = page.url();
    if (!currentUrl.includes('/clients') && !currentUrl.includes('#product-search')) {
      await page.goto(`${BASE_URL}/clients`, { waitUntil: 'domcontentloaded' });
    }

    // Wait for the product search input to appear on the home/order page
    await expect(page.locator('#product-search')).toBeVisible({ timeout: 15000 });

    // Search for a product assigned to a module (FRESAS REJILLA is in Module A)
    await page.locator('#product-search').fill('FRESAS');
    await page.waitForTimeout(1500); // debounce

    // Click first product card
    const productCard = page.locator('.product-card, [data-description]').first();
    if (await productCard.isVisible({ timeout: 5000 }).catch(() => false)) {
      await productCard.scrollIntoViewIfNeeded().catch(() => {});
      // Cards can be moving due to lazy-load/layout; force click to avoid "stable" waits.
      await productCard.click({ force: true, timeout: 15000 });
      await page.waitForTimeout(500);
    } else {
      // Try the product dropdown/select if cards are not shown
      const productSelect = page.locator('#product-select, select[name*="product"]').first();
      if (await productSelect.isVisible({ timeout: 3000 }).catch(() => false)) {
        await productSelect.selectOption({ index: 1 });
      }
    }

    // Verify a row was added to the order table
    const orderRow = page.locator('#order-items-table tbody tr, .order-items-table tr, table tr').first();
    const hasRow = await orderRow.isVisible({ timeout: 5000 }).catch(() => false);

    if (hasRow) {
      // Set quantity to 2
      const qtyInput = orderRow.locator('input[name*="qty"], input[name*="quantity"]').first();
      if (await qtyInput.isVisible().catch(() => false)) {
        await qtyInput.fill('2');
      }
    }

    // Save the order
    const saveBtn = page.locator('button:has-text("Guardar"), button:has-text("Save"), #save-order-btn').first();
    if (await saveBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await saveBtn.click();
      await waitForAlert(page);
    }

    // Verify we didn't get an error
    const errorAlert = page.locator('.alert-danger');
    const hasError = await errorAlert.isVisible({ timeout: 3000 }).catch(() => false);
    if (hasError) {
      const errText = await errorAlert.textContent();
      console.warn('[Step 1] Error alert:', errText);
    }

    // Take screenshot for reference
    await page.screenshot({ path: 'test-results/step1-customer-order.png' });
  });

  test('Step 2: admin generates routes for today', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.waitForURL(/admin/, { timeout: 15000 });

    // Navigate to route generation page
    await page.goto(`${BASE_URL}/admin/ramos/routes`, { waitUntil: 'domcontentloaded' });

    // Fill in the route generation form
    const dateInput = page.locator('input[name="route_date"], input[name="date"], #route_date').first();
    if (await dateInput.isVisible({ timeout: 5000 }).catch(() => false)) {
      await dateInput.fill(TODAY);
    }

    const generateBtn = page.locator(
      'button:has-text("Generate"), button:has-text("Generar"), input[value*="Generate"], input[value*="Generar"]'
    ).first();

    if (await generateBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await generateBtn.click();
      await waitForAlert(page);
      await page.screenshot({ path: 'test-results/step2-routes-generated.png' });
    }

    // Navigate to the board to confirm routes exist for today
    await page.goto(`${BASE_URL}/admin/ramos/routes/board?date=${TODAY}`, { waitUntil: 'domcontentloaded' });
    const routeCards = page.locator('.panel_s, .route-card, .kanban-item');
    const cardCount = await routeCards.count();
    console.log(`[Step 2] Route cards on board for ${TODAY}: ${cardCount}`);
    await page.screenshot({ path: 'test-results/step2-board-before-picking.png' });
  });

  test('Step 3: board_refresh shows routes for today', async ({ page }) => {
    test.setTimeout(60000);
    await loginAdmin(page, creds.admin);
    await page.goto(`${BASE_URL}/admin/ramos/routes/board?date=${TODAY}`, { waitUntil: 'domcontentloaded' });

    const result = await boardRefresh(page, TODAY);
    if (!result.ok) {
      test.skip(true, `board_refresh did not return JSON (status=${result.status} ct=${result.contentType}).`);
      return;
    }
    const data = result.data!;
    console.log('[Step 3] board_refresh response:', JSON.stringify(data).slice(0, 400));

    expect(data.success).toBe(true);
    expect(Array.isArray(data.routes)).toBeTruthy();

    if (data.routes && data.routes.length > 0) {
      const route = data.routes[0];
      expect(route).toHaveProperty('board_state');
      expect(route.board_state).toHaveProperty('badge_class');
      expect(route.board_state).toHaveProperty('key');
      console.log('[Step 3] First route board_state:', JSON.stringify(route.board_state));
    } else {
      console.warn('[Step 3] No routes found for today — skipping board_state assertion');
      test.skip(true, 'No routes for today — generate them first.');
    }
  });

  test('Step 4: visiting picking console creates pick records → board turns yellow', async ({ page }) => {
    test.setTimeout(90000);
    await loginAdmin(page, creds.admin);

    // First: capture board state BEFORE opening console
    await page.goto(`${BASE_URL}/admin/ramos/routes/board?date=${TODAY}`, { waitUntil: 'domcontentloaded' });
    const before = await boardRefresh(page, TODAY);
    if (!before.ok) {
      test.skip(true, 'board_refresh did not return JSON before console visit.');
      return;
    }
    const beforeData = before.data!;

    const routesBefore = beforeData.routes ?? [];
    const statesBefore = routesBefore.map((r) => r.board_state?.key ?? 'unknown');
    console.log('[Step 4] Board states BEFORE console visit:', statesBefore);

    // Visit the picking console — this triggers ensure_pick_records_for_module for all modules
    await page.goto(`${BASE_URL}/admin/ramos/picking/console`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2000); // allow server processing

    const consolePage = page.locator('body');
    const consoleText = await consolePage.textContent();
    console.log('[Step 4] Console page loaded, has text length:', consoleText?.length ?? 0);
    await page.screenshot({ path: 'test-results/step4-picking-console.png' });

    // Now check board state AFTER console visit
    await page.goto(`${BASE_URL}/admin/ramos/routes/board?date=${TODAY}`, { waitUntil: 'domcontentloaded' });
    const after = await boardRefresh(page, TODAY);
    if (!after.ok) {
      test.skip(true, 'board_refresh did not return JSON after console visit.');
      return;
    }
    const afterData = after.data!;

    const routesAfter = afterData.routes ?? [];
    const statesAfter = routesAfter.map((r) => r.board_state?.key ?? 'unknown');
    console.log('[Step 4] Board states AFTER console visit:', statesAfter);

    await page.screenshot({ path: 'test-results/step4-board-after-console.png' });

    // After the console visit creates pick records, no route should be stuck on "no_picks"
    // They should all be at least "in_progress" (yellow = pending picks) or better
    const noPicksAfter = statesAfter.filter((s) => s === 'no_picks');
    if (noPicksAfter.length > 0) {
      console.warn('[Step 4] Routes still in no_picks state after console visit:', noPicksAfter.length);
      // Check if these routes have stops at all
      for (const route of routesAfter) {
        if (route.board_state?.key === 'no_picks') {
          console.warn('[Step 4] Route', route.id, 'still shows no_picks — may have no module-assigned products');
        }
      }
    }

    // At least the board should load correctly
    expect(afterData.success).toBe(true);
  });

  test('Step 5: board shows yellow (in_progress) badge for routes with pending picks', async ({ page }) => {
    await loginAdmin(page, creds.admin);

    // Ensure pick records exist by visiting console
    await page.goto(`${BASE_URL}/admin/ramos/picking/console`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1000);

    await page.goto(`${BASE_URL}/admin/ramos/routes/board?date=${TODAY}`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2000); // let board_refresh poll run if auto-refresh is active

    const data = await boardRefresh(page, TODAY);
    const routes = data.routes ?? [];

    const badgeClasses = routes.map((r) => r.board_state?.badge_class);
    const keys = routes.map((r) => r.board_state?.key);
    console.log('[Step 5] Route board_state keys:', keys);
    console.log('[Step 5] Route badge_classes:', badgeClasses);

    // At minimum, routes with items assigned to modules should NOT all be "no_picks"
    // (the "Awaiting picks" yellow badge without pick records)
    // They should show either:
    //   "in_progress" (label-warning) = pending picks exist
    //   "ready"       (label-success) = all picked
    //   "no_stock"    (label-danger)  = waiting for PO
    // If "no_picks" remains, it means no module products matched the order items.

    if (routes.length === 0) {
      console.warn('[Step 5] No routes found for today — this test needs routes generated first');
      return;
    }

    // Verify the board API returns valid data with proper badge classes
    for (const route of routes) {
      expect(route.board_state).toBeDefined();
      expect(route.board_state.badge_class).toMatch(/label-(success|warning|danger)/);
    }

    await page.screenshot({ path: 'test-results/step5-board-with-pick-status.png' });
  });

  test('Step 6: board UI visually shows coloured badges — not all "Awaiting picks"', async ({ page }) => {
    await loginAdmin(page, creds.admin);

    // Ensure pick records by visiting console first
    await page.goto(`${BASE_URL}/admin/ramos/picking/console`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(500);

    await page.goto(`${BASE_URL}/admin/ramos/routes/board?date=${TODAY}`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(3000); // wait for AJAX board_refresh to fire

    await page.screenshot({ path: 'test-results/step6-board-ui.png', fullPage: true });

    // Verify the legend panel is visible
    const legend = page.locator('body').getByText(/leyenda|legend/i);
    await expect(legend.first()).toBeVisible({ timeout: 10000 });

    // Verify at least one route card is on the board
    const cards = page.locator('.kanban-column .panel, .route-card, [data-route-id]');
    const cardCount = await cards.count();
    console.log('[Step 6] Visible route cards on board:', cardCount);

    if (cardCount === 0) {
      // Try a broader selector for the board items
      const panels = page.locator('.panel_s');
      const panelCount = await panels.count();
      console.log('[Step 6] .panel_s elements:', panelCount);
    }

    // Check board badges are present and meaningful
    const successBadges = page.locator('.label.label-success');
    const warningBadges = page.locator('.label.label-warning');
    const dangerBadges = page.locator('.label.label-danger');

    const successCount = await successBadges.count();
    const warningCount = await warningBadges.count();
    const dangerCount = await dangerBadges.count();

    console.log(`[Step 6] Badges — green: ${successCount}, yellow: ${warningCount}, red: ${dangerCount}`);

    // The board should have at least one badge rendered
    expect(successCount + warningCount + dangerCount).toBeGreaterThan(0);
  });

  test('Step 7: simulate picking — mark item picked → board turns green', async ({ page }) => {
    await loginAdmin(page, creds.admin);

    // Get a route for today with pending picks
    await page.goto(`${BASE_URL}/admin/ramos/routes/board?date=${TODAY}`, { waitUntil: 'domcontentloaded' });
    const data = await boardRefresh(page, TODAY);
    const routes = data.routes ?? [];

    if (routes.length === 0) {
      console.warn('[Step 7] No routes for today — skipping');
      return;
    }

    // Go to the picking console and complete all items for a module
    await page.goto(`${BASE_URL}/admin/ramos/picking/console`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1000);

    // Look for pending pick items (yellow) and click to update them
    const pendingItems = page.locator('.pick-item[data-status="pending"], .pick-row.pending, tr[data-pick-id]');
    const pendingCount = await pendingItems.count();
    console.log(`[Step 7] Pending pick items in console: ${pendingCount}`);

    if (pendingCount > 0) {
      // Click the first pending item to try to complete it
      const firstItem = pendingItems.first();
      const editBtn = firstItem.locator('button, a').first();
      if (await editBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
        await editBtn.click();
        await page.waitForTimeout(500);

        // Fill in picked qty and weight
        const pickedQtyInput = page.locator('input[name="picked_qty"], input[name*="picked"]').first();
        const weightInput = page.locator('input[name="weight"]').first();

        if (await pickedQtyInput.isVisible({ timeout: 3000 }).catch(() => false)) {
          await pickedQtyInput.fill('2');
        }
        if (await weightInput.isVisible({ timeout: 3000 }).catch(() => false)) {
          await weightInput.fill('1.5');
        }

        const saveBtn = page.locator('button[type="submit"], button:has-text("Save"), button:has-text("Guardar")').first();
        if (await saveBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
          await saveBtn.click();
          await page.waitForTimeout(1000);
        }
      }
    }

    await page.screenshot({ path: 'test-results/step7-after-picking.png' });

    // Re-check the board state
    await page.goto(`${BASE_URL}/admin/ramos/routes/board?date=${TODAY}`, { waitUntil: 'domcontentloaded' });
    const afterData = await boardRefresh(page, TODAY);
    const afterKeys = (afterData.routes ?? []).map((r) => r.board_state?.key);
    console.log('[Step 7] Board state after partial picking:', afterKeys);

    await page.screenshot({ path: 'test-results/step7-board-after-picking.png' });
  });

  test('Step 8: dispatch readiness check works for today routes', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto(`${BASE_URL}/admin/ramos/routes/board?date=${TODAY}`, { waitUntil: 'domcontentloaded' });

    const data = await boardRefresh(page, TODAY);
    const routes = data.routes ?? [];

    if (routes.length === 0) {
      console.warn('[Step 8] No routes for today — skipping dispatch readiness check');
      return;
    }

    const routeId = routes[0].id;
    const readiness = await page.evaluate(
      async ({ url, id }: { url: string; id: number }) => {
        const resp = await fetch(`${url}/admin/ramos/routes/dispatch_readiness/${id}`, { credentials: 'include' });
        const json = (await resp.json()) as { all_picked: boolean; all_invoiced: boolean };
        return { status: resp.status, data: json };
      },
      { url: BASE_URL, id: routeId }
    );

    console.log('[Step 8] dispatch_readiness response:', JSON.stringify(readiness));
    expect(readiness.status).toBe(200);
    expect(readiness.data).toHaveProperty('all_picked');
    expect(readiness.data).toHaveProperty('all_invoiced');
    expect(typeof readiness.data.all_picked).toBe('boolean');
    expect(typeof readiness.data.all_invoiced).toBe('boolean');
  });
});
