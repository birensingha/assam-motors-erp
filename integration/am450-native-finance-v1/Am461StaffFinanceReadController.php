<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AM461: Laravel PSR-4 route handler. The read-side contract stays isolated
 * in app/Services/NativeFinanceV1 until the FINANCE v1 service is activated.
 *
 * No POST, money mutation or assumptions about outstanding Vendor balances.
 */
final class Am461StaffFinanceReadController extends Controller
{
    private function loadBridge(): void
    {
        $dir = app_path('Services/NativeFinanceV1');
        foreach ([
            'FinanceReadContract.php',
            'StaffExpenseFinanceProvider.php',
            'LaravelStaffFinanceReadBridge.php',
        ] as $file) {
            if (!is_file($dir.'/'.$file)) {
                abort(503, 'Required Staff Finance read service not installed');
            }
            require_once $dir.'/'.$file;
        }
    }

    public function payees(Request $request): JsonResponse
    {
        $this->loadBridge();
        return \AssamMotors\FinanceV1\LaravelStaffFinanceReadBridge::payees($request);
    }

    public function outstanding(Request $request): JsonResponse
    {
        $this->loadBridge();
        return \AssamMotors\FinanceV1\LaravelStaffFinanceReadBridge::outstanding($request);
    }

    public function masterItems(Request $request): JsonResponse
    {
        $this->loadBridge();
        return \AssamMotors\FinanceV1\LaravelStaffFinanceReadBridge::masterItems($request);
    }
}
