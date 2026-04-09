/**
 * Regression: each pick line (ramos_pick_items row) must appear in only one module column.
 * Same product on multiple module configs used to duplicate rows; backend keeps one canonical module per product.
 */

import { test, expect } from '@playwright/test';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded } from '../utils/guards';

test.describe('Picking console — single module per order', () => {
  test('each pick form (update_item id) appears at most once on the console', async ({ page }) => {
    test.skip(!creds.admin.password?.trim(), 'Set PW_ADMIN_PASSWORD for admin login.');

    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/picking/console');
    await assertPageLoaded(page);

    const shell = page.locator('#ramos-console-modules');
    if (!(await shell.isVisible({ timeout: 15000 }).catch(() => false))) {
      test.skip(true, 'Picking console not reachable (login/base URL/offline).');
    }

    const forms = shell.locator('form[action*="picking/update_item"]');
    const n = await forms.count();
    if (n === 0) {
      test.info().annotations.push({
        type: 'note',
        description: 'No pick rows on console — duplicate-module regression not exercised.',
      });
      return;
    }

    const pickIds = new Set<string>();
    for (let i = 0; i < n; i++) {
      const action = await forms.nth(i).getAttribute('action');
      const m = action?.match(/update_item\/(\d+)/);
      if (!m) {
        continue;
      }
      const id = m[1];
      expect(pickIds.has(id), `Pick id ${id} appears in more than one module column (duplicate assignment).`).toBe(
        false
      );
      pickIds.add(id);
    }

    const modules = shell.locator('.ramos-console-module');
    const modCount = await modules.count();
    for (let mi = 0; mi < modCount; mi++) {
      const card = modules.nth(mi);
      const mForms = card.locator('form[action*="picking/update_item"]');
      const mn = await mForms.count();
      const seenInModule = new Set<string>();
      for (let j = 0; j < mn; j++) {
        const action = await mForms.nth(j).getAttribute('action');
        const m = action?.match(/update_item\/(\d+)/);
        if (!m) {
          continue;
        }
        const id = m[1];
        expect(
          seenInModule.has(id),
          `Pick id ${id} appears twice in the same module card (duplicate rows / join fan-out).`
        ).toBe(false);
        seenInModule.add(id);
      }
    }
  });
});
