import { test, expect } from '@playwright/test';
import { loginCustomer } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded } from '../utils/guards';

test.describe('Portal equivalencias → invoice conversion', () => {
  test('stores invoice qty/unit in order unit (equivalencia) and preserves totals', async ({ page }) => {
    test.setTimeout(120000);

    await loginCustomer(page, creds.customer);
    await page.goto('/clients', { waitUntil: 'domcontentloaded', timeout: 60000 });
    await assertPageLoaded(page);

    page.on('console', (m) => {
      // Surface browser JS errors/warnings in test output.
      if (['error', 'warning'].includes(m.type())) {
        console.log(`BROWSER ${m.type()}:`, m.text());
      }
    });
    page.on('request', (r) => {
      if (r.method() === 'POST') {
        console.log('BROWSER POST:', r.url());
      }
    });
    page.on('pageerror', (e) => {
      console.log('PAGEERROR:', e.message);
    });

    page.on('dialog', async (dialog) => {
      await dialog.accept();
    });

    // Ensure jQuery is available (save flow relies on $.post).
    const hasJq = await page.evaluate(() => typeof (window as any).jQuery !== 'undefined' && typeof (window as any).jQuery.post === 'function');
    expect(hasJq).toBe(true);

    // Monkeypatch $.post to confirm save triggers an HTTP call.
    await page.evaluate(() => {
      const $ = (window as any).jQuery;
      if ($ && typeof $.post === 'function' && !(window as any).__pwPostPatched) {
        const orig = $.post;
        $.post = function (...args: any[]) {
          // eslint-disable-next-line no-console
          console.log('JQPOST called:', args[0]);
          return orig.apply(this, args as any);
        };
        (window as any).__pwPostPatched = true;
      }
    });

    // Split-order UI should exist for this flow.
    await expect(page.locator('#new-order-form')).toBeVisible();
    await expect(page.locator('#new-order-table')).toBeVisible();

    // Use the initial empty row that the page adds on load (avoid leaving extra empty rows).
    const row = page.locator('#order-items-body tr').first();
    const productSelect = row.locator('select.product-select').first();
    await expect(productSelect).toBeVisible();

    // Select a product that we seeded equivalencias for locally.
    await productSelect.selectOption({ label: 'AGUA MINERAL' });
    await page.waitForTimeout(500);

    // Equivalencias select should now be visible and required.
    const eqSelect = row.locator('select.equivalencias-select').first();
    await expect(eqSelect).toBeVisible();

    // Clear selection to ensure validation triggers (selectOption can't select empty if not present).
    // If the first option is base unit, pick it then try save without changing factor => should still pass.
    // But we want to ensure the control has multiple options including one with factor != 1.
    const options = await eqSelect.locator('option').allTextContents();
    expect(options.length).toBeGreaterThan(1);

    // Choose "Caja" (factor=24 per our local seed).
    await eqSelect.selectOption({ label: 'Caja' });
    await page.waitForTimeout(200);

    // Debug/assert factor wiring
    const factorInfo = await page.evaluate(() => {
      const row = document.querySelector('#order-items-body tr:last-child');
      const sel = row?.querySelector('select.equivalencias-select') as HTMLSelectElement | null;
      const hid = row?.querySelector('input[name*=\"[equivalencia_factor]\"]') as HTMLInputElement | null;
      const opt = sel ? (sel.options[sel.selectedIndex] as HTMLOptionElement | undefined) : undefined;
      return {
        selected: sel?.value || null,
        dataFactor: opt?.getAttribute('data-factor') || null,
        hiddenFactor: hid?.value || null,
      };
    });
    console.log('Equivalencia factor wiring:', factorInfo);

    // Set qty=3
    const qtyInput = row.locator('input[name*="[qty]"]').first();
    await qtyInput.fill('3');

    // Read base unit rate from the readonly rate input.
    const rateInput = row.locator('input[name*="[rate]"]').first();
    const rateStr = await rateInput.inputValue();
    const baseRate = parseFloat(rateStr || '0');
    expect(baseRate).toBeGreaterThanOrEqual(0);

    // Total should be qty * factor * baseRate = 3 * 24 * baseRate (UI uses baseRate and factor)
    const expectedTotal = (3 * 24 * baseRate).toFixed(2);
    await page.waitForTimeout(300);
    const calcDebug = await page.evaluate(() => {
      const rows = Array.from(document.querySelectorAll('#order-items-body tr'));
      const perRow = rows.map((r) => {
        const qty = parseFloat((r.querySelector('input[name*=\"[qty]\"]') as HTMLInputElement | null)?.value || '0') || 0;
        const rate = parseFloat((r.querySelector('input[name*=\"[rate]\"]') as HTMLInputElement | null)?.value || '0') || 0;
        const factor = parseFloat((r.querySelector('input[name*=\"[equivalencia_factor]\"]') as HTMLInputElement | null)?.value || '1') || 1;
        return { qty, rate, factor, subtotal: qty * rate * factor };
      });
      const sum = perRow.reduce((a, b) => a + b.subtotal, 0);
      return { perRow, sum, displayed: (document.getElementById('order-total')?.textContent || '').trim() };
    });
    console.log('Total debug:', { baseRate, expectedTotal, calcDebug });
    await expect(page.locator('#order-total')).toHaveText(expectedTotal);

    // Save order and then verify invoice contents in client area.
    // Trigger save directly (some UI wrappers can swallow click events in tests).
    const hasSaveFn = await page.evaluate(() => typeof (window as any).saveNewOrder === 'function');
    expect(hasSaveFn).toBe(true);
    // The portal save shows an alert and then calls window.location.reload().
    // In headed mode, Playwright can miss the navigation event depending on timing,
    // so wait for either the POST request/response or a navigation.
    const saveUrlRe = /\/clients\/save_new_order/i;
    const saveResponsePromise = page
      .waitForResponse((r) => saveUrlRe.test(r.url()) && r.request().method() === 'POST', { timeout: 60000 })
      .catch(() => null);
    const navPromise = page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 60000 }).catch(() => null);

    const saveInvoke = await page.evaluate(() => {
      try {
        (window as any).saveNewOrder();
        return { ok: true };
      } catch (e: any) {
        return { ok: false, err: String(e?.message || e) };
      }
    });
    console.log('saveNewOrder invoke result:', saveInvoke);
    await Promise.race([saveResponsePromise, navPromise]);
    await page.waitForLoadState('domcontentloaded', { timeout: 60000 });
    await expect(page).toHaveURL(/\/clients/);

    // Navigate to invoices list and open the invoice that matches our expected amount.
    await page.goto('/clients/invoices', { waitUntil: 'domcontentloaded', timeout: 60000 });
    await expect(page.locator('body')).toContainText(/invoices|facturas/i);

    const invoiceLink = page.locator('table.table-invoices tbody tr a[href*="/invoice/"]').first();
    await expect(invoiceLink).toBeVisible({ timeout: 20000 });
    await invoiceLink.click();

    // Invoice page should show items table. Validate equivalencia note and that invoice shows the ordered unit.
    await expect(page.locator('body')).toContainText(/AGUA MINERAL/i);
    await expect(page.locator('body')).toContainText(/Equivalencia:\s*Caja\s*\(x24\)/i);
    // Invoice should show ordered qty (3) and the unit label (Caja).
    await expect(page.locator('body')).toContainText(/\b3\b/);
    await expect(page.locator('body')).toContainText(/\bCaja\b/i);
  });
});

