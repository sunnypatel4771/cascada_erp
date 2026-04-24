import { expect, test } from '@playwright/test';
import * as fs from 'fs';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';

const xlsxPath =
  process.env.PW_CLIENTES_XLSX || '/home/usama-skakeel/Downloads/RAMOS CLIENTES ABRIL 2026 (3).xlsx';

const runLiveImport = process.env.PW_IMPORT_LIVE === '1';

test('Importar clientes (Ramos): upload XLSX, preview, optional live import', async ({ page }) => {
  test.setTimeout(runLiveImport ? 900_000 : 240_000);
  if (!creds.admin.password) {
    test.skip(true, 'PW_ADMIN_PASSWORD is required');
  }
  if (!fs.existsSync(xlsxPath)) {
    test.skip(true, `XLSX not found: ${xlsxPath}`);
  }

  await loginAdmin(page, creds.admin);

  await page.goto('/admin/importar_clientes/importar_clientes_ramos', { waitUntil: 'domcontentloaded' });

  await expect(page.locator('input[type="file"][name="file"]')).toBeVisible();
  await page.locator('input[type="file"][name="file"]').setInputFiles(xlsxPath);
  await page.getByRole('button', { name: /Subir y previsualizar/i }).click();

  // XLSX is large (many empty rows) - allow long server processing.
  await expect(page).toHaveURL(/step=preview/i, { timeout: 240_000 });

  // Should not have missing-header blocking errors
  await expect(page.locator('.alert-danger')).toHaveCount(0);
  await expect(page.locator('.alert-success')).toBeVisible();

  if (!runLiveImport) return;

  page.on('dialog', async (d) => d.accept());
  await page.getByRole('button', { name: /Confirmar e importar/i }).click({ timeout: 120_000 });
  await expect(page).toHaveURL(/step=results/i, { timeout: 120_000 });

  // Success + some counts
  const panel = page.locator('#wrapper .panel-body');
  await expect(panel.locator('.alert-success').first()).toBeVisible();
  const txt = await panel.innerText();
  // Ensure something was imported or we fail with UI text
  const m = txt.match(/Clientes insertados:\s*(\d+)/i);
  if (!m) throw new Error(`Could not find imported count in results.\n${txt}`);
  const imported = Number(m[1]);
  expect(imported).toBeGreaterThan(0);
});

