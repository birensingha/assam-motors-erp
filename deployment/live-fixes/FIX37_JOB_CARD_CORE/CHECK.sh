#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${1:-}"
[[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || {
  echo "Usage: bash CHECK.sh /path/to/app"
  exit 2
}

DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$APP_ROOT"

echo "ASSAM MOTORS FIX37 — JOB CARD CORE CHECK (READ ONLY)"

for f in   app/Http/Controllers/Web/AdminJobCardPageController.php   resources/views/job-cards/show.blade.php   routes/web.php
do
  if [[ ! -f "$f" ]]; then
    echo "NO-GO: missing file $f"
    exit 3
  fi
  php -l "$f" >/dev/null || {
    echo "NO-GO: PHP syntax error in $f"
    exit 3
  }
  echo "PASS file: $f"
done

php -l "$DIR/PATCH_FIX37.php" >/dev/null || {
  echo "NO-GO: PATCH_FIX37.php syntax error"
  exit 3
}
echo "PASS patcher syntax"

require_fixed() {
  local needle="$1"
  local file="$2"
  local label="$3"
  if grep -Fq "$needle" "$file"; then
    echo "PASS baseline: $label"
  else
    echo "NO-GO: baseline marker missing — $label"
    echo "FILE: $file"
    exit 4
  fi
}

require_fixed "public function deletePart"   app/Http/Controllers/Web/AdminJobCardPageController.php   "deletePart controller method"

require_fixed "job-cards/{job}/parts/{part}"   routes/web.php   "Job Card Part delete route"

require_fixed "Labour / ROT Control"   resources/views/job-cards/show.blade.php   "Labour / ROT block"

require_fixed "section-title\">Parts</h2>"   resources/views/job-cards/show.blade.php   "Parts block"

if grep -Fq "FIX37_JOB_CARD_CORE" app/Http/Controllers/Web/AdminJobCardPageController.php; then
  echo "FIX37 already appears installed"
  exit 5
fi

echo "== REQUIRED TABLES =="
php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$tables=["job_cards","job_card_parts","job_card_labour","parts_master","labour_master","inventory_movements","job_card_change_audits"];
$missing=[];
foreach($tables as $t){
  $ok=Illuminate\Support\Facades\Schema::hasTable($t);
  echo ($ok?"PASS":"MISSING")." table ".$t.PHP_EOL;
  if(!$ok)$missing[]=$t;
}
if($missing){
  fwrite(STDERR,"NO-GO: required tables missing: ".implode(",",$missing).PHP_EOL);
  exit(4);
}
'

echo "== DRY-RUN AGAINST CURRENT LIVE FILES =="
TMP="$(mktemp -d)"
cleanup(){ rm -rf "$TMP"; }
trap cleanup EXIT

mkdir -p   "$TMP/app/Http/Controllers/Web"   "$TMP/resources/views/job-cards"   "$TMP/routes"

cp -p app/Http/Controllers/Web/AdminJobCardPageController.php   "$TMP/app/Http/Controllers/Web/AdminJobCardPageController.php"
cp -p resources/views/job-cards/show.blade.php   "$TMP/resources/views/job-cards/show.blade.php"
cp -p routes/web.php   "$TMP/routes/web.php"

if ! php "$DIR/PATCH_FIX37.php" "$TMP"; then
  echo "NO-GO: FIX37 patcher does not match current live baseline."
  echo "Live source was NOT modified."
  exit 6
fi

for f in   "$TMP/app/Http/Controllers/Web/AdminJobCardPageController.php"   "$TMP/resources/views/job-cards/show.blade.php"   "$TMP/routes/web.php"
do
  php -l "$f" >/dev/null || {
    echo "NO-GO: dry-run produced invalid PHP/Blade in $f"
    exit 6
  }
done

grep -Fq "FIX37_STOCK_SAFE_PART_DELETE"   "$TMP/app/Http/Controllers/Web/AdminJobCardPageController.php" || {
  echo "NO-GO: dry-run stock reversal marker missing"
  exit 6
}
grep -Fq "FIX37_DIRECT_ENTRY_PANEL"   "$TMP/resources/views/job-cards/show.blade.php" || {
  echo "NO-GO: dry-run direct-entry UI marker missing"
  exit 6
}

echo "PASS dry-run patch against CURRENT live files"
echo "CHECK RESULT: GO"
