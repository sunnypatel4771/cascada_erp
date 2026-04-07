import { expect, Page } from '@playwright/test';

export type PickingStaffProfile = {
  email: string;
  password: string;
  firstName: string;
  lastName: string;
};

/**
 * Module-level timestamp ensures every call to buildPickingStaffProfile within the
 * same test-runner process returns the same email (serial tests share one process).
 */
const _runUnique = process.env.PW_PICKING_STAFF_UNIQUE || `${Date.now()}`.slice(-10);

/** True if this picker already appears in an "Active staff" block (avoids duplicate start_shift). */
function pickerListedInActiveStaffBlock(block: string, profile: PickingStaffProfile): boolean {
  const t = block.toLowerCase();
  if (!profile.firstName || !t.includes(profile.firstName.toLowerCase())) {
    return false;
  }
  const ln = profile.lastName.toLowerCase();
  if (t.includes(ln)) {
    return true;
  }
  const first4 = ln.length >= 4 ? ln.slice(0, 4) : ln;
  return first4.length >= 3 && t.includes(first4);
}

/**
 * Build unique picker credentials.
 * Override with PW_PICKING_STAFF_EMAIL / PASSWORD / FIRSTNAME / LASTNAME for a pre-created staff row
 * (dropdown shows "firstname lastname" in module shift form).
 * The unique suffix is stable within a single test-runner process so all serial
 * tests in a describe block see the same email address.
 */
export function buildPickingStaffProfile(workerIndex: number): PickingStaffProfile {
  const unique = `${workerIndex}-${_runUnique}`;

  return {
    email: process.env.PW_PICKING_STAFF_EMAIL || `e2e.picker.${unique}@ramos.test`,
    password: process.env.PW_PICKING_STAFF_PASSWORD || 'PickerE2E#2026',
    firstName: process.env.PW_PICKING_STAFF_FIRSTNAME || 'E2E',
    lastName: process.env.PW_PICKING_STAFF_LASTNAME || `Picker${workerIndex}`,
  };
}

/**
 * Find an existing staff member by email and set their Ramos permissions to
 * view + manage_shifts only (removes edit/create/delete/other-module permissions).
 * Used when PW_PICKING_STAFF_EMAIL targets a pre-existing account whose permissions may be broader.
 * No-ops silently if the staff member can't be found (test skips permission update gracefully).
 */
/**
 * Find a staff member by email (scans table rows), open their edit form and
 * restrict them to Ramos view + manage_shifts only.
 * Returns true if permissions were updated, false if the staff was not found or is an admin
 * (admins cannot be permission-restricted this way — callers should handle this).
 */
export async function restrictStaffToManageShiftsOnly(page: Page, email: string): Promise<boolean> {
  await page.goto('/admin/staff');
  await page.waitForLoadState('domcontentloaded');

  // Perfex staff table: rows contain email as cell text but the edit link uses the staff name.
  // We find the row containing the email string and click its first member-edit link.
  const memberLink = page.locator('table tbody tr').filter({ hasText: email }).locator('a[href*="staff/member"]').first();

  if ((await memberLink.count()) === 0) {
    return false;
  }

  await memberLink.click();
  await page.waitForURL(/staff\/member\/\d+/, { timeout: 20000 }).catch(() => {});
  await page.waitForLoadState('domcontentloaded');

  // If the account is a Perfex administrator, we cannot restrict their view via permissions.
  const adminCb = page.locator('input#administrator');
  const isAdmin = await adminCb.isChecked().catch(() => false);
  if (isAdmin) {
    return false;
  }

  await applyRamosManageShiftsPermissions(page);

  const saveBtn = page.locator('form.staff-form button[type="submit"].btn-primary');
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }).catch(() => {}),
    saveBtn.click(),
  ]);
  // Wait for the page to fully settle so subsequent goto() calls don't get ERR_ABORTED.
  await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
  return true;
}

/**
 * End ALL active shifts on every module (admin only).
 * Useful as a test setup step to guarantee a clean slate before tests that need free module slots.
 */
