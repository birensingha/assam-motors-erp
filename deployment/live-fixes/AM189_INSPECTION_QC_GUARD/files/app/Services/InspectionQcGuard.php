<?php
declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * AM189: compatible with the CURRENT 30-item Good/Fair/Poor ERP inspection.
 * No inspection mutation: QC checks saved rows and a separate audited register.
 * This guard is called only for QC PASS, never for inspection submission.
 */
final class InspectionQcGuard
{
    private function tables(): void
    {
        if (!Schema::hasTable('am189_inspection_defects')
            || !Schema::hasTable('am189_inspection_defect_audits')) {
            abort(503, 'Inspection QC protection is not initialized. Apply approved AM189 migration.');
        }
    }

    private function key(string $label): string
    {
        return sha1(mb_strtolower(trim($label)));
    }

    private function safety(string $label): bool
    {
        return (bool) preg_match('/brak|steer|suspension|tyre|tire|wheel|airbag|seat.?belt|abs|warning.light|headlight|indicator/i',$label);
    }

    /** Read only. A Fair/Poor point is unresolved unless audited as VERIFIED. */
    public function findings(int $jobId): array
    {
        $this->tables();
        $raw = DB::table('job_card_inspection')
            ->where('job_card_id',$jobId)->orderBy('id')->get();
        $records = DB::table('am189_inspection_defects')
            ->where('job_card_id',$jobId)->get()->keyBy('point_key');
        $findings = [];
        $seen = [];
        $currentPoints = [];
        foreach ($raw as $item) {
            $condition = strtolower(trim((string) ($item->status ?? '')));
            $label = trim((string)($item->item_name??''));
            if ($label === '') continue;
            $key = $this->key($label);
            // Track ALL saved answers: changing a registered Fair/Poor point
            // to Good must never silently bypass a previous safety finding.
            $currentPoints[$key][] = $condition;
            if (!in_array($condition,['fair','poor'],true)) continue;
            $record = $records->get($key);
            $remarks = trim((string)($item->remarks??''));
            $same = $record
                && strtolower((string)$record->original_condition)===$condition
                && trim((string)$record->original_observation)===$remarks;
            $duplicate = isset($seen[$key]);
            $seen[$key] = true;
            $verified = $same && !$duplicate && strtoupper((string)$record->status)==='VERIFIED';
            $findings[] = [
                'point_key'=>$key,'label'=>$label,'condition'=>$condition,
                'remarks'=>$remarks,'severity'=>$this->safety($label)?'safety':'normal',
                'record'=>$record,'same'=>$same,'verified'=>$verified,
                'duplicate'=>$duplicate,'status'=>$duplicate?'DUPLICATE':
                    (!$same?'UNREGISTERED_OR_CHANGED':strtoupper((string)$record->status)),
            ];
        }
        // Stale repair records must never be forgotten because a current
        // inspection was edited, removed or replaced after the original issue.
        foreach ($records as $key=>$record) {
            if (isset($seen[$key])) continue;
            // Even a VERIFIED defect remains tied to its original submitted
            // inspection item. If somebody deletes/reclassifies that item,
            // fail closed for QC rather than treating the history as resolved.
            $current = $currentPoints[$key] ?? [];
            $status = $current
                ? 'ORIGINAL_INSPECTION_CHANGED'
                : 'ORIGINAL_INSPECTION_MISSING';
            $findings[]=[
                'point_key'=>$key,'label'=>(string)$record->point_label,
                'condition'=>(string)$record->original_condition,
                'remarks'=>(string)$record->original_observation,
                'severity'=>(string)$record->severity,'record'=>$record,
                'same'=>false,'verified'=>false,'duplicate'=>count($current)>1,
                'status'=>$status,
            ];
        }
        return $findings;
    }

