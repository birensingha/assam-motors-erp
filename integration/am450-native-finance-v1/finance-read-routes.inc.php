<?php
declare(strict_types=1);

/**
 * AM483 — include ONLY inside the already-active, authenticated Laravel
 * API v1 route group, after verifying its exact prefix and middleware.
 *
 * This file deliberately does not add a new auth guard or guess whether
 * routes/api.php has been prefixed by bootstrap/app.php.
 *
 * Protected controller and bridge additionally require an active
 * App\Models\ErpAuthUser with isAdmin() on every request.
 *
 * Example, inside the EXISTING verified v1 authenticated group:
 *     require base_path('app/Services/NativeFinanceV1/finance-read-routes.inc.php');
 *
 * There are NO POST routes or public deployment actions.
 */
use App\Http\Controllers\Api\V1\Am461StaffFinanceReadController;
use Illuminate\Support\Facades\Route;

Route::get('admin/mobile/finance/v1/payees', [Am461StaffFinanceReadController::class, 'payees'])
    ->name('native-finance-v1.payees');
Route::get('admin/mobile/finance/v1/open-references', [Am461StaffFinanceReadController::class, 'outstanding'])
    ->name('native-finance-v1.open-references');
Route::get('admin/mobile/finance/v1/master-items', [Am461StaffFinanceReadController::class, 'masterItems'])
    ->name('native-finance-v1.master-items');
