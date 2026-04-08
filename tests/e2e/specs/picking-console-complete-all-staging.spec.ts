/**
 * Staging / manual: complete every line on the Picking Console (picked qty + weight)
 * so orders disappear from the console (all items → status completed).
 *
 * Run headed against staging:
 *   PW_BASE_URL=https://3ware.com.mx/ramos/ramos_staging/erp \
 *   PW_ADMIN_EMAIL=your@email \
 *   PW_ADMIN_PASSWORD='...' \
 *   npx playwright test tests/e2e/specs/picking-console-complete-all-staging.spec.ts --headed
 *
 * Requires staff with admin, ramos edit, or supervisor on modules (same as console edit rules).
 */

import { test, expect, Page } from '@playwright/test';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';

const BASE_URL = process.env.PW_BASE_URL || 'http://127.0.0.1:8080';

function consoleUrl(): string {
  return `${BASE_URL}/admin/ramos/picking/console`;
}

function consoleRoot(page: Page) {
  return page.locator('#ramos-console-modules');
}

function pickForms(page: Page) {
  return consoleRoot(page).locator('form[action*="update_item"]');
}

/**
 * Parse quantity from a table cell (US-style thousands: "10,002,000,000.00"
 * or EU-style "10.002.000.000,00").
 */
function parseQty(text: string | null): number {
  if (!text) return 1;
  let t = text.trim().replace(/\s/g, '');
  if (/^\d{1,3}(,\d{3})*(\.\d+)?$/.test(t)) {
    t = t.replace(/,/g, '');
  } else if (/^\d{1,3}(\.\d{3})*(,\d+)?$/.test(t)) {
    t = t.replace(/\./g, '').replace(',', '.');
  }
  const n = parseFloat(t);
  return Number.isFinite(n) && n > 0 ? n : 1;
}

function parseDisplayedFloat(text: string | null): number {
  if (!text) return 0;
  let t = text.trim().replace(/\s/g, '');
  if (/^\d{1,3}(,\d{3})*(\.\d+)?$/.test(t)) {
    t = t.replace(/,/g, '');
  } else if (/^\d{1,3}(\.\d{3})*(,\d+)?$/.test(t)) {
    t = t.replace(/\./g, '').replace(',', '.');
  }
  const n = parseFloat(t.replace(/[^\d.-]/g, ''));
  return Number.isFinite(n) ? n : 0;
}

/**
 * Submit the first pick row that still needs work (picked < required or weight missing).
 * The console lists every line on a partial order; DOM-first form may already be completed.
 */
async function submitFirstPendingPick(page: Page): Promise<boolean> {
  const forms = pickForms(page);
  const n = await forms.count();
  if (n === 0) {
    return false;
  }

  let form = null;
  let required = 1;

  for (let i = 0; i < n; i++) {
    const candidate = forms.nth(i);
    await candidate.scrollIntoViewIfNeeded().catch(() => {});
    if (!(await candidate.isVisible({ timeout: 2000 }).catch(() => false))) {
      continue;
    }

    const row = candidate.locator('xpath=ancestor::tr');
    const requiredText = await row.locator('td').nth(1).textContent();
    const pickedText = await row.locator('td').nth(2).textContent();
    const weightText = await row.locator('td').nth(3).textContent();

    const req = parseQty(requiredText);
    const picked = parseDisplayedFloat(pickedText);
    const weightVal = parseDisplayedFloat(weightText);

    const lineDone = picked >= req && weightVal > 0;
    if (lineDone) {
      continue;
    }

    form = candidate;
    required = req;
    break;
  }

  if (!form) {
    return false;
  }

  const pickedStr = String(required);

  // Set values inside the form DOM — Playwright .fill() can fail or clamp huge numbers in <input type="number">.
  await form.evaluate(
    (f, vals: { picked: string; weight: string }) => {
      const pq = f.querySelector<HTMLInputElement>('input[name="picked_qty"]');
      const w = f.querySelector<HTMLInputElement>('input[name="weight"]');
      if (pq) pq.value = vals.picked;
      if (w) w.value = vals.weight;
    },
    { picked: pickedStr, weight: '1' }
  );

  // Native submit bypasses HTML5 constraint validation (large qty / step issues on staging data).
  const requestPromise = page.waitForRequest(
    (r) => r.method() === 'POST' && r.url().includes('update_item'),
    { timeout: 90_000 }
  );
  await form.evaluate((f: HTMLFormElement) => {
    f.submit();
  });
  const req = await requestPromise;
  const resp = await req.response();
  if (!resp || (resp.status() !== 200 && resp.status() !== 302 && resp.status() !== 303)) {
    throw new Error(`update_item failed or unexpected status: ${resp?.status()}`);
  }

  await page.waitForLoadState('domcontentloaded');

  // Full navigation so the console reflects DB state (avoid stale DOM / AJAX partial mismatch).
  await page.goto(consoleUrl(), { waitUntil: 'domcontentloaded' });
  await consoleRoot(page).waitFor({ state: 'visible', timeout: 30_000 });

  return true;
}

