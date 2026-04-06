import { test, expect } from '@playwright/test';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded } from '../utils/guards';

test.describe('2. Automation Trigger', () => {
  test('automation settings: enabled checkbox, hour/minutes selects, and save controls are present', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/automation/settings');
    await assertPageLoaded(page);

    // Enabled toggle must exist
    await expect(page.locator('#automation_enabled')).toBeVisible();

    // Hour and minutes dropdowns for schedule
    await expect(page.locator('#schedule_hour')).toBeVisible();
    await expect(page.locator('#schedule_minutes')).toBeVisible();

    // The minutes dropdown must include a :30 option (30-minute cadence requirement)
    const minutesOptions = page.locator('#schedule_minutes option');
    const values = await minutesOptions.evaluateAll((opts) =>
      (opts as HTMLOptionElement[]).map((o) => o.value)
    );
    expect(values).toContain('30');

    // Max stops / route config controls present
    await expect(page.locator('#default_max_stops')).toBeVisible();
    const maxStopsVal = await page.locator('#default_max_stops').inputValue();
    expect(Number(maxStopsVal)).toBeGreaterThan(0);

    // Save button visible (admin has edit rights)
    await expect(page.locator('#automation-settings-form button[type="submit"]')).toBeVisible();
  });

  test('automation dashboard is reachable and manual trigger button is present', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/automation');
    await assertPageLoaded(page);

    await expect(page).toHaveURL(/ramos\/automation/i);

    // Unprocessed orders counter panel must load
    await expect(page.locator('#unprocessed-count')).toBeVisible();

    // Manual trigger button (admin with create permission)
    const triggerBtn = page.locator('#ramos-automation-trigger');
    await expect(triggerBtn).toBeVisible();

    // Click the trigger and confirm it fires without fatal error (JS alert or success toast)
    page.once('dialog', (d) => d.accept().catch(() => {}));
    await triggerBtn.click();
    // Give up to 10 s for the server to respond with a success/error toast
    await page.waitForTimeout(2000);

    // Page should still be on the automation route (no redirect to error/419)
    await expect(page).toHaveURL(/ramos\/automation/i);
    // Body should not show a raw PHP error
    await expect(page.locator('body')).not.toContainText(/Fatal error|Call to undefined|MySQL|SQLSTATE/i);
  });
});
