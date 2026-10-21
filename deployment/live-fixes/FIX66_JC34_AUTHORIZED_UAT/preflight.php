<?php
/** FIX66 exact pre-transaction guard for owner-approved STAGING JC-20261014-00034. READ ONLY. */
declare(strict_types=1);
$root=(string)($argv[1]??'');
if(!is_file($root.'/artisan'))throw new RuntimeException('Expected staging root');
chdir($root);
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=\Illuminate\Support\Facades\DB::class;
$job=$db::table('job_cards')->where('id',34)->first();
$inv=$db::table('invoices')->where('id',20)->first();
if(!$job||!$inv||$job->job_no!=='JC-20261014-00034'||$inv->invoice_no!=='INV-2026-00020')throw new RuntimeException('TARGET_IDENTITY_MISMATCH');
$tax=\App\Services\JobCardTaxTotals::forJob(34);
$round=fn($n)=>(int)round((float)$n*100);
$items=['parts_total','labour_total','gst_total','pre_round_total','round_off','grand_total'];
$taxChecks=[];
foreach($items as $key){$taxChecks[$key]=['recalc'=>$tax[$key],'stored'=>$job->$key??null];}
echo 'FIX66_JC34_TAX_RECONCILE '.json_encode($taxChecks,JSON_UNESCAPED_SLASHES)."\n";
$storedInvoice=['status'=>$inv->status,'grand_total'=>$inv->grand_total,'gst_total'=>$inv->gst_total,'subtotal'=>$inv->subtotal,'paid_amount'=>$inv->paid_amount,'paid_total'=>$inv->paid_total,'balance_due'=>$inv->balance_due,'balance_total'=>$inv->balance_total];
echo 'FIX66_JC34_ORIGINAL_INVOICE '.json_encode($storedInvoice,JSON_UNESCAPED_SLASHES)."\n";
$ledger=$db::table('customer_ledger')->where('invoice_id',20)->get();
echo 'FIX66_JC34_LEDGER '.json_encode(['count'=>$ledger->count(),'entries'=>$ledger->map(fn($l)=>['type'=>$l->entry_type,'debit'=>$l->debit,'credit'=>$l->credit,'customer_match'=>(int)$l->customer_id===(int)$inv->customer_id])->all()])."\n";
$count=[];
foreach(['customer_payments','customer_payment_allocations','invoice_credit_notes'] as $table){$count[$table]=$db::table($table)->where('invoice_id',20)->count();}
$count['other_active_invoices']=$db::table('invoices')->where('job_card_id',34)->where('id','<>',20)->whereRaw("LOWER(COALESCE(status,'')) <> 'reversed'")->count();
echo 'FIX66_JC34_GUARDS '.json_encode($count)."\n";
$admins=\App\Models\ErpAuthUser::query()->where('role','admin')->where('active',1)->count();
echo 'FIX66_ACTIVE_ADMIN_COUNT '.$admins."\n";
$assert=(int)$job->invoice_id===20
&&strtolower((string)$job->status)==='invoiced'
&&strtolower((string)$inv->status)==='issued'
&&$round($inv->grand_total)===133000
&&$round($inv->paid_amount)===0 && $round($inv->paid_total)===0
&&$round($inv->balance_due)===$round($inv->grand_total)
&&$round($inv->balance_total)===$round($inv->grand_total)
&&$round($tax['grand_total'])===$round($inv->grand_total)
&&abs($round($tax['gst_total'])-$round($inv->gst_total))<=2
&&$ledger->count()===1
&&strtoupper((string)$ledger[0]->entry_type)==='DEBIT'
&&$round($ledger[0]->debit)===$round($inv->grand_total)
&&$round($ledger[0]->credit)===0
&&(int)$ledger[0]->customer_id===(int)$inv->customer_id
&&$admins>0;
foreach($count as $c)$assert=$assert&&$c===0;
if(!$assert)throw new RuntimeException('FIX66_EXECUTION_BLOCKED_RECONCILIATION_FAILED');
echo 'FIX66_JC34_STRICT_PRE_TRANSACTION_PASS_NO_WRITES'."\n";
