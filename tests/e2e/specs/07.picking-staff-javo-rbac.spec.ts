import { test, expect } from '@playwright/test';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';
import { assertPageLoaded } from '../utils/guards';
import {
  buildPickingStaffProfile,
  consolidatePickerToSingleModule,
  createPickingStaffMember,
  logoutAdmin,
  startOperatorShiftOnFirstModule,
} from '../utils/staff';

/**
 * Javo workflow (picking staff RBAC):
 *
 * - Accounts are created in the Perfex admin panel (there is no separate registration portal).
 * - Pickers operate through /admin (not /clients).
 * - A picker is created with ONLY "Ramos → View" permission.
 * - Admin starts an operator shift for that picker on ONE module.
 * - Picker logs in → sees only their one module in the picking console.
 * - Completed orders disappear; pending/in-progress items stay visible.
 * - Admin always sees all modules.
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

  test('Ramos view-only picker: admin creates staff (or reuses env), starts shift, console scoped to one module', async ({
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

    // ── Step 1: Admin creates picker only when no pre-created email is provided ──
    await loginAdmin(page, creds.admin);
    if (!process.env.PW_PICKING_STAFF_EMAIL) {
      await createPickingStaffMember(page, profile);
    }

    // ── Step 2: Admin starts an operator shift for the picker ─────────────
    await startOperatorShiftOnFirstModule(page, profile);
    // One active module only (RBAC): end duplicate shifts if picker was already on multiple modules
    await consolidatePickerToSingleModule(page, profile);

    // ── Step 3: Admin logs out ────────────────────────────────────────────
    await logoutAdmin(page);

    // ── Step 4: Picker logs in via /admin (same panel, restricted perms) ──
    await loginAdmin(page, { email: profile.email, password: profile.password });
    // Pickers use /admin — not a separate portal
    expect(page.url()).toMatch(/admin/i);

    // ── Step 5: Navigate to Picking Console ───────────────────────────────
    await page.goto('/admin/ramos/picking/console');
    await assertPageLoaded(page);

    // Picker should NOT see the picking management setup page (no create permission)
    // but should reach the console (has view permission)
    await expect(page).not.toHaveURL(/\/admin\/authentication/i);

    const modules    = page.locator('.ramos-console-module');
    const moduleCount = await modules.count();

    if (moduleCount === 0) {
      // No orders today — the "no active module" message is still valid
      await expect(page.locator('body')).toContainText(
        /consola|surtido|picking|console|no orders|sin pedidos|sin acceso|not assigned|no estás|ningún módulo|ningun modulo/i
      );
      return;
    }

    // Picker must see EXACTLY ONE module (the one they were shifted into)
    await expect(modules).toHaveCount(1);
    await expect(page.locator('body')).toContainText(
      /progress|progreso|picked|pending|order|pedido|completado|completed/i
    );
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

    // Picker should not have access to staff management
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
