#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${1:-}"
if [[ -z "$APP_ROOT" || ! -f "$APP_ROOT/artisan" ]]; then
  echo "Usage: bash INSPECT.sh /path/to/laravel/app"
  exit 2
fi

cd "$APP_ROOT"
echo "ASSAM MOTORS FIX40 — LEGACY CUSTOMER AUTH / NATIVE CUSTOMER INSPECT (READ ONLY)"
echo "APP_ROOT: $APP_ROOT"
echo

echo "== LEGACY CUSTOMERS.PHP =="
LEG=public/legacy/workshop/customers.php
if [[ -f "$LEG" ]]; then
  echo "FOUND $LEG"
  sed -n '1,260p' "$LEG"
else
  echo "MISSING $LEG"
fi
echo

echo "== NATIVE CUSTOMER ROUTES =="
php artisan route:list 2>&1 | grep -Ei 'customer|native|erp/native' | head -n 260 || true
echo

echo "== NATIVE CUSTOMER CONTROLLER CANDIDATES =="
find app/Http/Controllers -maxdepth 4 -type f \( -iname '*Customer*.php' -o -iname '*Native*.php' -o -iname '*Admin*.php' \) -print | sort
echo

echo "== CUSTOMER CONTROLLER MARKERS =="
grep -Rni --include='*.php' -E 'function .*customers|function .*Customer|storeCustomer|updateCustomer|am_customer_master|am_vehicle_master|customer master' app/Http/Controllers app/Services app/Models 2>/dev/null | head -n 720 || true
echo

echo "== CUSTOMER VIEW CANDIDATES =="
find resources/views -maxdepth 4 -type f \( -iname '*customer*.blade.php' -o -path '*/admin/native/*.blade.php' \) -print | sort
echo

echo "== CUSTOMER VIEW MARKERS =="
grep -Rni --include='*.blade.php' -E 'Customer Master|New Customer|Add Customer|am_customer_master|vehicle|registration|mobile|whatsapp' resources/views 2>/dev/null | head -n 720 || true
echo

echo "== NAVIGATION LINKS TO LEGACY / NATIVE CUSTOMER =="
grep -Rni --include='*.php' --include='*.blade.php' --include='*.js' -E 'legacy/workshop/customers\.php|erp/native/customers|native\.customers|Customers' resources app routes public 2>/dev/null | head -n 720 || true
echo

echo "== AUTH / ADMIN MIDDLEWARE CONTEXT =="
php artisan route:list 2>&1 | grep -Ei 'erp/native|dashboard|job-cards|purchases|customers' | head -n 260 || true
echo

echo "== CUSTOMER / VEHICLE TABLES =="
php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
foreach(["am_customer_master","am_vehicle_master","customers","vehicles"] as $table){
  try{
    if(!Illuminate\\Support\\Facades\\Schema::hasTable($table)) continue;
    echo "-- ".$table." --".PHP_EOL;
    foreach(DB::select("SHOW COLUMNS FROM `".$table."`") as $c){
      echo $c->Field." | ".$c->Type." | ".$c->Null." | ".($c->Default??"NULL").PHP_EOL;
    }
  }catch(Throwable $e){ echo "SCHEMA ERROR ".$table.": ".$e->getMessage().PHP_EOL; }
}
'
echo

echo "== FILE HASHES =="
for f in \
  public/legacy/workshop/customers.php \
  app/Http/Controllers/Web/AdminNativeModuleController.php \
  resources/views/admin/native/customers.blade.php \
  routes/web.php
do
  [[ -f "$f" ]] && sha256sum "$f"
done

echo
echo "INSPECT COMPLETE — READ ONLY"
