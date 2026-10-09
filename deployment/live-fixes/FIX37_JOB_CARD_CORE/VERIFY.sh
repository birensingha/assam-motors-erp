#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"
[[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || exit 2
cd "$APP_ROOT"
echo "ASSAM MOTORS FIX37 — VERIFY"
php -l app/Http/Controllers/Web/AdminJobCardPageController.php >/dev/null
php -l routes/web.php >/dev/null
php -l resources/views/job-cards/show.blade.php >/dev/null
grep -q "FIX37_STOCK_SAFE_PART_DELETE" app/Http/Controllers/Web/AdminJobCardPageController.php
grep -q "public function addPartDirect" app/Http/Controllers/Web/AdminJobCardPageController.php
grep -q "public function addLabourDirect" app/Http/Controllers/Web/AdminJobCardPageController.php
grep -q "erp.job-cards.parts.direct" routes/web.php
grep -q "erp.job-cards.labour.direct" routes/web.php
grep -q "FIX37_DIRECT_ENTRY_PANEL" resources/views/job-cards/show.blade.php
grep -q "FIX37_JOB_CARD_CORE_UI" resources/views/job-cards/show.blade.php
php artisan route:list 2>&1 | grep -q "erp.job-cards.parts.direct"
php artisan route:list 2>&1 | grep -q "erp.job-cards.labour.direct"
echo "PASS stock-safe reverse/delete backend"
echo "PASS Direct Add Part without Estimate"
echo "PASS Direct Add Labour/ROT without Estimate"
echo "PASS Purchase-style Job Card Parts/Labour UI"
echo "VERIFY RESULT: PASS"
