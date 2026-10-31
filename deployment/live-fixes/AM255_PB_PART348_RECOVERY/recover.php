<?php
/**
 * AM258 surgical recovery. Convert the unreferenced replacement Parts Master id 900
 * into the historical id 348, restoring stock from movement 207's balance_after.
 * No purchases, invoices, JC lines, inventory movement rows or payments changed.
 * Modes: check|apply|verify ROOT. An immutable pre-change JSON is stored privately.
 */
declare(strict_types=1);
if(count($argv)!==3 || !in_array($argv[1],['check','apply','verify'],true)){fwrite(STDERR,"AM258_USAGE\n");exit(2);}
$mode=$argv[1];$root=realpath($argv[2])?:'';
if(basename($root)!=='assam-erp-staging'||!is_file($root.'/artisan')){fwrite(STDERR,"AM258_STOP_NOT_STAGING\n");exit(2);}
require $root.'/vendor/autoload.php';$app=require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
const OLD_ID=348;const NEW_ID=900;const CODE='AM-0348';const NAME='Starter Solenoid';
const PB='PB-261031192828-156';const EXPECT_STOCK='1.00';
$die=function(string $message):never{fwrite(STDERR,$message."\n");exit(3);};
$refScan=function(int $id):array{
 $refs=DB::select("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME IN ('part_master_id','part_id')
                   ORDER BY TABLE_NAME,COLUMN_NAME");
 $found=[];
 foreach($refs as $ref){
  $table=(string)$ref->TABLE_NAME;$column=(string)$ref->COLUMN_NAME;
  if(!preg_match('/^[a-z0-9_]+$/i',$table) || !preg_match('/^[a-z0-9_]+$/i',$column))throw new \RuntimeException('AM258_UNEXPECTED_SCHEMA');
  if(in_array($table,['parts_master','migrations'],true))continue;
  $ids=DB::table($table)->where($column,$id)->count();
  if($ids)$found[$table.'::'.$column]=(int)$ids;
 }
 return $found;
};
$validateHistorical=function()use($die):array{
 $p=DB::table('workshop_purchases')->where('id',51)->first();
 if(!$p || (int)$p->part_master_id!==OLD_ID || (string)$p->purchase_batch_no!==PB ||
    (string)$p->description!==NAME || (float)$p->qty!==1.0 || (string)$p->record_status!=='ACTIVE')
    $die('AM258_STOP_PB_LINE_CHANGED');
 $m=DB::table('inventory_movements')->where('id',207)->first();
 if(!$m || (int)$m->part_master_id!==OLD_ID || (string)$m->movement_type!=='PURCHASE_IN' ||
   (float)$m->qty_in!==1.0 || (float)$m->qty_out!==0.0 || abs((float)$m->balance_after-1.0)>0.001)
   $die('AM258_STOP_MOVEMENT_BALANCE_CHANGED');
 if(DB::table('inventory_movements')->where('part_master_id',OLD_ID)->count()!==1)
   $die('AM258_STOP_ADDITIONAL_STOCK_MOVEMENTS');
 $j=DB::table('job_card_parts')->where('id',588)->first();
 if(!$j || (int)$j->part_master_id!==OLD_ID || (string)$j->part_code!==CODE ||
   (string)$j->part_name!==NAME || (string)$j->issue_status!=='PLANNED')
   $die('AM258_STOP_JOB_CARD_LINE_CHANGED');
 $batch=DB::table('workshop_purchase_batches')->where('batch_no',PB)->first();
 if(!$batch || (string)$batch->record_status!=='ACTIVE')$die('AM258_STOP_PURCHASE_BATCH_CHANGED');
 return [
  'purchase'=>['id'=>51,'part_master_id'=>OLD_ID,'purchase_no'=>(string)$p->purchase_no,'batch'=>PB],
  'movement'=>['id'=>207,'part_master_id'=>OLD_ID,'balance_after'=>(string)$m->balance_after],
  'job_card'=>['id'=>588,'part_master_id'=>OLD_ID,'issue_status'=>(string)$j->issue_status]
 ];
};
$historical=$validateHistorical();
$now=DB::table('parts_master')->where('id',OLD_ID)->first();
$replacement=DB::table('parts_master')->where('id',NEW_ID)->first();
if($mode==='verify'||($now&&!$replacement)){
 if(!$now || $replacement || (string)$now->part_code!==CODE || (string)$now->part_name!==NAME ||
    abs((float)$now->stock_qty-1)>0.001) $die('AM258_VERIFY_FAILED_IDENTITY_OR_STOCK');
 $other=$refScan(NEW_ID);
 if($other)$die('AM258_VERIFY_FAILED_ID900_REFERENCES');
 echo "AM258_VERIFIED_ORIGINAL_ID_348_STOCK_1_PB_AND_MOVEMENT_PRESERVED\n";exit(0);
}
if($now || !$replacement)$die('AM258_STOP_MASTER_ID_STATE_CHANGED');
if((string)$replacement->part_code!==CODE||(string)$replacement->part_name!==NAME ||
   (string)$replacement->unit!=='PCS'||(string)$replacement->active!=='Yes'||$replacement->stock_qty!==null)
   $die('AM258_STOP_REPLACEMENT_MASTER_FIELDS_CHANGED');
if(DB::table('parts_master')->where('part_code',CODE)->count()!==1)
   $die('AM258_STOP_PART_CODE_COLLISION');
$newRefs=$refScan(NEW_ID);if($newRefs)$die('AM258_STOP_ID900_HAS_TRANSACTION_REFERENCES');
$oldRefs=$refScan(OLD_ID);
$expected=['inventory_movements::part_master_id'=>1,'job_card_parts::part_master_id'=>1,'workshop_purchases::part_master_id'=>1];
ksort($oldRefs);ksort($expected);
if($oldRefs!==$expected)$die('AM258_STOP_ORIGINAL_REFERENCES_CHANGED');
if($mode==='check'){echo "AM258_PRECHECK_PASS_ID900_UNUSED_ORIGINAL348_MISSING_STOCK1\n";exit(0);}
$dir=$root.'/storage/app/am-backups/AM258_PART348_CANONICAL_RECOVERY';
if(file_exists($dir))$die('AM258_STOP_BACKUP_ALREADY_EXISTS');
if(!mkdir($dir,0700,true))$die('AM258_STOP_CANNOT_CREATE_PRIVATE_BACKUP');
$before=[
 'at'=>date('c'),'action'=>'restore_deleted_parts_master_id_348_from_unreferenced_same_code_master_900',
 'replacement_before'=>(array)$replacement,'target_id_348_missing'=>true,
 'references348'=>$oldRefs,'references900'=>$newRefs,'linked_records'=>$historical,
 'stock_source'=>'inventory_movements.id=207.balance_after',
 'limitations'=>'Original deleted master metadata not independently recovered. Replacement row fields retained. No purchase/JC/movement updates.'
];
$file=$dir.'/before.json';
if(file_put_contents($file,json_encode($before,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE),LOCK_EX)===false)
  $die('AM258_STOP_CANNOT_WRITE_BACKUP');
chmod($file,0600);
DB::transaction(function()use($die,$refScan,$validateHistorical):void{
 $entry=DB::table('parts_master')->where('id',NEW_ID)->lockForUpdate()->first();
 if(!$entry || (string)$entry->part_code!==CODE || (string)$entry->part_name!==NAME ||
    $entry->stock_qty!==null || DB::table('parts_master')->where('id',OLD_ID)->exists())
    $die('AM258_STOP_CONCURRENT_MASTER_CHANGE');
 if($refScan(NEW_ID))$die('AM258_STOP_CONCURRENT_ID900_REFERENCE');
 $validateHistorical();
 // Only this Master row changes. No inventory movement is inserted.
 $affected=DB::table('parts_master')->where('id',NEW_ID)
  ->where('part_code',CODE)->where('part_name',NAME)->whereNull('stock_qty')
  ->update(['id'=>OLD_ID,'stock_qty'=>EXPECT_STOCK]);
 if($affected!==1)throw new \RuntimeException('AM258_STOP_MASTER_UPDATE_FAILED');
},1);
$restored=DB::table('parts_master')->where('id',OLD_ID)->first();
if(!$restored || (string)$restored->part_code!==CODE || abs((float)$restored->stock_qty-1)>0.001 ||
    DB::table('parts_master')->where('id',NEW_ID)->exists() || $refScan(NEW_ID))
    $die('AM258_POSTCOMMIT_VERIFY_FAILURE_REVIEW_REQUIRED');
$after=['at'=>date('c'),'id'=>$restored->id,'code'=>$restored->part_code,'name'=>$restored->part_name,'stock_qty'=>$restored->stock_qty,
 'purchase_51_master_id'=>DB::table('workshop_purchases')->where('id',51)->value('part_master_id'),
 'movement_207_master_id'=>DB::table('inventory_movements')->where('id',207)->value('part_master_id'),
 'job_card_line_588_master_id'=>DB::table('job_card_parts')->where('id',588)->value('part_master_id')];
file_put_contents($dir.'/after.json',json_encode($after,JSON_PRETTY_PRINT),LOCK_EX);chmod($dir.'/after.json',0600);
echo "AM258_STAGING_MASTER_ID348_RECOVERED_FROM_UNREFERENCED_ID900\n";
echo "AM258_STOCK1_FROM_MOVEMENT_207_NO_NEW_LEDGER_ENTRIES\n";
echo "AM258_PURCHASE_51_JOB_LINE_588_REFERENCES_PRESERVED\n";
