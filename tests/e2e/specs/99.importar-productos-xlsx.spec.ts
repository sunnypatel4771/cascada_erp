import { expect, test } from '@playwright/test';
import * as fs from 'fs';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';

/** Default: Abril 21 2026 catalog. Override with PW_IMPORT_XLSX. */
const xlsxPath =
  process.env.PW_IMPORT_XLSX ||
  '/home/usama-skakeel/Downloads/RAMOS PRODUCTOS ABRIL 21 1 PM  2026.xlsx';

/** Set to 1 to run the final real import (writes tblitems + Ramos has_maduracion). Otherwise only simulation runs. */
const runLiveImport = process.env.PW_IMPORT_LIVE === '1';

async function setColumnSelect(page: import('@playwright/test').Page, header: string, optionValue: string) {
  const code = page.locator('td code', { hasText: header }).first();
  await expect(code).toBeVisible();
  const row = code.locator('xpath=ancestor::tr[1]');
  await expect(row).toBeVisible();
  await row.locator('select[name^="column_map"]').first().selectOption(optionValue);
}

async function setColumnMapping(page: import('@playwright/test').Page, header: string, dbField: string) {
  await setColumnSelect(page, header, `db:${dbField}`);
}

test('Importar productos: upload XLSX, map, simulate, optional live import', async ({ page }) => {
  test.setTimeout(runLiveImport ? 900_000 : 180_000);
  if (!creds.admin.password) {
    test.skip(true, 'PW_ADMIN_PASSWORD is required');
  }
  if (!fs.existsSync(xlsxPath)) {
    test.skip(true, `XLSX not found: ${xlsxPath}`);
  }

  await loginAdmin(page, creds.admin);

  await page.goto('/admin/importar_productos', { waitUntil: 'domcontentloaded' });

  await expect(page.locator('input[type="file"][name="file"]')).toBeVisible();
  await page.locator('input[type="file"][name="file"]').setInputFiles(xlsxPath);
  await page.getByRole('button', { name: /Subir y continuar/i }).click();

  await expect(page).toHaveURL(/importar_productos\?step=map/i);
  await expect(page.locator('table th').first()).toBeVisible();
  const headerCount = await page.locator('table th').count();
  expect(headerCount).toBeGreaterThan(0);

  // proveedor, modulo, Clave_sat, stock_seguridad: map to custom fields if needed, or leave ignored.
  await setColumnMapping(page, 'commodity_name', 'commodity_name');
  await setColumnMapping(page, 'long_description', 'long_description');
  await setColumnMapping(page, 'description', 'description');
  await setColumnMapping(page, 'unit', 'unit');
  await setColumnMapping(page, 'group_id', 'group_id');
  await setColumnMapping(page, 'commodity_code', 'commodity_code');
  await setColumnMapping(page, 'commodity_barcode', 'commodity_barcode');
  await setColumnMapping(page, 'sku_code', 'sku_code');
  await setColumnMapping(page, 'sku_name', 'sku_name');
  await setColumnMapping(page, 'warehouse_id', 'warehouse_id');
  await setColumnMapping(page, 'Purchase_price', 'purchase_price');
  await setColumnMapping(page, 'active', 'active');
  await setColumnMapping(page, 'without_checking_warehouse', 'without_checking_warehouse');
  await setColumnMapping(page, 'can_be_sold', 'can_be_sold');
  await setColumnMapping(page, 'can_be_inventory', 'can_be_inventory');
  await setColumnMapping(page, 'can_be_purchased', 'can_be_purchased');
  await setColumnSelect(page, 'maduracion', 'ramos_sync:has_maduracion');

  const saveMapBtn = page.getByRole('button', { name: /Guardar mapeo|Save mapping|importar_productos_save_mapping/i });
  await expect(saveMapBtn).toBeVisible();
  await saveMapBtn.click();

  const mappingErrors = page.locator('.alert-danger li');
  if (await mappingErrors.count()) {
    const errors = await mappingErrors.allTextContents();
    throw new Error(`Mapping blocked: ${errors.join(' | ')}`);
  }

  await page.getByRole('button', { name: /Simular|Simulate|importar_productos_simulate/i }).click();
  await expect(page).toHaveURL(/step=results/i);
  await expect(page.locator('#wrapper .panel-body .alert-info, #wrapper .panel-body .alert-success').first()).toBeVisible();

  // Non-empty data rows in this file are ~1368; trailing blank rows are skipped by the importer.
  const summary = page.locator('#wrapper .panel-body .alert-info, #wrapper .panel-body .alert-success').first();
  const summaryText = await summary.innerText();
  const processed = summaryText.match(/Procesadas:\s*(\d+)/i);
  expect(processed && Number(processed[1])).toBeGreaterThanOrEqual(1300);

  if (!runLiveImport) {
    return;
  }

  await page.getByRole('link', { name: /Nueva importación|New import/i }).click();
  await expect(page).toHaveURL(/admin\/importar_productos/i);

  await page.locator('input[type="file"][name="file"]').setInputFiles(xlsxPath);
  await page.getByRole('button', { name: /Subir y continuar/i }).click();
  await expect(page).toHaveURL(/step=map/i);

  await setColumnMapping(page, 'commodity_name', 'commodity_name');
  await setColumnMapping(page, 'long_description', 'long_description');
  await setColumnMapping(page, 'description', 'description');
  await setColumnMapping(page, 'unit', 'unit');
  await setColumnMapping(page, 'group_id', 'group_id');
  await setColumnMapping(page, 'commodity_code', 'commodity_code');
  await setColumnMapping(page, 'commodity_barcode', 'commodity_barcode');
  await setColumnMapping(page, 'sku_code', 'sku_code');
  await setColumnMapping(page, 'sku_name', 'sku_name');
  await setColumnMapping(page, 'warehouse_id', 'warehouse_id');
  await setColumnMapping(page, 'Purchase_price', 'purchase_price');
  await setColumnMapping(page, 'active', 'active');
  await setColumnMapping(page, 'without_checking_warehouse', 'without_checking_warehouse');
  await setColumnMapping(page, 'can_be_sold', 'can_be_sold');
  await setColumnMapping(page, 'can_be_inventory', 'can_be_inventory');
  await setColumnMapping(page, 'can_be_purchased', 'can_be_purchased');
  await setColumnSelect(page, 'maduracion', 'ramos_sync:has_maduracion');

  const saveMapBtn2 = page.getByRole('button', { name: /Guardar mapeo|Save mapping|importar_productos_save_mapping/i });
  await expect(saveMapBtn2).toBeVisible();
  await saveMapBtn2.click();
  const mappingErrors2 = page.locator('.alert-danger li');
  if (await mappingErrors2.count()) {
    const errors = await mappingErrors2.allTextContents();
    throw new Error(`Mapping blocked before import: ${errors.join(' | ')}`);
  }

  page.on('dialog', async (dialog) => {
    await dialog.accept();
  });

  await page
    .getByRole('button', { name: /^Importar$|^Import$|importar_productos_import/i })
    .click({ timeout: 120_000 });
  await expect(page).toHaveURL(/step=results/i, { timeout: 120_000 });

  // Success banner (may be translated or show language keys depending on staff language)
  await expect(page.locator('#wrapper .panel-body .alert-success, #wrapper .panel-body .alert-info').first()).toBeVisible();
});
