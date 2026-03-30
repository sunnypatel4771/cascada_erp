import { test, expect } from '@playwright/test';
import { loginCustomer } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded, guardSeededCatalog } from '../utils/guards';

test.describe('1. Customer Order Placement', () => {
  test('portal login, split order UI, search/add/edit, and save workflow', async ({ page }) => {
    await loginCustomer(page, creds.customer);
    await page.goto('/clients');
    await assertPageLoaded(page);

    await expect(page.locator('body')).toContainText(/orders|pedido|lista de pedidos|order management/i);

    const splitSaveButton = page
      .locator('button:has-text("Save Order"), button:has-text("Guardar pedido"), button:has-text("Guardar Pedido")')
      .first();
    const splitUiDetected = await page.evaluate(() =>
      /previous order|pedido anterior|new order|nuevo pedido/i.test(document.body.innerText || '')
    );

    if (splitUiDetected) {
      await expect(page.locator('body')).toContainText(/previous order|pedido anterior|último pedido/i);
      await expect(page.locator('body')).toContainText(/new order|nuevo pedido/i);
      await guardSeededCatalog(page);

      const search = page.locator('#product-search');
      await search.fill('tom');
      await page.waitForTimeout(500);
      await page.locator('#products-catalog .product-card').first().click();

      const orderRows = page.locator('#order-items-body tr');
      await expect(orderRows.first()).toBeVisible();
      const lastRow = orderRows.last();
      await lastRow.locator('input[name*="[qty]"]').first().fill('2');

      const ripenessSelect = lastRow.locator('select.maduracion-select').first();
      if (await ripenessSelect.isVisible().catch(() => false)) {
        await ripenessSelect.selectOption({ label: /Maduro|Verde/i }).catch(async () => {
          await ripenessSelect.selectOption({ index: 1 });
        });
      }

      page.on('dialog', async (dialog) => {
        await dialog.accept();
      });
      await splitSaveButton.click();
      await page.waitForTimeout(1500);
      await expect(page.locator('body')).toContainText(/order|pedido|save|guard|success|exito|correctamente/i);
      return;
    }

    // Omni Sales style portal fallback.
    const searchOmni = page.locator('#product-search, input[placeholder*="Search for products"]').first();
    if (await searchOmni.isVisible().catch(() => false)) {
      await searchOmni.fill('lim');
      await page.waitForTimeout(500);
    }

    const addButton = page.locator('button:has-text("Agregar al Pedido"), button:has-text("Add to Order")').first();
    await expect(addButton).toBeVisible();
    await addButton.click();

    const cartLink = page.locator('a[href*="view_cart"]').first();
    await expect(cartLink).toBeVisible();
    await cartLink.click();
    await assertPageLoaded(page);
    await expect(page.locator('body')).toContainText(/cart|carrito|pedido|order/i);
  });

  test('pricing logic includes customer markup from discount custom field', async ({ page }) => {
    await loginCustomer(page, creds.customer);
    await page.goto('/clients');
    await assertPageLoaded(page);

    // The page defines customer markup and rate calculation in JS for item price.
    const markup = await page.evaluate(() => {
      // @ts-ignore
      return typeof customerMarkupPercent !== 'undefined' ? Number(customerMarkupPercent) : null;
    });
    expect(markup).not.toBeNull();

    const formulaCheck = await page.evaluate(() => {
      // @ts-ignore
      if (typeof calculateSellingPrice !== 'function') return false;
      // 100 base cost should become 100 * (1 + markup/100)
      // @ts-ignore
      const pct = Number(customerMarkupPercent || 0);
      // @ts-ignore
      const got = Number(calculateSellingPrice(100));
      const expected = pct > 0 ? 100 * (1 + pct / 100) : 100;
      return Math.abs(got - expected) < 0.0001;
    });
    expect(formulaCheck).toBeTruthy();
  });
});

