<?php
/**
 * FIX46 READ-ONLY RECOVERY AUDIT.
 * SELECT statements only. NO migrations, no HTTP POSTs, no invoice or ledger writes.
 * Execute from Laravel application root via: php < AUDIT_FIX46_READONLY.php
 */
declare(strict_types=1);
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$expectedRoutes=[
 'erp.job-cards.lifecycle.wip',
 'erp.job-cards.lifecycle.work-complete',
 'erp.job-cards.lifecycle.qc-pending',
 'erp.job-cards.lifecycle.qc-result',
 'erp.job-cards.lifecycle.ready',
 'erp.job-cards.lifecycle.close',
 'erp.job-cards.lifecycle.reopen',
 'erp.job-cards.invoice.create',
 'erp.invoices.show',
 'erp.invoices.print',
];
$routes=app('router')->getRoutes();
$missing=[];
foreach($expectedRoutes as $route){
    if(!$routes->getByName($route))$missing[]=$route;
}
echo 'ROUTE_COUNT='.count($expectedRoutes)."\n";
echo 'MISSING_ROUTES='.count($missing)."\n";
foreach($missing as $route)echo 'MISSING_ROUTE='.$route."\n";
foreach([
 'app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php',
 'resources/views/job-cards/lifecycle-panel.blade.php',
 'resources/views/invoices/show.blade.php',
 'resources/views/invoices/print.blade.php',
 'resources/views/job-cards/show.blade.php',
 'routes/web.php',
] as $path){
    echo 'SOURCE_HASH '.$path.' '.(is_file($path)?hash_file('sha256',$path):'MISSING')."\n";
}
foreach(['job_cards','invoices','customer_ledger','workshop_qc_checks','job_card_change_audits'] as $table){
    if(!Schema::hasTable($table)) {
        echo 'MISSING_TABLE='.$table."\n";
    } else {
        echo 'TABLE_COUNT '.$table.' '.DB::table($table)->count()."\n";
    }
}
if(!Schema::hasTable('invoices')||!Schema::hasTable('customer_ledger')||!Schema::hasTable('job_cards')){
    echo "FINANCIAL_READONLY_AUDIT=UNAVAILABLE\n";
    exit(21);
}
$invoices=DB::table('invoices')
    ->select('id','job_card_id','grand_total','status')
    ->orderBy('id')->get();
$ledger=DB::table('customer_ledger')
    ->select('invoice_id','entry_type','debit','credit','reference_type','reference_id')
    ->whereNotNull('invoice_id')
    ->get()->groupBy('invoice_id');
$jobs=DB::table('job_cards')->select('id','status','invoice_id')->get()->keyBy('id');
$nonReversedByJob=[];
$missingDebit=0;
$duplicateDebits=0;
$amountMismatch=0;
$unlinkedInvoice=0;
$badJobLinks=0;
foreach($invoices as $invoice){
    $status=strtolower(trim((string)$invoice->status));
    if($invoice->job_card_id && $status!=='reversed'){
        $job=(int)$invoice->job_card_id;
        $nonReversedByJob[$job]=($nonReversedByJob[$job]??0)+1;
    }
    $rows=$ledger->get($invoice->id,collect())->filter(
        fn($x)=>strtoupper((string)$x->entry_type)==='DEBIT'
              && strtoupper((string)($x->reference_type??''))==='INVOICE'
    );
    if($status!=='reversed'){
        if($rows->count()===0)$missingDebit++;
        if($rows->count()>1)$duplicateDebits++;
        if($rows->count()>0 && abs($rows->sum('debit')-(float)$invoice->grand_total)>0.01)$amountMismatch++;
    }
    if(!$invoice->job_card_id || !$jobs->has($invoice->job_card_id))$unlinkedInvoice++;
    if($invoice->job_card_id && $jobs->has($invoice->job_card_id)){
        $j=$jobs->get($invoice->job_card_id);
        if(!empty($j->invoice_id) && (int)$j->invoice_id===(int)$invoice->id
           && strtolower((string)$j->status)!=='invoiced')$badJobLinks++;
    }
}
$doubleInvoiced=0;
foreach($nonReversedByJob as $num)if($num>1)$doubleInvoiced++;
$jobMissingInvoice=0;
foreach($jobs as $job){
    if(strtolower((string)$job->status)==='invoiced'
        && (!($job->invoice_id) || !$invoices->contains('id',$job->invoice_id)))$jobMissingInvoice++;
}
echo 'ACTIVE_INVOICE_DUPLICATE_JOB_GROUPS='.$doubleInvoiced."\n";
echo 'ACTIVE_INVOICE_MISSING_DEBIT='.$missingDebit."\n";
echo 'ACTIVE_INVOICE_DUPLICATE_DEBIT='.$duplicateDebits."\n";
echo 'ACTIVE_INVOICE_LEDGER_AMOUNT_MISMATCH='.$amountMismatch."\n";
echo 'INVOICE_ORPHANED_JOB='.$unlinkedInvoice."\n";
echo 'JOB_INVOICE_LINK_STATUS_MISMATCH='.$badJobLinks."\n";
echo 'INVOICED_JOB_MISSING_INVOICE='.$jobMissingInvoice."\n";
echo "READONLY_AUDIT_FINISHED=PASS\n";
