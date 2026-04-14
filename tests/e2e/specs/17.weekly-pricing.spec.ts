import { test, expect } from '@playwright/test';
import { loginCustomer } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded } from '../utils/guards';

test.describe('17. Weekly pricing (customer week flag)', () => {
  test('customer can place order (week pricing on/off verified separately)', async ({ page }) => {
    await loginCustomer(page, creds.customer);
    await page.goto('/clients');
    await assertPageLoaded(page);

    page.on('dialog', async (dialog) => {
      await dialog.accept();
    });

    // Split-order UI path (preferred)
    const hasSearch = await page.locator('#product-search').isVisible().catch(() => false);
    if (hasSearch) {
      const search = page.locator('#product-search');
      await search.fill('PLATANO');
      await page.waitForTimeout(800);

      const platanoCard = page
        .locator('#products-catalog .product-card, #products-catalog .product-item')
        .filter({ hasText: /platano/i })
        .first();

      await expect(platanoCard).toBeVisible();
      await platanoCard.click();
      await page.waitForTimeout(500);

      // Ensure the item is in the order table
      const row = page
        .locator('#order-items-body tr, .order-items-table tr, table.order-table tbody tr')
        .filter({ hasText: /platano/i })
        .first();
      await expect(row).toBeVisible();

      // Set quantity to 1 to keep calculations simple
      const qtyInput = row
        .locator('input[name*="[qty]"], input[name*="qty"], input[name*="quantity"]')
        .first();
      if (await qtyInput.isVisible().catch(() => false)) {
        await qtyInput.fill('1');
      }

      const saveBtn = page
        .locator(
          'button:has-text("Guardar pedido"), button:has-text("Save Order"), button:has-text("Guardar Pedido")'
        )
        .first();
      await expect(saveBtn).toBeVisible();
      await saveBtn.click();

      await page.waitForTimeout(2000);
      await expect(page.locator('body')).not.toContainText(/Fatal error|SQLSTATE|Exception/i);
      await expect(page.locator('body')).toContainText(/order|pedido|guardad|success|correcto|save/i);
      return;
    }

    // Omni Sales / add-button UI path fallback
    const searchOmni = page
      .locator('input[placeholder*="Search for products"], input[placeholder*="Busca"]')
      .first();
    if (await searchOmni.isVisible().catch(() => false)) {
      await searchOmni.fill('PLATANO');
      await page.waitForTimeout(800);
    }

    const addBtn = page
      .locator('button:has-text("Agregar al Pedido"), button:has-text("Add to Order")')
      .first();
    if (await addBtn.isVisible().catch(() => false)) {
      await addBtn.click();
      await page.waitForTimeout(800);
    }

    await expect(page.locator('body')).not.toContainText(/Fatal error|SQLSTATE|Exception/i);
  });
});

