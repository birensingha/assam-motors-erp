<?php
declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class InspectionDefectService
{
    /**
     * Must be called INSIDE the existing successful inspection-submit transaction.
     * $validated comes only from InspectionMasterService::validateSubmission.
     * No repair is inferred from a later Good answer: explicit verification required.
     */
    public function recordFindings(int $jobId, array $validated, string $inspector): int
    {
        $this->requireTables();
        $count = 0;
        foreach ($validated as $item) {
            if (empty($item['defect'])) continue;
            $key = (string)$item['key'];
            $existing = DB::table('am_inspection_defects')->where('job_card_id',$jobId)
                ->where('point_key',$key)->lockForUpdate()->first();
            if ($existing) {
                // Never erase original defect; a repeat submit is handled by
                // submission idempotency upstream, not destructive upsert.
                continue;
            }
            $now = now();
            $id = DB::table('am_inspection_defects')->insertGetId([
                'job_card_id'=>$jobId,'point_key'=>$key,'point_label'=>(string)$item['label'],
                'original_answer'=>(string)$item['value'],
                'original_observation'=>(string)$item['remarks'],
                'severity'=>!empty($item['critical'])?'safety':'normal',
                'status'=>'OPEN','created_at'=>$now,'updated_at'=>$now,
            ]);
            $this->audit($id,$jobId,'FOUND',$inspector,null,[
                'point_key'=>$key,'answer'=>$item['value'],'remarks'=>$item['remarks'],
                'status'=>'OPEN','severity'=>!empty($item['critical'])?'safety':'normal',
            ]);
            $count++;
        }
        return $count;
    }

    public function rectify(int $defectId, string $staff, string $repairNote): void
    {
        if (mb_strlen(trim($repairNote))<8) throw ValidationException::withMessages([
            'repair_note'=>'Repair details require at least 8 characters.'
        ]);
        DB::transaction(function () use ($defectId,$staff,$repairNote): void {
            $row = DB::table('am_inspection_defects')->where('id',$defectId)->lockForUpdate()->first();
            if (!$row) abort(404);
            if ($row->status!=='OPEN') throw ValidationException::withMessages([
                'defect'=>'Only OPEN defects can be marked rectified.'
            ]);
            DB::table('am_inspection_defects')->where('id',$defectId)->update([
                'status'=>'RECTIFIED','repair_note'=>trim($repairNote),
                'repaired_by'=>$staff,'repaired_at'=>now(),'updated_at'=>now(),
            ]);
            $this->audit($defectId,(int)$row->job_card_id,'RECTIFIED',$staff,$row,[
                'status'=>'RECTIFIED','repair_note'=>trim($repairNote),
            ]);
        });
    }

    public function verify(int $defectId, string $inspector, string $note, bool $passed): void
    {
        if (mb_strlen(trim($note))<8) throw ValidationException::withMessages([
            'verification_note'=>'Re-inspection observations require at least 8 characters.'
        ]);
        DB::transaction(function () use ($defectId,$inspector,$note,$passed): void {
            $row = DB::table('am_inspection_defects')->where('id',$defectId)->lockForUpdate()->first();
            if (!$row) abort(404);
            if ($row->status!=='RECTIFIED') throw ValidationException::withMessages([
                'defect'=>'Repair must be recorded before re-inspection verification.'
            ]);
            // Maker/checker separation: same staff member cannot both fix and
            // independently verify a safety defect.
            if ($row->severity==='safety' && (string)$row->repaired_by===$inspector) {
                throw ValidationException::withMessages([
                    'defect'=>'A different authorized inspector must verify safety repairs.'
                ]);
            }
            $status = $passed?'VERIFIED':'OPEN';
            DB::table('am_inspection_defects')->where('id',$defectId)->update([
                'status'=>$status,'verification_note'=>trim($note),
                'verified_by'=>$inspector,'verified_at'=>now(),'updated_at'=>now(),
            ]);
            $this->audit($defectId,(int)$row->job_card_id,$passed?'VERIFIED':'REOPENED',$inspector,$row,[
                'status'=>$status,'verification_note'=>trim($note),'passed'=>$passed,
            ]);
        });
    }

    public function unresolved(int $jobId): array
    {
        $this->requireTables();
        return DB::table('am_inspection_defects')->where('job_card_id',$jobId)
            ->where('status','!=','VERIFIED')->orderBy('id')->get()->all();
    }

    /**
     * MUST be wired into the server-side QC PASS method within the same
     * transaction/Job Card lock. UI-only disabling is not sufficient.
     */
    public function assertQcReady(int $jobId): void
    {
        $open = $this->unresolved($jobId);
        if (!$open) return;
        $labels = array_map(static fn ($d) => $d->point_label.' ('.$d->status.')',array_slice($open,0,8));
        throw ValidationException::withMessages([
            'qc'=>'QC PASS blocked: '.count($open).' inspection defect(s) require repair and verified re-inspection: '.implode('; ',$labels),
        ]);
    }

    private function audit(int $id, int $job, string $action, string $actor, $before, array $after): void
    {
        DB::table('am_inspection_defect_audits')->insert([
            'defect_id'=>$id,'job_card_id'=>$job,'action'=>$action,'actor'=>$actor,
            'before_json'=>$before===null?null:json_encode($before,JSON_THROW_ON_ERROR),
            'after_json'=>json_encode($after,JSON_THROW_ON_ERROR),
            'created_at'=>now(),
        ]);
    }

    private function requireTables(): void
    {
        if (!Schema::hasTable('am_inspection_defects') || !Schema::hasTable('am_inspection_defect_audits')) {
            abort(503,'Inspection defect tracking migration missing. Do not allow QC PASS to bypass required verification.');
        }
    }
}
