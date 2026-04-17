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

  test('purorder_pdf for same PO returns binary PDF (%PDF)', async ({ page }) => {
    test.setTimeout(60000);
    await loginAdmin(page, creds.admin);

    const poId = process.env.PW_PO_PDF_ID || '7';
    const head = await page.evaluate(async (id) => {
      const adminUrl = (window as unknown as { admin_url?: string }).admin_url;
      if (!adminUrl) {
        throw new Error('admin_url missing');
      }
      const r = await fetch(`${adminUrl}purchase/purorder_pdf/${id}`, {
        method: 'GET',
        credentials: 'include',
      });
      const buf = new Uint8Array(await r.arrayBuffer());
      return { status: r.status, prefix: String.fromCharCode(buf[0], buf[1], buf[2], buf[3]) };
    }, poId);

    expect(head.status, `purorder_pdf HTTP ${head.status}`).toBe(200);
    expect(head.prefix).toBe('%PDF');
  });
});

