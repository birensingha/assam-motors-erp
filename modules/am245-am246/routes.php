<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AmCustomerEngagementController;

// Standalone module routing. Match the existing ERP admin route conventions during deployment.
Route::middleware(['web','auth','can:manage-customer-engagement'])
    ->prefix('erp/admin/customer-engagement')
    ->name('am.customer-engagement.')
    ->group(function () {
        Route::get('/', [AmCustomerEngagementController::class,'index'])->name('index');
        Route::post('/feedback', [AmCustomerEngagementController::class,'feedback'])->name('feedback');
        Route::post('/reply', [AmCustomerEngagementController::class,'reply'])->name('reply');
        Route::post('/complaint', [AmCustomerEngagementController::class,'complaint'])->name('complaint');
    });

// Separate page and stricter finance permission; no outstanding on Customer Feedback.
Route::middleware(['web','auth','can:manage-customer-engagement','can:view-customer-ledger'])
    ->prefix('erp/admin/customer-messaging')
    ->name('am.customer-messaging.')
    ->group(function () {
        Route::get('/', [AmCustomerEngagementController::class,'messaging'])->name('index');
        Route::get('/customers', [AmCustomerEngagementController::class,'customers'])->middleware('throttle:30,1')->name('customers');
        Route::post('/message', [AmCustomerEngagementController::class,'message'])->name('message');
    });
