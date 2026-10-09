#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${1:-}"
if [[ -z "$APP_ROOT" || ! -f "$APP_ROOT/artisan" ]]; then
  echo "Usage: bash INSPECT.sh /path/to/laravel/app"
  exit 2
fi

cd "$APP_ROOT"
echo "ASSAM MOTORS FIX39 — OSL PURCHASE MULTI-LINE INSPECT (READ ONLY)"
echo "APP_ROOT: $APP_ROOT"
echo

echo "== PURCHASE / OSL ROUTES =="
php artisan route:list 2>&1 | grep -Ei 'purchase|osl|outsource|outside|labour' | head -n 360 || true
echo

echo "== PURCHASE CONTROLLER OSL METHODS =="
F=app/Http/Controllers/Web/AdminPurchasePageController.php
if [[ -f "$F" ]]; then
  grep -nEi -A80 -B20 'function .*Osl|storeOsl|createOsl|osl|purchase|vendor|invoice|job.?card|vehicle|batch' "$F" | head -n 1200 || true
else
  echo "MISSING $F"
fi
echo

echo "== PURCHASE SERVICE OSL METHODS =="
F=app/Services/PurchaseService.php
if [[ -f "$F" ]]; then
  grep -nEi -A100 -B22 'function .*Osl|saveOsl|osl|batch|purchase_batch|vendor|job.?card|vehicle|labour|gst|discount' "$F" | head -n 1500 || true
else
  echo "MISSING $F"
fi
echo

echo "== OSL CREATE VIEW =="
F=resources/views/purchases/osl-create.blade.php
if [[ -f "$F" ]]; then
  sed -n '1,1000p' "$F"
else
  echo "MISSING $F"
fi
echo

echo "== PARTS PURCHASE REFERENCE VIEW =="
for F in resources/views/purchases/create.blade.php resources/views/purchases/parts-create.blade.php; do
  if [[ -f "$F" ]]; then
    echo "--- $F ---"
    grep -nEi -A100 -B25 'Add Line|Remove|line|tbody|Part|Vendor|Invoice|Purchase Date|Job Card|Vehicle|GST|Discount|Total|autocomplete|search' "$F" | head -n 1500 || true
  fi
done
echo

echo "== PURCHASE RELATED MODELS / SERVICES =="
find app/Models app/Services -maxdepth 3 -type f \( -iname '*Purchase*.php' -o -iname '*Osl*.php' -o -iname '*Labour*.php' -o -iname '*Vendor*.php' \) -print | sort
echo

echo "== OSL / PURCHASE TABLE CANDIDATES =="
php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try{
  $db=DB::getDatabaseName();
  $rows=DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema=? AND (table_name LIKE ? OR table_name LIKE ? OR table_name LIKE ? OR table_name LIKE ?) ORDER BY table_name",[$db,"%purchase%","%osl%","%vendor%","%labour%"]);
  foreach($rows as $r){ echo $r->table_name.PHP_EOL; }
}catch(Throwable $e){ echo "TABLE ERROR: ".$e->getMessage().PHP_EOL; }
'
echo

echo "== IMPORTANT PURCHASE TABLE SCHEMAS =="
php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$tables=[
 "workshop_purchases","workshop_purchase_batches","purchase_batches",
 "parts_purchases","osl_purchases","purchase_lines","purchase_items",
 "labour_master","job_cards","vehicles","vendors"
];
foreach($tables as $table){
  try{
    if(!Illuminate\Support\Facades\Schema::hasTable($table)) continue;
    echo "-- ".$table." --".PHP_EOL;
    foreach(DB::select("SHOW COLUMNS FROM `".$table."`") as $c){
      echo $c->Field." | ".$c->Type." | ".$c->Null." | ".($c->Default??"NULL").PHP_EOL;
    }
  }catch(Throwable $e){ echo "SCHEMA ERROR ".$table.": ".$e->getMessage().PHP_EOL; }
}
'
echo

echo "== RECENT PURCHASE / OSL SAMPLE =="
php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach(["workshop_purchases","osl_purchases","workshop_purchase_batches","purchase_batches"] as $table){
  try{
    if(!Illuminate\Support\Facades\Schema::hasTable($table)) continue;
    echo "-- ".$table." --".PHP_EOL;
    foreach(DB::table($table)->orderByDesc("id")->limit(10)->get() as $r){
      $a=(array)$r;
      foreach(["id","purchase_type","type","vendor_id","vendor_name","purchase_date","invoice_no","supplier_invoice_no","job_card_id","job_card_no","vehicle_id","vehicle_reg_no","osl_master_id","osl_code","description","qty","rate","discount_percent","gst_percent","taxable_amount","tax_amount","total","grand_total","purchase_batch_no","purchase_line_no","created_at"] as $k){
        if(array_key_exists($k,$a)) echo $k."=".($a[$k]===null?"NULL":str_replace(["\n","\r"],[" "," "],substr((string)$a[$k],0,100)))." ";
      }
      echo PHP_EOL;
    }
  }catch(Throwable $e){ echo "SAMPLE ERROR ".$table.": ".$e->getMessage().PHP_EOL; }
}
'
echo

echo "== JOB CARD / VEHICLE SEARCH SUPPORT =="
grep -Rni --include='*.php' --include='*.blade.php' -E 'job.?card.*search|vehicle.*search|registration.*search|job_card_no|vehicle_reg_no|purchase.*allocation|allocation.*purchase' app resources routes 2>/dev/null | head -n 900 || true
echo

echo "== FILE HASHES =="
for f in \
  app/Http/Controllers/Web/AdminPurchasePageController.php \
  app/Services/PurchaseService.php \
  resources/views/purchases/osl-create.blade.php \
  resources/views/purchases/create.blade.php \
  resources/views/purchases/parts-create.blade.php \
  routes/web.php
do
  [[ -f "$f" ]] && sha256sum "$f"
done

echo
echo "INSPECT COMPLETE — READ ONLY"
