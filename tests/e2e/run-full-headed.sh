#!/usr/bin/env bash
# Full Ramos E2E in headed mode. Optional: create tests/e2e/.env.picker with picker env vars.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

if [[ -f tests/e2e/.env.picker ]]; then
  set -a
  # shellcheck disable=SC1091
  source tests/e2e/.env.picker
  set +a
fi

exec npx playwright test --reporter=list "$@"
