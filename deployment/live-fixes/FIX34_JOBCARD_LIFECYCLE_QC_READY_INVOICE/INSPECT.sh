#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${1:-}"
if [[ -z "$APP_ROOT" || ! -f "$APP_ROOT/artisan" ]]; then
  echo "Usage: bash INSPECT.sh /path/to/laravel/app"
  exit 2
fi

cd "$APP_ROOT"
echo "ASSAM MOTORS FIX34 — JOB CARD LIFECYCLE INSPECT (READ ONLY)"
echo "APP_ROOT: $APP_ROOT"
echo

echo "== JOBCARD / QC / READY / CLOSE / INVOICE ROUTES =="
php artisan route:list 2>&1 | grep -Ei 'job-cards|quality|qc|ready|deliver|close|invoice|billing' | head -n 260 || true
echo

echo "== JOBCARD CONTROLLER LIFECYCLE MARKERS =="
F=app/Http/Controllers/Web/AdminJobCardPageController.php
if [[ -f "$F" ]]; then
  grep -nEi -A35 -B12     'quality|qc|ready|deliver|close|invoice|billing|work.?status|job.?status|complete|rework|inspection|estimate'     "$F" | head -n 620 || true
fi
echo

echo "== JOBCARD SERVICE LIFECYCLE MARKERS =="
F=app/Services/JobCardService.php
if [[ -f "$F" ]]; then
  grep -nEi -A40 -B15     'quality|qc|ready|deliver|close|invoice|billing|work.?status|job.?status|complete|rework|transition|audit'     "$F" | head -n 720 || true
fi
echo

echo "== INVOICE / BILLING SERVICE CANDIDATES =="
find app/Http app/Services app/Models -maxdepth 3 -type f   \( -iname '*Invoice*.php' -o -iname '*Billing*.php' -o -iname '*Quality*.php' -o -iname '*Delivery*.php' \)   -print | sort
echo

echo "== INVOICE / BILLING MARKERS =="
grep -Rni --include='*.php'   -E 'create.*invoice|convert.*invoice|invoice.*job|job.*invoice|ready.*delivery|quality.*check|job.*close|close.*job'   app/Http app/Services app/Models 2>/dev/null | head -n 520 || true
echo

echo "== JOBCARD SHOW UI WORKFLOW MARKERS =="
F=resources/views/job-cards/show.blade.php
if [[ -f "$F" ]]; then
  grep -nEi -A55 -B14     'Quality|QC|Ready|Delivery|Close|Invoice|Work In Progress|Completed|Rework|status-badge|workflow|action'     "$F" | head -n 720 || true
fi
echo

echo "== JOBCARD MODEL FILLABLE / CASTS / STATUS HELPERS =="
F=app/Models/JobCard.php
if [[ -f "$F" ]]; then
  sed -n '1,320p' "$F"
fi
echo

echo "== SCHEMA: job_cards =="
php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
try {
  $cols=DB::select("SHOW COLUMNS FROM job_cards");
  foreach($cols as $c){ echo $c->Field." | ".$c->Type." | ".$c->Null." | ".($c->Default??"NULL").PHP_EOL; }
} catch(Throwable $e){ echo "SCHEMA ERROR: ".$e->getMessage().PHP_EOL; }
'
echo

echo "== CURRENT STATUS DISTRIBUTION (READ ONLY) =="
php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
try {
  foreach(["status","work_status","customer_public_status","inspection_status","diagnosis_status"] as $col){
    try{
      $rows=DB::table("job_cards")->select($col,DB::raw("COUNT(*) c"))->groupBy($col)->orderByDesc("c")->get();
      echo "-- ".$col." --".PHP_EOL;
      foreach($rows as $r){ echo (($r->$col===null)?"NULL":$r->$col)." | ".$r->c.PHP_EOL; }
    }catch(Throwable $e){}
  }
} catch(Throwable $e){ echo "STATUS ERROR: ".$e->getMessage().PHP_EOL; }
'
echo

echo "== RECENT JOBCARD STATE SAMPLE =="
php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
try {
  $rows=DB::table("job_cards")->orderByDesc("id")->limit(12)->get();
  foreach($rows as $r){
    $a=(array)$r;
    foreach(["id","job_no","status","work_status","customer_public_status","inspection_status","diagnosis_status","current_estimate_id","invoice_id","closed_at","completed_at","ready_at","updated_at"] as $k){
      if(array_key_exists($k,$a)) echo $k."=".($a[$k]===null?"NULL":$a[$k])." ";
    }
    echo PHP_EOL;
  }
} catch(Throwable $e){ echo "SAMPLE ERROR: ".$e->getMessage().PHP_EOL; }
'
echo

echo "== JOBCARD AUDIT TABLE =="
php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
try {
  $cols=DB::select("SHOW COLUMNS FROM job_card_change_audits");
  foreach($cols as $c){ echo $c->Field." | ".$c->Type." | ".$c->Null." | ".($c->Default??"NULL").PHP_EOL; }
} catch(Throwable $e){ echo "AUDIT SCHEMA ERROR: ".$e->getMessage().PHP_EOL; }
'
echo

echo "== FILE HASHES =="
for f in   app/Http/Controllers/Web/AdminJobCardPageController.php   app/Services/JobCardService.php   app/Models/JobCard.php   resources/views/job-cards/show.blade.php
do
  [[ -f "$f" ]] && sha256sum "$f"
done

echo
echo "INSPECT COMPLETE — READ ONLY"
