#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${1:-}"
STAFF_QUERY="${2:-Firajul}"

if [[ -z "$APP_ROOT" || ! -f "$APP_ROOT/artisan" ]]; then
  echo "Usage: bash INSPECT.sh /path/to/laravel/app [staff-name-or-code]"
  exit 2
fi

cd "$APP_ROOT"

echo "ASSAM MOTORS FIX36 — STAFF INSPECTION SYNC INSPECT (READ ONLY)"
echo "APP_ROOT: $APP_ROOT"
echo "STAFF_QUERY: $STAFF_QUERY"
echo

echo "== INSPECTION ENDPOINT FILES =="
for f in public/legacy/api/staff-inspections.php public/legacy/api/staff-inspection.php; do
  if [[ -f "$f" ]]; then
    sha256sum "$f"
  else
    echo "MISSING $f"
  fi
done
echo

echo "== ENDPOINT QUERY / FILTER MARKERS =="
for f in public/legacy/api/staff-inspections.php public/legacy/api/staff-inspection.php; do
  if [[ -f "$f" ]]; then
    echo "--- $f ---"
    grep -nEi -A30 -B12       'staff|inspection|job_card|assigned|technician|employee|user|email|staff_id|inspector|status|where|select'       "$f" | head -n 420 || true
  fi
done
echo

echo "== JOBCARD INSPECTION ASSIGNMENT MARKERS =="
grep -Rni --include='*.php' --include='*.blade.php'   -E 'inspection.*staff|staff.*inspection|inspection.*technician|technician.*inspection|inspector|assigned.*inspection|inspection_assigned|inspection_staff_id|inspection_technician_id'   app resources routes public 2>/dev/null | head -n 520 || true
echo

echo "== JOBCARD CONTROLLER INSPECTION BLOCKS =="
F=app/Http/Controllers/Web/AdminJobCardPageController.php
if [[ -f "$F" ]]; then
  grep -nEi -A45 -B15     'inspection|technician|staff|assigned' "$F" | head -n 620 || true
fi
echo

echo "== JOBCARD VIEW INSPECTION BLOCKS =="
F=resources/views/job-cards/show.blade.php
if [[ -f "$F" ]]; then
  grep -nEi -A45 -B15     'inspection|technician|staff|assigned' "$F" | head -n 620 || true
fi
echo

echo "== STAFF TABLE MATCHES =="
php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$q=$argv[1]??"Firajul";
try {
  $cols=DB::select("SHOW COLUMNS FROM staff");
  $names=array_map(fn($c)=>$c->Field,$cols);
  echo "staff_columns=".implode(",",$names).PHP_EOL;
  $builder=DB::table("staff");
  $builder->where(function($w) use($q,$names){
    foreach(["name","staff_name","staff_code","code","email","mobile","phone"] as $c){
      if(in_array($c,$names,true)) $w->orWhere($c,"like","%".$q."%");
    }
  });
  $rows=$builder->limit(20)->get();
  echo "matches=".count($rows).PHP_EOL;
  foreach($rows as $r){ echo json_encode((array)$r,JSON_UNESCAPED_SLASHES).PHP_EOL; }
} catch(Throwable $e) { echo "STAFF ERROR: ".$e->getMessage().PHP_EOL; }
' "$STAFF_QUERY"
echo

echo "== JOBCARD SCHEMA INSPECTION FIELDS =="
php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
try {
  $cols=DB::select("SHOW COLUMNS FROM job_cards");
  foreach($cols as $c){
    if(preg_match("/inspection|technician|staff|inspector|assigned/i",$c->Field)){
      echo $c->Field." | ".$c->Type." | ".$c->Null." | ".($c->Default??"NULL").PHP_EOL;
    }
  }
} catch(Throwable $e) { echo "SCHEMA ERROR: ".$e->getMessage().PHP_EOL; }
'
echo

echo "== RECENT JOBCARD INSPECTION ASSIGNMENTS =="
php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
try {
  $cols=DB::select("SHOW COLUMNS FROM job_cards");
  $names=array_map(fn($c)=>$c->Field,$cols);
  $wanted=array_values(array_filter($names,fn($c)=>preg_match("/^(id|job_no|vehicle_reg_no|customer_name|inspection.*|.*inspection.*|technician.*|.*technician.*|staff.*|.*staff.*|updated_at)$/i",$c)));
  if(!$wanted){ echo "NO MATCHING FIELDS".PHP_EOL; exit; }
  $rows=DB::table("job_cards")->select($wanted)->orderByDesc("id")->limit(20)->get();
  foreach($rows as $r){ echo json_encode((array)$r,JSON_UNESCAPED_SLASHES).PHP_EOL; }
} catch(Throwable $e) { echo "JOBCARD ERROR: ".$e->getMessage().PHP_EOL; }
'
echo

echo "== INSPECTION-RELATED TABLES =="
php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
try {
  $db=DB::getDatabaseName();
  $rows=DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema=? AND table_name LIKE ? ORDER BY table_name",[$db,"%inspection%"]);
  foreach($rows as $r){ echo $r->table_name.PHP_EOL; }
} catch(Throwable $e) { echo "TABLE ERROR: ".$e->getMessage().PHP_EOL; }
'
echo

echo "== FILE HASHES =="
for f in   app/Http/Controllers/Web/AdminJobCardPageController.php   resources/views/job-cards/show.blade.php   public/legacy/api/staff-inspections.php   public/legacy/api/staff-inspection.php
do
  [[ -f "$f" ]] && sha256sum "$f"
done

echo
echo "INSPECT COMPLETE — READ ONLY"
