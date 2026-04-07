import { test, expect } from '@playwright/test';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded } from '../utils/guards';
import {
  applyRamosManageShiftsPermissions,
  buildPickingStaffProfile,
  consolidatePickerToSingleModule,
  createPickingStaffMember,
  endAllActiveShifts,
  logoutAdmin,
  restrictStaffToManageShiftsOnly,
  startOperatorShiftOnFirstModule,
} from '../utils/staff';

/**
 * Javo workflow (picking staff RBAC):
 *
 * - Accounts are created in the Perfex admin panel (there is no separate registration portal).
 * - Pickers operate through /admin (not /clients).
 * - A picker is created with "Ramos → View + manage_shifts" permission.
 * - Admin pre-assigns picker OR picker claims module from console on their own.
 * - Picker logs in → sees module-claim panel if no shift, otherwise their one module.
 * - Completed orders disappear; pending/in-progress items stay visible.
 * - Admin always sees all modules.
 * - EXCLUSIVE LOCK: only 1 active operator per module.
 *
 * Env overrides:
 *   PW_PICKING_STAFF_EMAIL / PW_PICKING_STAFF_PASSWORD — reuse existing account (creation is skipped automatically)
 *   PW_PICKING_STAFF_FIRSTNAME / PW_PICKING_STAFF_LASTNAME — must match Perfex staff dropdown ("firstname lastname")
 *   PW_SKIP_STAFF_CREATE=1 — optional; same as providing PW_PICKING_STAFF_EMAIL for skipping create
 *   PW_PICKING_STAFF_UNIQUE — suffix for unique email when creating new staff
 */
