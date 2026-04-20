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

function adminLoginUrlCandidates(): string[] {
  const envBase = (process.env.PW_BASE_URL || '').replace(/\/$/, '');
  if (envBase) {
    return [
      `${envBase}/admin/authentication`,
      `${envBase}/index.php/admin/authentication`,
    ];
  }
  return ['/admin/authentication', '/index.php/admin/authentication'];
}

function customerLoginUrlCandidates(): string[] {
  const envBase = (process.env.PW_BASE_URL || '').replace(/\/$/, '');
  if (envBase) {
    return [
      `${envBase}/authentication/login`,
      `${envBase}/authentication`,
      `${envBase}/index.php/authentication/login`,
      `${envBase}/index.php/authentication`,
    ];
  }
  return ['/authentication/login', '/authentication', '/index.php/authentication/login', '/index.php/authentication'];
}

export async function loginAdmin(page: Page, user: UserCreds) {
  const candidates = adminLoginUrlCandidates();
  let opened = false;
  for (const url of candidates) {
    for (let attempt = 0; attempt < 3; attempt++) {
      try {
        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 25000 });
        break;
      } catch (err) {
        const msg = String(err);
        if (!/interrupted|ERR_ABORTED|Timeout/i.test(msg) || attempt === 2) {
          throw err;
        }
        await page.waitForTimeout(600);
      }
    }
    const hasEmail = await page.locator('input[name="email"], #email').first().isVisible().catch(() => false);
    if (hasEmail) {
      opened = true;
      break;
    }
  }
  if (!opened) {
    throw new Error(`Admin login page not found. Tried: ${candidates.join(' | ')}`);
  }

  await expect(page).toHaveURL(/authentication|admin/i);

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
  const candidates = customerLoginUrlCandidates();
  let opened = false;
  for (const url of candidates) {
    for (let attempt = 0; attempt < 3; attempt++) {
      try {
        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 25000 });
        break;
      } catch (err) {
        const msg = String(err);
        if (!/interrupted|ERR_ABORTED|Timeout/i.test(msg) || attempt === 2) {
          throw err;
        }
        await page.waitForTimeout(600);
      }
    }
    const hasEmail = await page
      .locator('input[name="email"], input[name="username"], #email')
      .first()
      .isVisible()
      .catch(() => false);
    if (hasEmail) {
      opened = true;
      break;
    }
  }
  if (!opened) {
    throw new Error(`Customer login page not found. Tried: ${candidates.join(' | ')}`);
  }

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

