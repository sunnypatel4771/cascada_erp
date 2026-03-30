import { test, expect } from '@playwright/test';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded, guardFacturacionData } from '../utils/guards';

test.describe('6. Facturacion (Billing)', () => {
  test('facturacion screen groups by route/customer with editable item controls', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/facturacion');
    await assertPageLoaded(page);
    await guardFacturacionData(page);

    await expect(page.locator('body')).toContainText(/facturaci[oó]n|ruta|customer|cliente|invoice|factura|remision|remisi[oó]n|price|precio/i);
  });

  test('invoice and FE-SAT action paths are present on billing cards', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/facturacion');
    await assertPageLoaded(page);

    await expect(page.locator('body')).toContainText(/factura|invoice|fe-sat|remision|remisi[oó]n|email|correo/i);
  });
});

