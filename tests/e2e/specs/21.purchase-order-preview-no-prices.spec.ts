import { test, expect } from '@playwright/test';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded } from '../utils/guards';

test.describe('21. Purchase Order preview hides prices', () => {
  test.skip(!creds.admin.password, 'PW_ADMIN_PASSWORD not set');

  test('purchase_order#7 preview omits price/tax/discount/total columns', async ({ page }) => {
    test.setTimeout(60000);
    await loginAdmin(page, creds.admin);

    await page.goto('/admin/purchase/purchase_order#7', { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);

    // Wait for the small-table preview to load
    const previewTable = page.locator('table.items-preview, table.estimate-items-preview').first();
    await expect(previewTable).toBeVisible({ timeout: 30000 });

    const headerText = (await previewTable.locator('thead').innerText()).toLowerCase();

    // These are the "NOW" columns in the client screenshot (EN/ES variants).
    const forbidden = [
      'unit price',
      'precio unitario',
      'tax',
      'impuesto',
      'discount',
      'descuento',
      'sub total',
      'subtotal',
      'total',
      'into money',
      'en dinero',
    ];

    for (const token of forbidden) {
      expect(headerText, `Header should not include "${token}"`).not.toContain(token);
    }

    // Sanity: should still have items + quantity columns
    expect(headerText).toContain('items');
    expect(headerText).toContain('quantity');
  });
});

