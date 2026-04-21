import { test, expect, type Locator } from '@playwright/test';
import { loginAdmin, loginCustomer } from '../utils/auth';

const admin = {
  email: process.env.PW_ADMIN_EMAIL || 'developer@3ware.mx',
  password: process.env.PW_ADMIN_PASSWORD || '',
};

const customer = {
  email: process.env.PW_CUSTOMER_EMAIL || 'usuario@3ware.mx',
  password: process.env.PW_CUSTOMER_PASSWORD || 'ramos123',
};

test.describe('29. GARBANZO — enable maduración ($17)', () => {
  test('enable has_maduracion for GARBANZO kilo line (inventory + portal verify)', async ({ page }) => {
    test.setTimeout(120000);
    const base = (process.env.PW_BASE_URL || '').replace(/\/$/, '') || 'http://127.0.0.1:8080';

    await loginCustomer(page, customer);
    await page.goto(`${base}/clients`, { waitUntil: 'domcontentloaded' });

    const prodSelect = page.locator('select.product-select').first();
    await expect(prodSelect).toBeVisible({ timeout: 20000 });

    const garbanzo = await page.evaluate(() => {
      const sel = document.querySelector('select.product-select') as HTMLSelectElement | null;
      if (!sel) return null;
      const want = 17;
      let fallback: { value: string; rate: number; label: string } | null = null;
      for (let i = 0; i < sel.options.length; i++) {
        const opt = sel.options[i];
        const v = (opt.value || '').trim();
        if (!v) continue;
        const upper = v.toUpperCase();
        if (upper !== 'GARBANZO') continue;
        const rate = parseFloat(opt.getAttribute('data-rate') || '0');
        const label = (opt.textContent || opt.label || v).trim();
        if (Math.abs(rate - want) < 0.02) {
          return { value: v, rate, label };
        }
        if (!fallback) {
          fallback = { value: v, rate, label };
        }
      }
      return fallback;
    });

    expect(garbanzo?.value, 'Expected product-select to contain a GARBANZO option').toBeTruthy();

    if (admin.password) {
      await page.goto(`${base}/authentication/logout`, { waitUntil: 'domcontentloaded' }).catch(() => {});

      await loginAdmin(page, admin);

      async function gotoAdmin(path: string) {
        const candidates = [`${base}/admin/${path}`, `${base}/index.php/admin/${path}`];
        for (const url of candidates) {
          await page.goto(url, { waitUntil: 'domcontentloaded' });
          const bodyText = await page.locator('body').innerText().catch(() => '');
          if (!/404|This Page Does Not Exist/i.test(bodyText)) return;
        }
        throw new Error(`Admin path not found: ${candidates.join(' | ')}`);
      }

      await gotoAdmin('ramos/inventory');
      const searchInput = page.locator('input[name="search"]').first();
      if (await searchInput.isVisible().catch(() => false)) {
        await searchInput.fill(garbanzo!.label);
        await Promise.all([
          page.waitForLoadState('domcontentloaded'),
          page.locator('form button[type="submit"]').first().click(),
        ]);
      } else {
        await gotoAdmin(`ramos/inventory?search=${encodeURIComponent(garbanzo!.label)}`);
      }

      let targetRow = page.locator('table tbody tr').filter({ hasText: garbanzo!.label }).first();
      let hasRow = await targetRow.isVisible().catch(() => false);
      if (!hasRow) {
        targetRow = page.locator('table tbody tr').filter({ hasText: /GARBANZO/i }).first();
        hasRow = await targetRow.isVisible().catch(() => false);
      }

      if (hasRow) {
        const editBtn = targetRow.locator('button.ramos-edit-item').first();
        await expect(editBtn, 'Expected edit button for GARBANZO row').toBeVisible({ timeout: 15000 });
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
        const addBtn = page.locator('button[data-target="#ramosInventoryModal"]').first();
        if (!(await addBtn.isVisible().catch(() => false))) {
          throw new Error(
            'No GARBANZO inventory row and Add button unavailable — create the row in Ramos Inventario or grant permission.'
          );
        }
        await addBtn.click();
        const addModal = page.locator('#ramosInventoryModal');
        await expect(addModal).toBeVisible({ timeout: 10000 });
        await addModal.locator('input[name="item_name"]').fill(garbanzo!.label);
        await addModal.locator('#ramos_inventory_has_maduracion_add').check();
        await Promise.all([
          page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }),
          addModal.locator('button[type="submit"]').click(),
        ]);
      }

      await page.screenshot({ path: 'test-results/garbanzo-maduracion-enabled.png', fullPage: true });

      await page.goto(`${base}/admin/authentication/logout`, { waitUntil: 'domcontentloaded' }).catch(() => {});
      await loginCustomer(page, customer);
      await page.goto(`${base}/clients`, { waitUntil: 'domcontentloaded' });
    } else {
      // No admin creds: verify portal after inventory was configured elsewhere (e.g. DB or manual admin).
      await page.reload({ waitUntil: 'domcontentloaded' });
    }

    const tbody = page.locator('#order-items-body');
    await expect(tbody).toBeVisible({ timeout: 20000 });

    const rows = tbody.locator('tr');
    const n = await rows.count();
    let target: Locator | null = null;
    for (let i = 0; i < n; i++) {
      const row = rows.nth(i);
      const prodSel = row.locator('select.product-select').first();
      if ((await prodSel.count()) === 0) {
        continue;
      }
      const v = await prodSel.inputValue();
      if (v === garbanzo!.value) {
        target = row;
        break;
      }
    }

    if (!target) {
      await page.locator('button[onclick="addNewRow()"]').first().click();
      target = tbody.locator('tr').last();
      await target.locator('select.product-select').first().selectOption(garbanzo!.value);
    }

    await expect(target.locator('.maduracion-select-wrap')).toBeVisible({ timeout: 15000 });
    const madNative = target.locator('select.maduracion-select').first();
    await madNative.selectOption('Verde');
    await expect(madNative).toHaveValue('Verde');

    await page.screenshot({ path: 'test-results/garbanzo-maduracion-selected.png', fullPage: true });
  });
});