export async function endAllActiveShifts(page: Page): Promise<void> {
  // Retry the initial navigation in case a previous redirect is still in flight (ERR_ABORTED guard).
  for (let nav = 0; nav < 3; nav++) {
    try {
      await page.goto('/admin/ramos/picking', { waitUntil: 'domcontentloaded', timeout: 20000 });
      break;
    } catch {
      await page.waitForTimeout(800);
    }
  }
  await expect(page.locator('.ramos-picking-modules')).toBeVisible({ timeout: 20000 });

  const moduleCards = page.locator('.ramos-picking-modules > .col-md-6');
  const n = await moduleCards.count();

  for (let i = 0; i < n; i++) {
    const card = moduleCards.nth(i);
    // Click every "End shift" link in the card (exclusive lock = at most 1).
    const endLinks = card.getByRole('link', { name: /end shift|finalizar turno/i });
    const count = await endLinks.count();
    for (let j = 0; j < count; j++) {
      page.once('dialog', (d) => d.accept().catch(() => {}));
      await endLinks.first().click().catch(() => {});
      await page.waitForURL(/ramos\/picking/i, { timeout: 15000 }).catch(() => {});
      await page.waitForLoadState('domcontentloaded').catch(() => {});
    }
  }
}

/**
 * On the new/edit staff member form: leave only Ramos → View checked (uncheck other capabilities).
 * Admin must have permission to manage staff.
 */
export async function applyRamosViewOnlyPermissions(page: Page): Promise<void> {
  const permTab = page.locator('a[href="#staff_permissions"], a[aria-controls="staff_permissions"]').first();
  await permTab.click();
  await expect(page.locator('#staff_permissions table.roles')).toBeVisible({ timeout: 15000 });

  await page.evaluate(() => {
    document.querySelectorAll<HTMLInputElement>('input.capability:not([disabled])').forEach((el) => {
      el.checked = false;
    });
    const ramosView = document.querySelector<HTMLInputElement>('#ramos_view');
    if (ramosView && !ramosView.disabled) {
      ramosView.checked = true;
    }
  });
}

/**
 * On the new/edit staff member form: grant Ramos → View + manage_shifts only.
 * This is the correct minimal set for a picker operator (Javo requirement).
 */
export async function applyRamosManageShiftsPermissions(page: Page): Promise<void> {
  const permTab = page.locator('a[href="#staff_permissions"], a[aria-controls="staff_permissions"]').first();
  await permTab.click();
  await expect(page.locator('#staff_permissions table.roles')).toBeVisible({ timeout: 15000 });

  await page.evaluate(() => {
    document.querySelectorAll<HTMLInputElement>('input.capability:not([disabled])').forEach((el) => {
      el.checked = false;
    });
    const ramosView = document.querySelector<HTMLInputElement>('#ramos_view');
    if (ramosView && !ramosView.disabled) {
      ramosView.checked = true;
    }
    const ramosManageShifts = document.querySelector<HTMLInputElement>('#ramos_manage_shifts');
    if (ramosManageShifts && !ramosManageShifts.disabled) {
      ramosManageShifts.checked = true;
    }
  });
}

const submitStaffForm = async (page: Page) => {
  await page.locator('form.staff-form button[type="submit"].btn-primary').click();
};

/**
 * Create staff member with Ramos view-only, non-admin.
 */