    public function assertQcReady(int $jobId): void
    {
        // QC PASS concerns work performed, not a certification that every advised
        // repair was purchased. Preserve every genuinely refused finding as DECLINED.
        // An unchanged and evidenced refusal is outside the approved repair scope.
        $open = array_values(array_filter(
            $this->findings($jobId),static function(array $f): bool {
                if ($f['verified']) return false;
                if ($f['status']!=='DECLINED' || !$f['same'] || $f['duplicate']) return true;
                $record=$f['record'];
                if (!$record || trim((string)($record->decline_reason??''))==='') return true;
                $ack=(string)($record->decline_acknowledgement??'');
                return !in_array($ack,['VERBAL','WRITTEN','REFUSED_TO_SIGN'],true);
            }
        ));
        if (!$open) return;
        $examples=array_map(
            static fn(array $f): string => $f['label'].' ('.$f['status'].')',
            array_slice($open,0,5)
        );
        throw ValidationException::withMessages([
            'qc'=>'QC PASS blocked: '.count($open)
                .' inspection defect(s) need recorded repair and verified re-inspection. '
                .implode('; ',$examples)
                .'. Owner/Admin: open Job Card Inspection Defects register.',
        ]);
    }

    /**
     * Vehicle release is distinct from workmanship QC. Until an explicit
     * risk/roadworthiness decision is integrated, never silently deliver
     * a vehicle with a known, unrepaired declined finding.
     */
    public function assertDeliveryRiskReady(int $jobId): void
    {
        $declined=array_values(array_filter(
            $this->findings($jobId),
            static fn(array $f): bool => $f['status']==='DECLINED'
        ));
        if (!$declined) return;
        $names=array_map(static fn(array $f): string => $f['label'], $declined);
        throw ValidationException::withMessages([
            'delivery'=>'Vehicle delivery HOLD: '.count($declined)
                .' unrepaired customer-declined inspection defect(s) recorded ('
                .implode(', ',$names).'). Approved repair-work QC is not'
                .' an authorization to release an unassessed vehicle.'
                .' Complete an authorised risk/roadworthiness decision first.',
        ]);
    }

