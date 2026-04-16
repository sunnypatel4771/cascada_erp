import { test, expect } from '@playwright/test';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded } from '../utils/guards';
import { parseInvoiceClientIdFromHtml } from '../utils/invoice-html';

function mergeTestInvoiceId(): string {
  return (
    process.env.PW_MERGE_TEST_INVOICE_ID ||
    process.env.PW_INVOICE_ID ||
    '34'
  ).trim();
}

async function fetchInvoiceEditHtml(page: Page, invoiceId: string): Promise<string> {
  const base = process.env.PW_BASE_URL?.replace(/\/$/, '') || new URL(page.url()).origin;
  const path = `/admin/invoices/invoice/${invoiceId}`;
  const r = await page.request.get(`${base}${path}`, { maxRedirects: 10, timeout: 45000 });
  expect(r.ok(), `GET ${path} expected 2xx, got ${r.status()}`).toBeTruthy();
  return r.text();
}

test.describe('22. Invoice merge (same customer)', () => {
  test.skip(!creds.admin.password, 'PW_ADMIN_PASSWORD not set – skipping invoice merge tests');

  test('merge candidates share the same clientid as the open invoice', async ({ page }, testInfo) => {
    await loginAdmin(page, creds.admin);
    const id = mergeTestInvoiceId();
    await page.goto(`/admin/invoices/invoice/${id}`, { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);
    await expect(page.locator('body')).not.toContainText(/access denied|^403$/i);

    const notFound = await page.locator('body').textContent().then((t) => /invoice not found|factura no encontrada/i.test(t || ''));
    if (notFound) {
      testInfo.skip(true, `Invoice ${id} not found – set PW_MERGE_TEST_INVOICE_ID to a valid invoice id`);
    }

    const mergeRoot = page.locator('.mergeable-invoices');
    if (!(await mergeRoot.isVisible().catch(() => false))) {
      testInfo.skip(
        true,
        `No merge candidates for invoice ${id} (or top panel hidden). Use an unpaid/draft invoice with other mergeable invoices for the same customer, or set PW_MERGE_TEST_INVOICE_ID.`
      );
    }

    await expect(mergeRoot.locator('h4')).toBeVisible();
    const baseClientId = await page.locator('select#clientid').inputValue();
    expect(baseClientId, 'Invoice should have a customer selected').toMatch(/^\d+$/);

    const checkboxes = mergeRoot.locator('input[name="invoices_to_merge[]"]');
    const count = await checkboxes.count();
    expect(count, 'Expected at least one merge checkbox').toBeGreaterThan(0);

    for (let i = 0; i < count; i++) {
      const mergeId = await checkboxes.nth(i).getAttribute('value');
      expect(mergeId, `merge checkbox ${i} value`).toMatch(/^\d+$/);
      const html = await fetchInvoiceEditHtml(page, mergeId!);
      const otherClient = parseInvoiceClientIdFromHtml(html);
      expect(otherClient, `Could not parse clientid for merge candidate invoice ${mergeId}`).toBeTruthy();
      expect(otherClient, `Merge candidate ${mergeId} must belong to the same customer as invoice ${id}`).toBe(
        baseClientId
      );
    }

    await checkboxes.first().check();
    await expect(checkboxes.first()).toBeChecked();

    // Optional: actually merge on save (mutates data). Set PW_INVOICE_MERGE_SUBMIT=1 only on a disposable DB.
    if (process.env.PW_INVOICE_MERGE_SUBMIT === '1') {
      await page.locator('button.btn-primary.invoice-form-submit.transaction-submit').first().click();
      await page.waitForLoadState('domcontentloaded').catch(() => {});
    }
  });
});