export async function createPickingStaffMember(page: Page, profile: PickingStaffProfile): Promise<void> {
  await page.goto('/admin/staff/member');
  await expect(page.locator('input[name="firstname"]')).toBeVisible({ timeout: 20000 });

  await page.locator('input[name="firstname"]').fill(profile.firstName);
  await page.locator('input[name="lastname"]').fill(profile.lastName);
  await page.locator('input[name="email"]').fill(profile.email);

  const adminCb = page.locator('input#administrator');
  if (await adminCb.isVisible().catch(() => false)) {
    await adminCb.setChecked(false);
  }

  const welcome = page.locator('input#send_welcome_email');
  if (await welcome.isVisible().catch(() => false)) {
    await welcome.setChecked(false);
  }

  const passwordInput = page.locator('input[name="password"].password');
  await passwordInput.fill(profile.password);

  await applyRamosViewOnlyPermissions(page);
  await submitStaffForm(page);

  await page.waitForURL(/staff\/member\/\d+/, { timeout: 30000 }).catch(async () => {
    const body = await page.locator('body').innerText();
    if (/already exists|duplicate|exists/i.test(body)) {
      throw new Error(`Staff email may already exist: ${profile.email}. Set PW_PICKING_STAFF_EMAIL or PW_PICKING_STAFF_UNIQUE.`);
    }
    throw new Error('Staff create did not redirect to member profile.');
  });
}

/**
 * Create staff member with Ramos view + manage_shifts only (non-admin).
 * This matches the minimal picker RBAC required by Javo.
 */
export async function createManageShiftsOnlyStaffMember(page: Page, profile: PickingStaffProfile): Promise<void> {
  await page.goto('/admin/staff/member');
  await expect(page.locator('input[name="firstname"]')).toBeVisible({ timeout: 20000 });

  await page.locator('input[name="firstname"]').fill(profile.firstName);
  await page.locator('input[name="lastname"]').fill(profile.lastName);
  await page.locator('input[name="email"]').fill(profile.email);

  const adminCb = page.locator('input#administrator');
  if (await adminCb.isVisible().catch(() => false)) {
    await adminCb.setChecked(false);
  }

  const welcome = page.locator('input#send_welcome_email');
  if (await welcome.isVisible().catch(() => false)) {
    await welcome.setChecked(false);
  }

  const passwordInput = page.locator('input[name="password"].password');
  await passwordInput.fill(profile.password);

  await applyRamosManageShiftsPermissions(page);
  await submitStaffForm(page);

  await page.waitForURL(/staff\/member\/\d+/, { timeout: 30000 }).catch(async () => {
    const body = await page.locator('body').innerText();
    if (/already exists|duplicate|exists/i.test(body)) {
      throw new Error(`Staff email may already exist: ${profile.email}. Set PW_PICKING_STAFF_EMAIL or PW_PICKING_STAFF_UNIQUE.`);
    }
    throw new Error('Staff create did not redirect to member profile.');
  });
}

/**
 * Start operator shift for the given staff on the first picking module card (requires admin with ramos edit + staff list includes new user).
 */
/**
 * Start an operator shift for {@link profile} on the first picking module that has
 * fewer than two active shifts (DB rule in Modules_model).
 */
