import { test, expect, Page, Response } from '@playwright/test';
import * as fs from 'fs/promises';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded } from '../utils/guards';

function bodyLooksLikePdf(buf: Buffer): boolean {
  return buf.length >= 4 && buf.subarray(0, 4).toString('ascii') === '%PDF';
}

async function assertPdfResponse(response: Response): Promise<void> {
  expect(response.status(), `Expected 200, got ${response.status()} for ${response.url()}`).toBe(200);
  const contentType = (response.headers()['content-type'] || '').toLowerCase();
  const buf = await response.body();
  expect(
    contentType.includes('pdf') || bodyLooksLikePdf(buf),
    `Expected PDF response; content-type=${contentType} first-bytes=${buf.subarray(0, 8).toString('hex')}`
  ).toBeTruthy();
}

async function assertPdfUrlViaRequest(page: Page, href: string): Promise<void> {
  const r = await page.request.get(href, { timeout: 45000 });
  expect(r.status(), `Expected 200, got ${r.status()} for ${href}`).toBe(200);
  const contentType = (r.headers()['content-type'] || '').toLowerCase();
  const buf = Buffer.from(await r.body());
  expect(
    contentType.includes('pdf') || bodyLooksLikePdf(buf),
    `Expected PDF response; content-type=${contentType} first-bytes=${buf.subarray(0, 8).toString('hex')}`
  ).toBeTruthy();
}

async function openPoVoucherMenu(page: Page): Promise<{ menu: ReturnType<Page['locator']> }> {
  const toggle = page.locator('a.dropdown-toggle').filter({ hasText: /PO Voucher|Vale de orden|orden de compra/i });
  await expect(toggle.first()).toBeVisible({ timeout: 15000 });
  await toggle.first().click();
  const menu = page.locator('.panel_s .btn-group.open ul.dropdown-menu').first();
  await expect(menu).toBeVisible({ timeout: 5000 });
  return { menu };
}

test.describe('20. Purchase PO Voucher', () => {
  test.skip(!creds.admin.password, 'PW_ADMIN_PASSWORD not set – skipping PO voucher tests');

  test.beforeEach(async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto('/admin/purchase/purchase_order', { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);
    await expect(page.locator('body')).not.toContainText(/access denied|403/i);

    // Set a deterministic filter range to ensure voucher URL carries it
    await page.locator('input[name="from_date"]').fill('2026-04-15');
    await page.locator('input[name="to_date"]').fill('2026-04-15');
  });

  test('PO Voucher menu: View PDF (same tab) returns PDF', async ({ page }) => {
    const { menu } = await openPoVoucherMenu(page);
    const viewPdf = menu.locator('a').nth(0);
    await expect(viewPdf).toBeVisible();
    const href = await viewPdf.getAttribute('href');
    expect(href, 'View PDF link must target po_voucher').toMatch(/po_voucher/);
    expect(href || '', 'View PDF link must include from/to date when filters are set').toMatch(/from_date=.+&to_date=.+/);

    const [response] = await Promise.all([
      page.waitForResponse(
        (r) =>
          r.url().includes('po_voucher') &&
          r.request().method() === 'GET' &&
          (r.request().resourceType() === 'document' || r.request().resourceType() === 'other'),
        { timeout: 45000 }
      ),
      viewPdf.click(),
    ]);
    await assertPdfResponse(response);
  });

  test('PO Voucher menu: View PDF in new tab returns PDF', async ({ page, context }) => {
    const { menu } = await openPoVoucherMenu(page);
    const viewNewTab = menu.locator('a').nth(1);
    await expect(viewNewTab).toBeVisible();
    const href = await viewNewTab.getAttribute('href');
    expect(href || '', 'View PDF new-tab link must target po_voucher').toMatch(/po_voucher/);
    // New-tab PDF rendering is browser-dependent; validate the URL returns a PDF via API request.
    await assertPdfUrlViaRequest(page, href!);

    // Still click to exercise the UI (but don't rely on response-body capture for PDF viewers).
    const pagePromise = context.waitForEvent('page', { timeout: 45000 });
    await viewNewTab.click();
    const newPage = await pagePromise;
    await newPage.close().catch(() => {});
  });

  test('PO Voucher menu: Download returns PDF', async ({ page }) => {
    const { menu } = await openPoVoucherMenu(page);
    const downloadLink = menu.locator('a').nth(2);
    await expect(downloadLink).toBeVisible();
    const href = await downloadLink.getAttribute('href');
    expect(href, 'Download link must target po_voucher without print').toMatch(/po_voucher/);
    expect(href).not.toMatch(/print/);
    const [download] = await Promise.all([
      page.waitForEvent('download', { timeout: 45000 }),
      downloadLink.click(),
    ]);
    const path = await download.path();
    expect(path, 'Expected a downloadable file path').toBeTruthy();
    const buf = await fs.readFile(path!);
    expect(bodyLooksLikePdf(buf), 'Downloaded file should look like a PDF').toBeTruthy();
  });

  test('PO Voucher menu: Print opens PDF in new tab', async ({ page, context }) => {
    const { menu } = await openPoVoucherMenu(page);
    const printLink = menu.locator('a').nth(3);
    await expect(printLink).toBeVisible();
    expect(await printLink.getAttribute('href')).toMatch(/print=true/);

    const pagePromise = context.waitForEvent('page', { timeout: 45000 });
    await printLink.click();
    const newPage = await pagePromise;
    try {
      const href = newPage.url();
      if (href.includes('po_voucher')) {
        await assertPdfUrlViaRequest(page, href);
      } else {
        const originalHref = await printLink.getAttribute('href');
        await assertPdfUrlViaRequest(page, originalHref!);
      }
    } finally {
      await newPage.close().catch(() => {});
    }
  });
});
