<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use App\Services\Am288InspectionAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AM288 protected Owner/Admin endpoints — staged only.
 * No routes installed by this package; requires existing token auth middleware.
 */
final class Am288InspectionAssignmentController extends Controller
{
    private function admin(Request $request): ErpAuthUser
    {
        $actor = $request->user();
        abort_unless($actor instanceof ErpAuthUser && $actor->active && $actor->isAdmin(),
            403, 'Active ERP Owner/Admin required.');
        return $actor;
    }

    public function preview(Request $request, int $job): JsonResponse
    {
        $this->admin($request);
        abort_unless($job > 0, 404);
        if (!Schema::hasTable('am206_job_inspection_forms')) {
            return response()->json(['ok' => false, 'message' => 'AM206 migration missing'], 503);
        }
        $card = DB::table('job_cards')->where('id', $job)->first();
        abort_unless($card, 404);
        $form = DB::table('am206_job_inspection_forms')
            ->where('job_card_id', $job)->first();
        return response()->json([
            'ok' => true,
            'contract' => 'AM288_LOCKED_INSPECTION_V1',
            'job_card_id' => $job,
            'inspection_status' => (string)($card->inspection_status ?? ''),
            'form' => $form ? [
                'source' => $form->source,
                'state' => $form->state,
                'master_version_id' => $form->master_version_id,
                'assigned_staff_id' => $form->assigned_staff_id,
                'locked_at' => $form->locked_at,
                'points' => json_decode((string)$form->points_json, true, 512, JSON_THROW_ON_ERROR),
            ] : null,
            'typed_submit_enabled' => false,
        ]);
    }

    public function configure(
        Request $request, int $job, Am288InspectionAssignment $service
    ): JsonResponse {
        $admin = $this->admin($request);
        $data = $request->validate([
            'mode' => ['required', 'in:STANDARD,CUSTOM'],
            'points' => ['required_if:mode,CUSTOM', 'nullable', 'array', 'min:1', 'max:100'],
            'reason' => ['required', 'string', 'min:8', 'max:1000'],
        ]);
        // Reason may be recorded by the controller audit integration on rollout;
        // until then configuration is explicitly blocked rather than posting
        // without reason to the audit service.
        if (!Schema::hasTable('am288_inspection_assignment_audits')) {
            return response()->json([
                'ok' => false, 'message' => 'AM288 audit migration missing.',
            ], 503);
        }
        $result = $service->configure($job, (int)$admin->id, (string)$data['mode'],
            $data['points'] ?? null);
        return response()->json(['ok' => true, 'form' => $result], 200);
    }

    public function assign(
        Request $request, int $job, Am288InspectionAssignment $service
    ): JsonResponse {
        $admin = $this->admin($request);
        $data = $request->validate([
            'staff_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:8', 'max:1000'],
        ]);
        $assigned = $service->assign($job, (int)$admin->id, (int)$data['staff_id'],
            (string)$data['reason']);
        return response()->json(['ok' => true, 'assignment' => $assigned]);
    }
}
