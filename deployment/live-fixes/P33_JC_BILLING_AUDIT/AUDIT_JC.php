<?php
declare(strict_types=1);
/** Read-only estimate-to-Job-Card tax reconciliation. Never mutates business data. */
$root=rtrim((string)($argv[1]??''),'/');$number=(string)($argv[2]??'JC-20261014-00034');
if(!is_file($root.'/artisan')){fwrite(STDERR,"ERP root required\n");exit(2);}
chdir($root);require 'vendor/autoload.php';$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
function outputRow(string $label,?object $row,array $wanted):void{
 echo "$label\n";
 if(!$row){echo "  Not found\n";return;}
 foreach($wanted as $key){if(property_exists($row,$key))printf("  %s: %s\n",$key,is_numeric($row->$key)?number_format((float)$row->$key,2,'.',''): '[set]');}
}
$jc=DB::table('job_cards')->where('job_no',$number)->first();
if(!$jc){fwrite(STDERR,"NOT FOUND: $number\n");exit(3);}
echo "READ ONLY BILLING AUDIT $number / ID ".$jc->id."\n";
outputRow('Job Card',$jc,['status','parts_total','labour_total','subtotal','gst_total','tax_total','pre_round_total','round_off','grand_total','invoice_id']);
foreach(['job_card_parts','job_card_labour'] as $table){
 if(!Schema::hasTable($table)){echo "$table missing\n";continue;}
 $rows=DB::table($table)->where('job_card_id',$jc->id)->get();
 $fields=array_filter(['qty','rate','labour_rate','discount_percent','gst_percent','total','taxable_amount','gst_amount'],fn($v)=>Schema::hasColumn($table,$v));
 echo "$table count=".count($rows)." total=".number_format((float)$rows->sum('total'),2)."\n";
 foreach($rows as $row){echo '  line#'.$row->id;foreach($fields as $field){$value=$row->$field??null;if(is_numeric($value))echo " $field=".$value;}echo "\n";}
}
$estCandidates=['estimates','job_card_estimates','workshop_estimates'];
foreach($estCandidates as $table){
 if(!Schema::hasTable($table)||!Schema::hasColumn($table,'job_card_id'))continue;
 $cols=array_intersect(['id','status','subtotal','parts_total','labour_total','gst_total','tax_total','grand_total','total','approved_at','created_at'],Schema::getColumnListing($table));
 echo "$table\n";
 foreach(DB::table($table)->where('job_card_id',$jc->id)->get() as $row){outputRow('Estimate', $row,array_values($cols));}
}
if(Schema::hasTable('invoices') && Schema::hasColumn('invoices','job_card_id')){
 foreach(DB::table('invoices')->where('job_card_id',$jc->id)->get() as $row){
 outputRow('Invoice',$row,['id','status','parts_total','labour_total','subtotal','gst_total','tax_total','round_off','grand_total']);
 }
}
echo "OBSERVATION: 1330 * 0.18 = 239.40 (clue only, not definitive proof).\n";
echo "NO WRITES PERFORMED\n";
