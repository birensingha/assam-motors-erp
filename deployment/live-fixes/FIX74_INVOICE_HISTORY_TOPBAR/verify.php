<?php
/** FIX74 staging: read-only invoice history/index validation.
 * Usage: php verify.php <app_root> before|after <private_snapshot_path>
 */
declare(strict_types=1);
[$script,$root,$phase,$snapshot]=$argv+[null,null,null,null];
if(!in_array($phase,['before','after'],true)||!is_file($root.'/artisan')||!$snapshot)exit(2);
chdir($root);
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
$c=DB::connection();
$c->statement('START TRANSACTION READ ONLY');
try{
 $keys=collect(DB::select('SHOW INDEX FROM invoices'));
 $old=$keys->contains(fn($x)=>$x->Key_name==='uq_invoice_job' && (int)$x->Non_unique===0 && $x->Column_name==='job_card_id');
 $new=$keys->contains(fn($x)=>$x->Key_name==='uq_invoice_active_job' && (int)$x->Non_unique===0 && $x->Column_name==='active_job_card_id');
 $col=Schema::hasColumn('invoices','active_job_card_id');
 if($phase==='before'&&(!$old||$new||$col))throw new RuntimeException('Pre-migration index state unexpectedly changed');
 if($phase==='after'&&($old||!$new||!$col))throw new RuntimeException('Post-migration index state invalid');
 $jc=DB::table('job_cards')->where('id',34)->first();
 $inv=DB::table('invoices')->where('id',20)->first();
 if(!$jc||!$inv||$jc->job_no!=='JC-20261014-00034'||
    $jc->status!=='closed'||$jc->invoice_id!==null||
    $inv->invoice_no!=='INV-2026-00020'||$inv->status!=='reversed'||
    (int)$inv->job_card_id!==34||
    abs((float)$jc->grand_total-1500.00)>0.02||
    abs((float)$jc->gst_total-228.78)>0.02)throw new RuntimeException('JC34 or historical invoice changed');
 if($phase==='after'&&$inv->active_job_card_id!==null)throw new RuntimeException('Reversed invoice still reserves active key');
 $activeClash=DB::table('invoices')->whereNotNull('job_card_id')
   ->whereRaw("LOWER(COALESCE(status,'')) <> 'reversed'")
   ->select('job_card_id')->groupBy('job_card_id')->havingRaw('COUNT(*)>1')->exists();
 if($activeClash)throw new RuntimeException('Multiple active invoices');
 $ledger=DB::table('customer_ledger')->where('invoice_id',20)->get();
 if($ledger->count()!==2||abs($ledger->sum('debit')-1330)>0.02||
   abs($ledger->sum('credit')-1330)>0.02)throw new RuntimeException('Original ledger no longer reconciles');
 $creditNotes=DB::table('invoice_credit_notes')->where('invoice_id',20)->get();
 if($creditNotes->count()!==1||$creditNotes[0]->status!=='posted'||
   abs((float)$creditNotes[0]->credit_amount-1330)>0.02)throw new RuntimeException('Original CN no longer reconciles');
 $rows=DB::table('invoices')->orderBy('id')->get([
    'id','job_card_id','original_job_card_id','invoice_no','status','grand_total','gst_total'
  ])->map(fn($r)=>(array)$r)->all();
 $digest=hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
 $result=['sha256'=>$digest,'invoice_count'=>count($rows),'credit_note_count'=>DB::table('invoice_credit_notes')->count(),'ledger_count'=>DB::table('customer_ledger')->count()];
 if($phase==='before'){
   if(is_file($snapshot))throw new RuntimeException('Refusing to overwrite existing finance snapshot');
   if(file_put_contents($snapshot,json_encode($result,JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('Cannot save snapshot');
   chmod($snapshot,0600);
 }else{
   $before=json_decode(file_get_contents($snapshot),true,512,JSON_THROW_ON_ERROR);
   if($before!==$result)throw new RuntimeException('Invoice/CN/ledger rows changed during migration; check staging writes');
 }
 echo 'FIX74_'.strtoupper($phase).'_VERIFY_PASS indexed_by_active_unique='.($new?'yes':'no').
     ' historic_invoice20_preserved=yes credit_note_and_ledger_balanced=yes invoices='.count($rows)."\n";
}finally{$c->rollBack();}
