<?php

declare(strict_types=1);

$root=rtrim((string)($argv[1]??''),'/');
if($root==='' || !is_file($root.'/artisan')){
    fwrite(STDERR,"Usage: php PATCH_FIX46.php /path/to/laravel/app\n");
    exit(2);
}

$routeFile=$root.'/routes/web.php';
$showFile=$root.'/resources/views/job-cards/show.blade.php';

$routes=file_get_contents($routeFile);
$show=file_get_contents($showFile);
if($routes===false || $show===false) throw new RuntimeException('Cannot read target source.');

$import="use App\\Http\\Controllers\\Web\\AdminJobCardLifecyclePageController;";
if(!str_contains($routes,$import)){
    $anchor="use App\\Http\\Controllers\\Web\\AdminJobCardPageController;";
    if(substr_count($routes,$anchor)!==1) throw new RuntimeException('Controller import anchor mismatch.');
    $routes=str_replace($anchor,$anchor."\n".$import,$routes);
}

$routeMarker="// FIX46_WEB_JOBCARD_LIFECYCLE_INVOICE";
if(!str_contains($routes,$routeMarker)){
    $anchor="    Route::post('/job-cards/{job}/labour/{labour}/assign-mechanic', [AdminJobCardPageController::class,'assignMechanic'])->whereNumber('job')->whereNumber('labour')->name('erp.job-cards.labour.assign');";
    if(substr_count($routes,$anchor)!==1) throw new RuntimeException('Job Card route anchor mismatch.');

    $block=<<<'PHPROUTES'

    // FIX46_WEB_JOBCARD_LIFECYCLE_INVOICE — controlled web workflow + customer invoice.
    Route::post('/job-cards/{job}/lifecycle/wip', [AdminJobCardLifecyclePageController::class,'markWip'])->whereNumber('job')->name('erp.job-cards.lifecycle.wip');
    Route::post('/job-cards/{job}/lifecycle/work-complete', [AdminJobCardLifecyclePageController::class,'markWorkComplete'])->whereNumber('job')->name('erp.job-cards.lifecycle.work-complete');
    Route::post('/job-cards/{job}/lifecycle/qc-pending', [AdminJobCardLifecyclePageController::class,'qcPending'])->whereNumber('job')->name('erp.job-cards.lifecycle.qc-pending');
    Route::post('/job-cards/{job}/lifecycle/qc-result', [AdminJobCardLifecyclePageController::class,'qcResult'])->whereNumber('job')->name('erp.job-cards.lifecycle.qc-result');
    Route::post('/job-cards/{job}/lifecycle/ready-for-delivery', [AdminJobCardLifecyclePageController::class,'readyForDelivery'])->whereNumber('job')->name('erp.job-cards.lifecycle.ready');
    Route::post('/job-cards/{job}/lifecycle/close', [AdminJobCardLifecyclePageController::class,'closeJob'])->whereNumber('job')->name('erp.job-cards.lifecycle.close');
    Route::post('/job-cards/{job}/lifecycle/reopen', [AdminJobCardLifecyclePageController::class,'reopenClosed'])->whereNumber('job')->name('erp.job-cards.lifecycle.reopen');
    Route::post('/job-cards/{job}/invoice', [AdminJobCardLifecyclePageController::class,'createInvoice'])->whereNumber('job')->name('erp.job-cards.invoice.create');
    Route::get('/invoices/{invoice}', [AdminJobCardLifecyclePageController::class,'showInvoice'])->whereNumber('invoice')->name('erp.invoices.show');
    Route::get('/invoices/{invoice}/print', [AdminJobCardLifecyclePageController::class,'printInvoice'])->whereNumber('invoice')->name('erp.invoices.print');
PHPROUTES;
    $routes=str_replace($anchor,$anchor.$block,$routes);
}

$include="@include('job-cards.lifecycle-panel')";
if(!str_contains($show,$include)){
    $anchor="@if(\$latestEstimate && \$latestEstimateStatus==='APPROVED')";
    if(substr_count($show,$anchor)!==1) throw new RuntimeException('Job Card show include anchor mismatch.');
    $show=str_replace($anchor,$include."\n\n".$anchor,$show);
}

if(file_put_contents($routeFile,$routes)===false) throw new RuntimeException('Cannot write routes/web.php');
if(file_put_contents($showFile,$show)===false) throw new RuntimeException('Cannot write Job Card show view.');

echo "FIX46 route + Job Card view patch: PASS\n";
