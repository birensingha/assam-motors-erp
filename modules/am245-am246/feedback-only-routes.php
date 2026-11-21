<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AmCustomerEngagementController;

// AM245 FEEDBACK ONLY — requires real Owner/Admin Gate registration.
// Mount once, after verifying existing route prefixes/middleware in deployed ERP.
// Do not mount AM246 customer-messaging routes until canonical ledger is mapped.
Route::middleware(['web','auth','can:manage-customer-engagement'])
    ->prefix('erp/admin/customer-engagement')
    ->name('am.customer-engagement.')
    ->group(function () {
        Route::get('/', [AmCustomerEngagementController::class,'index'])->name('index');
        Route::get('/export', [AmCustomerEngagementController::class,'exportCsv'])->middleware('throttle:10,1')->name('export');
        Route::get('/targets', [AmCustomerEngagementController::class,'feedbackTargets'])->middleware('throttle:30,1')->name('targets');
        Route::post('/feedback', [AmCustomerEngagementController::class,'feedback'])->name('feedback');
        Route::post('/reply', [AmCustomerEngagementController::class,'reply'])->name('reply');
        Route::post('/complaint', [AmCustomerEngagementController::class,'complaint'])->name('complaint');
    });
