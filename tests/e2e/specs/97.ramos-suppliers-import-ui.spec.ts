import { expect, test } from '@playwright/test';
import * as XLSX from 'xlsx';
import { loginAdmin } from '../utils/auth';
import { creds } from '../utils/credentials';

const xlsxPath =
  process.env.PW_SUPPLIERS_XLSX ||
  '/home/usama-skakeel/Downloads/RAMOS_PROVEEDORES-ABRIL 2026 (1).xlsx';

type SupplierRow = {
  supplier_name: string;
  contact_name?: string;
  phone?: string;
  phone2?: string;
  Notes?: string;
};

function normalizeName(name: string) {
  return name.trim().toLowerCase();
}

function readUniqueSuppliers(path: string): SupplierRow[] {
  const wb = XLSX.readFile(path);
  const sheetName = wb.SheetNames[0];
  const ws = wb.Sheets[sheetName];
  const rows = XLSX.utils.sheet_to_json<SupplierRow>(ws, { defval: '' });

  const seen = new Set<string>();
  const out: SupplierRow[] = [];
  for (const r of rows) {
    const name = String((r as any).supplier_name || '').trim();
    if (!name) continue;
    const key = normalizeName(name);
    if (seen.has(key)) continue;
    seen.add(key);
    out.push({
      supplier_name: name,
      contact_name: String((r as any).contact_name || '').trim(),
      phone: String((r as any).phone || '').trim(),
      phone2: String((r as any).phone2 || '').trim(),
      Notes: String((r as any).Notes || '').trim(),
    });
  }
  return out;
}

async function addSupplier(page: import('@playwright/test').Page, s: SupplierRow) {
  await page.getByRole('button', { name: /New Supplier|\+ New Supplier|ramos_suppliers_add/i }).click();

  await expect(page.locator('#ramosSupplierModal')).toBeVisible();

  await page.locator('#ramosSupplierModal input[name="supplier_name"]').fill(s.supplier_name);
  if (s.contact_name) await page.locator('#ramosSupplierModal input[name="contact_name"]').fill(s.contact_name);
  if (s.phone) await page.locator('#ramosSupplierModal input[name="phone"]').fill(s.phone);

  const notes = [s.Notes || '', s.phone2 ? `Phone2: ${s.phone2}` : ''].filter(Boolean).join('\n');
  if (notes) await page.locator('#ramosSupplierModal textarea[name="notes"]').fill(notes);

  await Promise.all([
    page.waitForLoadState('domcontentloaded'),
    page.locator('#ramosSupplierModal button[type="submit"]').click(),
  ]);
}

test('Import Ramos suppliers via UI (skip duplicates in file)', async ({ page }) => {
  test.setTimeout(900_000);
  if (!creds.admin.password) {
    test.skip(true, 'PW_ADMIN_PASSWORD is required');
  }

  const suppliers = readUniqueSuppliers(xlsxPath);
  expect(suppliers.length).toBeGreaterThan(0);

  await loginAdmin(page, creds.admin);
  await page.goto('/admin/ramos/suppliers', { waitUntil: 'domcontentloaded' });

  // Build an in-page set of existing supplier names (if any are visible).
  const existing = new Set<string>(
    (await page.locator('table tbody tr td:first-child').allTextContents()).map((t) => normalizeName(t))
  );

  let created = 0;
  let skipped = 0;
  for (const s of suppliers) {
    const key = normalizeName(s.supplier_name);
    if (existing.has(key)) {
      skipped++;
      continue;
    }
    await addSupplier(page, s);

    // Some installs show success/error toasts; just ensure we are back on the list.
    await expect(page).toHaveURL(/\/admin\/ramos\/suppliers/i);
    existing.add(key);
    created++;
  }

  // Verify at least one known record is visible
  await expect(page.locator('table')).toContainText(/EVA/i);
  test.info().annotations.push({ type: 'import', description: `created=${created} skipped=${skipped}` });
});

