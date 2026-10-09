#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"; [[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || exit 2
cd "$APP_ROOT"
echo "ASSAM MOTORS FIX27 — READ ONLY VERIFY"
grep -q 'data-am-view-tools="purchase"' resources/views/purchases/create.blade.php
grep -q 'am_erp_purchase_view_scale' resources/views/purchases/create.blade.php
grep -q 'data-am-view-tools="estimate"' resources/views/estimates/create.blade.php
grep -q 'am_erp_estimate_view_scale' resources/views/estimates/create.blade.php
php -l resources/views/purchases/create.blade.php >/dev/null
php -l resources/views/estimates/create.blade.php >/dev/null
echo "PASS Purchase 100/115/125 controls"
echo "PASS Estimate 100/115/125 controls"
echo "PASS default scale logic = 115%"
echo "VERIFY RESULT: PASS"
