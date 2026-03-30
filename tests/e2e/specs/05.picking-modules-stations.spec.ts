import { test, expect } from '@playwright/test';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded, guardPickingData } from '../utils/guards';

test.describe('5. Picking Modules and Stations', () => {
  test('picking module management and assignments page loads', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/picking');
    await assertPageLoaded(page);

    await expect(page.locator('body')).toContainText(/picking|module|m[oó]dulo|staff|products|productos/i);
  });

  test('picking console shows workflow statuses and item edit controls', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/picking/console');
    await assertPageLoaded(page);
    await guardPickingData(page);

    await expect(page.locator('body')).toContainText(/progress|progreso|weight|peso|required|picked|pending|completed|completado/i);
  });
});

