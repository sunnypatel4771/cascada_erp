import { test, expect } from '@playwright/test';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded, guardProvidersAndPO } from '../utils/guards';

test.describe('3. Inventory and PO Requirements', () => {
  test('purchase planner shows deficit analysis and supplier grouping', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/purchases');
    await assertPageLoaded(page);
    await guardProvidersAndPO(page);

    await expect(page.locator('body')).toContainText(/purchase|compra|supplier|proveedor|stock|deficit|batch|lote/i);
  });

  test('batch receiving flow is reachable for inventory update path', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/purchases');
    await assertPageLoaded(page);

    const firstBatchLink = page.locator('a[href*="/admin/ramos/purchases/batch/"]').first();
    if (await firstBatchLink.isVisible().catch(() => false)) {
      await firstBatchLink.click();
      await assertPageLoaded(page);
      await expect(page.locator('body')).toContainText(/batch|lote|receive|recibir|requested|solicitado|received|recibido/i);
    } else {
      test.skip(true, 'No purchase batch found in seeded environment.');
    }
  });
});

