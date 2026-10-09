#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${1:-}"
if [[ -z "$APP_ROOT" || ! -f "$APP_ROOT/artisan" ]]; then
  echo "Usage: bash INSPECT.sh /path/to/laravel/app"
  exit 2
fi

cd "$APP_ROOT"

echo "ASSAM MOTORS FIX33 — JOB CARD PART REVERSAL / UI INSPECT (READ ONLY)"
echo "APP_ROOT: $APP_ROOT"
echo

echo "== JOB CARD / PART ROUTES =="
php artisan route:list 2>&1 | grep -Ei 'job-cards|job.card|part|labour|rot' | grep -Ei 'delete|destroy|remove|part|labour|job-cards' | head -n 220 || true
echo

echo "== EXACT DELETE-BLOCK MESSAGE LOCATION =="
grep -Rni --include='*.php' --include='*.blade.php'   -E 'already been issued/fitted|Stock reversal is required|Direct Delete is blocked|issued/fitted'   app resources routes public 2>/dev/null | head -n 120 || true
echo

echo "== PART DELETE / REVERSAL METHODS =="
grep -Rni --include='*.php'   -E 'function .*part|destroyPart|deletePart|removePart|reverse.*stock|stock.*revers|issue.*part|fitted|issued_qty|stock_ledger|inventory'   app/Http app/Services app/Models 2>/dev/null | head -n 320 || true
echo

echo "== ADMIN JOBCARD CONTROLLER RELEVANT BLOCKS =="
F=app/Http/Controllers/Web/AdminJobCardPageController.php
if [[ -f "$F" ]]; then
  grep -nEi -A30 -B10     'part|labour|delete|destroy|remove|stock|reverse|issued|fitted' "$F" | head -n 420 || true
fi
echo

echo "== JOBCARD SERVICE RELEVANT BLOCKS =="
F=app/Services/JobCardService.php
if [[ -f "$F" ]]; then
  grep -nEi -A35 -B12     'part|labour|delete|destroy|remove|stock|reverse|issued|fitted|inventory' "$F" | head -n 520 || true
fi
echo

echo "== STOCK / INVENTORY SERVICE CANDIDATES =="
find app/Services app/Models -maxdepth 2 -type f \( -iname '*Stock*.php' -o -iname '*Inventory*.php' -o -iname '*Part*.php' \) -print | sort
echo

echo "== STOCK / INVENTORY REVERSAL MARKERS =="
grep -Rni --include='*.php'   -E 'reverse|reversal|return.*stock|stock.*return|movement|ledger|issue.*qty|available_qty|on_hand|stock_qty'   app/Services app/Models 2>/dev/null | head -n 420 || true
echo

echo "== JOBCARD SHOW PARTS/LABOUR MARKUP =="
F=resources/views/job-cards/show.blade.php
if [[ -f "$F" ]]; then
  grep -nEi -A55 -B12     'Parts|Labour|ROT|job_card_parts|job_card_labour|Delete|Remove|issued|fitted|table|section-title' "$F" | head -n 620 || true
fi
echo

echo "== PURCHASE LINE-ITEM UI REFERENCE =="
F=resources/views/purchases/create.blade.php
if [[ -f "$F" ]]; then
  grep -nEi -A45 -B12     'line-table|line-scroll|thead|tbody|Part|Qty|Rate|Amount|remove|table' "$F" | head -n 420 || true
fi
echo

echo "== SCHEMA: job_card_parts =="
php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
try {
  $cols=DB::select("SHOW COLUMNS FROM job_card_parts");
  foreach($cols as $c){ echo $c->Field." | ".$c->Type." | ".$c->Null." | ".($c->Default??"NULL").PHP_EOL; }
} catch(Throwable $e){ echo "SCHEMA ERROR: ".$e->getMessage().PHP_EOL; }
'
echo

echo "== SCHEMA: STOCK-LIKE TABLES =="
php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
try {
  $db=DB::getDatabaseName();
  $rows=DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema=? AND (table_name LIKE ? OR table_name LIKE ? OR table_name LIKE ? OR table_name LIKE ?) ORDER BY table_name",[$db,"%stock%","%invent%","%ledger%","%movement%"]);
  foreach($rows as $r){ echo $r->table_name.PHP_EOL; }
} catch(Throwable $e){ echo "TABLE ERROR: ".$e->getMessage().PHP_EOL; }
'
echo

echo "== SAMPLE JOB CARD PART STATES (READ ONLY) =="
php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
try {
  $rows=DB::table("job_card_parts")->orderByDesc("id")->limit(12)->get();
  echo "rows=".count($rows).PHP_EOL;
  foreach($rows as $r){
    $a=(array)$r;
    foreach(["id","job_card_id","part_id","part_code","part_name","qty","quantity","issued_qty","fitted_qty","status","source","stock_issue_id","created_at","updated_at"] as $k){
      if(array_key_exists($k,$a)) echo $k."=".($a[$k]===null?"NULL":$a[$k])." ";
    }
    echo PHP_EOL;
  }
} catch(Throwable $e){ echo "SAMPLE ERROR: ".$e->getMessage().PHP_EOL; }
'
echo

echo "== FILE HASHES =="
for f in   app/Http/Controllers/Web/AdminJobCardPageController.php   app/Services/JobCardService.php   resources/views/job-cards/show.blade.php   resources/views/purchases/create.blade.php
do
  [[ -f "$f" ]] && sha256sum "$f"
done

echo
echo "INSPECT COMPLETE — READ ONLY"
