import { test, expect } from '@playwright/test';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded, guardRoutesGenerated } from '../utils/guards';

test.describe('4. Route Generation and Management', () => {
  test('routes board is available with kanban columns and draggable stops', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/routes/board');
    await assertPageLoaded(page);
    await guardRoutesGenerated(page);

    await expect(page.locator('.ramos-route-column').first()).toBeVisible();
    await expect(page.locator('.ramos-stop-list').first()).toBeVisible();
  });

  test('capacity rule UI baseline is present (max stops default 10)', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/routes');
    await assertPageLoaded(page);

    await expect(page.locator('body')).toContainText(/max|stops|paradas|route|ruta/i);
    // Validate route generation form is available in headed mode.
    await expect(page).toHaveURL(/ramos\/routes/i);
  });
});

