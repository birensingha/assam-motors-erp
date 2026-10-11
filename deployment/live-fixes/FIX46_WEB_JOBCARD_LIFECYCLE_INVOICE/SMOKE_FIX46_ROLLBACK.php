<?php

declare(strict_types=1);

$root=rtrim((string)($argv[1]??''),'/');
if($root==='' || !is_file($root.'/artisan')){fwrite(STDERR,"Invalid Laravel root\n");exit(2);}
chdir($root);
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Web\AdminJobCardLifecyclePageController;
use App\Models\ErpAuthUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$admin=ErpAuthUser::query()->where('active',1)->get()->first(fn($u)=>$u->isAdmin());
if(!$admin){fwrite(STDERR,"No active Admin for smoke\n");exit(10);}

$seed=DB::table('job_cards')->whereNotNull('customer_id')->where('customer_id','>',0)->orderByDesc('id')->first();
if(!$seed){fwrite(STDERR,"No customer-linked Job Card seed found\n");exit(11);}

function req(ErpAuthUser $admin,array $body=[]): Request {
    $r=Request::create('/','POST',$body);
    $r->setUserResolver(fn()=>$admin);
    return $r;
}
function expectRedirect($response,string $name): void {
    $code=$response->getStatusCode();
    if($code!==302){
        fwrite(STDERR,"$name expected HTTP 302, got $code\n");
        exit(20);
    }
    echo "$name: PASS\n";
}
function assertTrue(bool $ok,string $name): void {
    if(!$ok){fwrite(STDERR,"ASSERT FAIL: $name\n");exit(21);}
    echo "$name: PASS\n";
}

$c=app(AdminJobCardLifecyclePageController::class);

