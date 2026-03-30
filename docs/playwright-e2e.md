# Playwright Headed E2E (Ramos Workflow)

This setup validates the 6 workflow groups in headed mode on:

- Base URL: `http://127.0.0.1:8080` (default)
- Repo: `/var/www/ramos-php`

## Installed

- `@playwright/test`
- Chromium browser (`npx playwright install chromium`)

## Test Coverage

- `tests/e2e/specs/01.customer-order-placement.spec.ts`
- `tests/e2e/specs/02.automation-trigger.spec.ts`
- `tests/e2e/specs/03.inventory-po-requirements.spec.ts`
- `tests/e2e/specs/04.route-generation-management.spec.ts`
- `tests/e2e/specs/05.picking-modules-stations.spec.ts`
- `tests/e2e/specs/06.facturacion-billing.spec.ts`

## Credentials

Credentials are read from env vars first, then fallback to local defaults in `tests/e2e/utils/credentials.ts`.

Recommended env vars:

- `PW_BASE_URL=http://127.0.0.1:8080`
- `PW_ADMIN_EMAIL=developer@3ware.mx`
- `PW_ADMIN_PASSWORD=...`
- `PW_CUSTOMER_EMAIL=usuario@3ware.mx`
- `PW_CUSTOMER_PASSWORD=...`
- `PW_MOD1_EMAIL=modulo1@ramos.mx`
- `PW_MOD1_PASSWORD=...`
- `PW_MOD2_EMAIL=modulo2@ramos.mx`
- `PW_MOD2_PASSWORD=...`
- `PW_MOD3_EMAIL=modulo3@ramos.mx`
- `PW_MOD3_PASSWORD=...`
- `PW_MOD4_EMAIL=modulo4@ramos.mx`
- `PW_MOD4_PASSWORD=...`

## Run (Headed)

```bash
npm run pw:test:headed
```

Run all tests (uses headed by default from config):

```bash
npm run pw:test
```

Open HTML report:

```bash
npm run pw:report
```

## Notes

- Tests include setup guards to fail fast with clear messages when required seeded data is missing.
- Artifacts are saved to `test-results/playwright-artifacts`.
- For strict CI behavior, pass `--headed` only for local, and override `headless` if needed in CI.
