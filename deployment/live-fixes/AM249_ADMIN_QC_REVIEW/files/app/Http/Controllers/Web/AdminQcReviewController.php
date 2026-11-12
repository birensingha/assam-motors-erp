<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * AM249: GET-only Admin QC review. This controller never submits QC.
 * Existing protected POST erp.job-cards.lifecycle.qc-result owns PASS/FAIL,
 * inspection defect checks, independent verification, audit and JC lifecycle.
 */
final class AdminQcReviewController extends Controller
{
    public function show(Request $request, int $job): View
    {
        $admin = $request->user();
        abort_unless(
            $admin instanceof ErpAuthUser && $admin->active && $admin->isAdmin(),
            403,
            'Active ERP Admin login required.'
        );
        abort_unless(Schema::hasTable('workshop_qc_checks'), 503, 'QC register unavailable.');

        $card = DB::table('job_cards')->where('id', $job)->first();
        abort_unless($card, 404, 'Job Card not found.');

        $qc = DB::table('workshop_qc_checks')
            ->where('job_card_id', $job)
            ->orderByDesc('id')
            ->first();

        $assigned = null;
        if ($qc && !empty($qc->assigned_staff_id)) {
            $assigned = DB::table('staff')
                ->where('id', $qc->assigned_staff_id)
                ->first();
        }

        // Admin permissions alone do not assert they are the QC assignee.
        // For this Admin review action, require an actual matching linked
        // staff identity so a different Admin can't silently submit as staff #4.
        $adminStaffId = (int)($admin->staff_id ?? 0);
        $qcStaffId = (int)($qc->assigned_staff_id ?? 0);
        $sameAssignedReviewer = $qcStaffId > 0 && $adminStaffId === $qcStaffId;

        $status = strtolower(trim((string)($card->status ?? '')));
        $publicStatus = strtolower(trim((string)($card->customer_public_status ?? '')));
        $pending = $qc && strtoupper(trim((string)($qc->result ?? ''))) === 'PENDING';
        $stageReady = $status === 'completed'
            && str_contains($publicStatus, 'quality check');
        $canRecord = $pending && $stageReady && $sameAssignedReviewer;

        return view('job-cards.admin-qc-review', [
            'job' => $card,
            'qc' => $qc,
            'assigned' => $assigned,
            'adminStaffId' => $adminStaffId,
            'sameAssignedReviewer' => $sameAssignedReviewer,
            'canRecord' => $canRecord,
        ]);
    }
}
