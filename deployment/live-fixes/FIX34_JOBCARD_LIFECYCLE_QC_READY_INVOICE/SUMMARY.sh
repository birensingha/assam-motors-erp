#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"
[[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || { echo "Usage: bash SUMMARY.sh /path/to/app"; exit 2; }
cd "$APP_ROOT"

echo "ASSAM MOTORS FIX34 — LIFECYCLE FOCUSED SUMMARY (READ ONLY)"
echo

echo "== ROUTES =="
php artisan route:list 2>&1 | grep -Ei 'job-cards|invoice|billing|quality|qc|ready|deliver|close' | head -n 220 || true
echo

echo "== CONTROLLER METHODS =="
grep -nEi -A70 -B15 'function .*complete|function .*quality|function .*qc|function .*ready|function .*deliver|function .*close|function .*invoice|customer_public_status|completed_at|closed_at|invoice_id' app/Http/Controllers/Web/AdminJobCardPageController.php | head -n 900 || true
echo

echo "== JOBCARD SERVICE METHODS =="
if [[ -f app/Services/JobCardService.php ]]; then
  grep -nEi -A90 -B20 'function .*complete|function .*quality|function .*qc|function .*ready|function .*deliver|function .*close|function .*invoice|customer_public_status|completed_at|closed_at|invoice_id' app/Services/JobCardService.php | head -n 1100 || true
fi
echo

echo "== INVOICE/BILLING METHODS =="
grep -Rni --include='*.php' -E 'function .*invoice|function .*billing|create.*invoice|invoice_id|convert.*invoice|closed_at|Ready for Delivery|Quality Check' app/Http/Controllers/Web app/Services 2>/dev/null | head -n 900 || true
echo

echo "== LIFECYCLE COLUMNS =="
php <<'PHP' || true
<?php
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$wanted=["status","work_status","customer_public_status","completed_at","closed_at","invoice_id","ready_at","quality_check_at","qc_status","quality_status","ready_status","delivered_at"];
try{
  $cols=DB::select("SHOW COLUMNS FROM job_cards");
  $map=[];
  foreach($cols as $col){$map[$col->Field]=$col;}
  foreach($wanted as $key){
    if(isset($map[$key])) echo $key." | ".$map[$key]->Type." | ".$map[$key]->Null." | ".($map[$key]->Default??"NULL").PHP_EOL;
    else echo $key." | MISSING".PHP_EOL;
  }
}catch(Throwable $e){echo "ERROR: ".$e->getMessage().PHP_EOL;}
PHP
echo

echo "== ACTIVE ROT / PART WORK GUARDS =="
grep -Rni --include='*.php' -E 'job_card_labour|job_card_parts|execution_status|issue_status|Running|Paused|Assigned|Completed|incomplete' app/Http/Controllers/Web/AdminJobCardPageController.php app/Services/JobCardService.php app/Services/RotWorkflowService.php 2>/dev/null | head -n 900 || true
echo

echo "SUMMARY COMPLETE — READ ONLY"
