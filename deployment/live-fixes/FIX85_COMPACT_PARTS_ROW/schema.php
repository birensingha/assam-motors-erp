<?php
declare(strict_types=1);
$root=$argv[1]??null;if(!$root||!is_file("$root/artisan"))exit(2);
chdir($root);require 'vendor/autoload.php';
$a=require 'bootstrap/app.php';$a->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
$c=DB::connection();$c->statement('START TRANSACTION READ ONLY');
try{
 foreach(['job_card_parts','parts_master','part_master','parts','job_cards','job_card_labour'] as $tbl){
  if(!Schema::hasTable($tbl)){echo "FIX85_TABLE_ABSENT ".$tbl."\n";continue;}
  echo 'FIX85_SCHEMA '.json_encode(['table'=>$tbl,'columns'=>Schema::getColumnListing($tbl)])."\n";
 }
 $r=DB::table('job_card_parts')->first();
 $j=DB::table('job_cards')->where('job_no','JC-20261018-00037')->first();
 if($j){
  echo 'FIX85_JC37_PARTS_COUNT '.DB::table('job_card_parts')->where('job_card_id',$j->id)->count()."\n";
 }
 echo "FIX85_DATABASE_READ_ONLY_PASS\n";
}finally{$c->rollBack();}
