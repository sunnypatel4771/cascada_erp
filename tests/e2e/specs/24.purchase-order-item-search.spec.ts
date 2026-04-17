import { test, expect } from '@playwright/test';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded } from '../utils/guards';

test.describe('24. Purchase order item search', () => {
  test.skip(!creds.admin.password, 'PW_ADMIN_PASSWORD not set');

  test('pur_commodity_code_search returns purchasable rows after PO form load', async ({ page }) => {
    test.setTimeout(90000);
    await loginAdmin(page, creds.admin);

    await page.goto('/admin/purchase/pur_order', { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);

    await expect(page.locator('#pur_order-form')).toBeVisible({ timeout: 30000 });

    // When "item by vendor" is on, item AJAX binds only after vendor change.
    await page.evaluate(() => {
      const w = window as unknown as { jQuery?: CallableFunction };
      const $ = w.jQuery as ((sel: string) => { length: number; find: (s: string) => { length: number; first: () => { val: () => string } }; selectpicker?: (m: string, v?: string) => void; trigger: (e: string) => void }) | undefined;
      if (!$) {
        return;
      }
      const $v = $('#vendor');
      if ($v.length && $v.find('option[value!=""]').length > 0) {
        const firstVal = String($v.find('option[value!=""]').first().val() || '');
        if (firstVal) {
          $v.selectpicker?.('val', firstVal);
          $v.trigger('change');
        }
      }
    });
    await page.waitForTimeout(2500);

    const items = await page.evaluate(async () => {
      const adminUrl = (window as unknown as { admin_url?: string }).admin_url;
      if (!adminUrl) {
        throw new Error('admin_url not defined on window');
      }
      const res = await fetch(`${adminUrl}purchase/pur_commodity_code_search`, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: new URLSearchParams({ q: 'L' }).toString(),
      });
      if (!res.ok) {
        throw new Error(`pur_commodity_code_search HTTP ${res.status}`);
      }
      return res.json() as Promise<Array<{ id: number | string }>>;
    });

    expect(Array.isArray(items)).toBeTruthy();
    expect(
      items.length,
      'Expected at least one active item matching "L" (description/code/SKU) for PO picker'
    ).toBeGreaterThan(0);
    expect(items[0]).toHaveProperty('id');
  });
});
