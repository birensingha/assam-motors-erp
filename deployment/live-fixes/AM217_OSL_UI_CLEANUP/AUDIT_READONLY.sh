#!/usr/bin/env bash
# AM217 read-only server-side OSL modal / billing register / Master diagnostics.
set -Eeuo pipefail
cd "$1";test -f artisan
echo AM217_OSL_AUDIT_BEGIN
view=resources/views/job-cards/show.blade.php
echo "BLADE_LINES=$(wc -l < "$view")"
echo '=== OSL SOURCE MARKERS ==='
grep -nEi -m 75 'FIX93_UNIFIED|AM146_OSL_MASTER|AM150_TYPED|am93-purchase|am93Purchase|am83-osl|am146-master|Select Linked OSL|OSL · PURCHASE ONLY|Purchase only|Purchase linked|Supplier Purchase|am91-osl-dialog|OSL Master Item' "$view" | cut -c1-230 || true
echo '=== OSL REGISTER AND PURCHASE CONTEXT ==='
for key in 'OSL · PURCHASE ONLY' 'Select Linked OSL / Supplier Purchase Options' 'am83-osl-source' 'am146-master-row'; do
 n="$(grep -n -F -m1 "$key" "$view" | cut -d: -f1 || true)"
 if [[ -n "$n" ]];then
  lo=$((n>65?n-65:1));hi=$((n+95))
  echo "=== CONTEXT $key at $n ==="
  sed -n "${lo},${hi}p" "$view" | cut -c1-240
 fi
done
echo '=== OSL MASTER ACTIVE COUNT / linked purchase state (aggregates only) ==='
php <<'PHP'
<?php
declare(strict_types=1);
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
foreach(['workshop_osl_master','workshop_purchases','workshop_purchase_lines','job_card_labour'] as $tbl){
 if(Schema::hasTable($tbl)){
   echo $tbl.'_COLUMNS='.implode(',',Schema::getColumnListing($tbl))."\n";
 }else echo $tbl.'_NOT_PRESENT'."\n";
}
if(Schema::hasTable('workshop_osl_master')){
 echo 'MASTER_TOTAL='.DB::table('workshop_osl_master')->count()."\n";
 echo 'MASTER_ACTIVE='.DB::table('workshop_osl_master')->where('active',1)->count()."\n";
 echo 'MASTER_INACTIVE='.DB::table('workshop_osl_master')->where('active',0)->count()."\n";
}
if(Schema::hasTable('workshop_purchases') && Schema::hasColumn('workshop_purchases','purchase_no')){
  $purchase=DB::table('workshop_purchases')->where('purchase_no','OSL-261030184631-427')->first();
  if($purchase){echo 'SCREENSHOT_PURCHASE_FOUND=YES ID='.$purchase->id."\n";
  foreach(['job_card_id','labour_line_id','customer_billing_status','billing_line_created','status','deleted_at','supplier_id'] as $key){
    echo 'SCREENSHOT_PURCHASE_'.strtoupper($key).'='.($purchase->{$key}??'(null)')."\n";
  }
  } else echo 'SCREENSHOT_PURCHASE_FOUND=NO'."\n";
}
echo 'AM217_DB_INSPECTION_COMPLETE_NO_WRITES'."\n";
PHP
echo AM217_OSL_AUDIT_COMPLETE_NO_WRITES
