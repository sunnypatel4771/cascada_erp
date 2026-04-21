/**
 * Verifies Maduración is controlled from Ramos → Inventario (admin).
 * Requires PW_ADMIN_PASSWORD and a matching inventory row + catalog item (default: PLATANO).
 */
import { test, expect, type Locator, type Page } from '@playwright/test';
import { loginAdmin, loginCustomer } from '../utils/auth';

const admin = {
  email: process.env.PW_ADMIN_EMAIL || 'developer@3ware.mx',
  password: process.env.PW_ADMIN_PASSWORD || '',
};

const customer = {
  email: process.env.PW_CUSTOMER_EMAIL || 'usuario@3ware.mx',
  password: process.env.PW_CUSTOMER_PASSWORD || 'ramos123',
};

/** Catalog description / inventory item_name (must exist in tblramos_inventory_items). */
const TEST_PRODUCT = (process.env.PW_MADURACION_TEST_SKU || 'PLATANO').trim();

test.describe('30. Admin inventory toggles Maduración', () => {
  test('disable then enable from Ramos Inventario; portal follows', async ({ page }) => {
    test.setTimeout(180000);

    if (!admin.password) {
      test.skip(true, 'PW_ADMIN_PASSWORD not set');
    }

    const base = (process.env.PW_BASE_URL || '').replace(/\/$/, '') || 'http://127.0.0.1:8080';

    await loginAdmin(page, admin);

    async function gotoAdmin(path: string) {
      const candidates = [`${base}/admin/${path}`, `${base}/index.php/admin/${path}`];
      for (const url of candidates) {
        await page.goto(url, { waitUntil: 'domcontentloaded' });
        const bodyText = await page.locator('body').innerText().catch(() => '');
        if (!/404|This Page Does Not Exist/i.test(bodyText)) {
          return;
        }
      }
      throw new Error(`Admin path not found: ${candidates.join(' | ')}`);
    }

    async function inventoryRow() {
      return page.locator('table tbody tr[data-item]').filter({ hasText: new RegExp(TEST_PRODUCT, 'i') }).first();
    }

    async function ensureMaduracionOnAdmin() {
      await gotoAdmin(`ramos/inventory?search=${encodeURIComponent(TEST_PRODUCT)}`);
      const row = await inventoryRow();
      await expect(row, `Inventory row for ${TEST_PRODUCT}`).toBeVisible({ timeout: 20000 });
      const onBadge = row.locator('.label-success');
      if (await onBadge.isVisible().catch(() => false)) {
        return;
      }
      await row.locator('button.ramos-edit-item').click();
      const editModal = page.locator('#ramosInventoryEditModal');
      await expect(editModal).toBeVisible({ timeout: 10000 });
      await editModal.locator('#ramos_inventory_has_maduracion_edit').check();
      await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }),
        editModal.locator('button[type="submit"]').click(),
      ]);
      await gotoAdmin(`ramos/inventory?search=${encodeURIComponent(TEST_PRODUCT)}`);
      const row2 = await inventoryRow();
      await expect(row2.locator('.label-success')).toBeVisible({ timeout: 15000 });
    }

    await ensureMaduracionOnAdmin();

    // --- Disable Maduración (admin) ---
    await gotoAdmin(`ramos/inventory?search=${encodeURIComponent(TEST_PRODUCT)}`);
    let row = await inventoryRow();
    await row.locator('button.ramos-edit-item').click();
    let editModal = page.locator('#ramosInventoryEditModal');
    await expect(editModal).toBeVisible({ timeout: 10000 });
    await editModal.locator('#ramos_inventory_has_maduracion_edit').uncheck();
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }),
      editModal.locator('button[type="submit"]').click(),
    ]);

    await gotoAdmin(`ramos/inventory?search=${encodeURIComponent(TEST_PRODUCT)}`);
    row = await inventoryRow();
    await expect(row.locator('.label-default').filter({ hasText: /^(No|Off)$/i })).toBeVisible({ timeout: 15000 });

    await page.goto(`${base}/admin/authentication/logout`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await loginCustomer(page, customer);
    await page.goto(`${base}/clients`, { waitUntil: 'domcontentloaded' });

    const optValue = await firstOptionValueForProduct(page, TEST_PRODUCT);
    expect(optValue, `Option for ${TEST_PRODUCT}`).toBeTruthy();

    const rowClient = await findOrderRowByProduct(page, optValue!);
    await rowClient.locator('select.product-select').first().selectOption(optValue!);
    await expect(rowClient.locator('.maduracion-select-wrap')).toBeHidden({ timeout: 15000 });
    await expect(rowClient.locator('.maduracion-placeholder')).toBeVisible({ timeout: 5000 });

    // --- Enable Maduración (admin) ---
    await page.goto(`${base}/authentication/logout`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await loginAdmin(page, admin);
    await gotoAdmin(`ramos/inventory?search=${encodeURIComponent(TEST_PRODUCT)}`);
    row = await inventoryRow();
    await row.locator('button.ramos-edit-item').click();
    editModal = page.locator('#ramosInventoryEditModal');
    await expect(editModal).toBeVisible({ timeout: 10000 });
    await editModal.locator('#ramos_inventory_has_maduracion_edit').check();
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }),
      editModal.locator('button[type="submit"]').click(),
    ]);

    await gotoAdmin(`ramos/inventory?search=${encodeURIComponent(TEST_PRODUCT)}`);
    row = await inventoryRow();
    await expect(row.locator('.label-success').filter({ hasText: /^(Sí|On)$/i })).toBeVisible({ timeout: 15000 });

    await page.goto(`${base}/admin/authentication/logout`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await loginCustomer(page, customer);
    await page.goto(`${base}/clients`, { waitUntil: 'domcontentloaded' });

    const rowClient2 = await findOrderRowByProduct(page, optValue!);
    await rowClient2.locator('select.product-select').first().selectOption(optValue!);
    await expect(rowClient2.locator('.maduracion-select-wrap')).toBeVisible({ timeout: 15000 });
    const mad = rowClient2.locator('select.maduracion-select').first();
    await mad.selectOption('Verde');
    await expect(mad).toHaveValue('Verde');

    await page.screenshot({ path: 'test-results/maduracion-admin-toggle-restored.png', fullPage: true });
  });
});

async function firstOptionValueForProduct(page: Page, label: string): Promise<string | null> {
  const sel = page.locator('select.product-select').first();
  await expect(sel).toBeVisible({ timeout: 20000 });
  return sel.evaluate((el, wanted) => {
    const s = el as HTMLSelectElement;
    const re = new RegExp(`^${wanted.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`, 'i');
    for (let i = 0; i < s.options.length; i++) {
      const o = s.options[i];
      if (re.test((o.value || '').trim())) {
        return o.value;
      }
    }
    return null;
  }, label);
}

async function findOrderRowByProduct(page: Page, productValue: string): Promise<Locator> {
  const tbody = page.locator('#order-items-body');
  await expect(tbody).toBeVisible({ timeout: 20000 });
  const rows = tbody.locator('tr');
  const n = await rows.count();
  for (let i = 0; i < n; i++) {
    const r = rows.nth(i);
    const prodSel = r.locator('select.product-select').first();
    if ((await prodSel.count()) === 0) {
      continue;
    }
    const v = await prodSel.inputValue();
    if (v === productValue) {
      return r;
    }
  }
  await page.locator('button[onclick="addNewRow()"]').first().click();
  const last = tbody.locator('tr').last();
  await last.locator('select.product-select').first().selectOption(productValue);
  return last;
}
