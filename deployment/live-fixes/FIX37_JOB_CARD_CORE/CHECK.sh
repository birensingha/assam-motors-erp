#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"
[[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || { echo "Usage: bash CHECK.sh /path/to/app"; exit 2; }
DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$APP_ROOT"
echo "ASSAM MOTORS FIX37 — JOB CARD CORE CHECK (READ ONLY)"
for f in app/Http/Controllers/Web/AdminJobCardPageController.php resources/views/job-cards/show.blade.php routes/web.php; do test -f "$f" || { echo "NO-GO missing $f"; exit 3; }; php -l "$f" >/dev/null; echo "PASS $f"; done
php -l "$DIR/PATCH_FIX37.php" >/dev/null
grep -q "public function deletePart" app/Http/Controllers/Web/AdminJobCardPageController.php
grep -q "job-cards/{job}/parts/{part}" routes/web.php
grep -q "Labour / ROT Control" resources/views/job-cards/show.blade.php
grep -q "<h2 class=\"section-title\">Parts</h2>" resources/views/job-cards/show.blade.php
php -r '
require "vendor/autoload.php";$app=require "bootstrap/app.php";$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach(["job_cards","job_card_parts","job_card_labour","parts_master","labour_master","inventory_movements","job_card_change_audits"] as $t){echo (Illuminate\Support\Facades\Schema::hasTable($t)?"PASS":"MISSING")." table $t\n";}
'
if grep -q "FIX37_JOB_CARD_CORE" app/Http/Controllers/Web/AdminJobCardPageController.php; then echo "FIX37 already appears installed"; exit 5; fi
echo "CHECK RESULT: GO"
