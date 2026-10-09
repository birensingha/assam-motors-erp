#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${1:-}"
if [[ -z "$APP_ROOT" || ! -f "$APP_ROOT/artisan" ]]; then
  echo "Usage: bash INSPECT.sh /path/to/laravel/app"
  exit 2
fi

cd "$APP_ROOT"
echo "ASSAM MOTORS FIX38 — JOB CARD EDIT INSPECT (READ ONLY)"
echo "APP_ROOT: $APP_ROOT"
echo

echo "== JOB CARD EDIT / UPDATE ROUTES =="
php artisan route:list 2>&1 | grep -Ei 'job-cards|job.card' | grep -Ei 'edit|update|patch|put|show|index|parts|labour' | head -n 320 || true
echo

echo "== ADMIN JOB CARD CONTROLLER EDIT/UPDATE METHODS =="
F=app/Http/Controllers/Web/AdminJobCardPageController.php
if [[ -f "$F" ]]; then
  grep -nEi -A70 -B18 'function (edit|update|show|index)|customer|vehicle|odometer|advisor|complaint|instruction|promised|delivery|editable|locked|invoiced|closed|auditChange|recalculateJobTotals' "$F" | head -n 1100 || true
fi
echo

echo "== JOB CARD MODEL =="
F=app/Models/JobCard.php
if [[ -f "$F" ]]; then
  sed -n '1,380p' "$F"
fi
echo

echo "== JOB CARD LIST / EDIT BUTTON CANDIDATES =="
find resources/views -maxdepth 3 -type f \( -iname '*job*card*.blade.php' -o -path '*/job-cards/*.blade.php' \) -print | sort
for F in resources/views/job-cards/*.blade.php; do
  [[ -f "$F" ]] || continue
  echo "--- $F ---"
  grep -nEi -A24 -B12 'Edit|View|job-cards|route\(|status|workflow|Actions|action' "$F" | head -n 360 || true
done
echo

echo "== CURRENT SHOW UI HEADER / PARTS / LABOUR MARKERS =="
F=resources/views/job-cards/show.blade.php
if [[ -f "$F" ]]; then
  grep -nEi -A65 -B18 'Customer|Vehicle|Odometer|Advisor|Complaint|Workshop|Promised|Parts|Labour / ROT|Direct Job Card Entry|FIX37|Save|Edit' "$F" | head -n 1100 || true
fi
echo

echo "== CUSTOMER / VEHICLE LOOKUP SOURCES =="
grep -Rni --include='*.php' -E 'Customer::|Vehicle::|customers|vehicles|customer_id|vehicle_id|service_advisor|advisor_id' app/Http app/Models app/Services 2>/dev/null | head -n 620 || true
echo

echo "== JOB CARD SCHEMA =="
php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach(["job_cards","job_card_parts","job_card_labour","job_card_change_audits","job_card_edit_audit"] as $table){
  echo "-- ".$table." --".PHP_EOL;
  try{
    if(!Illuminate\Support\Facades\Schema::hasTable($table)){ echo "MISSING".PHP_EOL; continue; }
    $cols=DB::select("SHOW COLUMNS FROM `".$table."`");
    foreach($cols as $c){ echo $c->Field." | ".$c->Type." | ".$c->Null." | ".($c->Default??"NULL").PHP_EOL; }
  }catch(Throwable $e){ echo "SCHEMA ERROR: ".$e->getMessage().PHP_EOL; }
}
'
echo

echo "== RECENT JOB CARD EDITABILITY SAMPLE =="
php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try{
  $rows=DB::table("job_cards")->orderByDesc("id")->limit(12)->get();
  foreach($rows as $r){
    $a=(array)$r;
    foreach(["id","job_no","job_card_no","status","work_status","customer_id","vehicle_id","odometer","service_advisor_id","service_advisor","customer_complaint","workshop_instruction","promised_delivery_at","invoice_id","closed_at","updated_at"] as $k){
      if(array_key_exists($k,$a)) echo $k."=".($a[$k]===null?"NULL":str_replace(["\n","\r"],[" "," "],substr((string)$a[$k],0,100)))." ";
    }
    echo PHP_EOL;
  }
}catch(Throwable $e){ echo "SAMPLE ERROR: ".$e->getMessage().PHP_EOL; }
'
echo

echo "== EXISTING EDIT / AUDIT MARKERS ACROSS APP =="
grep -Rni --include='*.php' --include='*.blade.php' -E 'JOB_CARD_EDIT|PART_UPDATE|LABOUR_UPDATE|changed_by|change_reason|before_json|after_json|reopen|Invoiced Job Card|Job Card.*locked' app resources routes 2>/dev/null | head -n 620 || true
echo

echo "== FILE HASHES =="
for f in \
  app/Http/Controllers/Web/AdminJobCardPageController.php \
  app/Models/JobCard.php \
  resources/views/job-cards/show.blade.php \
  resources/views/job-cards/index.blade.php \
  routes/web.php
do
  [[ -f "$f" ]] && sha256sum "$f"
done

echo
echo "INSPECT COMPLETE — READ ONLY"
