<?php
declare(strict_types=1);
$root=$argv[1]??'';if(!is_file($root.'/artisan'))exit(2);
chdir($root);require 'vendor/autoload.php';$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$c=DB::connection();$c->statement('START TRANSACTION READ ONLY');
try{
echo 'FIX74_SERVER_VERSION '.DB::selectOne('SELECT VERSION() as v')->v."\n";
foreach(DB::select("SHOW INDEX FROM invoices") as $idx){
 echo 'FIX74_INDEX '.json_encode(['name'=>$idx->Key_name,'unique'=>(int)$idx->Non_unique===0,'sequence'=>$idx->Seq_in_index,'column'=>$idx->Column_name,'type'=>$idx->Index_type])."\n";
}
foreach(DB::select("SHOW FULL COLUMNS FROM invoices") as $col){
 if(in_array($col->Field,['id','job_card_id','original_job_card_id','status','invoice_no','returned_at','return_reason'],true))
  echo 'FIX74_COLUMN '.json_encode(['name'=>$col->Field,'type'=>$col->Type,'nullable'=>$col->Null,'default'=>$col->Default,'extra'=>$col->Extra])."\n";
}
echo 'FIX74_TOTAL_REVERSED '.DB::table('invoices')->whereRaw("LOWER(COALESCE(status,''))='reversed'")->count()."\n";
echo 'FIX74_ACTIVE_DUPLICATE_JOB_CARDS '.DB::table('invoices')->select('job_card_id')->whereRaw("LOWER(COALESCE(status,'')) <> 'reversed'")->whereNotNull('job_card_id')->groupBy('job_card_id')->havingRaw('COUNT(*)>1')->get()->count()."\n";
echo 'FIX74_INVOICES_WITH_NULL_JOB_ID '.DB::table('invoices')->whereNull('job_card_id')->count()."\n";
echo 'FIX74_STAGING_SCHEMA_READONLY_PASS'."\n";
} finally {$c->rollBack();}
