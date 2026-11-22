<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AmFeedbackInvitationController;

// AM245 ONLY: opt-in extension to feedback-only-routes.php.
// Add only after real ERP source adapter, Owner/Admin gate and HTTPS are verified.
// AM246 finance messaging routes are NOT involved.
Route::middleware(['web', 'auth', 'can:manage-customer-engagement'])
    ->prefix('erp/admin/customer-engagement')
    ->name('am.feedback.invitations.')
    ->group(function () {
        Route::post('/invite', [AmFeedbackInvitationController::class, 'openWhatsApp'])
            ->middleware('throttle:10,1')->name('open');
    });

// Public customer survey uses opaque token only. No Admin session or finance access.
// The public form retains web CSRF protection and independent rate limits.
Route::middleware(['web'])->prefix('erp/feedback')
    ->name('am.feedback.public.')
    ->group(function () {
        Route::get('/{token}', [AmFeedbackInvitationController::class, 'publicForm'])
            ->where('token', '[a-f0-9]{64}')->middleware('throttle:20,1')->name('show');
        Route::post('/{token}', [AmFeedbackInvitationController::class, 'publicSubmit'])
            ->where('token', '[a-f0-9]{64}')->middleware('throttle:5,1')->name('submit');
    });
