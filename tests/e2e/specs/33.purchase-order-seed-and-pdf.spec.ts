import { test, expect } from '@playwright/test';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded } from '../utils/guards';

test.describe('33. Seed Purchase Order + verify PDF', () => {
  test.skip(!creds.admin.password, 'PW_ADMIN_PASSWORD not set');

  test('seed a sample PO (local-only) and verify purorder_pdf returns %PDF', async ({ page }) => {
    test.setTimeout(90000);
    await loginAdmin(page, creds.admin);

    // Seed a PO if list is empty (endpoint is local-only and safe).
    await page.goto('/admin/purchase/seed_sample_pur_order', { waitUntil: 'domcontentloaded' });
    // Capture alert (success/failure) if present.
    const alert = page.locator('.alert').first();
    if ((await alert.count()) > 0) {
      const msg = (await alert.innerText()).trim();
      // If something went wrong, fail early with the server-provided message.
      if (/failed|danger|no vendors/i.test(msg.toLowerCase())) {
        throw new Error(`Seed failed: ${msg}`);
      }
    }

    await page.goto('/admin/purchase/purchase_order', { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);

    const table = page.locator('table.table-table_pur_order').first();
    await expect(table).toBeVisible({ timeout: 30000 });

    // Wait for DataTable to populate (not "No entries found").
    await expect(table.locator('tbody tr td.dataTables_empty')).toHaveCount(0, { timeout: 30000 });

    const firstLink = table.locator('tbody tr a[href*="purchase/purchase_order/"]').first();
    await expect(firstLink).toBeVisible({ timeout: 30000 });

    const href = await firstLink.getAttribute('href');
    expect(href, 'Expected first PO link to have href').toBeTruthy();

    const match = String(href).match(/purchase\/purchase_order\/(\d+)/);
    expect(match, `Could not parse PO id from href: ${href}`).toBeTruthy();
    const poId = match ? match[1] : '0';

    // Open the PO preview page (UI sanity).
    await page.goto(`/admin/purchase/purchase_order/${poId}`, { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);
    await expect(page.locator('table.items-preview, table.estimate-items-preview').first()).toBeVisible({
      timeout: 30000,
    });

    // Fetch the PDF directly; this avoids flaky download dialogs and still validates the TCPDF headers fix.
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

