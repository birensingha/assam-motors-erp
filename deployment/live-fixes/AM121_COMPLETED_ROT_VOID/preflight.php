<?php
/** AM121 staging inspection: reads schema and code metadata, no data writes. */
declare(strict_types=1);
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "AM121_PREFLIGHT_READ_ONLY_START\n";
$ok=true;
$wanted=[
  'job_cards'=>['id','status','invoice_id','parts_total','labour_total','gst_total','pre_round_total','round_off','grand_total'],
  'job_card_labour'=>['id','job_card_id','rot_code','labour_rate','total'],
  'rot_sessions'=>['job_card_id','labour_line_id','status'],
  'job_card_change_audits'=>['job_card_id','entity_type','entity_id','action','before_json','after_json','reason','actor_id','actor_name','created_at','updated_at'],
];
$allowed=['labour_rate','total','amount','line_amount','line_total','gross','gross_amount','gross_total','discount_amount','discount_amt','discount_rs','taxable','taxable_amount','taxable_total','gst_amount','gst_amt','tax_amount','tax_total','final_amount','final_total','net_amount','net_total','cess_amount','cgst_amount','sgst_amount','igst_amount'];
foreach($wanted as $table=>$required){
  if(!Schema::hasTable($table)){
    echo "TABLE_{$table}=MISSING\n";$ok=false;continue;
  }
  $columns=Schema::getColumnListing($table);
  $missing=array_diff($required,$columns);
  echo "TABLE_{$table}_PRESENT=yes\n";
  echo "TABLE_{$table}_MISSING_COLUMNS=".($missing?implode(',',$missing):'none')."\n";
  if($missing)$ok=false;
  if($table==='job_card_labour'){
    echo "LABOUR_COLUMNS=".implode(',',$columns)."\n";
    $unknown=[];
    foreach($columns as $col)if(preg_match('/(?:^|_)(?:amount|amt|total|taxable|gross)$/i',$col)&&!in_array($col,$allowed,true))$unknown[]=$col;
    echo "LABOUR_UNKNOWN_FINANCIAL_COLUMNS=".($unknown?implode(',',$unknown):'none')."\n";
    if($unknown)$ok=false;
  }
}
if(Schema::hasTable('rot_sessions') && Schema::hasColumn('rot_sessions','status')){
  $counts=DB::table('rot_sessions')->select('status')->selectRaw('COUNT(*) AS n')->groupBy('status')->limit(20)->get();
  foreach($counts as $row)echo "ROT_STATUS=".preg_replace('/[^A-Za-z_\-]/','',(string)$row->status).' COUNT='.(int)$row->n."\n";
}
$service=getcwd().'/app/Services/JobCardTaxTotals.php';
echo 'TAX_TOTALS_SERVICE_PRESENT='.(is_file($service)?'yes':'no')."\n";
if(!is_file($service))$ok=false;
foreach(['app/Http/Controllers/Web/AdminLabourLineAuditController.php','resources/views/job-cards/show.blade.php','routes/web.php'] as $path){
  echo 'FILE_'.str_replace('/','_',$path).'='.(is_file($path)?'present':'missing')."\n";
  if(!is_file($path))$ok=false;
}
echo 'AM121_PREFLIGHT_RESULT='.($ok?'READY_FOR_STAGING_CODE_REVIEW':'STOP_SCHEMA_OR_SOURCE_MISMATCH')."\n";
echo "AM121_PREFLIGHT_COMPLETE_NO_WRITES\n";
