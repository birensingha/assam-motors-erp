<?php
/** FIX48 guarded route/button installer. Does not run DB migrations. */
declare(strict_types=1);
$root=rtrim((string)($argv[1]??''),'/');
if($root==='' || !is_file($root.'/artisan')) {fwrite(STDERR,"Usage: php PATCH_FIX48.php /path/to/laravel/app\n");exit(2);}
$routesPath=$root.'/routes/web.php';
$jobShow=$root.'/resources/views/job-cards/show.blade.php';
if(!is_file($routesPath)||!is_file($jobShow)) throw new RuntimeException('Missing expected files. Aborted.');
$routes=file_get_contents($routesPath);
$view=file_get_contents($jobShow);
$marker='FIX48_WORKSHOP_DIRECT_CONTROLS';
$paymentIndexPath=$root.'/resources/views/payments/index.blade.php';
if(!is_file($paymentIndexPath))throw new RuntimeException('Payment Voucher index missing; no files changed.');
$paymentIndex=file_get_contents($paymentIndexPath);
if(substr_count($paymentIndex,"@section('content')")!==1)throw new RuntimeException('Payment index insertion marker absent; no files changed.');
if(str_contains($routes,$marker)){echo "FIX48 already installed\n";exit(0);}
$anchorImport='use App\\Http\\Controllers\\Web\\AdminJobCardLifecyclePageController;';
if(substr_count($routes,$anchorImport)!==1) throw new RuntimeException('FIX46 import anchor absent or non-unique; inspect live routes before installing.');
$anchor="    Route::get('/invoices/{invoice}/print', [AdminJobCardLifecyclePageController::class,'printInvoice'])->whereNumber('invoice')->name('erp.invoices.print');";
if(substr_count($routes,$anchor)!==1) throw new RuntimeException('FIX46 route anchor absent or non-unique. Nothing changed.');
$linkAnchor="@include('job-cards.lifecycle-panel')";
if(substr_count($view,$linkAnchor)!==1) throw new RuntimeException('FIX46 Job Card view anchor absent or non-unique. Nothing changed.');
$import='use App\\Http\\Controllers\\Web\\AdminWorkshopControlPageController;';
$paymentImport='use App\\Http\\Controllers\\Web\\AdminPaymentVoucherEditController;';
$block=<<<'ROUTES'

    // FIX48_WORKSHOP_DIRECT_CONTROLS — inside the existing protected ERP Admin route group.
    Route::get('/workshop-tools/job-cards/{job}/billing-edit', [AdminWorkshopControlPageController::class,'jobAmounts'])->whereNumber('job')->name('erp.fix48.job-amounts');
    Route::post('/workshop-tools/job-cards/{job}/billing-edit/{kind}/{line}', [AdminWorkshopControlPageController::class,'updateJobLine'])->whereNumber('job')->where('kind','parts|labour')->whereNumber('line')->name('erp.fix48.job-line');
    Route::get('/workshop-tools/osl/register', [AdminWorkshopControlPageController::class,'oslIndex'])->name('erp.fix48.osl-index');
    Route::get('/workshop-tools/osl/{purchase}/edit', [AdminWorkshopControlPageController::class,'editOsl'])->whereNumber('purchase')->name('erp.fix48.osl-edit');
    Route::post('/workshop-tools/osl/{purchase}/edit', [AdminWorkshopControlPageController::class,'updateOsl'])->whereNumber('purchase')->name('erp.fix48.osl-save');
    Route::get('/workshop-tools/vendor-database', [AdminWorkshopControlPageController::class,'vendorDatabase'])->name('erp.fix48.vendor-database');
ROUTES;
$routes=str_replace($anchorImport,$anchorImport."\n".$import."\n".$paymentImport,$routes);
$paymentRoutes=<<<'PAYMENT_ROUTES'

    // FIX49_PAYMENT_VOUCHER_EDIT — verified amount correction with before/after audit.
    Route::get('/workshop-tools/payment-vouchers', [AdminPaymentVoucherEditController::class,'register'])->name('erp.fix49.payment-register');
    Route::get('/workshop-tools/payment-vouchers/{payment}/edit', [AdminPaymentVoucherEditController::class,'edit'])->whereNumber('payment')->name('erp.fix49.payment-edit');
    Route::post('/workshop-tools/payment-vouchers/{payment}/edit', [AdminPaymentVoucherEditController::class,'update'])->whereNumber('payment')->name('erp.fix49.payment-save');
PAYMENT_ROUTES;
$routes=str_replace($anchor,$anchor.$block.$paymentRoutes,$routes);
$toolbar=<<<'BLADE'

{{-- FIX48_WORKSHOP_DIRECT_CONTROLS: direct Admin access to financial editing and Vendor Database --}}
<div style="display:flex;flex-wrap:wrap;gap:9px;margin:12px 0 16px">
  <a href="{{ route('erp.fix48.job-amounts',$job->id) }}" style="background:#096bb2;color:white;padding:10px 15px;border-radius:9px;font-weight:800;text-decoration:none">✎ Edit Job Card Amount / GST</a>
  <a href="{{ route('erp.fix48.osl-index') }}" style="background:#e2a01f;color:#182d45;padding:10px 15px;border-radius:9px;font-weight:800;text-decoration:none">🛠 Edit OSL Purchases</a>
  <a href="{{ route('erp.fix48.vendor-database') }}" style="background:#118045;color:white;padding:10px 15px;border-radius:9px;font-weight:800;text-decoration:none">🏢 Vendor Database</a>
</div>
BLADE;
$view=str_replace($linkAnchor,$linkAnchor.$toolbar,$view);
$paymentToolbar=<<<'PAYMENT_TOOLBAR'

{{-- FIX49_PAYMENT_VOUCHER_EDIT register navigation --}}
<div style="display:flex;justify-content:flex-end;margin-bottom:14px">
 <a class="btn primary" href="{{ route('erp.fix49.payment-register') }}">✎ Edit Past Payment Vouchers</a>
</div>
PAYMENT_TOOLBAR;
$paymentIndex=str_replace("@section('content')","@section('content')".$paymentToolbar,$paymentIndex);
// All preflight transformations completed; write only now.
if(file_put_contents($routesPath,$routes)===false||file_put_contents($jobShow,$view)===false){
    throw new RuntimeException('Unable to write protected routes/view. Restore backup.');
}
if(file_put_contents($paymentIndexPath,$paymentIndex)===false)throw new RuntimeException('Unable to write voucher index; restore backup.');
echo "FIX49 route + buttons + payment navigation patched.\n";