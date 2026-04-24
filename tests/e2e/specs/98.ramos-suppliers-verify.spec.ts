import { expect, test } from '@playwright/test';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';

test('Ramos suppliers list shows imported suppliers', async ({ page }) => {
  test.setTimeout(120_000);
  if (!creds.admin.password) {
    test.skip(true, 'PW_ADMIN_PASSWORD is required');
  }

  await loginAdmin(page, creds.admin);
  await page.goto('/admin/ramos/suppliers', { waitUntil: 'domcontentloaded' });

  // Table should not be empty after import
  await expect(page.locator('table')).toBeVisible();
  await expect(page.locator('text=/No suppliers added yet\\.|Aún no se han agregado proveedores\\./i')).toHaveCount(0);

  // Spot-check one known imported supplier
  await expect(page.locator('table')).toContainText(/EVA/i);
});

