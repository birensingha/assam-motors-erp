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
php <<'PHP' || true
<?php
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $cols=DB::select("SHOW COLUMNS FROM job_cards");
    foreach($cols as $col){
        echo $col->Field." | ".$col->Type." | ".$col->Null." | ".($col->Default??"NULL").PHP_EOL;
    }
} catch(Throwable $e){
    echo "SCHEMA ERROR: ".$e->getMessage().PHP_EOL;
}
PHP
echo

echo "== CURRENT STATUS DISTRIBUTION (READ ONLY) =="
php <<'PHP' || true
<?php
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach(["status","work_status","customer_public_status","inspection_status","diagnosis_status"] as $field){
    try{
        if(!Illuminate\Support\Facades\Schema::hasColumn("job_cards",$field)){
            echo "-- ".$field." -- MISSING".PHP_EOL;
            continue;
        }
        $rows=DB::table("job_cards")->select($field,DB::raw("COUNT(*) c"))->groupBy($field)->orderByDesc("c")->get();
        echo "-- ".$field." --".PHP_EOL;
        foreach($rows as $row){
            $v=$row->{$field};
            echo (($v===null)?"NULL":$v)." | ".$row->c.PHP_EOL;
        }
    }catch(Throwable $e){
        echo "-- ".$field." ERROR: ".$e->getMessage().PHP_EOL;
    }
}
PHP
echo

echo "== RECENT JOBCARD STATE SAMPLE =="
php <<'PHP' || true
<?php
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $rows=DB::table("job_cards")->orderByDesc("id")->limit(12)->get();
    foreach($rows as $row){
        $a=(array)$row;
        foreach(["id","job_no","status","work_status","customer_public_status","inspection_status","diagnosis_status","current_estimate_id","invoice_id","closed_at","completed_at","ready_at","updated_at"] as $k){
            if(array_key_exists($k,$a)) echo $k."=".($a[$k]===null?"NULL":$a[$k])." ";
        }
        echo PHP_EOL;
    }
} catch(Throwable $e){
    echo "SAMPLE ERROR: ".$e->getMessage().PHP_EOL;
}
PHP
echo

echo "== JOBCARD AUDIT TABLE =="
php <<'PHP' || true
<?php
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    if(!Illuminate\Support\Facades\Schema::hasTable("job_card_change_audits")){
        echo "MISSING job_card_change_audits".PHP_EOL;
    } else {
        $cols=DB::select("SHOW COLUMNS FROM job_card_change_audits");
        foreach($cols as $col){
            echo $col->Field." | ".$col->Type." | ".$col->Null." | ".($col->Default??"NULL").PHP_EOL;
        }
    }
} catch(Throwable $e){
    echo "AUDIT SCHEMA ERROR: ".$e->getMessage().PHP_EOL;
}
PHP
echo

echo "== FILE HASHES =="
for f in   app/Http/Controllers/Web/AdminJobCardPageController.php   app/Services/JobCardService.php   app/Models/JobCard.php   resources/views/job-cards/show.blade.php
do
  [[ -f "$f" ]] && sha256sum "$f"
done

echo
echo "INSPECT COMPLETE — READ ONLY"
