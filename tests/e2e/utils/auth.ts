import { expect, Page } from '@playwright/test';
import { UserCreds } from './credentials';

async function fillIfPresent(page: Page, selectors: string[], value: string) {
  for (const selector of selectors) {
    const field = page.locator(selector).first();
    if (await field.isVisible().catch(() => false)) {
      await field.fill(value);
      return true;
    }
  }
  return false;
}

export async function loginAdmin(page: Page, user: UserCreds) {
  await page.goto('/admin/authentication');
  await expect(page).toHaveURL(/admin\/authentication|admin/i);

  const emailOk = await fillIfPresent(page, ['input[name="email"]', '#email'], user.email);
  const passOk = await fillIfPresent(page, ['input[name="password"]', '#password'], user.password);
  if (!emailOk || !passOk) {
    throw new Error('Admin login fields were not found.');
  }

  await Promise.all([
    page.waitForLoadState('domcontentloaded'),
    page.locator('button[type="submit"], input[type="submit"]').first().click(),
  ]);
}

export async function loginCustomer(page: Page, user: UserCreds) {
  await page.goto('/authentication');
  await expect(page).toHaveURL(/authentication|login/i);

  const emailOk = await fillIfPresent(
    page,
    ['input[name="email"]', 'input[name="username"]', '#email'],
    user.email
  );
  const passOk = await fillIfPresent(page, ['input[name="password"]', '#password'], user.password);
  if (!emailOk || !passOk) {
    throw new Error('Customer login fields were not found.');
  }

  await Promise.all([
    page.waitForLoadState('domcontentloaded'),
    page.locator('button[type="submit"], input[type="submit"]').first().click(),
  ]);
}