export async function startOperatorShiftOnFirstModule(page: Page, profile: PickingStaffProfile): Promise<void> {
  await page.goto('/admin/ramos/picking');
  await expect(page.locator('.ramos-picking-modules')).toBeVisible({ timeout: 20000 });

  const moduleCards = page.locator('.ramos-picking-modules > .col-md-6');
  const n = await moduleCards.count();
  if (n === 0) {
    throw new Error('No picking module cards found.');
  }

  const modulesRoot = page.locator('.ramos-picking-modules');
  const escape = (s: string) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const namePattern = new RegExp(escape(profile.lastName), 'i');

  // Already active on at least one module: do not require another free slot (max-2 rule can block every other card).
  for (let i = 0; i < n; i++) {
    const card = moduleCards.nth(i);
    const activeHeading = card.locator('h6').filter({ hasText: /Active staff|Personal activo|staff/i });
    const activeUl = activeHeading.locator('xpath=following-sibling::ul[1]');
    const activeText =
      (await activeUl.count()) > 0 ? ((await activeUl.innerText().catch(() => '')) || '') : '';
    if (pickerListedInActiveStaffBlock(activeText, profile)) {
      return;
    }
  }

  let target = moduleCards.first();
  let chosen = false;
  for (let i = 0; i < n; i++) {
    const card = moduleCards.nth(i);
    // Exclusive lock: only 1 active operator per module.
    const endShiftCount = await card.getByRole('link', { name: /end shift|finalizar turno/i }).count();
    if (endShiftCount >= 1) {
      continue;
    }
    const activeHeading = card.locator('h6').filter({ hasText: /Active staff|Personal activo|staff/i });
    const activeUl = activeHeading.locator('xpath=following-sibling::ul[1]');
    const activeText =
      (await activeUl.count()) > 0 ? ((await activeUl.innerText().catch(() => '')) || '') : '';
    if (pickerListedInActiveStaffBlock(activeText, profile)) {
      continue;
    }
    target = card;
    chosen = true;
    break;
  }

  if (!chosen) {
    throw new Error(
      'No picking module has a free shift slot for this user, or the user already has an active shift on every reachable module. ' +
        'End shifts on /admin/ramos/picking or free a slot (max 1 operator per module).'
    );
  }

  const staffSelect = target.locator('select[name="staff_id"]');
  await expect(staffSelect).toBeAttached();

  const shiftForm = target.locator('form[action*="start_shift"]');
  const formAction = await shiftForm.getAttribute('action');
  const idMatch = formAction?.match(/start_shift\/(\d+)/);
  const moduleId = idMatch ? idMatch[1] : null;

  let opt = target
    .locator('select[name="staff_id"] option')
    .filter({ hasText: new RegExp(escape(profile.lastName), 'i') })
    .first();
  if ((await opt.count()) === 0 && profile.firstName) {
    opt = target
      .locator('select[name="staff_id"] option')
      .filter({ hasText: new RegExp(escape(profile.firstName), 'i') })
      .first();
  }
  if ((await opt.count()) === 0) {
    opt = target
      .locator('select[name="staff_id"] option')
      .filter({ hasText: new RegExp(`${escape(profile.firstName)}\\s+${escape(profile.lastName)}`, 'i') })
      .first();
  }
  if ((await opt.count()) === 0) {
    throw new Error(
      `Staff "${profile.firstName} ${profile.lastName}" not found in module dropdown. ` +
        `Set PW_PICKING_STAFF_FIRSTNAME / PW_PICKING_STAFF_LASTNAME to match Perfex staff name.`
    );
  }
  const value = await opt.getAttribute('value');

  const pickerId = `#picking_staff_${moduleId}`;
  const staffById = moduleId ? page.locator(pickerId) : staffSelect;
  await staffById.selectOption(value!, { force: true });

  await page.evaluate(
    ({ selector, val }) => {
      const el = document.querySelector(selector) as HTMLSelectElement | null;
      if (!el) return;
      el.value = String(val);
      el.dispatchEvent(new Event('change', { bubbles: true }));
      type Jq = (sel: string) => { selectpicker: (method: string) => void };
      const w = window as unknown as { jQuery?: Jq };
      if (typeof w.jQuery === 'function') {
        try {
          w.jQuery(selector).selectpicker('refresh');
        } catch {
          /* ignore */
        }
      }
    },
    { selector: moduleId ? pickerId : 'select[name="staff_id"]', val: value }
  );

  // Only try to select the role dropdown if it's visible (canEdit=true for admin, hidden for manage_shifts-only).
  const roleSelect = target.locator('select[name="role"]');
  const hasRoleSelect = await roleSelect.isVisible().catch(() => false);
  if (hasRoleSelect) {
    await roleSelect.selectOption('operator', { force: true });
    await roleSelect.dispatchEvent('change');
  }

  // Short-circuit: if the staff member is already in the active list (stale page state), we're done.
  if ((await modulesRoot.getByText(namePattern).count()) > 0) {
    return;
  }

  // Record how many end-shift links the chosen card has before submission.
  const endLinksBefore = await target.getByRole('link', { name: /end shift|finalizar turno/i }).count();

  await target.locator('button[type="submit"]').filter({ hasText: /start|iniciar/i }).click();
  await page.waitForURL(/ramos\/picking/i, { timeout: 25000 }).catch(() => {});
  await page.waitForLoadState('networkidle').catch(() => {});

  // Check if a warning alert was shown (shift failed).
  const shiftFailed = await page
    .locator('.alert-warning, .float-alert.alert-warning')
    .filter({ hasText: /shift|turno|Unable|No se pudo|active operator|operador activo|start shift/i })
    .first()
    .isVisible()
    .catch(() => false);

  if (shiftFailed) {
    throw new Error(
      'Shift did not start. Module already has an active operator (exclusive 1-per-module lock). ' +
        'End the current shift on /admin/ramos/picking or pick another module.'
    );
  }

  // Primary success check: name appears in active staff (works when PW_PICKING_STAFF_LASTNAME matches Perfex name).
  if ((await modulesRoot.getByText(namePattern).count()) > 0) {
    return;
  }

  // Fallback success check: the chosen module now has more "End shift" links than before.
  // This works even when the Perfex display name differs from the env-var name.
  const moduleCards2 = page.locator('.ramos-picking-modules > .col-md-6');
  const n2 = await moduleCards2.count();
  for (let i = 0; i < n2; i++) {
    const card = moduleCards2.nth(i);
    const endLinksAfter = await card.getByRole('link', { name: /end shift|finalizar turno/i }).count();
    if (endLinksAfter > endLinksBefore) {
      return; // A shift was started on this module — success.
    }
  }

  // Last resort: wait for name to appear (will eventually show with full page refresh).
  await expect(modulesRoot.getByText(namePattern).first()).toBeVisible({ timeout: 20000 });
}

