import { test, expect } from '@playwright/test';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded } from '../utils/guards';

test.describe('16. Admin Modules Page', () => {
  test.skip(!creds.admin.password, 'PW_ADMIN_PASSWORD not set – skipping admin modules test');

  test('admin/modules page loads without errors and shows modules table', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (err) => errors.push(err.message));

    await loginAdmin(page, creds.admin);

    const response = await page.goto('/admin/modules', { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);

    // Page must not return a server error status
    expect(response?.status(), `Expected 200, got ${response?.status()}`).toBeLessThan(400);

    // No fatal PHP errors rendered in the body
    const body = await page.locator('body').innerText();
    expect(body).not.toMatch(/Fatal error|TypeError|strtolower|Uncaught/i);

    // The upload section heading is visible
    await expect(page.getByText('Upload Module')).toBeVisible({ timeout: 10000 });

    // The modules data table exists and is rendered
    await expect(page.locator('table.dt-table')).toBeVisible({ timeout: 10000 });

    // At least one module row should be in the table body
    const rows = page.locator('table.dt-table tbody tr');
    const rowCount = await rows.count();
    expect(rowCount, 'Expected at least one module row in the table').toBeGreaterThan(0);

    // No JS exceptions from the page itself
    expect(errors, `Unexpected JS errors: ${errors.join(', ')}`).toHaveLength(0);
  });

  test('admin/modules page: upload form submits to correct action', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto('/admin/modules', { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);

    const form = page.locator('#module_install_form');
    await expect(form).toBeVisible({ timeout: 10000 });

    // Form action should point to modules/upload (not nested/broken)
    const action = await form.getAttribute('action');
    expect(action).toMatch(/modules\/upload/i);

    // File input and install button are inside the form (not in a rogue nested form)
    await expect(form.locator('input[name="module"]')).toBeVisible();
    await expect(form.locator('button[type="submit"]')).toBeVisible();
  });
});
