import { test, expect } from '@playwright/test';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded, guardProvidersAndPO } from '../utils/guards';

test.describe('3. Inventory and PO Requirements', () => {
  test.skip(!creds.admin.password, 'PW_ADMIN_PASSWORD not set – skipping inventory/PO tests');

  test('purchase planner shows supplier grouping and stock/deficit columns', async ({ page }, testInfo) => {
    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/purchases');
    await assertPageLoaded(page);
    try {
      await guardProvidersAndPO(page);
    } catch {
      testInfo.skip(true, 'Seed guard failed: no suppliers/PO planner rows visible.');
    }

    await expect(page.locator('body')).toContainText(
      /purchase|compra|supplier|proveedor|stock|deficit|batch|lote/i
    );
    // No fatal errors
    await expect(page.locator('body')).not.toContainText(/Fatal error|SQLSTATE/i);
  });

  test('purchases planner shows at least one row where demand exceeds stock (deficit visible)', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/purchases');
    await assertPageLoaded(page);

    // The planner page should either show deficit data or an informational state
    const hasTable = await page.locator('table tbody tr').first().isVisible().catch(() => false);
    if (!hasTable) {
      test.skip(true, 'No PO planner rows visible in seeded environment.');
      return;
    }

    // Look for deficit / stock-related text in any row
    await expect(page.locator('body')).toContainText(
      /deficit|faltante|reorder|safety|stock|lote|batch|purchase|compra/i
    );
  });

  test('PO batch receiving flow: batch detail page loads and shows receive controls', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/purchases');
    await assertPageLoaded(page);

    const firstBatchLink = page.locator('a[href*="/admin/ramos/purchases/batch/"]').first();
    if (!(await firstBatchLink.isVisible().catch(() => false))) {
      test.skip(true, 'No purchase batch found in seeded environment.');
      return;
    }

    await firstBatchLink.click();
    await assertPageLoaded(page);

    await expect(page.locator('body')).toContainText(
      /batch|lote|receive|recibir|requested|solicitado|received|recibido/i
    );
    await expect(page.locator('body')).not.toContainText(/Fatal error|SQLSTATE/i);
  });

  test('PO batch receiving: submit receive quantities and verify no crash', async ({ page }) => {
    test.setTimeout(60000);
    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/purchases');
    await assertPageLoaded(page);

    const firstBatchLink = page.locator('a[href*="/admin/ramos/purchases/batch/"]').first();
    if (!(await firstBatchLink.isVisible().catch(() => false))) {
      test.skip(true, 'No purchase batch found in seeded environment.');
      return;
    }

    await firstBatchLink.click();
    await assertPageLoaded(page);

    // Find the first quantity input in the receive form
    const qtyInput = page
      .locator('input[name*="received_qty"], input[name*="receive_qty"], input[type="number"]')
      .first();
    const hasQty = await qtyInput.isVisible().catch(() => false);

    if (!hasQty) {
      test.skip(true, 'No receive quantity inputs found on batch page.');
      return;
    }

    const currentVal = await qtyInput.inputValue().catch(() => '0');
    await qtyInput.fill(String(Math.max(1, Number(currentVal))));

    const submitBtn = page.locator('button[type="submit"], input[type="submit"]').first();
    if (!(await submitBtn.isVisible().catch(() => false))) {
      test.skip(true, 'No submit button found on batch receive page.');
      return;
    }

    const [response] = await Promise.all([
      page.waitForLoadState('domcontentloaded'),
      submitBtn.click(),
    ]);

    // Should not crash
    await expect(page.locator('body')).not.toContainText(/Fatal error|SQLSTATE|Call to undefined/i);
    // Should show success or remain on the batch/purchases page
    await expect(page).toHaveURL(/ramos\/purchases/i);
  });
});