DB::beginTransaction();
try {
    $row=(array)$seed;
    unset($row['id']);
    $row['job_no']='FIX46-SMOKE-'.date('YmdHis');
    $row['status']='open';
    $row['inspection_status']='completed';
    if(array_key_exists('diagnosis_status',$row)) $row['diagnosis_status']='completed';
    if(array_key_exists('decision_status',$row)) $row['decision_status']='completed';
    if(array_key_exists('estimate_allowed',$row)) $row['estimate_allowed']=1;
    if(array_key_exists('technician_decision',$row) && trim((string)$row['technician_decision'])==='') $row['technician_decision']='REPAIR';
    if(array_key_exists('customer_public_status',$row)) $row['customer_public_status']='Inspection / Diagnosis Completed';
    if(array_key_exists('completed_at',$row)) $row['completed_at']=null;
    if(array_key_exists('closed_at',$row)) $row['closed_at']=null;
    if(array_key_exists('cancelled_at',$row)) $row['cancelled_at']=null;
    if(array_key_exists('invoice_id',$row)) $row['invoice_id']=null;
    if(array_key_exists('work_started_at',$row)) $row['work_started_at']=null;
    if(array_key_exists('created_at',$row)) $row['created_at']=now();
    if(array_key_exists('updated_at',$row)) $row['updated_at']=now();

    // Keep this lifecycle smoke financially harmless but non-zero.
    if(array_key_exists('parts_total',$row)) $row['parts_total']=100.00;
    if(array_key_exists('labour_total',$row)) $row['labour_total']=200.00;
    if(array_key_exists('gst_total',$row)) $row['gst_total']=0.00;
    if(array_key_exists('pre_round_total',$row)) $row['pre_round_total']=300.00;
    if(array_key_exists('round_off',$row)) $row['round_off']=0.00;
    if(array_key_exists('grand_total',$row)) $row['grand_total']=300.00;

    $jobId=(int)DB::table('job_cards')->insertGetId($row);
    assertTrue($jobId>0,'temporary-job-created');

    expectRedirect($c->markWip(req($admin,['reason'=>'FIX46 rollback-only WIP test']),$jobId),'wip');
    $j=DB::table('job_cards')->where('id',$jobId)->first();
    assertTrue(strtolower((string)$j->status)==='in-progress','wip-state');

    expectRedirect($c->markWorkComplete(req($admin,['reason'=>'FIX46 rollback-only Work Complete test']),$jobId),'work-complete');
    $j=DB::table('job_cards')->where('id',$jobId)->first();
    assertTrue(strtolower((string)$j->status)==='completed' && str_contains(strtolower((string)$j->customer_public_status),'work complete'),'work-complete-state');

    expectRedirect($c->qcPending(req($admin,[
        'reason'=>'FIX46 rollback-only QC start test',
        'notes'=>'Temporary QC record'
    ]),$jobId),'qc-pending');
    $j=DB::table('job_cards')->where('id',$jobId)->first();
    assertTrue(str_contains(strtolower((string)$j->customer_public_status),'quality check'),'qc-pending-state');

    expectRedirect($c->qcResult(req($admin,[
        'result'=>'PASS',
        'reason'=>'FIX46 rollback-only QC PASS test',
        'notes'=>'Temporary PASS'
    ]),$jobId),'qc-pass');
    $latestQc=DB::table('workshop_qc_checks')->where('job_card_id',$jobId)->orderByDesc('id')->first();
    assertTrue($latestQc && strtoupper((string)$latestQc->result)==='PASS','qc-pass-record');

    expectRedirect($c->readyForDelivery(req($admin,['reason'=>'FIX46 rollback-only Ready for Delivery test']),$jobId),'ready-for-delivery');
    $j=DB::table('job_cards')->where('id',$jobId)->first();
    assertTrue(str_contains(strtolower((string)$j->customer_public_status),'ready for delivery'),'ready-state');

    expectRedirect($c->closeJob(req($admin,['reason'=>'FIX46 rollback-only Close test']),$jobId),'close-job');
    $j=DB::table('job_cards')->where('id',$jobId)->first();
    assertTrue(strtolower((string)$j->status)==='closed' && !empty($j->closed_at),'closed-state');

    expectRedirect($c->createInvoice(req($admin,[
        'invoice_date'=>now('Asia/Kolkata')->toDateString(),
        'reason'=>'FIX46 rollback-only Invoice conversion test'
    ]),$jobId),'create-invoice');

    $invoice=DB::table('invoices')->where('job_card_id',$jobId)->orderByDesc('id')->first();
    assertTrue((bool)$invoice,'invoice-row');
    assertTrue(str_starts_with((string)$invoice->invoice_no,'INV-'),'invoice-number');
    assertTrue(abs((float)$invoice->grand_total-300.00)<0.01,'invoice-total');

    $ledger=DB::table('customer_ledger')->where('invoice_id',$invoice->id)->where('reference_type','INVOICE')->orderByDesc('id')->first();
    assertTrue((bool)$ledger,'ledger-debit-row');
    assertTrue(strtoupper((string)$ledger->entry_type)==='DEBIT' && abs((float)$ledger->debit-300.00)<0.01,'ledger-debit-amount');

    $j=DB::table('job_cards')->where('id',$jobId)->first();
    assertTrue(strtolower((string)$j->status)==='invoiced' && (int)$j->invoice_id===(int)$invoice->id,'job-invoiced-lock');

    $auditActions=DB::table('job_card_change_audits')->where('job_card_id',$jobId)->pluck('action')->map(fn($x)=>strtoupper((string)$x))->all();
    foreach(['WIP_START','WORK_COMPLETE','QC_PENDING','QC_PASS','READY_FOR_DELIVERY','CLOSED','INVOICE_CREATE'] as $required){
        assertTrue(in_array($required,$auditActions,true),'audit-'.$required);
    }

    echo "FIX46 FULL LIFECYCLE MUTATION SMOKE: PASS\n";
} finally {
    DB::rollBack();
}
echo "FIX46 ROLLBACK: PASS (no smoke Job Card / QC / Invoice / Ledger data persisted)\n";
