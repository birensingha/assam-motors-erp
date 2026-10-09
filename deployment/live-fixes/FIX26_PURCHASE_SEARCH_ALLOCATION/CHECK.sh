#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"
[[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || { echo "Usage: bash CHECK.sh /path/to/app"; exit 2; }
cd "$APP_ROOT"
echo "ASSAM MOTORS FIX26 — READ ONLY CHECK"
for f in app/Http/Controllers/Web/AdminPurchasePageController.php app/Services/PurchaseService.php resources/views/purchases/create.blade.php public/js/purchase-entry.js; do test -f "$f" || exit 3; echo "PASS $f"; done
grep -q "function partSearch" app/Http/Controllers/Web/AdminPurchasePageController.php
grep -q "function searchJobs" app/Services/PurchaseService.php
grep -q 'class="job-select"' resources/views/purchases/create.blade.php
php -l app/Http/Controllers/Web/AdminPurchasePageController.php >/dev/null
php -l app/Services/PurchaseService.php >/dev/null
echo "CHECK RESULT: GO"
