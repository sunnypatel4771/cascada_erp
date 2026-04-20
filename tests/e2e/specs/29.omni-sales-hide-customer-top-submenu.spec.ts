import { test, expect } from '@playwright/test';
import { loginCustomer } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded } from '../utils/guards';

test.describe('29. Omni Sales client portal - hide content area', () => {
  test('does not render #content on omni_sales_client pages', async ({ page }) => {
    test.setTimeout(60000);

    await loginCustomer(page, creds.customer);

    await page.goto('/omni_sales/omni_sales_client/index/1/0/0', {
      waitUntil: 'domcontentloaded',
      timeout: 60000,
    });
    await assertPageLoaded(page);

    // The entire #content block must not be present on Omni Sales client pages.
    await expect(page.locator('#content')).toHaveCount(0);

    // No product cards, no category sidebar, no search form should be visible.
    await expect(page.locator('.ramos-product-card')).toHaveCount(0);
    await expect(page.locator('ul.nav-tabs--vertical.nav')).toHaveCount(0);
    await expect(page.locator('ul.submenu.customer-top-submenu')).toHaveCount(0);
  });
});
