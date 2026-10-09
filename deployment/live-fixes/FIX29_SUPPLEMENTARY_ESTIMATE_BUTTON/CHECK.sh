#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"
[[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || { echo "Usage: bash CHECK.sh /path/to/app"; exit 2; }
cd "$APP_ROOT"
F=resources/views/job-cards/show.blade.php
echo "ASSAM MOTORS FIX29 — READ ONLY CHECK"
test -f "$F" || { echo "MISSING $F"; exit 3; }
php artisan route:list --path=erp/job-cards 2>&1 | grep -q 'erp.estimates.create' || { echo "NO-GO: estimate create route missing"; exit 4; }
grep -q "@section('content')" "$F" || { echo "NO-GO: content marker missing"; exit 4; }
if grep -q "FIX29_SUPPLEMENTARY_ESTIMATE_SHORTCUT" "$F"; then echo "FIX29 already installed"; exit 5; fi
echo "PASS estimate route exists"
echo "PASS Job Card detail view baseline"
echo "CHECK RESULT: GO"
