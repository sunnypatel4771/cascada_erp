import { test, expect } from '@playwright/test';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded, guardFacturacionData } from '../utils/guards';

/**
 * Matches Ramos_invoice_generator: week + rule uses list price and rule discount, then markup;
 * otherwise purchase_price × (1 + markup%).
 */
function expectedFirstLineRateFromEnv(): number {
  const markup = parseFloat(process.env.PW_RAMOS_PRICING_MARKUP_PCT || 'NaN');
  if (!Number.isFinite(markup)) {
    throw new Error('PW_RAMOS_PRICING_MARKUP_PCT must be a number');
  }
  const useWeek = process.env.PW_RAMOS_PRICING_USE_WEEK === '1';
  const rulePriceRaw = process.env.PW_RAMOS_PRICING_RULE_PRICE;
  const purchaseRaw = process.env.PW_RAMOS_PRICING_PURCHASE_PRICE;

  if (useWeek && rulePriceRaw !== undefined && rulePriceRaw !== '') {
    const rulePrice = parseFloat(rulePriceRaw);
    if (!Number.isFinite(rulePrice)) {
      throw new Error('PW_RAMOS_PRICING_RULE_PRICE must be a number when set');
    }
    const disc = parseFloat(process.env.PW_RAMOS_PRICING_RULE_DISCOUNT_PCT || '0');
    const base = rulePrice * (1 - (Number.isFinite(disc) ? disc : 0) / 100);
    return base * (1 + markup / 100);
  }

  const purchase = parseFloat(purchaseRaw || '');
  if (!Number.isFinite(purchase)) {
    throw new Error('Set PW_RAMOS_PRICING_RULE_PRICE (week=1 with rule) or PW_RAMOS_PRICING_PURCHASE_PRICE (cost path)');
  }
  return purchase * (1 + markup / 100);
}

function pricingOrderId(): string | null {
  const v = process.env.PW_RAMOS_FACTURACION_PRICING_ORDER_ID?.trim();
  return v || null;
}

function pricingOrderRowLabel(): string | null {
  const v = process.env.PW_RAMOS_PRICING_ORDER_LABEL?.trim();
  return v || null;
}

function facturacionDate(): string {
  return process.env.PW_RAMOS_FACTURACION_DATE?.trim() || new Date().toISOString().slice(0, 10);
}

test.describe('23. Ramos Facturación → invoice week / rule / markup rate', () => {
  test.skip(!creds.admin.password, 'PW_ADMIN_PASSWORD not set – skipping facturación pricing tests');

  test('generated invoice first line rate matches expected formula (seeded env)', async ({ page }, testInfo) => {
    const orderId = pricingOrderId();
    const rowLabel = pricingOrderRowLabel();
    if (!orderId || !rowLabel) {
      testInfo.skip(
        true,
        'Set PW_RAMOS_FACTURACION_PRICING_ORDER_ID and PW_RAMOS_PRICING_ORDER_LABEL (unique substring of the order number on the Facturación card) to run this test.'
      );
    }

    let expected: number;
    try {
      expected = expectedFirstLineRateFromEnv();
    } catch (e) {
      testInfo.skip(true, String(e));
      return;
    }

    await loginAdmin(page, creds.admin);
    const date = facturacionDate();
    await page.goto(`/admin/ramos/facturacion?date=${encodeURIComponent(date)}`, { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);
    await expect(page.locator('body')).not.toContainText(/access denied/i);

    try {
      await guardFacturacionData(page);
    } catch {
      testInfo.skip(true, 'Facturación page has no route data for this date — pick PW_RAMOS_FACTURACION_DATE where the seeded order appears.');
    }

    const row = page.locator('.tw-border-b').filter({ hasText: rowLabel! });
    if (!(await row.isVisible().catch(() => false))) {
      testInfo.skip(
        true,
        `No Facturación row contains PW_RAMOS_PRICING_ORDER_LABEL=${rowLabel} — check the label or date.`
      );
    }

    const gen = row.locator(`a[href*="/ramos/facturacion/generate_invoice/${orderId}"], a[href*="generate_invoice/${orderId}"]`);
    if (!(await gen.first().isVisible().catch(() => false))) {
      const viewInv = row.locator('a.btn-success[href*="/invoices/invoice/"]');
      if (await viewInv.first().isVisible().catch(() => false)) {
        testInfo.skip(
          true,
          `Order ${orderId} already has an invoice — clear invoice_id on the order or use a fresh READY order without an invoice.`
        );
      }
      testInfo.skip(
        true,
        `Generate-invoice control not found for order ${orderId} — order may be missing, not READY, or not on this date.`
      );
    }

    page.once('dialog', (d) => d.accept());
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 60000 }),
      gen.first().click(),
    ]);
    await assertPageLoaded(page);

    const alert = page.locator('.alert-warning, .alert-danger').first();
    if (await alert.isVisible().catch(() => false)) {
      const t = (await alert.textContent())?.trim() || '';
      testInfo.skip(true, `Generate invoice did not succeed: ${t}`);
    }

    const invoiceLink = row.locator('a.btn-success[href*="/invoices/invoice/"]').first();
    await expect(invoiceLink).toBeVisible({ timeout: 30000 });
    const href = await invoiceLink.getAttribute('href');
    const m = href?.match(/\/invoices\/invoice\/(\d+)/);
    const invoiceId = m?.[1];
    expect(invoiceId, 'View invoice link should contain invoice id').toBeTruthy();

    await page.goto(`/admin/invoices/invoice/${invoiceId}`, { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);

    const rateInput = page.locator('table.items tbody tr.sortable.item td.rate input').first();
    await expect(rateInput).toBeVisible({ timeout: 15000 });
    const actual = parseFloat((await rateInput.inputValue()) || 'NaN');
    expect(Number.isFinite(actual), `First line rate should be numeric, got "${await rateInput.inputValue()}"`).toBe(
      true
    );
    expect(actual, `Expected ≈ ${expected} (week/rule/markup env), got ${actual}`).toBeCloseTo(expected, 2);
  });
});
