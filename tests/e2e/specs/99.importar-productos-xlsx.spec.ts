import { expect, test } from '@playwright/test';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';

const xlsxPath = '/home/usama-skakeel/Downloads/RAMOS PRODUCTOS ABRIL 2026 (1).xlsx';

test('Importar productos: upload XLSX, simulate, import', async ({ page }) => {
  test.setTimeout(120_000);
  if (!creds.admin.password) {
    test.skip(true, 'PW_ADMIN_PASSWORD is required');
  }

  await loginAdmin(page, creds.admin);

  await page.goto('/admin/importar_productos', { waitUntil: 'domcontentloaded' });

  // Upload step
  await expect(page.locator('input[type="file"][name="file"]')).toBeVisible();
  await page.locator('input[type="file"][name="file"]').setInputFiles(xlsxPath);
  await page.getByRole('button', { name: /Subir y continuar/i }).click();

  // Map step
  await expect(page).toHaveURL(/importar_productos\?step=map/i);
  const headerCount = await page.locator('table th').count();
  expect(headerCount).toBeGreaterThan(0);

  // Save mapping (auto mapping should include description + rate)
  await page.getByRole('button', { name: /Guardar mapeo/i }).click();

  // If mapping errors exist, fail with details
  const mappingErrors = page.locator('.alert-danger li');
  if (await mappingErrors.count()) {
    const errors = await mappingErrors.allTextContents();
    throw new Error(`Mapping blocked: ${errors.join(' | ')}`);
  }

  // Simulate
  await page.getByRole('button', { name: /Simular/i }).click();
  await expect(page).toHaveURL(/step=results/i);
  await expect(page.locator('.alert-info, .alert-success')).toBeVisible();

  // Start a fresh import run for real import
  await page.getByRole('link', { name: /Nueva importación/i }).click();
  await expect(page).toHaveURL(/admin\/importar_productos/i);

  await page.locator('input[type="file"][name="file"]').setInputFiles(xlsxPath);
  await page.getByRole('button', { name: /Subir y continuar/i }).click();
  await expect(page).toHaveURL(/step=map/i);

  await page.getByRole('button', { name: /Guardar mapeo/i }).click();
  const mappingErrors2 = page.locator('.alert-danger li');
  if (await mappingErrors2.count()) {
    const errors = await mappingErrors2.allTextContents();
    throw new Error(`Mapping blocked before import: ${errors.join(' | ')}`);
  }

  page.on('dialog', async (dialog) => {
    await dialog.accept();
  });

  await page.getByRole('button', { name: /^Importar$/i }).click();
  await expect(page).toHaveURL(/step=results/i);

  // We should see completion and no PHP fatal pages
  await expect(page.locator('text=/Importaci[oó]n completada|Simulation completed|Import completed/i')).toBeVisible();
});
