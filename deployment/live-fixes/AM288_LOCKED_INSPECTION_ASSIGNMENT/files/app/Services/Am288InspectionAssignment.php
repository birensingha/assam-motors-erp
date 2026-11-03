<?php
declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * AM288 STAGING PROPOSAL. Atomic per-Job-Card checklist configuration and
 * assignment. Not connected to live routes; no old 30-point data modified.
 */
final class Am288InspectionAssignment
{
    private function ready(): void
    {
        foreach (['am206_inspection_master_versions', 'am206_job_inspection_forms',
                   'job_cards', 'staff', 'job_card_inspection',
                   'am288_inspection_assignment_audits'] as $table) {
            if (!Schema::hasTable($table)) {
                abort(503, "AM288 dependency missing: {$table}");
            }
        }
    }

    public function configure(
        int $jobId, int $adminId, string $mode, ?array $customPoints, string $reason
    ): array {
        $this->ready();
        if ($jobId < 1 || $adminId < 1) abort(422, 'Valid Job Card and Admin required.');
        if (!in_array($mode, ['STANDARD', 'CUSTOM'], true)) {
            throw ValidationException::withMessages([
                'mode' => 'Expected STANDARD or CUSTOM.',
            ]);
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 8 || mb_strlen($reason) > 1000)
            throw ValidationException::withMessages(['reason' => 'Audit reason must be 8-1000 characters.']);
        return DB::transaction(function () use ($jobId, $adminId, $mode, $customPoints, $reason): array {
            $job = DB::table('job_cards')->where('id', $jobId)->lockForUpdate()->first();
            if (!$job) abort(404, 'Job Card not found.');
            $this->assertCanConfigure($job, $jobId);
            $existing = DB::table('am206_job_inspection_forms')
                ->where('job_card_id', $jobId)->lockForUpdate()->first();
            if ($existing && $existing->state !== 'DRAFT') {
                throw ValidationException::withMessages([
                    'inspection' => 'Assigned forms are immutable. Use audited Admin reversal.',
                ]);
            }
            $master = app(Am206InspectionMaster::class)->active();
            if (($master['version'] ?? null) === null) {
                abort(409, 'No published AM206 master; legacy inspection remains unchanged.');
            }
            $points = $mode === 'CUSTOM'
                ? app(Am206InspectionMaster::class)->validateDefinition($customPoints ?? [])
                : $master['points'];
            $versionId = DB::table('am206_inspection_master_versions')
                ->where('version', (int)$master['version'])
                ->where('status', 'PUBLISHED')->value('id');
            if (!$versionId) abort(409, 'Published master changed. Reload and review.');
            $data = [
                'master_version_id' => (int)$versionId,
                'source' => $mode,
                'points_json' => json_encode($points, JSON_THROW_ON_ERROR),
                'state' => 'DRAFT',
                'assigned_staff_id' => null,
                'locked_at' => null,
                'configured_by_admin_id' => $adminId,
                'updated_at' => now(),
            ];
            if ($existing) {
                DB::table('am206_job_inspection_forms')
                    ->where('job_card_id', $jobId)->update($data);
            } else {
                $data['job_card_id'] = $jobId;
                $data['created_at'] = now();
                DB::table('am206_job_inspection_forms')->insert($data);
            }
            $this->audit($jobId, $adminId, 'CONFIGURED', [
                'source' => $mode,
                'reason' => $reason,
                'master_version' => (int)$master['version'],
                'points_count' => count($points),
                'old_version_id' => $existing ? (int)$existing->master_version_id : null,
                'new_points_sha256' => hash('sha256', json_encode($points, JSON_THROW_ON_ERROR)),
            ]);
            return [
                'job_card_id' => $jobId, 'state' => 'DRAFT',
                'source' => $mode, 'master_version' => (int)$master['version'],
                'points_count' => count($points),
                'message' => 'Configured only. Not assigned or submitted.',
            ];
        }, 3);
    }

    public function assign(int $jobId, int $adminId, int $staffId, string $reason): array
    {
        $this->ready();
        if ($jobId < 1 || $adminId < 1 || $staffId < 1 ||
            mb_strlen(trim($reason)) < 8 || mb_strlen(trim($reason)) > 1000) {
            throw ValidationException::withMessages([
                'assignment' => 'Valid Job, Staff and audit reason (8 to 1000 characters) required.',
            ]);
        }
        return DB::transaction(function () use ($jobId, $adminId, $staffId, $reason): array {
            $job = DB::table('job_cards')->where('id', $jobId)->lockForUpdate()->first();
            if (!$job) abort(404, 'Job Card not found.');
            $this->assertCanConfigure($job, $jobId);
            $form = DB::table('am206_job_inspection_forms')
                ->where('job_card_id', $jobId)->lockForUpdate()->first();
            if (!$form || $form->state !== 'DRAFT') {
                throw ValidationException::withMessages([
                    'assignment' => 'Create or customize DRAFT form before Direct Send.',
                ]);
            }
            $staff = DB::table('staff')->where('id', $staffId)
                ->whereRaw("LOWER(COALESCE(status,'active'))='active'")->first();
            if (!$staff) {
                throw ValidationException::withMessages([
                    'staff_id' => 'Select an active ERP Staff member.',
                ]);
            }
            // Do NOT modify technician_id: inspection assignee and mechanic
            // are separate responsibilities. A dedicated typed API reads this.
            $now = now();
            DB::table('am206_job_inspection_forms')
                ->where('job_card_id', $jobId)
                ->where('state', 'DRAFT')
                ->update(['state' => 'ASSIGNED', 'assigned_staff_id' => $staffId,
                    'locked_at' => $now, 'updated_at' => $now]);
            $this->audit($jobId, $adminId, 'ASSIGNED', [
                'staff_id' => $staffId, 'reason' => trim($reason),
                'form_master_version_id' => (int)$form->master_version_id,
                'source' => (string)$form->source,
            ]);
            return [
                'job_card_id' => $jobId, 'state' => 'ASSIGNED',
                'assigned_staff_id' => $staffId, 'points_count' =>
                    count(json_decode((string)$form->points_json, true, 512, JSON_THROW_ON_ERROR)),
            ];
        }, 3);
    }

    private function assertCanConfigure(object $job, int $jobId): void
    {
        if (strtolower(trim((string)$job->status)) !== 'open' ||
            strtolower(trim((string)($job->inspection_status ?? ''))) !== 'pending' ||
            !empty($job->invoice_id)) {
            throw ValidationException::withMessages([
                'inspection' => 'Only Open, pending, non-invoiced Job Cards are configurable.',
            ]);
        }
        if (DB::table('job_card_inspection')->where('job_card_id', $jobId)->exists()) {
            throw ValidationException::withMessages([
                'inspection' => 'Existing inspection answers cannot be overwritten.',
            ]);
        }
    }

    private function audit(int $job, int $actor, string $action, array $payload): void
    {
        if (!Schema::hasTable('am288_inspection_assignment_audits')) {
            abort(503, 'AM288 assignment audit migration missing.');
        }
        DB::table('am288_inspection_assignment_audits')->insert([
            'job_card_id' => $job, 'actor_admin_id' => $actor,
            'action' => $action,
            'payload_json' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
