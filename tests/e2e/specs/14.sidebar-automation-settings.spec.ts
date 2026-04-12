import { test, expect } from '@playwright/test';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded } from '../utils/guards';

test.describe('Ramos sidebar', () => {
  test('Automation Settings link is visible and navigates', async ({ page }) => {
    test.skip(!creds.admin.password, 'Set PW_ADMIN_PASSWORD to run admin UI tests.');

    await loginAdmin(page, creds.admin);
    await page.goto('/admin', { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);

    // Expand the "Ramos" group (submenu items may not exist until expanded)
    const ramosGroup = page
      .locator('#side-menu')
      .getByRole('link', { name: /^Ramos$/ })
      .first();
    if (await ramosGroup.isVisible().catch(() => false)) {
      await ramosGroup.click();
    }

    const sidebarLinks = await page.locator('#side-menu a').evaluateAll((els) =>
      (els as HTMLAnchorElement[])
        .map((a) => (a.getAttribute('href') || '').trim())
        .filter(Boolean)
    );
    console.log('Sidebar hrefs:', sidebarLinks);

    // Sidebar link should exist
    const link = page.locator('a[href*="ramos/automation/settings"]').first();
    await expect(link).toBeVisible();

    // Click and verify navigation
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      link.click(),
    ]);

    await expect(page).toHaveURL(/\/admin\/ramos\/automation\/settings/i);
    await expect(page.locator('body')).not.toContainText(/Fatal error|Call to undefined|SQLSTATE/i);

    // New frequency controls should be present on the settings page
    await expect(page.locator('input[name="schedule_mode"][value="daily_once"]')).toBeAttached();
    await expect(page.locator('input[name="schedule_mode"][value="weekly_once"]')).toBeAttached();
    await expect(page.locator('input[name="schedule_mode"][value="multi_daily"]')).toBeAttached();
    await expect(page.locator('select[name="schedule_hour"]')).toBeAttached();
    await expect(page.locator('select[name="schedule_minutes"]')).toBeAttached();
  });

  test('Automation settings: save daily_once at 15:00 via AJAX', async ({ page }) => {
    test.skip(!creds.admin.password, 'Set PW_ADMIN_PASSWORD to run admin UI tests.');

    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/automation/settings', { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);
    await expect(page.locator('body')).not.toContainText(/Fatal error|Call to undefined|SQLSTATE/i);

    const saveBtn = page.locator('#automation-settings-form button[type="submit"]');
    await expect(saveBtn).toBeVisible();

    await page.locator('#automation_enabled').check();
    await page.locator('input[name="schedule_mode"][value="daily_once"]').check();
    await page.locator('#schedule_hour').selectOption('15');
    await page.locator('#schedule_minutes').selectOption('0');

    const [saveResp] = await Promise.all([
      page.waitForResponse(
        (res) =>
          res.request().method() === 'POST' && /ramos\/automation\/settings/i.test(res.url())
      ),
      saveBtn.click(),
    ]);

    const body = JSON.parse(await saveResp.text()) as { success: boolean };
    expect(body.success).toBe(true);

    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.locator('#schedule_hour')).toHaveValue('15');
    await expect(page.locator('#schedule_minutes')).toHaveValue('0');
    await expect(page.locator('input[name="schedule_mode"][value="daily_once"]')).toBeChecked();
  });

  test('Automation settings: weekly_once shows weekday row and saves', async ({ page }) => {
    test.skip(!creds.admin.password, 'Set PW_ADMIN_PASSWORD to run admin UI tests.');

    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/automation/settings', { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);

    await page.locator('#automation_enabled').check();
    await page.locator('input[name="schedule_mode"][value="weekly_once"]').check();
    await expect(page.locator('#schedule-weekday-row')).toBeVisible();
    await expect(page.locator('#schedule-run-at-row')).toBeVisible();

    await page.locator('#schedule_date').selectOption('friday');
    await page.locator('#schedule_hour').selectOption('14');
    await page.locator('#schedule_minutes').selectOption('30');

    const saveBtn = page.locator('#automation-settings-form button[type="submit"]');
    const [saveRespWeekly] = await Promise.all([
      page.waitForResponse(
        (res) =>
          res.request().method() === 'POST' && /ramos\/automation\/settings/i.test(res.url())
      ),
      saveBtn.click(),
    ]);
    const bodyWeekly = JSON.parse(await saveRespWeekly.text()) as { success: boolean };
    expect(bodyWeekly.success).toBe(true);

    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.locator('input[name="schedule_mode"][value="weekly_once"]')).toBeChecked();
    await expect(page.locator('#schedule_date')).toHaveValue('friday');
  });

  test('Automation settings: multi_daily shows hour checkboxes and saves', async ({ page }) => {
    test.skip(!creds.admin.password, 'Set PW_ADMIN_PASSWORD to run admin UI tests.');

    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/automation/settings', { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);

    await page.locator('#automation_enabled').check();
    await page.locator('input[name="schedule_mode"][value="multi_daily"]').check();
    await expect(page.locator('#schedule-multi-hours-row')).toBeVisible();
    await expect(page.locator('#schedule-run-at-row')).toBeHidden();

    await page.locator('input.schedule-multi-hour[value="8"]').check();
    await page.locator('input.schedule-multi-hour[value="15"]').check();
    await page.locator('#schedule_minutes_multi').selectOption('0');

    const saveBtn = page.locator('#automation-settings-form button[type="submit"]');
    const [saveResp] = await Promise.all([
      page.waitForResponse(
        (res) =>
          res.request().method() === 'POST' && /ramos\/automation\/settings/i.test(res.url())
      ),
      saveBtn.click(),
    ]);
    const body = JSON.parse(await saveResp.text()) as { success: boolean };
    expect(body.success).toBe(true);
  });
});

