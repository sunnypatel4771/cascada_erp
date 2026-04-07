import { test, expect } from '@playwright/test';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded } from '../utils/guards';

const SEEDED_DATE = '2026-03-27';

test.describe('8. Javo new requirements (board colors, dispatch readiness, module report)', () => {
  test('routes board shows color legend panel', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto(`/admin/ramos/routes/board?date=${SEEDED_DATE}`, { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);

    const legendPanel = page.locator('.panel_s').filter({ has: page.locator('.label.label-success, .label.label-warning, .label.label-danger') });
    await expect(legendPanel.first()).toBeVisible();
    await expect(page.locator('body')).toContainText(/legend|leyenda/i);
  });

  test('board_refresh returns routes with pick-based board_state', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto(`/admin/ramos/routes/board?date=${SEEDED_DATE}`, { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);

    const baseUrl = process.env.PW_BASE_URL || 'http://127.0.0.1:8080';
    const result = await page.evaluate(async (args: { url: string; date: string }) => {
      const resp = await fetch(
        `${args.url}/admin/ramos/routes/board_refresh?date=${encodeURIComponent(args.date)}`,
        { credentials: 'include' }
      );
      let data: Record<string, unknown> | null = null;
      try {
        data = (await resp.json()) as Record<string, unknown>;
      } catch {
        /* ignore */
      }
      return { status: resp.status, data };
    }, { url: baseUrl, date: SEEDED_DATE });

    expect(result.status).toBe(200);
    expect(result.data?.success).toBe(true);
    const routes = result.data?.routes as Array<Record<string, unknown>> | undefined;
    expect(Array.isArray(routes)).toBeTruthy();
    if (routes && routes.length > 0) {
      const first = routes[0];
      expect(first).toHaveProperty('board_state');
      const state = first.board_state as Record<string, string>;
      expect(state).toHaveProperty('badge_class');
      expect(state).toHaveProperty('label');
      expect(state.badge_class).toMatch(/label-(success|warning|danger|default)/);
    }
  });

  test('dispatch_readiness returns JSON with all_picked and all_invoiced', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto(`/admin/ramos/routes/board?date=${SEEDED_DATE}`, { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);

    const baseUrl = process.env.PW_BASE_URL || 'http://127.0.0.1:8080';
    const routeId = await page.evaluate(async (args: { url: string; date: string }) => {
      const resp = await fetch(
        `${args.url}/admin/ramos/routes/board_refresh?date=${encodeURIComponent(args.date)}`,
        { credentials: 'include' }
      );
      const data = (await resp.json()) as { routes?: Array<{ id: number }> };
      return data.routes?.[0]?.id ?? 0;
    }, { url: baseUrl, date: SEEDED_DATE });

    if (!routeId) {
      test.skip(true, 'No route id for seeded date — cannot call dispatch_readiness.');
      return;
    }

    const readiness = await page.evaluate(async (args: { url: string; id: number }) => {
      const resp = await fetch(`${args.url}/admin/ramos/routes/dispatch_readiness/${args.id}`, {
        credentials: 'include',
      });
      let data: Record<string, unknown> | null = null;
      try {
        data = (await resp.json()) as Record<string, unknown>;
      } catch {
        /* ignore */
      }
      return { status: resp.status, data };
    }, { url: baseUrl, id: routeId });

    expect(readiness.status).toBe(200);
    expect(readiness.data).toHaveProperty('all_picked');
    expect(readiness.data).toHaveProperty('all_invoiced');
    expect(typeof readiness.data?.all_picked).toBe('boolean');
    expect(typeof readiness.data?.all_invoiced).toBe('boolean');
  });

  test('route detail page has dispatch warning modal and status form', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto(`/admin/ramos/routes?date=${SEEDED_DATE}`, { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);

    const viewLink = page.locator('a[href*="/admin/ramos/routes/view/"]').first();
    const href = await viewLink.getAttribute('href');
    if (!href) {
      test.skip(true, 'No route view link on planner for seeded date.');
      return;
    }

    await page.goto(href, { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);

    await expect(page.locator('#ramos-route-status-form')).toBeVisible();
    await expect(page.locator('#ramos-dispatch-warning-modal')).toBeAttached();
    await expect(page.locator('#dispatch_confirmed')).toHaveAttribute('value', '0');
  });

  test('module usage report page loads with filters', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/report', { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);

    await expect(page).toHaveURL(/ramos\/report/i);
    await expect(page.locator('input[name="date_from"]')).toBeVisible();
    await expect(page.locator('input[name="date_to"]')).toBeVisible();
    await expect(page.locator('body')).toContainText(/module|módulo|usage|uso|report|reporte/i);
  });

  test('module usage report export returns CSV', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    const baseUrl = process.env.PW_BASE_URL || 'http://127.0.0.1:8080';
    const exportUrl = `${baseUrl}/admin/ramos/report/export?date_from=2020-01-01&date_to=2099-12-31`;

    const response = await page.request.get(exportUrl, {
      failOnStatusCode: false,
    });

    // Request uses storage from context — need cookies. page.request might not have session.
    // Use evaluate with fetch + credentials after visiting admin once.
    await page.goto('/admin/ramos/report', { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);

    const csvResult = await page.evaluate(async (url: string) => {
      const resp = await fetch(url, { credentials: 'include' });
      const text = await resp.text();
      return { status: resp.status, contentType: resp.headers.get('content-type') || '', snippet: text.slice(0, 200) };
    }, exportUrl);

    expect(csvResult.status).toBe(200);
    expect(csvResult.contentType).toMatch(/csv|text\/plain|octet-stream/i);
    expect(csvResult.snippet.length).toBeGreaterThan(5);
  });
});
