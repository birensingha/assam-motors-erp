#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${1:-}"
if [[ -z "$APP_ROOT" || ! -f "$APP_ROOT/artisan" ]]; then
  echo "Usage: bash INSPECT.sh /path/to/laravel/app"
  exit 2
fi

cd "$APP_ROOT"
echo "ASSAM MOTORS FIX35 — DIRECT PARTS / LABOUR INSPECT (READ ONLY)"
echo "APP_ROOT: $APP_ROOT"
echo

echo "== JOB CARD PART / LABOUR ROUTES =="
php artisan route:list 2>&1 | grep -Ei 'job-cards|part|labour|rot' | head -n 320 || true
echo

echo "== JOBCARD CONTROLLER PART/LABOUR METHODS =="
F=app/Http/Controllers/Web/AdminJobCardPageController.php
if [[ -f "$F" ]]; then
  grep -nEi -A45 -B14     'part|labour|rot|estimate|add|store|issue|stock|assign|delete|remove'     "$F" | head -n 760 || true
fi
echo

echo "== JOBCARD SERVICE PART/LABOUR METHODS =="
F=app/Services/JobCardService.php
if [[ -f "$F" ]]; then
  grep -nEi -A50 -B16     'part|labour|rot|estimate|add|store|issue|stock|assign|audit|direct'     "$F" | head -n 900 || true
fi
echo

echo "== ESTIMATE DEPENDENCY GUARDS =="
grep -Rni --include='*.php'   -E 'estimate.*required|requires.*estimate|current_estimate|estimate_id|converted_to_job|SEND TO JOB CARD|sent to job'   app/Http app/Services app/Models resources/views/job-cards 2>/dev/null | head -n 520 || true
echo

echo "== PART MASTER / LABOUR MASTER LOOKUP =="
grep -Rni --include='*.php'   -E 'parts_master|labour_master|PartMaster|LabourMaster|partSearch|labourSearch|search.*part|search.*labour'   app/Http app/Services app/Models routes 2>/dev/null | head -n 520 || true
echo

echo "== STOCK ISSUE / INVENTORY HOOKS =="
grep -Rni --include='*.php'   -E 'issue.*part|stock.*issue|inventory|stock_ledger|stock.*movement|available_qty|on_hand|issued_qty|fitted_qty'   app/Http app/Services app/Models 2>/dev/null | head -n 620 || true
echo

echo "== ROT ASSIGNMENT HOOKS =="
grep -Rni --include='*.php'   -E 'RotAssignmentService|rot_session|rot_sessions|assign.*rot|labour.*staff|mechanic|technician.*labour'   app/Http app/Services app/Models 2>/dev/null | head -n 620 || true
echo

echo "== JOBCARD SHOW ADD-ACTION MARKUP =="
F=resources/views/job-cards/show.blade.php
if [[ -f "$F" ]]; then
  grep -nEi -A70 -B18     'Parts|Labour|ROT|Add Part|Add Labour|Estimate|Purchase|jobParts|jobLabour|section-title|action'     "$F" | head -n 900 || true
fi
echo

echo "== MODELS =="
for f in app/Models/JobCardPart.php app/Models/JobCardLabour.php; do
  if [[ -f "$f" ]]; then
    echo "--- $f ---"
    sed -n '1,260p' "$f"
  fi
done
echo

echo "== SCHEMA: job_card_parts =="
php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
foreach(["job_card_parts","job_card_labour"] as $table){
  echo "-- ".$table." --".PHP_EOL;
  try{
    $cols=DB::select("SHOW COLUMNS FROM ".$table);
    foreach($cols as $c){ echo $c->Field." | ".$c->Type." | ".$c->Null." | ".($c->Default??"NULL").PHP_EOL; }
  }catch(Throwable $e){ echo "SCHEMA ERROR: ".$e->getMessage().PHP_EOL; }
}
'
echo

echo "== RECENT PART/LABOUR SAMPLE (READ ONLY) =="
php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
foreach(["job_card_parts","job_card_labour"] as $table){
  echo "-- ".$table." --".PHP_EOL;
  try{
    $rows=DB::table($table)->orderByDesc("id")->limit(8)->get();
    foreach($rows as $r){
      $a=(array)$r;
      foreach(["id","job_card_id","part_id","labour_id","part_code","part_name","labour_code","description","qty","quantity","rate","amount","source","estimate_id","rot_code","status","created_at"] as $k){
        if(array_key_exists($k,$a)) echo $k."=".($a[$k]===null?"NULL":$a[$k])." ";
      }
      echo PHP_EOL;
    }
  }catch(Throwable $e){ echo "SAMPLE ERROR: ".$e->getMessage().PHP_EOL; }
}
'
echo

echo "== FILE HASHES =="
for f in   app/Http/Controllers/Web/AdminJobCardPageController.php   app/Services/JobCardService.php   resources/views/job-cards/show.blade.php   app/Models/JobCardPart.php   app/Models/JobCardLabour.php
do
  [[ -f "$f" ]] && sha256sum "$f"
done

echo
echo "INSPECT COMPLETE — READ ONLY"
