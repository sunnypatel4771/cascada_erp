import { test, expect } from '@playwright/test';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';

test('report table rows are visible after full page load', async ({ page }) => {
  await loginAdmin(page, creds.admin);

  // Navigate to the report page and wait until network is completely idle (no AJAX pending)
  await page.goto('http://127.0.0.1:8080/admin/ramos/report', { waitUntil: 'networkidle' });

  // Extra settle time so DataTables has time to initialise
  await page.waitForTimeout(3000);

  // Count rows in tbody
  const rowCount = await page.locator('#ramos-report-table tbody tr').count();
  console.log('Rows in #ramos-report-table tbody after full load:', rowCount);

  // Take screenshot for visual verification
  await page.screenshot({ path: 'test-results/report-fully-loaded.png', fullPage: true });
  console.log('Screenshot saved → test-results/report-fully-loaded.png');

  // Table should have at least 1 visible row (we know from DB there are 37 records in the date range)
  expect(rowCount).toBeGreaterThan(0);
});
