<?php
declare(strict_types=1);
$root=$argv[1]??null;if(!$root||!is_file($root.'/artisan'))exit(2);
chdir($root);require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
$c=DB::connection();$c->statement('START TRANSACTION READ ONLY');
try{
foreach(['rot_master','labour_master','rot_codes','labour_rot_master','job_card_labour','invoice_lines','invoice_items','rot_sessions','job_card_rot','rot_catalog','workshop_rot_master'] as $t){
 if(!Schema::hasTable($t)){echo "FIX76_TABLE_ABSENT $t\n";continue;}
 $cols=Schema::getColumnListing($t);
 echo "FIX76_TABLE ".json_encode(['name'=>$t,'count'=>DB::table($t)->count(),'columns'=>$cols])."\n";
 $fields=array_values(array_filter($cols,fn($x)=>preg_match('/sac|hsn|tax_code|gst|service_code|rot_code/i',$x)));
 foreach($fields as $f){
  $n=DB::table($t)->whereNull($f)->orWhere($f,'')->count();
  echo "FIX76_FIELD ".json_encode(['table'=>$t,'field'=>$f,'blank_count'=>$n,'total'=>DB::table($t)->count()])."\n";
 }
}
echo "FIX76_SCHEMA_READONLY_COMPLETE\n";
}finally{$c->rollBack();}
