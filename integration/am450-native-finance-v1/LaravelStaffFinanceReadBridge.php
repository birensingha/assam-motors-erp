<?php
declare(strict_types=1);

namespace AssamMotors\FinanceV1;

require_once __DIR__ . '/FinanceReadContract.php';
require_once __DIR__ . '/StaffExpenseFinanceProvider.php';

use App\Models\ErpAuthUser;
use App\Services\StaffMultiExpensePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AM460: read-only API bridge for existing Assam Motors Staff Expense ledgers.
 *
 * SAFETY REQUIREMENTS:
 * - register GET routes inside the EXISTING authenticated /api/v1 group.
 * - additionally check the real ERP ErpAuthUser Admin role here.
 * - reject any unmapped payee type/ledger; NEVER return pretend zero values.
 * - use AM245 existing StaffMultiExpensePaymentService::outstandingRows,
 *   which accounts for ACTIVE direct and multi-bill payment allocations.
 *
 * Deployment is a separate, guarded STAGING action; adding this file to
 * the GitHub integration branch does not expose a live Laravel endpoint.
 */
final class LaravelStaffFinanceReadBridge
{
    private static function authorize(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user instanceof ErpAuthUser && $user->active && $user->isAdmin(),
            403,
            'Authenticated ERP Admin permission required'
        );
    }

    private static function provider(): StaffExpenseFinanceProvider
    {
        return new StaffExpenseFinanceProvider(
            static function (int $limit): array {
                // No cross-account fallback. Never fabricate missing staff.
                $rows = DB::table('staff')
                    ->where('status', 'active')
                    ->orderBy('name')->orderBy('id')
                    ->limit($limit + 1)
                    ->get(['id', 'name']);
                return $rows->map(static fn($r) => [
                    'id' => (string)$r->id,
                    'name' => (string)$r->name,
                ])->all();
            },
            static function (string $staffId, int $limit): array {
                // The same staff master and active status as ERP voucher posting.
                $valid = DB::table('staff')->where('id', (int)$staffId)
                    ->where('status', 'active')->exists();
                if (!$valid) {
                    throw new FinanceContractFailure('Staff payee inactive or missing');
                }
                if (!class_exists(StaffMultiExpensePaymentService::class)) {
                    throw new FinanceContractFailure(
                        'AM245 audited Staff Expense service not installed'
                    );
                }
                // AM245 source caps at 2,000; refuse any partial/incomplete
                // ledger beyond that cap rather than showing a false balance.
                $total = DB::table('workshop_expenses')
                    ->where('record_status', 'ACTIVE')
                    ->where('staff_id', (int)$staffId)
                    ->count();
                if ($total >= 2000) {
                    throw new FinanceContractFailure(
                        'Staff expense history exceeds reconciled source cap'
                    );
                }
                $data = (new StaffMultiExpensePaymentService())
                    ->outstandingRows((int)$staffId);
                if (count($data) > $limit) {
                    throw new FinanceContractFailure(
                        'Outstanding Staff expenses exceed native limit'
                    );
                }
                return $data;
            }
        );
    }

    private static function blocked(FinanceContractFailure $e): JsonResponse
    {
        // Never echo Staff names, voucher totals or DB exception details.
        return response()->json([
            'success' => false,
            'error' => 'FINANCE_READ_CONTRACT_UNAVAILABLE',
            'message' => 'Finance data cannot be safely verified for mobile.',
        ], 409);
    }

    public static function payees(Request $request): JsonResponse
    {
        self::authorize($request);
        try {
            $type = (string)$request->query('type', '');
            if (strlen($type) > 20) {
                throw new FinanceContractFailure('Invalid finance payee type');
            }
            return response()->json(
                (new FinanceReadContract(self::provider()))
                    ->payees(true, $type)
            );
        } catch (FinanceContractFailure $e) {
            return self::blocked($e);
        }
    }

    public static function outstanding(Request $request): JsonResponse
    {
        self::authorize($request);
        try {
            $type = (string)$request->query('payee_type', '');
            $payeeId = (string)$request->query('payee_id', '');
            if (strlen($type) > 20 || strlen($payeeId) > 30) {
                throw new FinanceContractFailure('Invalid finance reference request');
            }
            return response()->json(
                (new FinanceReadContract(self::provider()))
                    ->outstanding(true, $type, $payeeId)
            );
        } catch (FinanceContractFailure $e) {
            return self::blocked($e);
        }
    }
}
