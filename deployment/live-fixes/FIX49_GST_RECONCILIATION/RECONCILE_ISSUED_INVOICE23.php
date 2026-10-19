<?php
/** FIX49: one-time audited correction of unpaid staging invoice INV-2026-00023. */
declare(strict_types=1);
$mode=(string)($argv[1]??'');
if(!in_array($mode,['--check','--apply'],true))exit(2);
if($mode==='--apply' && getenv('FIX49_APPROVE_INVOICE23')!=='APPROVED_STAGING_INVOICE23')exit(3);
chdir((string)($argv[2]??''));
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function sameAmount(mixed $actual,float $expected):bool {
    return abs(round((float)$actual,2)-$expected)<0.011;
}
function numericSnapshot(object $row,array $cols):array {
    $out=[];
    foreach($cols as $col)if(property_exists($row,$col))$out[$col]=$row->$col;
    return $out;
}

DB::transaction(function()use($mode){
    $job=DB::table('job_cards')->where('job_no','JC-20261019-00039')->lockForUpdate()->first();
    $inv=DB::table('invoices')->where('invoice_no','INV-2026-00023')->lockForUpdate()->first();
    if(!$job||!$inv||(int)$job->id!==39||(int)$inv->id!==23
       ||(int)$inv->job_card_id!==39||(int)$job->invoice_id!==23
       ||strtolower((string)$job->status)!=='invoiced'
       ||strtolower((string)$inv->status)!=='issued') {
        throw new RuntimeException('FIX49 correction halted: record identity/status mismatch.');
    }
    $ledger=DB::table('customer_ledger')->where('invoice_id',23)->lockForUpdate()->get();
    if($ledger->count()!==1)throw new RuntimeException('FIX49 halted: customer ledger count mismatch.');
    $debit=$ledger->first();
    if((string)$debit->entry_type!=='DEBIT'||(string)$debit->reference_type!=='INVOICE'
       ||(int)$debit->customer_id!==(int)$inv->customer_id
       ||!sameAmount($debit->credit,0)
       ||!sameAmount($debit->debit,3255)||!sameAmount($debit->amount,3255)){
        throw new RuntimeException('FIX49 halted: ledger details modified.');
    }
    foreach(['paid_amount','paid_total'] as $key){
        if(!sameAmount($inv->{$key}??0,0))throw new RuntimeException('FIX49 halted: paid invoice.');
    }
    foreach(['balance_due','balance_total','grand_total'] as $key){
        if(!sameAmount($inv->$key,3255))throw new RuntimeException('FIX49 halted: invoice amount changed.');
    }
    if(!sameAmount($inv->gst_total,0)||!sameAmount($inv->tax_total,0)
       ||!sameAmount($job->gst_total,0)||!sameAmount($job->grand_total,3255)){
        throw new RuntimeException('FIX49 halted: old tax values changed.');
    }
    $auditAlready=DB::table('job_card_change_audits')
        ->where('job_card_id',39)->where('action','INVOICE_GST_RECONCILE_FIX49')->exists();
    if($auditAlready)throw new RuntimeException('FIX49 halted: correction audit already exists.');

    $allTables=DB::select('SHOW TABLES');
    foreach($allTables as $tableRow){
        $values=array_values((array)$tableRow);
        $t=(string)($values[0]??'');
        if(!$t||!preg_match('/^[A-Za-z0-9_]+$/D',$t))continue;
        if(!Schema::hasColumn($t,'invoice_id'))continue;
        $count=DB::table($t)->where('invoice_id',23)->count();
        if($t==='job_cards' && $count!==1)throw new RuntimeException('Invalid JC invoice reference count.');
        if($t==='customer_ledger' && $count!==1)throw new RuntimeException('Invalid ledger reference count.');
        if(!in_array($t,['job_cards','customer_ledger'],true)&&$count>0){
            throw new RuntimeException('Invoice already referenced in another module; manual reconciliation required.');
        }
    }

    DB::table('job_card_parts')->where('job_card_id',39)->lockForUpdate()->get();
    DB::table('job_card_labour')->where('job_card_id',39)->lockForUpdate()->get();
    $total=\App\Services\JobCardTaxTotals::forJob(39);
    $expect=['parts_total'=>1941.14,'labour_total'=>1313.55,'subtotal'=>3254.69,
        'gst_total'=>585.84,'pre_round_total'=>3840.53,'round_off'=>0.47,'grand_total'=>3841.00];
    foreach($expect as $key=>$value){
        if(!sameAmount($total[$key],$value))throw new RuntimeException('Itemwise GST fixture mismatch '.$key);
    }
    echo 'RECONCILIATION_CHECK=PASS EXISTING=3255.00 CORRECTED=3841.00 PAID=0.00'.PHP_EOL;
    if($mode==='--check'){echo "DRY_RUN_ONLY_NO_DB_CHANGES\n";return;}

    $before=[
        'job'=>numericSnapshot($job,['parts_total','labour_total','gst_total','pre_round_total','round_off','grand_total']),
        'invoice'=>numericSnapshot($inv,['subtotal','parts_total','labour_total','gst_total','tax_total','pre_round_total','round_off','grand_total','balance_due','balance_total']),
        'ledger'=>numericSnapshot($debit,['amount','debit','credit']),
    ];
    DB::table('job_cards')->where('id',39)->update([
        'parts_total'=>1941.14,'labour_total'=>1313.55,'gst_total'=>585.84,
        'pre_round_total'=>3840.53,'round_off'=>0.47,'grand_total'=>3841.00,
    ]);
    DB::table('invoices')->where('id',23)->update([
        'subtotal'=>3254.69,'parts_total'=>1941.14,'labour_total'=>1313.55,
        'gst_total'=>585.84,'tax_total'=>585.84,'pre_round_total'=>3840.53,
        'round_off'=>0.47,'grand_total'=>3841.00,'balance_due'=>3841.00,
        'balance_total'=>3841.00,
    ]);
    DB::table('customer_ledger')->where('id',$debit->id)->update(['amount'=>3841.00,'debit'=>3841.00]);
    $now=now('Asia/Kolkata');
    DB::table('job_card_change_audits')->insert([
        'job_card_id'=>39,'entity_type'=>'INVOICE','entity_id'=>23,
        'action'=>'INVOICE_GST_RECONCILE_FIX49',
        'before_json'=>json_encode($before,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        'after_json'=>json_encode([
            'parts_total'=>1941.14,'labour_total'=>1313.55,'gst_total'=>585.84,
            'pre_round_total'=>3840.53,'round_off'=>0.47,'grand_total'=>3841,
            'invoice_balance'=>3841,'ledger_debit'=>3841,
        ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        'reason'=>'FIX49 audit: taxable line totals plus 18 percent itemwise GST; unpaid invoice corrected before delivery, original number retained.',
        'actor_id'=>null,'actor_name'=>'SYSTEM:FIX49_STAGING_RECONCILIATION',
        'created_at'=>$now,'updated_at'=>$now,
    ]);
    $afterInv=DB::table('invoices')->where('id',23)->first();
    $afterDebit=DB::table('customer_ledger')->where('id',$debit->id)->first();
    if(!sameAmount($afterInv->grand_total,3841)||!sameAmount($afterInv->gst_total,585.84)
       ||!sameAmount($afterDebit->debit,3841))throw new RuntimeException('Post-update verification failed; transaction rolled back.');
    echo "FIX49_INVOICE23_CORRECTION_COMMITTED=PASS GRAND=3841.00 GST=585.84 LEDGER=3841.00\n";
},3);
