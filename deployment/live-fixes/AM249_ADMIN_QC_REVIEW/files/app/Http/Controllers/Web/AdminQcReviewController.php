<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
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
    /**
     * AM249: Admin must be linked to the exact assigned Staff ID.
     * Do not impersonate staff_web or change the original QC assignment.
     * Delegates the result to the pre-existing Admin QC handler and its guards.
     */
    public function submit(Request $request, int $job): RedirectResponse
    {
        $admin = $request->user();
        abort_unless(
            $admin instanceof ErpAuthUser && $admin->active && $admin->isAdmin(),
            403, 'Active ERP Admin login required.'
        );

        $data = $request->validate([
            'result' => ['required','in:PASS,FAIL'],
            'expected_qc_id' => ['required','integer','min:1'],
            'checked_physical_vehicle' => ['required','accepted'],
            'notes' => ['required','string','min:8','max:3000'],
            'reason' => ['required','string','min:8','max:1000'],
            'failed_item' => ['required_if:result,FAIL','nullable','string','max:2000'],
        ]);

        return DB::transaction(function () use ($request, $job, $admin, $data): RedirectResponse {
            $card = DB::table('job_cards')
                ->where('id',$job)->lockForUpdate()->first();
            abort_unless($card, 404);

            $current = DB::table('workshop_qc_checks')
                ->where('job_card_id',$job)
                ->orderByDesc('id')
                ->lockForUpdate()->first();
            abort_unless($current, 404, 'No active QC record.');

            if ((int)$current->id !== (int)$data['expected_qc_id']
                || strtoupper(trim((string)$current->result)) !== 'PENDING') {
                throw ValidationException::withMessages([
                    'qc' => 'QC assignment/status changed since you opened the page. Reload before saving.'
                ]);
            }

            $assignedStaffId = (int)($current->assigned_staff_id ?? 0);
            $adminStaffId = (int)($admin->staff_id ?? 0);
            if (!$assignedStaffId || $assignedStaffId !== $adminStaffId) {
                abort(403,'This QC is assigned to another Staff ID. No Admin impersonation allowed.');
            }
            $assignedStaff = DB::table('staff')->where('id',$assignedStaffId)->first();
            abort_unless(
                $assignedStaff &&
                strtolower(trim((string)($assignedStaff->status ?? 'active'))) === 'active',
                403,'Assigned QC staff is not active.'
            );

            if (strtolower(trim((string)$card->status)) !== 'completed'
                || !str_contains(strtolower((string)$card->customer_public_status),'quality check')) {
                throw ValidationException::withMessages([
                    'qc' => 'Job Card must be COMPLETED and at QUALITY CHECK stage.'
                ]);
            }

            // Prevent client-supplied staff attribution: preserve QC-assigned ID.
            $request->merge(['assigned_staff_id'=>$assignedStaffId]);

            // Existing Admin QC handler is the canonical, independently audited
            // PASS/FAIL writer with all defect and roadworthiness gates.
            // No direct DB write of PASS/FAIL is performed here.
            return app(AdminJobCardLifecyclePageController::class)->qcResult($request,$job);
        });
    }

}
