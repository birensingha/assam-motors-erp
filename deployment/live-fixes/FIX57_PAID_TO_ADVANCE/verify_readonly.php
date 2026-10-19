<?php
/** FIX57 smoke test: read-only; never triggers a paid invoice credit action. */
declare(strict_types=1);
$root=(string)($argv[1]??'');
if(!is_file($root.'/artisan'))exit(2);
chdir($root);require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
foreach(['customer_advance_transfers','customer_advance_applications','invoice_credit_notes'] as $t)
    if(!Schema::hasTable($t))throw new RuntimeException('FIX57 table missing '.$t);
if(!Route::has('erp.job-cards.credit-note.paid'))throw new RuntimeException('FIX57 route missing');
foreach(['resources/views/job-cards/credit-correction.blade.php'] as $path){
    $blade=app('blade.compiler')->compileString(file_get_contents($path));
    token_get_all($blade,TOKEN_PARSE);
}
foreach(['App\\Services\\PaidInvoiceAdvanceService',
         'App\\Http\\Controllers\\Web\\AdminPaidInvoiceAdvancePageController'] as $cls)
    if(!class_exists($cls))throw new RuntimeException('FIX57 class not autoloaded '.$cls);
$inv=DB::table('invoices')->find(23);
$job=DB::table('job_cards')->find(39);
$p=DB::table('customer_payments')->find(11);
$a=DB::table('customer_payment_allocations')->find(15);
if(!$inv||!$job||!$p||!$a ||
   strtolower((string)$inv->status)!=='paid' ||
   abs((float)$inv->grand_total-3500)>0.01 ||
   abs((float)$inv->paid_amount-3500)>0.01 ||
   (int)$p->invoice_id!==23 ||
   (string)$p->receipt_no!=='RCPT-2026-00011' ||
   abs((float)$p->allocated_amount-3500)>0.01 ||
   strtolower((string)$a->status)!=='posted' ||
   DB::table('invoice_credit_notes')->where('invoice_id',23)->exists() ||
   DB::table('customer_advance_transfers')->where('original_invoice_id',23)->exists())
    throw new RuntimeException('FIX57 original paid receipt fixture changed; no financial posting permitted by smoke.');
app('view')->share('errors',new Illuminate\Support\ViewErrorBag());
$card=$job;
$invoices=DB::table('invoices')->where('job_card_id',39)->orderByDesc('id')->get();
$notes=DB::table('invoice_credit_notes')->where('job_card_id',39)->orderByDesc('id')->get();
$sessions=DB::table('rot_sessions')->where('job_card_id',39)->get();
$html=view('job-cards.credit-correction',compact('card','invoices','notes','sessions'))->render();
if(!str_contains($html,'Full Credit Note + Transfer Receipt to Advance'))
    throw new RuntimeException('FIX57 paid finance confirmation action missing');
if(str_contains($html,'Issue Full Credit Note (Unpaid Invoice Only)'))
    throw new RuntimeException('FIX57 unsafe unpaid posting button shown');
echo "FIX57_UI_PAID_INVOICE23_GUARD_PASS\n";
$job=$card;$parts=DB::table('job_card_parts')->where('job_card_id',39)->get();
$labour=DB::table('job_card_labour')->where('job_card_id',39)->where('billing_included',1)->get();
$ledger=DB::table('customer_ledger')->where('invoice_id',23)->get();
$printed=view('invoices.print',compact('inv','job','parts','labour','ledger'))->render();
if(!str_contains($printed,'3,500.00'))throw new RuntimeException('Original print changed');
echo "FIX57_INVOICE23_UNCHANGED_3500\n";
echo "FIX57_STAGING_SOURCE_DEPLOY_VERIFIED_NO_ACCOUNTING_WRITES\n";
