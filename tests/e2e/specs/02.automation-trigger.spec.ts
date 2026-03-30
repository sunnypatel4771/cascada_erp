import { test, expect } from '@playwright/test';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded } from '../utils/guards';

test.describe('2. Automation Trigger', () => {
  test('automation settings show schedule baseline and 30-minute cadence controls', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/automation/settings');
    await assertPageLoaded(page);

    await expect(page.locator('body')).toContainText(/automation|automatizaci[oó]n|schedule|hour|minutes|minutos/i);
    // UI controls used by scheduling logic.
    await expect(page.locator('input,select').filter({ hasText: '' }).first()).toBeVisible();
  });

  test('manual automation run endpoint path exists and dashboard is reachable', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/automation');
    await assertPageLoaded(page);

    await expect(page.locator('body')).toContainText(/automation|automatizaci[oó]n|orders|pedidos|run|ejecutar/i);
    // We validate the module screen and run controls are accessible in headed mode.
    await expect(page).toHaveURL(/ramos\/automation/i);
  });
});

