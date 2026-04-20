import { test, expect } from '@playwright/test';
import { loginAdmin, loginCustomer } from '../utils/auth';

const admin = {
  email: process.env.PW_ADMIN_EMAIL || 'developer@3ware.mx',
  password: process.env.PW_ADMIN_PASSWORD || '',
};

const customer = {
  email: process.env.PW_CUSTOMER_EMAIL || 'usuario@3ware.mx',
  password: process.env.PW_CUSTOMER_PASSWORD || 'ramos123',
};

test.describe('27. NOPAL — enable maduración and select value', () => {
  test('enable has_maduracion for NOPAL in Ramos inventory, then select on /clients', async ({ page }) => {
    test.setTimeout(120000);

    if (!admin.password) {
      test.skip(true, 'PW_ADMIN_PASSWORD not set');
    }

    // --- Customer: capture the exact item label used by /clients (must match inventory.item_name) ---
    await loginCustomer(page, customer);
    await page.goto('/clients', { waitUntil: 'domcontentloaded' });

    const firstProduct = page.locator('select.product-select').first();
    await expect(firstProduct).toBeVisible({ timeout: 15000 });
    const nopal = await firstProduct.evaluate((el) => {
      const sel = el as HTMLSelectElement;
      const opt = Array.from(sel.options).find((o) => /NOPAL/i.test(o.textContent || o.label || ''));
      return { value: opt?.value || '', label: (opt?.textContent || opt?.label || '').trim() };
    });
    expect(nopal.value, 'Expected product-select to contain NOPAL option').toBeTruthy();
    expect(nopal.label, 'Expected NOPAL option to have a label').toBeTruthy();

    // --- Admin: enable has_maduracion on Ramos inventory row matching the exact label ---
    await page.goto('/authentication/logout', { waitUntil: 'domcontentloaded' }).catch(() => {});
    await loginAdmin(page, admin);
    await page.goto(`/admin/ramos/inventory?search=${encodeURIComponent(nopal.label)}`, { waitUntil: 'domcontentloaded' });

    const nopalRow = page.locator('table tbody tr').filter({ hasText: nopal.label }).first();
    const hasRow = await nopalRow.isVisible().catch(() => false);

    if (hasRow) {
      await nopalRow.locator('button.ramos-edit-item').click();
      const editModal = page.locator('#ramosInventoryEditModal');
      await expect(editModal).toBeVisible({ timeout: 10000 });
      await editModal.locator('#ramos_inventory_has_maduracion_edit').check();
      await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }),
        editModal.locator('button[type="submit"]').click(),
      ]);
    } else {
      await page.locator('button[data-target=\"#ramosInventoryModal\"]').click();
      const addModal = page.locator('#ramosInventoryModal');
      await expect(addModal).toBeVisible({ timeout: 10000 });
      await addModal.locator('input[name=\"item_name\"]').fill(nopal.label);
      await addModal.locator('#ramos_inventory_has_maduracion_add').check();
      await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }),
        addModal.locator('button[type=\"submit\"]').click(),
      ]);
    }

    // --- Customer: select maduración for NOPAL on /clients (now that has_maduracion is enabled) ---
    await page.goto('/admin/authentication/logout', { waitUntil: 'domcontentloaded' }).catch(() => {});
    await loginCustomer(page, customer);
    await page.goto('/clients', { waitUntil: 'domcontentloaded' });

    const prodSel2 = page.locator('select.product-select').first();
    await expect(prodSel2).toBeVisible({ timeout: 15000 });
    await prodSel2.selectOption(nopal.value);

    const firstRow = page.locator('#order-items-body tr').first();
    const madSel = firstRow.locator('select.maduracion-select').first();
    await expect(madSel).toBeVisible({ timeout: 15000 });
    await madSel.selectOption('Verde');
    await expect(madSel).toHaveValue('Verde');

    await page.screenshot({ path: 'test-results/nopal-maduracion-selected.png', fullPage: true });
  });
});

