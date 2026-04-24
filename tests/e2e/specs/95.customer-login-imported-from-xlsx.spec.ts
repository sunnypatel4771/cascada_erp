import { expect, test } from '@playwright/test';
import { loginCustomer } from '../utils/auth';

test('Imported customer can login', async ({ page }) => {
  test.setTimeout(120_000);

  const email = process.env.PW_IMPORTED_CUSTOMER_EMAIL || 'cliente2@gmail.com';
  const password = process.env.PW_IMPORTED_CUSTOMER_PASSWORD || 'Ramos123';

  await loginCustomer(page, { email, password });

  // After login, customers area should load (not login form)
  await expect(page.locator('input[name="password"], #password')).toHaveCount(0);
  await expect(page).not.toHaveURL(/authentication|login/i);
});

