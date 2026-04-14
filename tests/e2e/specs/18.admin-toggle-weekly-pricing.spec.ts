import { test, expect } from '@playwright/test';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded } from '../utils/guards';

test.describe('18. Admin customer weekly pricing toggle', () => {
  test.skip(!creds.admin.password, 'PW_ADMIN_PASSWORD not set – skipping admin weekly pricing toggle test');

  test('admin can toggle customer week flag and it persists', async ({ page }) => {
    await loginAdmin(page, creds.admin);

    // Use the known seeded test customer (usuario@3ware.mx => userid=3).
    await page.goto('/admin/clients/client/3?group=profile', { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);
    await expect(page).toHaveURL(/\/admin\/clients\/client\/3/i);

    // Ensure the week select exists (bootstrap-select may hide the <select>).
    const weekSelect = page.locator('select#week, select[name="week"]').first();
    await weekSelect.waitFor({ state: 'attached', timeout: 10000 });

    // Toggle to Yes (1) and save.
    await weekSelect.selectOption('1');
    const saveBtn = page.locator('#profile-save-section button.only-save, #profile-save-section button:has-text(\"Submit\"), #profile-save-section button:has-text(\"Enviar\"), #profile-save-section button:has-text(\"Guardar\"), #profile-save-section button:has-text(\"Save\"), #profile-save-section button.btn-primary').first();
    await expect(saveBtn).toBeVisible();
    await Promise.all([page.waitForLoadState('domcontentloaded'), saveBtn.click()]);

    // Re-open and confirm it stayed 1.
    await page.goto('/admin/clients/client/3?group=profile', { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);
    await expect(weekSelect).toHaveValue('1');

    // Toggle back to No (0) and save.
    await weekSelect.selectOption('0');
    await Promise.all([page.waitForLoadState('domcontentloaded'), saveBtn.click()]);

    // Re-open and confirm it stayed 0.
    await page.goto('/admin/clients/client/3?group=profile', { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);
    await expect(weekSelect).toHaveValue('0');

    // No fatal PHP errors in the response body.
    await expect(page.locator('body')).not.toContainText(/Fatal error|Uncaught|SQLSTATE/i);
  });
});