    /** Admin action, inside transaction. Registers saved defects only. */
    public function sync(int $jobId, int $actorAdmin): int
    {
        $this->tables();
        return DB::transaction(function () use ($jobId,$actorAdmin): int {
            $card=DB::table('job_cards')->where('id',$jobId)->lockForUpdate()->first();
            if (!$card) abort(404);
            if (strtolower((string)$card->inspection_status)!=='completed') {
                throw ValidationException::withMessages(['inspection'=>'Complete Inspection in ERP before registering defects.']);
            }
            $this->assertEditable($card);
            $count=0;
            foreach ($this->findings($jobId) as $finding) {
                if ($finding['duplicate']) {
                    throw ValidationException::withMessages(['inspection'=>'Duplicate inspection point found: '.$finding['label']]);
                }
                if ($finding['record']) {
                    if (!$finding['same']) {
                        throw ValidationException::withMessages(['inspection'=>'Previously registered inspection point changed. Admin audit review required: '.$finding['label']]);
                    }
                    continue;
                }
                $id=DB::table('am189_inspection_defects')->insertGetId([
                    'job_card_id'=>$jobId,'point_key'=>$finding['point_key'],
                    'point_label'=>$finding['label'],'original_condition'=>$finding['condition'],
                    'original_observation'=>$finding['remarks'],
                    'severity'=>$finding['severity'],'status'=>'OPEN',
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
                $this->audit($id,$jobId,$actorAdmin,'REGISTERED',null,[
                    'status'=>'OPEN','point'=>$finding['label'],
                    'condition'=>$finding['condition'],'remarks'=>$finding['remarks'],
                ]);
                $count++;
            }
            return $count;
        });
    }

    public function repair(int $id, int $actorAdmin, int $repairStaffId, string $reason): int
    {
        $this->tables();
        $reason=trim($reason);
        if (mb_strlen($reason)<8 || mb_strlen($reason)>2000) {
            throw ValidationException::withMessages(['repair_note'=>'Repair details must be 8 to 2000 characters.']);
        }
        return DB::transaction(function () use ($id,$actorAdmin,$repairStaffId,$reason): int {
            $item=DB::table('am189_inspection_defects')->where('id',$id)->first();
            if (!$item) abort(404);
            $card=DB::table('job_cards')->where('id',$item->job_card_id)->lockForUpdate()->first();
            if (!$card) abort(404);
            $this->assertEditable($card);
            $item=DB::table('am189_inspection_defects')->where('id',$id)->lockForUpdate()->first();
            if ($item->status!=='OPEN') throw ValidationException::withMessages(['status'=>'Only OPEN defects can be rectified.']);
            $activeStaff=DB::table('staff')->where('id',$repairStaffId)
                ->whereRaw("LOWER(COALESCE(status,'active'))='active'")->exists();
            if (!$activeStaff) throw ValidationException::withMessages(['repair_staff_id'=>'Select an active repair technician.']);
            DB::table('am189_inspection_defects')->where('id',$id)->update([
                'status'=>'RECTIFIED','repair_staff_id'=>$repairStaffId,
                'repair_admin_id'=>$actorAdmin,'repair_note'=>$reason,
                'repaired_at'=>now(),'updated_at'=>now(),
            ]);
            $this->audit($id,(int)$item->job_card_id,$actorAdmin,'RECTIFIED',$item,[
                'status'=>'RECTIFIED','repair_staff_id'=>$repairStaffId,'repair_note'=>$reason,
            ]);
            return (int)$item->job_card_id;
        });
    }

    public function verify(int $id, int $actorAdmin, int $actorStaffId, bool $pass, string $note, ?int $reviewerStaffId=null, bool $attested=false): int
    {
        $this->tables();
        $note=trim($note);
        if (mb_strlen($note)<8 || mb_strlen($note)>2000) {
            throw ValidationException::withMessages(['verification_note'=>'Re-inspection observation must be 8 to 2000 characters.']);
        }
        return DB::transaction(function () use ($id,$actorAdmin,$actorStaffId,$pass,$note,$reviewerStaffId,$attested): int {
            $original=DB::table('am189_inspection_defects')->where('id',$id)->first();
            if (!$original) abort(404);
            $card=DB::table('job_cards')->where('id',$original->job_card_id)->lockForUpdate()->first();
            if (!$card) abort(404);
            $this->assertEditable($card);
            $item=DB::table('am189_inspection_defects')->where('id',$id)->lockForUpdate()->first();
            if ($item->status!=='RECTIFIED') {
                throw ValidationException::withMessages(['status'=>'Repair must be RECTIFIED before re-inspection.']);
            }
            if ($pass && $item->severity==='safety') {
                if (!$reviewerStaffId || !$attested) {
                    throw ValidationException::withMessages(['reviewer_staff_id'=>'Select the independent reviewer who personally performed the test and confirm the attestation.']);
                }
                if ($reviewerStaffId===(int)$item->repair_staff_id) {
                    throw ValidationException::withMessages(['reviewer_staff_id'=>'Safety reviewer cannot be the repairing technician.']);
                }
                if (!DB::table('staff')->where('id',$reviewerStaffId)
                    ->whereRaw("LOWER(COALESCE(status,'active'))='active'")->exists()) {
                    throw ValidationException::withMessages(['reviewer_staff_id'=>'Select an active, qualified independent reviewer.']);
                }
                if (!Schema::hasColumn('am189_inspection_defects','verified_by_staff_id')) {
                    abort(503,'Independent QC reviewer database migration is required before PASS.');
                }
            }
            $newStatus=$pass?'VERIFIED':'OPEN';
            $updates=[
                'status'=>$newStatus,'verification_result'=>$pass?'PASS':'FAIL',
                'verification_note'=>$note,'verified_by_admin_id'=>$actorAdmin,
                'verified_at'=>now(),'updated_at'=>now(),
            ];
            if (Schema::hasColumn('am189_inspection_defects','verified_by_staff_id')) {
                $updates['verified_by_staff_id']=$pass ? $reviewerStaffId : null;
            }
            DB::table('am189_inspection_defects')->where('id',$id)->update($updates);
            $this->audit($id,(int)$item->job_card_id,$actorAdmin,$pass?'VERIFIED':'REOPENED',$item,[
                'status'=>$newStatus,'result'=>$pass?'PASS':'FAIL','verification_note'=>$note,
                'reviewer_staff_id'=>$pass ? $reviewerStaffId : null,
                'independent_review_attested'=>$pass && $item->severity==='safety' ? $attested : false,
            ]);
            return (int)$item->job_card_id;
        });
    }

    /** Record customer refusal without falsely completing repair or QC. */
    public function decline(int $id, int $adminId, string $reason, string $ack, ?string $evidence): int
    {
        $this->tables();
        $reason=trim($reason);
        $evidence=trim((string)$evidence);
        if (mb_strlen($reason)<12 || mb_strlen($reason)>2000) {
            throw ValidationException::withMessages(['decline_reason'=>'Enter the actual customer refusal details (12–2000 characters).']);
        }
        if (!in_array($ack,['WRITTEN','VERBAL','REFUSED_TO_SIGN','PENDING'],true)) {
            throw ValidationException::withMessages(['decline_acknowledgement'=>'Select a valid acknowledgement status.']);
        }
        if ($ack==='WRITTEN' && $evidence==='') {
            throw ValidationException::withMessages(['decline_evidence_ref'=>'Enter the signed customer acknowledgement reference.']);
        }
        return DB::transaction(function () use ($id,$adminId,$reason,$ack,$evidence): int {
            $item=DB::table('am189_inspection_defects')->where('id',$id)->first();
            if (!$item) abort(404);
            $card=DB::table('job_cards')->where('id',$item->job_card_id)->lockForUpdate()->first();
            if (!$card) abort(404);
            $this->assertEditable($card);
            $item=DB::table('am189_inspection_defects')->where('id',$id)->lockForUpdate()->first();
            if (!in_array($item->status,['OPEN','RECTIFIED'],true)) {
                throw ValidationException::withMessages(['status'=>'Only OPEN or RECTIFIED defects can be marked customer declined.']);
            }
            foreach (['decline_reason','decline_acknowledgement','decline_evidence_ref','declined_by_admin_id','declined_at'] as $column) {
                if (!Schema::hasColumn('am189_inspection_defects',$column)) {
                    abort(503,'Customer decline database migration is required.');
                }
            }
            DB::table('am189_inspection_defects')->where('id',$id)->update([
                'status'=>'DECLINED','decline_reason'=>$reason,
                'decline_acknowledgement'=>$ack,'decline_evidence_ref'=>$evidence ?: null,
                'declined_by_admin_id'=>$adminId,'declined_at'=>now(),'updated_at'=>now(),
            ]);
            $this->audit($id,(int)$item->job_card_id,$adminId,'DECLINED',$item,[
                'status'=>'DECLINED','reason'=>$reason,'acknowledgement'=>$ack,
                'evidence_ref'=>$evidence ?: null,'repaired'=>false,'verified'=>false,
            ]);
            return (int)$item->job_card_id;
        });
    }

    /** Resume the repair workflow after a customer reverses an earlier refusal. */
    public function reopenDeclined(int $id, int $adminId, string $reason): int
    {
        $this->tables();
        $reason=trim($reason);
        if (mb_strlen($reason)<8 || mb_strlen($reason)>2000) {
            throw ValidationException::withMessages(['reopen_reason'=>'Provide a reason (8–2000 characters).']);
        }
        return DB::transaction(function () use ($id,$adminId,$reason): int {
            $item=DB::table('am189_inspection_defects')->where('id',$id)->first();
            if (!$item) abort(404);
            $card=DB::table('job_cards')->where('id',$item->job_card_id)->lockForUpdate()->first();
            if (!$card) abort(404);
            $this->assertEditable($card);
            $item=DB::table('am189_inspection_defects')->where('id',$id)->lockForUpdate()->first();
            if ($item->status!=='DECLINED') {
                throw ValidationException::withMessages(['status'=>'Only customer-declined defects can be reopened here.']);
            }
            DB::table('am189_inspection_defects')->where('id',$id)->update([
                'status'=>'OPEN','updated_at'=>now(),
            ]);
            $this->audit($id,(int)$item->job_card_id,$adminId,'DECLINE_REOPENED',$item,[
                'status'=>'OPEN','reason'=>$reason,
            ]);
            return (int)$item->job_card_id;
        });
    }

    private function assertEditable(object $card): void
    {
        if (in_array(strtolower(trim((string)$card->status)),['invoiced','closed','cancelled'],true)) {
            throw ValidationException::withMessages(['status'=>'Closed, invoiced or cancelled Job Card needs an authorized reopen workflow.']);
        }
    }

    private function audit(int $id,int $job,int $actor,string $action,$before,array $after): void
    {
        DB::table('am189_inspection_defect_audits')->insert([
            'defect_id'=>$id,'job_card_id'=>$job,'actor_admin_id'=>$actor,
            'action'=>$action,
            'before_json'=>$before===null?null:json_encode($before,JSON_THROW_ON_ERROR),
            'after_json'=>json_encode($after,JSON_THROW_ON_ERROR),
            'created_at'=>now(),
        ]);
    }
}
