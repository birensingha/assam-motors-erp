<?php
/** AM255 read-only missing Parts Master ID 348 incident forensic check. */
declare(strict_types=1);
$root = realpath($argv[1] ?? '') ?: '';
if (basename($root) !== 'assam-erp-staging' || !is_file($root.'/artisan')) {fwrite(STDERR,"AM255_STOP_NOT_STAGING\n");exit(2);}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
$target=348;$bill='PB-261031192828-156';
$show=function(string $key,$data){echo $key.' '.json_encode($data,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE)."\n";};
$names=['parts_master','workshop_purchases','workshop_purchase_batches','inventory_movements','job_card_parts','purchase_lines','purchases','part_rot_mapping','workshop_purchase_edit_audits','workshop_purchase_audits','parts_master_audits','inventory_stock_adjustments'];
$preferred=['id','part_master_id','part_id','part_code','part_name','description','purchase_number','purchase_no','purchase_ref','purchase_id','purchase_batch_id','batch_no','batch_number','reference_no','document_number','movement_type','record_status','qty','quantity','qty_in','qty_out','stock_before','stock_after','stock_qty','unit_cost','rate','gst_percent','created_at','updated_at','deleted_at'];
foreach($names as $name){
 try{
  if(!Schema::hasTable($name)){ $show('AM255_TABLE_ABSENT',['table'=>$name]);continue;}
  $columns=Schema::getColumnListing($name);
  $print=array_values(array_intersect($preferred,$columns));
  $show('AM255_TABLE',['table'=>$name,'columns'=>array_values(array_intersect($columns,$preferred))]);
  if($name==='parts_master'){
    $rows=DB::table($name)->where('id',$target)->limit(1)->get($print);
  }elseif(in_array('part_master_id',$columns,true)){
    $rows=DB::table($name)->where('part_master_id',$target)->orderBy('id')->limit(30)->get($print);
  }elseif(in_array('part_id',$columns,true)){
    $rows=DB::table($name)->where('part_id',$target)->orderBy('id')->limit(30)->get($print);
  }else{continue;}
  $show('AM255_PART348_ROWS',['table'=>$name,'count'=>$rows->count(),'rows'=>$rows->toArray()]);
 }catch(Throwable $e){$show('AM255_TABLE_ERROR',['table'=>$name,'error'=>get_class($e)]);}
}
foreach(['workshop_purchases','workshop_purchase_batches','purchases'] as $name){
 if(!Schema::hasTable($name))continue;
 $cols=Schema::getColumnListing($name);
 $possible=array_values(array_intersect(['purchase_no','purchase_number','purchase_ref','batch_no','batch_number','reference_no'], $cols));
 if(!$possible)continue;
 $query=DB::table($name)->where(function($q)use($possible,$bill){foreach($possible as $c)$q->orWhere($c,$bill);});
 $found=$query->limit(25)->get(array_values(array_intersect($preferred,$cols)));
 $show('AM255_PB_MATCH',['table'=>$name,'rows'=>$found->toArray()]);
}
$backups=$root.'/storage/app/am-backups';
$files=[];
if(is_dir($backups)){
 foreach(new DirectoryIterator($backups) as $item){
  if($item->isDot())continue;
  $name=$item->getFilename();
  if(preg_match('/(database|db-|sql|backup|snapshot|am2[1-5][0-9])/i',$name)){
   $files[]=['name'=>mb_substr($name,0,90),'type'=>$item->isDir()?'directory':'file',
     'modified'=>date('Y-m-d',$item->getMTime())];
  }
 }
}
usort($files,fn($a,$b)=>strcmp($b['modified'],$a['modified']));
$show('AM255_BACKUP_CANDIDATES',array_slice($files,0,35));
echo "AM255_READONLY_FINISHED_NO_DB_WRITES\n";