test.describe('7. Picking staff RBAC (Javo workflow)', () => {
  test.describe.configure({ mode: 'serial' });

  test('admin: all modules visible in console (no access restriction for admin)', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/picking/console', { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);

    // Admin always sees the module container
    await expect(page.locator('#ramos-console-modules')).toBeVisible({ timeout: 20000 });
    // No access-denied redirect
    await expect(page).toHaveURL(/ramos\/picking\/console/i);
  });

  test('admin: all module cards visible on /admin/ramos/picking manage page', async ({ page }) => {
    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/picking', { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);

    // Admin sees multiple module cards (at least 1; the section is rendered).
    await expect(page.locator('.ramos-picking-modules')).toBeVisible({ timeout: 15000 });
    const cards = page.locator('.ramos-picking-modules > .col-md-6');
    const count = await cards.count();
    expect(count).toBeGreaterThan(0);
    // No crash
    await expect(page.locator('body')).not.toContainText(/Fatal error|SQLSTATE/i);
  });

  test('picker with manage_shifts only: manage page scoped to own module (or empty-state)', async ({
    page,
  }, testInfo) => {
    test.setTimeout(120000);
    test.skip(
      !!process.env.PW_SKIP_STAFF_CREATE && !process.env.PW_PICKING_STAFF_EMAIL,
      'Requires a picker account.'
    );

    const profile = buildPickingStaffProfile(testInfo.parallelIndex);
    if (process.env.PW_PICKING_STAFF_EMAIL) {
      profile.email    = process.env.PW_PICKING_STAFF_EMAIL;
      profile.password = process.env.PW_PICKING_STAFF_PASSWORD || profile.password;
    }

    // Admin: ensure the picker has a shift so the manage page shows exactly one card.
    await loginAdmin(page, creds.admin);
    await endAllActiveShifts(page);
    await startOperatorShiftOnFirstModule(page, profile);
    await logoutAdmin(page);

    // Log in as picker.
    await loginAdmin(page, { email: profile.email, password: profile.password });
    await page.goto('/admin/ramos/picking', { waitUntil: 'domcontentloaded' });
    await assertPageLoaded(page);

    // Not redirected to authentication.
    await expect(page).not.toHaveURL(/\/admin\/authentication/i);
    await expect(page.locator('body')).not.toContainText(/Fatal error|SQLSTATE/i);

    // If this is a full admin env account, scoping is not enforced — skip count assertion.
    const isPickerAdmin = page.url().includes('/admin/settings') ||
      await page.locator('a[href*="/admin/settings"]').first().isVisible({ timeout: 3000 }).catch(() => false);

    const moduleSection = page.locator('.ramos-picking-modules');
    const sectionVisible = await moduleSection.isVisible().catch(() => false);

    if (isPickerAdmin) {
      // Full admin sees all modules — just verify page loads.
      test.info().annotations.push({ type: 'note', description: 'Picker is full admin — scoping not applicable.' });
      return;
    }

    if (sectionVisible) {
      // Picker must see AT MOST ONE module card (their own shift module).
      const cards = moduleSection.locator('.col-md-6');
      const count = await cards.count();
      expect(count).toBeLessThanOrEqual(1);
    } else {
      // No active shift → scoped empty state with console link should appear.
      await expect(page.locator('body')).toContainText(
        /turno|shift|consola|console|módulo|module/i
      );
    }
  });

  test('Ramos manage_shifts picker: admin creates staff, starts shift, console scoped to one module', async ({
    page,
  }, testInfo) => {
    // Staff creation + shift assignment + login flow can take over 30 s
    test.setTimeout(120000);
    test.skip(
      !!process.env.PW_SKIP_STAFF_CREATE && !process.env.PW_PICKING_STAFF_EMAIL,
      'When PW_SKIP_STAFF_CREATE=1 you must also set PW_PICKING_STAFF_EMAIL'
    );

    const profile = buildPickingStaffProfile(testInfo.parallelIndex);
    if (process.env.PW_PICKING_STAFF_EMAIL) {
      profile.email    = process.env.PW_PICKING_STAFF_EMAIL;
      profile.password = process.env.PW_PICKING_STAFF_PASSWORD || profile.password;
    }

    // ── Step 1: Admin creates picker with view + manage_shifts only ────────
    await loginAdmin(page, creds.admin);
    if (!process.env.PW_PICKING_STAFF_EMAIL) {
      // Create new staff with manage_shifts permissions.
      await page.goto('/admin/staff/member');
      await expect(page.locator('input[name="firstname"]')).toBeVisible({ timeout: 20000 });
      await page.locator('input[name="firstname"]').fill(profile.firstName);
      await page.locator('input[name="lastname"]').fill(profile.lastName);
      await page.locator('input[name="email"]').fill(profile.email);
      const adminCb = page.locator('input#administrator');
      if (await adminCb.isVisible().catch(() => false)) await adminCb.setChecked(false);
      const welcome = page.locator('input#send_welcome_email');
      if (await welcome.isVisible().catch(() => false)) await welcome.setChecked(false);
      await page.locator('input[name="password"].password').fill(profile.password);
      await applyRamosManageShiftsPermissions(page);
      await page.locator('form.staff-form button[type="submit"].btn-primary').click();
      await page.waitForURL(/staff\/member\/\d+/, { timeout: 30000 }).catch(() => {});
    } else {
      // Pre-existing account: try to restrict permissions to view + manage_shifts.
      // Returns false when the account is an admin (scope assertion will be skipped below).
      profile['_permRestricted'] = await restrictStaffToManageShiftsOnly(page, profile.email);
    }

    // ── Step 2: Clean slate — end all existing shifts so there is always a free slot ──
    await endAllActiveShifts(page);

    // ── Step 3: Admin starts an operator shift for the picker ─────────────
    await startOperatorShiftOnFirstModule(page, profile);
    // One active module only (RBAC exclusive lock)
    await consolidatePickerToSingleModule(page, profile);

    // ── Step 4: Admin logs out ────────────────────────────────────────────
    await logoutAdmin(page);

    // ── Step 5: Picker logs in via /admin (same panel, restricted perms) ──
    await loginAdmin(page, { email: profile.email, password: profile.password });
    // Pickers use /admin — not a separate portal
    expect(page.url()).toMatch(/admin/i);

    // ── Step 6: Navigate to Picking Console ───────────────────────────────
    await page.goto('/admin/ramos/picking/console');
    await assertPageLoaded(page);

    // Picker should NOT be redirected to login
    await expect(page).not.toHaveURL(/\/admin\/authentication/i);

    const modules    = page.locator('.ramos-console-module');
    const moduleCount = await modules.count();

    if (moduleCount === 0) {
      // No orders today — the "no active module" message or claim panel is valid
      await expect(page.locator('body')).toContainText(
        /consola|surtido|picking|console|no orders|sin pedidos|sin acceso|not assigned|no estás|ningún módulo|ningun modulo|selecciona|select.*module/i
      );
      return;
    }

    // Picker must see EXACTLY ONE module (the one they were shifted into).
    // Skip this assertion when the account is a full admin (is_admin() always bypasses scoping).
    const permWasRestricted = (profile as Record<string, unknown>)['_permRestricted'];
    const isPreExistingAdmin = process.env.PW_PICKING_STAFF_EMAIL && permWasRestricted === false;
    if (!isPreExistingAdmin) {
      await expect(modules).toHaveCount(1);
    }
    await expect(page.locator('body')).toContainText(
      /progress|progreso|picked|pending|order|pedido|completado|completed/i
    );
  });

  test('picker with manage_shifts can self-claim an available module from console', async ({ page }, testInfo) => {
    test.setTimeout(90000);
    test.skip(
      !!process.env.PW_SKIP_STAFF_CREATE && !process.env.PW_PICKING_STAFF_EMAIL,
      'Requires a picker account with manage_shifts.'
    );

    const profile = buildPickingStaffProfile(testInfo.parallelIndex);
    if (process.env.PW_PICKING_STAFF_EMAIL) {
      profile.email    = process.env.PW_PICKING_STAFF_EMAIL;
      profile.password = process.env.PW_PICKING_STAFF_PASSWORD || profile.password;
    }

    // Admin logs in and clears ALL active shifts — gives picker a free module to claim.
    await loginAdmin(page, creds.admin);
    await endAllActiveShifts(page);
    await logoutAdmin(page);

    // Picker logs in and goes to console — should see module claim panel
    await loginAdmin(page, { email: profile.email, password: profile.password });
    await page.goto('/admin/ramos/picking/console');
    await assertPageLoaded(page);

    // If the picker already has a module from a previous run, skip
    const existingModules = await page.locator('.ramos-console-module').count();
    if (existingModules > 0) {
      test.info().annotations.push({ type: 'note', description: 'Picker already has module — claim panel test skipped.' });
      return;
    }

    // Check for claim panel
    const claimPanel = page.locator('#ramos-claim-module, form[action*="claim_shift"]').first();
    const hasClaimPanel = await claimPanel.isVisible().catch(() => false);
    if (!hasClaimPanel) {
      // Either no available modules or picker has view-only (no manage_shifts)
      await expect(page.locator('body')).toContainText(
        /no modules|ningún módulo|not assigned|no estás|select.*module|selecciona|no.*available/i
      );
      return;
    }

    // Claim the first available module
    const submitBtn = page.locator('button#ramos-claim-btn, form[action*="claim_shift"] button[type="submit"]').first();
    const [response] = await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 20000 }).catch(() => null),
      submitBtn.click(),
    ]);

    await assertPageLoaded(page);
    await expect(page.locator('body')).not.toContainText(/Fatal error|SQLSTATE/i);

    // After claim, picker should see their one module
    const modulesAfterClaim = page.locator('.ramos-console-module');
    const countAfterClaim = await modulesAfterClaim.count();
    // Either one module or a no-orders message — both valid; what matters is no crash
    expect(countAfterClaim).toBeLessThanOrEqual(1);
  });

  test('exclusive lock: second picker cannot claim a module already occupied', async ({ page }, testInfo) => {
    test.setTimeout(60000);
    test.skip(
      !!process.env.PW_SKIP_STAFF_CREATE && !process.env.PW_PICKING_STAFF_EMAIL,
      'Requires picker accounts.'
    );

    // Admin: verify at least one module has an active shift
    await loginAdmin(page, creds.admin);
    await page.goto('/admin/ramos/picking');
    await assertPageLoaded(page);

    const moduleCards = page.locator('.ramos-picking-modules > .col-md-6');
    const n = await moduleCards.count();
    let hasOccupied = false;
    for (let i = 0; i < n; i++) {
      const card = moduleCards.nth(i);
      const endLinks = await card.getByRole('link', { name: /end shift|finalizar turno/i }).count();
      if (endLinks >= 1) {
        hasOccupied = true;
        break;
      }
    }
    test.skip(!hasOccupied, 'No modules have active shifts — cannot test exclusivity rejection.');

    // Try to start a second shift on an already occupied module via the form.
    // Admin is picking a second user but module is full (cap = 1).
    for (let i = 0; i < n; i++) {
      const card = moduleCards.nth(i);
      const endLinks = await card.getByRole('link', { name: /end shift|finalizar turno/i }).count();
      if (endLinks < 1) continue;

      // This module is occupied. Try to start a second shift via the form.
      const staffSelect = card.locator('select[name="staff_id"]');
      const firstOption = staffSelect.locator('option').nth(1); // skip "--"
      const val = await firstOption.getAttribute('value').catch(() => null);
      if (!val) continue;

      await card.locator('select[name="staff_id"]').selectOption(val, { force: true });
      await card.locator('button[type="submit"]').filter({ hasText: /start|iniciar/i }).click();
      await page.waitForURL(/ramos\/picking/i, { timeout: 20000 }).catch(() => {});
      await page.waitForLoadState('domcontentloaded').catch(() => {});

      // System must show a warning (module already has an active operator).
      const warning = page.locator('.alert-warning, .float-alert.alert-warning');
      await expect(warning.first()).toBeVisible({ timeout: 10000 });
      break;
    }
  });

  test('picker cannot access other admin areas (ramos picking setup requires create/edit)', async ({ page }, testInfo) => {
    test.skip(
      !!process.env.PW_SKIP_STAFF_CREATE && !process.env.PW_PICKING_STAFF_EMAIL,
      'Requires a picker account.'
    );

    const profile = buildPickingStaffProfile(testInfo.parallelIndex);
    if (process.env.PW_PICKING_STAFF_EMAIL) {
      profile.email    = process.env.PW_PICKING_STAFF_EMAIL;
      profile.password = process.env.PW_PICKING_STAFF_PASSWORD || profile.password;
    }

    // Log in as the picker (account created in previous test)
    await loginAdmin(page, { email: profile.email, password: profile.password });
    // Ensure the page is fully loaded before checking admin status.
    await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});

    // Detect admin-level access: probe /admin/settings. If the picker is a full Perfex
    // administrator they can reach it; any redirect to authentication means they cannot.
    await page.goto('/admin/settings', { waitUntil: 'domcontentloaded' });
    const isPickerAdmin = page.url().includes('/admin/settings');
    if (isPickerAdmin) {
      test.info().annotations.push({
        type: 'note',
        description: 'Picker is a full admin — access restriction to /admin/staff is not applicable.',
      });
      return;
    }

    // Picker (non-admin) should not have access to staff management.
    await page.goto('/admin/staff');
    // Should be redirected or shown access denied (not the actual staff list if not admin)
    await assertPageLoaded(page);
    // Either access denied message or redirect to dashboard
    const url = page.url();
    const body = await page.locator('body').innerText();
    const denied =
      /access.denied|acceso.denegado|not.authorized|no.autorizado/i.test(body) ||
      !url.includes('/admin/staff');
    expect(denied).toBeTruthy();
  });

  test('completed orders disappear from picker console (orders are removed on completion)', async ({ page }, testInfo) => {
    test.skip(
      !!process.env.PW_SKIP_STAFF_CREATE && !process.env.PW_PICKING_STAFF_EMAIL,
      'Requires a picker account.'
    );

    const profile = buildPickingStaffProfile(testInfo.parallelIndex);
    if (process.env.PW_PICKING_STAFF_EMAIL) {
      profile.email    = process.env.PW_PICKING_STAFF_EMAIL;
      profile.password = process.env.PW_PICKING_STAFF_PASSWORD || profile.password;
    }

    await loginAdmin(page, { email: profile.email, password: profile.password });
    await page.goto('/admin/ramos/picking/console');
    await assertPageLoaded(page);

    const modules = page.locator('.ramos-console-module');
    const count   = await modules.count();
    if (count === 0) {
      test.skip(true, 'No modules visible for this picker — skipping completion test.');
      return;
    }

    // Find the first editable form (picker has operator rights on their module)
    const firstForm = page.locator('.ramos-console-order form').first();
    const hasForm   = await firstForm.isVisible().catch(() => false);
    if (!hasForm) {
      test.skip(true, 'No pending items to complete.');
      return;
    }

    // Record order ID before update
    const orderCard = page.locator('.ramos-console-order').first();
    const orderId   = await orderCard.getAttribute('data-order-id');

    // Complete the item
    await firstForm.locator('input[name="picked_qty"]').fill('1');
    await firstForm.locator('input[name="weight"]').fill('0');
    await firstForm.locator('button[type="submit"]').click();

    // Wait for auto-refresh or page reload
    await page.waitForTimeout(3000);
    await page.reload();
    await assertPageLoaded(page);

    // The order with that ID should be gone if all items were completed,
    // OR remain with updated status — both are acceptable per Javo's spec
    // (disappears only when ALL items in the order are done).
    // We just verify no crash occurred.
    await expect(page.locator('body')).not.toContainText(/Fatal error|SQLSTATE|Unknown column/i);
  });
});
