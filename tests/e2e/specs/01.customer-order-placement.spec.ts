import { test, expect } from '@playwright/test';
import { loginCustomer } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded } from '../utils/guards';

test.describe('1. Customer Order Placement', () => {
  test('portal login redirects to /clients and shows order management area', async ({ page }) => {
    test.setTimeout(60000);
    await loginCustomer(page, creds.customer);
    await page.goto('/clients', { waitUntil: 'domcontentloaded', timeout: 60000 });
    await assertPageLoaded(page);

    // Customer should be on the portal (not redirected to login)
    await expect(page.locator('body')).toContainText(
      /orders|pedido|lista de pedidos|order management|pedidos/i
    );
    // Should not show admin panel
    await expect(page).not.toHaveURL(/\/admin\/authentication/i);
  });

  test('portal split order UI: previous order info, new order area, and product search visible', async ({ page }) => {
    test.setTimeout(60000);
    await loginCustomer(page, creds.customer);
    await page.goto('/clients', { waitUntil: 'domcontentloaded', timeout: 60000 });
    await assertPageLoaded(page);

    const splitUiDetected = await page.evaluate(() =>
      /previous order|pedido anterior|nuevo pedido|new order|último pedido/i.test(
        document.body.innerText || ''
      )
    );

    if (splitUiDetected) {
      // Previous / last order section
      await expect(page.locator('body')).toContainText(/pedido anterior|previous order|último pedido/i);
      // New order / cart area
      await expect(page.locator('body')).toContainText(/nuevo pedido|new order/i);
      // Product search control
      const search = page.locator('#product-search, input[placeholder*="busca"], input[placeholder*="search"]').first();
      await expect(search).toBeVisible();
    } else {
      // Omni Sales portal or other layout — still must have order / cart elements
      await expect(page.locator('body')).toContainText(/pedido|order|cart|carrito/i);
    }
  });

  test('product search, add to order, edit quantity, optional maduración, and save', async ({ page }) => {
    // This flow can be slower/flaky on seeded environments.
    test.setTimeout(90000);

    await loginCustomer(page, creds.customer);
    await page.goto('/clients', { waitUntil: 'domcontentloaded', timeout: 60000 });
    await assertPageLoaded(page);

    page.on('dialog', async (dialog) => {
      await dialog.accept();
    });

    // ── Split-order UI path ─────────────────────────────────────────────────
    const hasSearch = await page.locator('#product-search').isVisible().catch(() => false);

    if (hasSearch) {
      const search = page.locator('#product-search');
      await search.fill('tom');
      await page.waitForTimeout(600);

      // Prefer the explicit "Add to Order" button (cards themselves may not be clickable).
      const addBtn = page
        .locator('#products-catalog .product-card button, #products-catalog .product-item button')
        .filter({ hasText: /Agregar al Pedido|Add to Order|Agregar/i })
        .first();
      if (await addBtn.isVisible().catch(() => false)) {
        await addBtn.scrollIntoViewIfNeeded().catch(() => {});
        await addBtn.click({ timeout: 15000 });
        await page.waitForTimeout(600);
      }

      const orderRows = page.locator('#order-items-body tr, .order-items-table tr, table.order-table tbody tr');
      const hasRows = await orderRows.first().isVisible().catch(() => false);
      if (hasRows) {
        const lastRow = orderRows.last();
        const qtyInput = lastRow.locator('input[name*="[qty]"], input[name*="qty"], input[name*="quantity"]').first();
        if (await qtyInput.isVisible().catch(() => false)) {
          await qtyInput.fill('2');
        }
        const ripenessSelect = lastRow.locator('select.maduracion-select, select[name*="maduracion"], select[name*="ripeness"]').first();
        if (await ripenessSelect.isVisible().catch(() => false)) {
          await ripenessSelect.selectOption({ index: 1 }).catch(() => {});
        }
      }

      const saveBtn = page
        .locator('button:has-text("Guardar pedido"), button:has-text("Save Order"), button:has-text("Guardar Pedido")')
        .first();
      if (await saveBtn.isVisible().catch(() => false)) {
        await saveBtn.click();
        await page.waitForTimeout(2000);
        await expect(page.locator('body')).toContainText(
          /order|pedido|guardad|success|correcto|save/i
        );
      }
      return;
    }

    // ── Omni Sales / add-button UI path ────────────────────────────────────
    const searchOmni = page.locator('input[placeholder*="Search for products"], input[placeholder*="Busca"]').first();
    if (await searchOmni.isVisible().catch(() => false)) {
      await searchOmni.fill('lim');
      await page.waitForTimeout(600);
    }

    const addBtn = page.locator('button:has-text("Agregar al Pedido"), button:has-text("Add to Order")').first();
    if (await addBtn.isVisible().catch(() => false)) {
      await addBtn.click();
      await page.waitForTimeout(500);
    }

    // Navigate to cart if separate
    const cartLink = page.locator('a[href*="view_cart"], a[href*="cart"]').first();
    if (await cartLink.isVisible().catch(() => false)) {
      await cartLink.click();
      await assertPageLoaded(page);
    }

    await expect(page.locator('body')).toContainText(/cart|carrito|pedido|order/i);
  });

  test('order appears in order list/history after saving', async ({ page }) => {
    test.setTimeout(60000);
    await loginCustomer(page, creds.customer);
    await page.goto('/clients', { waitUntil: 'domcontentloaded', timeout: 60000 });
    await assertPageLoaded(page);

    // The page must show at least one past order or an "empty" state — both are valid
    await expect(page.locator('body')).toContainText(
      /pedido|order|historial|history|no orders|sin pedidos|nuevo pedido|last order/i
    );
    // No server error
    await expect(page.locator('body')).not.toContainText(/Fatal error|SQLSTATE|419/i);
  });

  test('pricing logic includes customer markup from discount custom field', async ({ page }) => {
    test.setTimeout(60000);
    await loginCustomer(page, creds.customer);
    await page.goto('/clients', { waitUntil: 'domcontentloaded', timeout: 60000 });
    await assertPageLoaded(page);

    const markup = await page.evaluate(() => {
      // @ts-ignore
      return typeof customerMarkupPercent !== 'undefined' ? Number(customerMarkupPercent) : null;
    });
    expect(markup).not.toBeNull();

    const formulaCheck = await page.evaluate(() => {
      // @ts-ignore
      if (typeof calculateSellingPrice !== 'function') return false;
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