test.describe('Picking console — complete all items (staging)', () => {
  test('fill picked qty and weight for every line until no pending orders', async ({ page }) => {
    test.setTimeout(1_200_000);
    test.skip(!creds.admin.password?.trim(), 'Set PW_ADMIN_PASSWORD (or creds.admin) for login.');

    await loginAdmin(page, creds.admin);
    await page.waitForURL(/admin/, { timeout: 20000 });

    await page.goto(consoleUrl(), { waitUntil: 'domcontentloaded' });
    await consoleRoot(page).waitFor({ state: 'visible', timeout: 30_000 });
    await page.waitForLoadState('networkidle').catch(() => {});

    const maxSubmits = 500;
    let submits = 0;
    let lastRemaining = -1;
    let stuckIterations = 0;

    while (submits < maxSubmits) {
      const remaining = await pickForms(page).count();
      if (remaining === 0) {
        break;
      }
      if (remaining === lastRemaining) {
        stuckIterations += 1;
        if (stuckIterations >= 3) {
          throw new Error(
            `Pick console still shows ${remaining} row(s) after repeated submits — check permissions, PO-blocked lines, or bad qty data.`
          );
        }
      } else {
        stuckIterations = 0;
      }
      lastRemaining = remaining;

      const done = await submitFirstPendingPick(page);
      if (!done) {
        // Every visible line looks complete; reload in case orders just dropped off.
        await page.goto(consoleUrl(), { waitUntil: 'domcontentloaded' });
        await consoleRoot(page).waitFor({ state: 'visible', timeout: 30_000 });
        const still = await pickForms(page).count();
        if (still === 0) {
          break;
        }
        throw new Error(
          `No incomplete pick row found but ${still} form(s) remain — inspect PO-blocked lines or UI state.`
        );
      }
      submits += 1;
    }

    await page.goto(consoleUrl(), { waitUntil: 'domcontentloaded' }).catch(() =>
      page.reload({ waitUntil: 'domcontentloaded' })
    );

    const updateForms = pickForms(page);
    await expect(updateForms).toHaveCount(0);

    const modulePanels = consoleRoot(page).locator('.ramos-console-module');

    const moduleCount = await modulePanels.count();
    expect(moduleCount).toBeGreaterThan(0);

    for (let i = 0; i < moduleCount; i++) {
      const panel = modulePanels.nth(i);
      await expect(panel.getByText(/No orders pending for this module|Sin pedidos pendientes para este módulo/i)).toBeVisible({
        timeout: 15000,
      });
    }

    await expect(page.locator('.label-warning:has-text("Weight missing")')).toHaveCount(0);
    await expect(page.locator('.label-warning:has-text("Peso faltante")')).toHaveCount(0);
  });
});