/**
 * Ensure at most 1 module has an active shift overall (exclusive lock = 1 per module and only 1 module per picker).
 * With the exclusive lock, at most 1 module can ever have a shift, but this guard handles edge cases.
 * If name-based lookup fails (Perfex name differs from env vars), falls back to end-shift-link count.
 */
export async function consolidatePickerToSingleModule(page: Page, profile: PickingStaffProfile): Promise<void> {
  for (let attempt = 0; attempt < 8; attempt++) {
    await page.goto('/admin/ramos/picking');
    await expect(page.locator('.ramos-picking-modules')).toBeVisible({ timeout: 20000 });

    const moduleCards = page.locator('.ramos-picking-modules > .col-md-6');
    const n = await moduleCards.count();
    const moduleIndexesWithPicker: number[] = [];
    const moduleIndexesWithShift: number[] = [];

    for (let i = 0; i < n; i++) {
      const card = moduleCards.nth(i);
      const endLinks = await card.getByRole('link', { name: /end shift|finalizar turno/i }).count();
      if (endLinks > 0) {
        moduleIndexesWithShift.push(i);
      }
      const activeHeading = card.locator('h6').filter({ hasText: /Active staff|Personal activo/i });
      const activeUl = activeHeading.locator('xpath=following-sibling::ul[1]');
      if ((await activeUl.count()) === 0) continue;
      const activeText = await activeUl.innerText().catch(() => '');
      if (pickerListedInActiveStaffBlock(activeText, profile)) {
        moduleIndexesWithPicker.push(i);
      }
    }

    // Use name-based list if available; fall back to end-shift-link list (exclusive lock means ≤1 anyway).
    const relevant = moduleIndexesWithPicker.length > 0 ? moduleIndexesWithPicker : moduleIndexesWithShift;

    if (relevant.length <= 1) {
      return;
    }

    // End the last one (keep the first).
    const endIdx = relevant[relevant.length - 1];
    const card = moduleCards.nth(endIdx);
    page.once('dialog', (d) => d.accept().catch(() => {}));
    await card.getByRole('link', { name: /end shift|finalizar turno/i }).first().click();
    await page.waitForURL(/ramos\/picking/i, { timeout: 20000 }).catch(() => {});
  }
}

export async function logoutAdmin(page: Page): Promise<void> {
  page.once('dialog', (dialog) => {
    dialog.accept().catch(() => {});
  });
  await page.goto('/admin/authentication/logout');
  await page.waitForURL(/authentication/i, { timeout: 20000 }).catch(() => {});
}
