<?php
/** AM257 READ ONLY verify alternate Part Master ID 900 is unreferenced before canonical ID reconciliation */
declare(strict_types=1);
$root=realpath($argv[1]??'')?:'';
if(basename($root)!=='assam-erp-staging'||!is_file($root.'/artisan'))exit("AM257_STOP_NOT_STAGING\n");
require $root.'/vendor/autoload.php';$app=require $root.'/bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB; use Illuminate\Support\Facades\Schema;
$out=fn($k,$v)=>print($k.' '.json_encode($v,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE)."\n");
$ids=[348,900];
$parts=DB::table('parts_master')->whereIn('id',$ids)->orWhere('part_code','AM-0348')->get(['id','part_code','part_name','category','sub_category','vehicle_make','vehicle_model','purchase_price','selling_price','hsn_code','gst_percent','unit','minimum_stock','active','stock_qty','created_at','updated_at'])->toArray();
$out('AM257_PARTS',$parts);
$out('AM257_ORIGINAL_ID_EXISTS',DB::table('parts_master')->where('id',348)->exists());
$refs=DB::select("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME IN ('part_master_id','part_id') ORDER BY TABLE_NAME,COLUMN_NAME");
foreach($refs as $ref){
 $table=(string)$ref->TABLE_NAME;$col=(string)$ref->COLUMN_NAME;
 if(!preg_match('/^[a-z0-9_]+$/i',$table.$col))continue;
 if(in_array($table,['parts_master','migrations'],true))continue;
 foreach($ids as $id){
  try{
   $count=DB::table($table)->where($col,$id)->count();
   if($count)$out('AM257_REFERENCE',['table'=>$table,'column'=>$col,'part_id'=>$id,'count'=>$count]);
  }catch(\Throwable $e){$out('AM257_REFERENCE_ERROR',['table'=>$table,'error'=>get_class($e)]);}
 }
}
$out('AM257_MOVEMENT_ROWS',DB::table('inventory_movements')->whereIn('part_master_id',$ids)
 ->get(['id','part_master_id','movement_type','qty_in','qty_out','balance_after','reference_no','job_card_id','created_at'])->toArray());
$out('AM257_PART_CODE_MATCH_COUNT',DB::table('parts_master')->where('part_code','AM-0348')->count());
$out('AM257_PB_LINE',DB::table('workshop_purchases')->where('id',51)
 ->first(['id','purchase_no','part_master_id','description','qty','unit_cost','record_status','purchase_batch_no','purchase_date']));
$out('AM257_JC_LINE',DB::table('job_card_parts')->where('id',588)
 ->first(['id','part_master_id','part_code','part_name','issue_status','qty']));
echo "AM257_READONLY_COMPLETE\n";
