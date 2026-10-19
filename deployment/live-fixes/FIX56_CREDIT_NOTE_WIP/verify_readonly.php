<?php
/** FIX56 read-only test after deployment. Never posts a credit note. */
declare(strict_types=1);
$root=(string)($argv[1]??'');
if(!is_file($root.'/artisan'))exit(2);
chdir($root);
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=Illuminate\Support\Facades\DB::class;
$sc=Illuminate\Support\Facades\Schema::class;
$route=Illuminate\Support\Facades\Route::class;
if(!$sc::hasTable('invoice_credit_notes'))throw new RuntimeException('Credit note table missing');
foreach(['invoice_id','credit_note_no','original_snapshot','credit_amount'] as $field)
    if(!$sc::hasColumn('invoice_credit_notes',$field))throw new RuntimeException('Credit note field missing: '.$field);
foreach(['erp.job-cards.credit-correction','erp.job-cards.credit-note.post','erp.invoice-credit-note.print'] as $name)
    if(!$route::has($name))throw new RuntimeException('Route missing: '.$name);
echo "FIX56_ROUTES_SCHEMA_PASS\n";
$blades=[
  resource_path('views/job-cards/credit-correction.blade.php'),
  resource_path('views/invoices/credit-note.blade.php'),
  resource_path('views/job-cards/show.blade.php')
];
foreach($blades as $file){
    $compiled=app('blade.compiler')->compileString(file_get_contents($file));
    token_get_all($compiled,TOKEN_PARSE);
}
echo "FIX56_BLADE_PARSE_PASS\n";
app('view')->share('errors',new Illuminate\Support\ViewErrorBag());
$card=$db::table('job_cards')->where('id',39)->first();
$inv=$db::table('invoices')->where('id',23)->first();
if(!$card||!$inv)throw new RuntimeException('Safety fixture missing');
if(abs((float)$inv->grand_total-3500)>0.01 ||
   strtolower((string)$inv->status)!=='paid' ||
   abs((float)$inv->paid_amount-3500)>0.01)
   throw new RuntimeException('Invoice 23 changed since paid fixture');
$invoices=$db::table('invoices')->where('job_card_id',39)->orderByDesc('id')->get();
$notes=$db::table('invoice_credit_notes')->where('job_card_id',39)->get();
$sessions=$db::table('rot_sessions')->where('job_card_id',39)->get();
$html=view('job-cards.credit-correction',compact('card','invoices','notes','sessions'))->render();
if(!str_contains($html,'Payment linked')||
   str_contains($html,'Issue Full Credit Note (Unpaid Invoice Only)'))
   throw new RuntimeException('Paid invoice safeguard failed');
if($notes->count()>0)throw new RuntimeException('Test fixture must not have an issued credit note');
echo "FIX56_PAID_INVOICE23_POSTING_DISABLED_RENDER_PASS\n";
$job=$card;
$parts=$db::table('job_card_parts')->where('job_card_id',39)->orderBy('id')->get();
$labour=$db::table('job_card_labour')->where('job_card_id',39)->where('billing_included',1)->orderBy('id')->get();
$ledger=$db::table('customer_ledger')->where('invoice_id',23)->get();
$printed=view('invoices.print',compact('inv','job','parts','labour','ledger'))->render();
if(!str_contains($printed,'3,500.00'))throw new RuntimeException('Invoice 23 print amount changed');
echo "FIX56_OLD_INVOICE23_UNCHANGED_3500_PRINT_PASS\n";
