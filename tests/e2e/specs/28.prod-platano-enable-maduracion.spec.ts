import { test, expect } from '@playwright/test';
import { loginAdmin, loginCustomer } from '../utils/auth';

const admin = {
  email: process.env.PW_ADMIN_EMAIL || 'developer@3ware.mx',
  password: process.env.PW_ADMIN_PASSWORD || '',
};

const customer = {
  email: process.env.PW_CUSTOMER_EMAIL || 'usuario@3ware.mx',
  password: process.env.PW_CUSTOMER_PASSWORD || '',
};

test.describe('28. PROD — enable maduración for PLATANO', () => {
  test('enable has_maduracion for PLATANO (inventory + portal verify)', async ({ page }) => {
    test.setTimeout(120000);
    const base = (process.env.PW_BASE_URL || '').replace(/\/$/, '');
    if (!base) {
      test.skip(true, 'PW_BASE_URL not set');
    }

    if (!admin.password) {
      test.skip(true, 'PW_ADMIN_PASSWORD not set');
    }
    if (!customer.password) {
      test.skip(true, 'PW_CUSTOMER_PASSWORD not set');
    }

    // --- Customer: capture the exact PLATANO label from /clients ---
    await loginCustomer(page, customer);
    await page.goto(`${base}/clients`, { waitUntil: 'domcontentloaded' });
    const prodSelect = page.locator('select.product-select').first();
    await expect(prodSelect).toBeVisible({ timeout: 20000 });
    const platano = await prodSelect.evaluate((el) => {
      const sel = el as HTMLSelectElement;
      const opt = Array.from(sel.options).find((o) => /PLATANO/i.test(o.textContent || o.label || ''));
      return { value: opt?.value || '', label: (opt?.textContent || opt?.label || '').trim() };
    });
    expect(platano.value, 'Expected product-select to contain PLATANO option').toBeTruthy();
    expect(platano.label, 'Expected PLATANO option to have a label').toBeTruthy();

    await page.goto(`${base}/authentication/logout`, { waitUntil: 'domcontentloaded' }).catch(() => {});

    await loginAdmin(page, admin);

    async function gotoAdmin(path: string) {
      const candidates = [`/admin/${path}`, `/index.php/admin/${path}`];
      for (const url of candidates) {
        await page.goto(url, { waitUntil: 'domcontentloaded' });
        const bodyText = await page.locator('body').innerText().catch(() => '');
        if (!/404|This Page Does Not Exist/i.test(bodyText)) return;
      }
      throw new Error(`Admin path not found: ${candidates.join(' | ')}`);
    }

    // --- Admin: enable maduración for the matching PLATANO inventory item ---
    await gotoAdmin('ramos/inventory');
    const searchInput = page.locator('input[name="search"]').first();
    if (await searchInput.isVisible().catch(() => false)) {
      // Inventory names may include size/unit suffixes; search by keyword.
      await searchInput.fill('PLATANO');
      await Promise.all([
        page.waitForLoadState('domcontentloaded'),
        page.locator('form button[type="submit"]').first().click(),
      ]);
    } else {
      // fallback
      await gotoAdmin('ramos/inventory?search=PLATANO');
    }

    const row = page.locator('table tbody tr').filter({ hasText: /PLATANO/i }).first();
    const hasRow = await row.isVisible().catch(() => false);

    if (hasRow) {
      const editBtn = row.locator('button.ramos-edit-item').first();
      await expect(editBtn, 'Expected edit button for PLATANO row').toBeVisible({ timeout: 15000 });
      await editBtn.click();
      const editModal = page.locator('#ramosInventoryEditModal');
      await expect(editModal).toBeVisible({ timeout: 10000 });

      const chk = editModal.locator('#ramos_inventory_has_maduracion_edit');
      await chk.check();

      await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }),
        editModal.locator('button[type="submit"]').click(),
      ]);
    } else {
      // If we can't find it, try to create it (requires Ramos create permission).
      const addBtn = page.locator('button[data-target="#ramosInventoryModal"]').first();
      if (!(await addBtn.isVisible().catch(() => false))) {
        throw new Error(
          'No inventory row containing "PLATANO" was found, and the Add button is not available. This usually means PLATANO is not created in Ramos Inventory and your admin user lacks Ramos create permission.'
        );
      }
      await addBtn.click();
      const addModal = page.locator('#ramosInventoryModal');
      await expect(addModal).toBeVisible({ timeout: 10000 });
      await addModal.locator('input[name="item_name"]').fill('PLATANO');
      await addModal.locator('#ramos_inventory_has_maduracion_add').check();
      await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }),
        addModal.locator('button[type="submit"]').click(),
      ]);
    }

    await page.screenshot({ path: 'test-results/prod-platano-maduracion-enabled.png', fullPage: true });

    // --- Customer: verify maduración dropdown is visible for PLATANO on /clients ---
    await page.goto(`${base}/admin/authentication/logout`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await loginCustomer(page, customer);
    await page.goto(`${base}/clients`, { waitUntil: 'domcontentloaded' });
    const prodSelect2 = page.locator('select.product-select').first();
    await expect(prodSelect2).toBeVisible({ timeout: 20000 });
    await prodSelect2.selectOption(platano.value);

    const firstRow = page.locator('#order-items-body tr').first();
    const madSel = firstRow.locator('select.maduracion-select').first();
    await expect(madSel).toBeVisible({ timeout: 15000 });
    await madSel.selectOption('Verde');
    await expect(madSel).toHaveValue('Verde');
    await page.screenshot({ path: 'test-results/prod-platano-maduracion-selected.png', fullPage: true });
  });
});

