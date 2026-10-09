#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"
if [[ -z "$APP_ROOT" || ! -f "$APP_ROOT/artisan" ]]; then
  echo "Usage: $0 /path/to/laravel/app"
  exit 2
fi
cd "$APP_ROOT"

echo "== ERP SIDEBAR CUSTOMER REFERENCES =="
grep -nE -B8 -A12 'legacy/workshop/customers\.php|erp\.customers|Customers'   resources/views/layouts/erp.blade.php 2>/dev/null || true

echo
echo "== JOB CARD INDEX HEADER / ACTIONS =="
sed -n '1,180p' resources/views/job-cards/index.blade.php 2>/dev/null || true

echo
echo "== FILE HASHES =="
sha256sum   resources/views/layouts/erp.blade.php   resources/views/job-cards/index.blade.php

echo
echo "READ ONLY COMPLETE"
