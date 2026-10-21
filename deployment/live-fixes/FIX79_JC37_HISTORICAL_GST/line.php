<?php
declare(strict_types=1);
$root=$argv[1]??'';if(!is_file($root.'/artisan'))exit(2);
chdir($root);require 'vendor/autoload.php';$a=require 'bootstrap/app.php';$a->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
$c=DB::connection();$c->statement('START TRANSACTION READ ONLY');
try{
  $j=DB::table('job_cards')->where('job_no','JC-20261018-00037')->first();
  if(!$j)throw new RuntimeException('JC37 missing');
  foreach(['job_card_labour','job_card_parts'] as $t){
   $cols=Schema::getColumnListing($t);
   echo 'FIX79_SCHEMA '.$t.' '.json_encode(array_values(array_intersect(
     ['quantity','qty','standard_hours','labour_rate','rate','discount_percent','gst_percent','total','billing_included','original_total','updated_at'],$cols)))."\n";
   foreach(DB::table($t)->where('job_card_id',$j->id)->get() as $r){
     $s=[];
     foreach(['id','rot_code','description','quantity','qty','standard_hours','labour_rate','rate','discount_percent','gst_percent','total','billing_included','updated_at'] as $k)if(property_exists($r,$k))$s[$k]=$r->$k;
     echo 'FIX79_ROW_DETAIL '.json_encode($s)."\n";
   }
  }
  $est=Schema::hasTable('estimates')&&Schema::hasColumn('estimates','job_card_id')?
   DB::table('estimates')->where('job_card_id',$j->id)->count():null;
  echo 'FIX79_ESTIMATE_COUNT '.json_encode($est)."\n";
  echo 'FIX79_EXISTING_JC_GRAND '.json_encode(['grand'=>$j->grand_total,'gst'=>$j->gst_total])."\n";
}finally{$c->rollBack();}
