#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"; [[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || exit 2
cd "$APP_ROOT"
F=resources/views/job-cards/show.blade.php
echo "ASSAM MOTORS FIX29 — READ ONLY VERIFY"
grep -q "FIX29_SUPPLEMENTARY_ESTIMATE_SHORTCUT" "$F"
grep -q "Estimate / Supplementary Estimate" "$F"
grep -q "erp.estimates.create" "$F"
php artisan route:list --path=erp/job-cards 2>&1 | grep -q 'erp.estimates.create'
echo "PASS Job Card Estimate / Supplementary Estimate shortcut"
echo "VERIFY RESULT: PASS"
