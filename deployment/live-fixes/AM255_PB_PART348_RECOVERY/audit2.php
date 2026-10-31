<?php
/** AM256 targeted read-only orphan-source recovery verification. */
declare(strict_types=1);
$root=realpath($argv[1]??'')?:'';
if(basename($root)!=='assam-erp-staging'||!is_file($root.'/artisan'))exit("AM256_STOP_NOT_STAGING\n");
require $root.'/vendor/autoload.php';$app=require $root.'/bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;use Illuminate\Support\Facades\Schema;
$out=fn($k,$v)=>print($k.' '.json_encode($v,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE)."\n");
foreach(['parts_master','workshop_purchases','inventory_movements','job_card_parts','workshop_purchase_batches'] as $t){
 if(!Schema::hasTable($t))continue;
 $cols=Schema::getColumnListing($t);
 $out('AM256_ALL_COLUMNS',['table'=>$t,'columns'=>$cols]);
 $wanted=['id','part_master_id','part_id','part_code','part_name','description','batch_no','purchase_no',
   'purchase_batch_id','unit_cost','purchase_price','selling_price','rate','stock_qty','qty','qty_in','qty_out',
   'movement_type','reference_no','job_card_id','purchase_type','allocation_type','stock_before','stock_after',
   'gst_percent','tax_percent','status','record_status','unit','active','category','sub_category',
   'supplier_invoice','created_at','updated_at','deleted_at','final_amount','total'];
 $sel=array_values(array_intersect($wanted,$cols));
 if($t==='parts_master'){
  $rows=DB::table($t)->where('part_code','AM-0348')->orWhere('id',348)->limit(4)->get($sel);
  $out('AM256_TARGET_MASTER',$rows->toArray());
  $rows=DB::table($t)->whereIn('id',[347,349])->limit(4)->get($sel);
  $out('AM256_ADJACENT_MASTER',$rows->toArray());
 }elseif(in_array('part_master_id',$cols,true)){
  $out('AM256_TARGET_LINKS',['table'=>$t,'rows'=>DB::table($t)->where('part_master_id',348)->limit(30)->get($sel)->toArray()]);
 }
}
$schema=DB::select("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME IN ('part_master_id','part_id') ORDER BY TABLE_NAME,COLUMN_NAME");
foreach($schema as $ref){
 $t=(string)$ref->TABLE_NAME;$c=(string)$ref->COLUMN_NAME;
 if(!preg_match('/^(am_|workshop_|job_|inventory_|part_|estimate_|direct_|counter_|invoice_)/',$t))continue;
 if(!Schema::hasTable($t))continue;
 try{$n=DB::table($t)->where($c,348)->count();
   if($n)$out('AM256_REFERENCE',['table'=>$t,'column'=>$c,'count'=>$n]);
 }catch(\Throwable $e){$out('AM256_SCAN_ERROR',['table'=>$t,'error'=>get_class($e)]);}
}
foreach(['app/Services/PurchaseService.php','app/Http/Controllers/Web/AdminPurchasePageController.php'] as $name){
 $path=$root.'/'.$name;if(!is_file($path))continue;
 $lines=file($path);
 $out('AM256_SOURCE_META',['file'=>$name,'lines'=>count($lines)]);
 foreach($lines as $i=>$line){
  if(!preg_match('/(no longer exists|Part ID|function (edit|update)|part_master_id|stock_qty|partsMaster)/i',$line))continue;
  if(preg_match('/(password|secret|token)/i',$line))continue;
  if($name==='app/Services/PurchaseService.php' && ($i<280||$i>690))continue;
  $out('AM256_SOURCE',['file'=>$name,'line'=>$i+1,'text'=>mb_substr(trim($line),0,350)]);
 }
}
echo "AM256_READONLY_COMPLETE\n";
