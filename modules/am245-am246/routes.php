<?php
// Include once inside your ERP's existing verified admin route group.
// IMPORTANT: Route authentication is intentionally strict; do not expose publicly.
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AmCustomerEngagementController;

Route::middleware(['web','auth','can:manage-customer-engagement'])
    ->prefix('erp/admin/customer-engagement')
    ->name('am.customer-engagement.')
    ->group(function () {
        Route::get('/', [AmCustomerEngagementController::class,'index'])->name('index');
        Route::post('/feedback', [AmCustomerEngagementController::class,'feedback'])->name('feedback');
        Route::post('/reply', [AmCustomerEngagementController::class,'reply'])->name('reply');
        Route::post('/complaint', [AmCustomerEngagementController::class,'complaint'])->name('complaint');
        Route::post('/message', [AmCustomerEngagementController::class,'message'])->name('message');
    });
