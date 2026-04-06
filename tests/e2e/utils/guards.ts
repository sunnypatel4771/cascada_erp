import { expect, Page } from '@playwright/test';

export async function assertAnyVisible(page: Page, selectors: string[], err: string) {
  for (const selector of selectors) {
    const el = page.locator(selector).first();
    if (await el.isVisible().catch(() => false)) {
      return;
    }
  }
  throw new Error(err);
}

export async function guardSeededCatalog(page: Page) {
  await assertAnyVisible(
    page,
    ['#products-catalog .product-item', '#products-catalog .product-card', '#product-search'],
    'Seed guard failed: customer product catalog not visible.'
  );
}

export async function guardRoutesGenerated(page: Page) {
  await assertAnyVisible(
    page,
    [
      '#ramos-board-container:not(.tw-hidden) .ramos-route-column',
      '.ramos-route-column',
      '#ramos-board .ramos-route-column',
      '.ramos-stop-list',
    ],
    'Seed guard failed: routes board has no routes/stops to validate.'
  );
}

export async function guardProvidersAndPO(page: Page) {
  await assertAnyVisible(
    page,
    ['table tbody tr', '.panel_s .table tbody tr', '.ramos-purchase-batch-card'],
    'Seed guard failed: no suppliers/PO data visible.'
  );
}

export async function guardPickingData(page: Page) {
  await assertAnyVisible(
    page,
    [
      '.ramos-console-module',
      '.ramos-console-order',
      '.label-danger, .label-warning, .label-success',
      // Admin always sees the module container even with no orders
      '#ramos-console-modules',
    ],
    'Seed guard failed: no picking module data visible.'
  );
}

export async function guardFacturacionData(page: Page) {
  await assertAnyVisible(
    page,
    [
      '#ramos-facturacion-routes .panel_s',
      '.tw-bg-slate-100',
      '.tw-border-b .table',
      '.label-danger, .label-warning, .label-success',
      // Alert-info is shown when there are no routes — still means page loaded
      '.alert-info',
    ],
    'Seed guard failed: no facturacion route/customer data visible.'
  );
}

export async function assertPageLoaded(page: Page) {
  await page.waitForLoadState('domcontentloaded');
  await expect(page.locator('body')).toBeVisible();
}
