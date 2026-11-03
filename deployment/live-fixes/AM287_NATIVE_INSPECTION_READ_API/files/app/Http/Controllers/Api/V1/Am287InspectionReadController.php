<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AM287 — staged, READ-ONLY native Admin inspection contracts.
 *
 * These methods do not create inspection records, assign Staff, publish
 * masters, update repairs or modify QC. They are intentionally NOT routed by
 * this package; a guarded, verified route installation is a separate action.
 *
 * Depends on existing AM189 and AM206 staging patches after their migrations.
 */
final class Am287InspectionReadController extends Controller
{
    private function admin(Request $request): ErpAuthUser
    {
        $user = $request->user();
        abort_unless(
            $user instanceof ErpAuthUser && $user->active && $user->isAdmin(),
            403,
            'Active ERP Owner/Admin token required.'
        );
        return $user;
    }

    /** Preview of published AM206 master. NOT a dynamic Staff submit contract. */
    public function master(Request $request): JsonResponse
    {
        $this->admin($request);
        $class = \App\Services\Am206InspectionMaster::class;
        if (!class_exists($class)) {
            return response()->json([
                'ok' => false,
                'message' => 'AM206 Inspection Master not installed on staging.',
            ], 503);
        }
        if (!Schema::hasTable('am206_inspection_master_versions')) {
            return response()->json([
                'ok' => false,
                'message' => 'AM206 migration pending; no dynamic inspection allowed.',
            ], 503);
        }
        $master = app($class)->active();
        $version = $master['version'] ?? null;
        $points = $master['points'] ?? [];
        if ($version === null || !is_array($points)) {
            return response()->json([
                'ok' => false,
                'message' => 'No published inspection master version; legacy checklist unchanged.',
            ], 409);
        }

        return response()->json([
            'ok' => true,
            'contract' => 'AM287_READONLY_V1',
            'mode' => 'PUBLISHED_MASTER_PREVIEW',
            'version' => (int)$version,
            'points' => $points,
            'dynamic_submit_enabled' => false,
            'assignment_snapshot_enabled' => false,
            'active_staff_submit_contract' => 'LEGACY_FIXED_30_GFP',
            'message' => 'This is a preview only. No typed form submission or staff assignment changed.',
        ]);
    }

    /** Read-only AM189 original findings + audited repair/verification state. */
    public function defects(Request $request, int $job): JsonResponse
    {
        $this->admin($request);
        if ($job <= 0) {
            return response()->json(['ok' => false, 'message' => 'Invalid Job Card ID.'], 422);
        }
        $card = Schema::hasTable('job_cards')
            ? DB::table('job_cards')->where('id', $job)->first()
            : null;
        if (!$card) {
            return response()->json(['ok' => false, 'message' => 'Job Card not found.'], 404);
        }
        foreach ([
            'job_card_inspection',
            'am189_inspection_defects',
            'am189_inspection_defect_audits'
        ] as $table) {
            if (!Schema::hasTable($table)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'AM189 Inspection Defect Guard is not initialized.',
                ], 503);
            }
        }
        $class = \App\Services\InspectionQcGuard::class;
        if (!class_exists($class)) {
            return response()->json([
                'ok' => false,
                'message' => 'AM189 Inspection QC Guard service missing.',
            ], 503);
        }

        // The actual AM189 guard detects unregistered, changed, duplicate and
        // historical findings. Do not reimplement or bypass its QC rules here.
        $findings = app($class)->findings($job);
        $result = [];
        $unresolved = 0;
        foreach ($findings as $row) {
            $record = $row['record'] ?? null;
            $verified = ($row['verified'] ?? false) === true;
            if (!$verified) $unresolved++;
            $result[] = [
                'point_key' => (string)($row['point_key'] ?? ''),
                'label' => (string)($row['label'] ?? ''),
                'original_condition' => (string)($row['condition'] ?? ''),
                'original_observation' => (string)($row['remarks'] ?? ''),
                'severity' => (string)($row['severity'] ?? 'normal'),
                'status' => (string)($row['status'] ?? 'UNREGISTERED'),
                'verified' => $verified,
                'same_original' => ($row['same'] ?? false) === true,
                'defect_id' => $record ? (int)$record->id : null,
                'repair_staff_id' => $record && $record->repair_staff_id
                    ? (int)$record->repair_staff_id : null,
                'repair_note' => $record ? (string)($record->repair_note ?? '') : '',
                'verification_result' => $record
                    ? (string)($record->verification_result ?? '') : '',
                'verification_note' => $record
                    ? (string)($record->verification_note ?? '') : '',
            ];
        }
        return response()->json([
            'ok' => true,
            'contract' => 'AM287_READONLY_V1',
            'job_card_id' => $job,
            'inspection_status' => (string)($card->inspection_status ?? ''),
            'saved_points' => DB::table('job_card_inspection')
                ->where('job_card_id', $job)->count(),
            'defects' => $result,
            'findings_count' => count($result),
            'unresolved_count' => $unresolved,
            'qc_pass_authorized' => false,
            'message' => 'Read-only report. ERP QC PASS guard must be checked again at transaction time.',
        ]);
    }
}
