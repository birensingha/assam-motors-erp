<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use App\Services\InspectionQcGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class InspectionQcDefectController extends Controller
{
    private function admin(Request $request): ErpAuthUser
    {
        $user=$request->user();
        abort_unless($user instanceof ErpAuthUser && $user->active && $user->isAdmin(),
            403,'Active ERP Owner/Admin login required.');
        return $user;
    }

    public function index(Request $request,int $job,InspectionQcGuard $guard)
    {
        $this->admin($request);
        $card=DB::table('job_cards')->where('id',$job)->first();
        if (!$card) abort(404);
        $staff=DB::table('staff')->whereRaw("LOWER(COALESCE(status,'active'))='active'")
            ->orderBy('name')->get(['id','name','staff_code']);
        return view('inspection-defects.index',[
            'job'=>$card,'findings'=>$guard->findings($job),'staff'=>$staff,
            'identity'=>$this->jobIdentity($card),
            'inspectionMeta'=>$this->inspectionMeta($job),
            'conditionalReview'=>Schema::hasTable('am_qc_conditional_reviews') ? DB::table('am_qc_conditional_reviews')->where('job_card_id',$job)->first() : null,
        ]);
    }

    /** Original saved point-count and missing-observation details; no mutation. */
    private function inspectionMeta(int $job): array
    {
        $rows=DB::table('job_card_inspection')->where('job_card_id',$job)
            ->get(['item_name','status','remarks']);
        $official=\App\Support\VehicleInspectionChecklist::items();
        $names=$rows->map(static fn ($r): string => trim((string)$r->item_name))->all();
        $isCurrent=count($names)===count($official)
            && count(array_unique($names))===count($names)
            && !array_diff($names,$official)
            && !array_diff($official,$names);
        $missing=0;
        foreach ($rows as $item) {
            if (in_array(strtolower(trim((string)$item->status)),['fair','poor'],true)
                && trim((string)($item->remarks??''))==='') $missing++;
        }
        return [
            'saved_count'=>$rows->count(),
            'official_count'=>count($official),
            'current_30'=>$isCurrent,
            'missing_defect_notes'=>$missing,
        ];
    }

    /** Read-only header data, compatible with older and staging JC field names. */
    private function firstPresent(object $row,array $names): ?string
    {
        foreach ($names as $name) {
            $value=$row->{$name} ?? null;
            if ($value === null || is_array($value) || is_object($value)) continue;
            $value=trim((string)$value);
            if ($value!=='' && $value!=='0') return $value;
        }
        return null;
    }

    private function relatedById(array $tables,int $id): ?object
    {
        if ($id<=0) return null;
        foreach ($tables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table,'id')) continue;
            $found=DB::table($table)->where('id',$id)->first();
            if ($found) return $found;
        }
        return null;
    }

    /** Present stored identifiers; never invent a registration or customer. */
    private function jobIdentity(object $card): array
    {
        $jobNo=$this->firstPresent($card,[
            'job_card_number','job_card_no','job_no','jobcard_no','jc_no',
            'jc_number','job_number','jobcard_number','number','card_number'
        ]);
        $vehicle=$this->firstPresent($card,[
            'vehicle_no','vehicle_number','vehicle_reg_no','vehicle_registration_no',
            'vehicle_registration_number','registration_no','registration_number',
            'vehicle_reg_number','reg_no','regn_no','reg_number','vehicle_reg'
        ]);
        $customer=$this->firstPresent($card,[
            'customer_name','customer_full_name','name_of_customer',
            'client_name','owner_name','vehicle_owner_name'
        ]);
        $vehicleId=(int)($this->firstPresent($card,['vehicle_id','customer_vehicle_id'])??0);
        $vehicleRow=null;
        if (!$vehicle || !$customer) {
            $vehicleRow=$this->relatedById([
                'am_erp_vehicles','customer_vehicles','vehicle_master',
                'workshop_vehicles','vehicles'
            ],$vehicleId);
        }
        if (!$vehicle && $vehicleRow) {
            $vehicle=$this->firstPresent($vehicleRow,[
                'registration_no','registration_number','vehicle_no',
                'vehicle_number','reg_no','reg_number','registration',
                'registration_mark','vehicle_reg_no'
            ]);
        }
        $customerId=(int)($this->firstPresent($card,[
            'customer_id','customer_master_id','client_id','owner_id'
        ])??0);
        if ($customerId===0 && $vehicleRow) {
            $customerId=(int)($this->firstPresent($vehicleRow,['customer_id','owner_id'])??0);
        }
        if (!$customer) {
            $customerRow=$this->relatedById([
                'am_erp_customers','customer_master','customers','workshop_customers'
            ],$customerId);
            if ($customerRow) {
                $customer=$this->firstPresent($customerRow,[
                    'customer_name','name','full_name','customer_full_name',
                    'display_name','client_name'
                ]);
            }
        }
        return [
            'job_no'=>$jobNo ?? 'Not recorded',
            'vehicle_no'=>$vehicle ?? 'Not recorded',
            'customer_name'=>$customer ?? 'Not recorded',
        ];
    }

    public function sync(Request $request,int $job,InspectionQcGuard $guard)
    {
        $admin=$this->admin($request);
        $created=$guard->sync($job,(int)$admin->id);
        return redirect()->route('erp.job-cards.inspection-defects',$job)
            ->with('success',$created.' inspection defect(s) registered. No existing inspection answers were altered.');
    }

    public function rectify(Request $request,int $defect,InspectionQcGuard $guard)
    {
        $admin=$this->admin($request);
        $data=$request->validate([
            'repair_staff_id'=>['required','integer','min:1'],
            'repair_note'=>['required','string','min:8','max:2000'],
        ]);
        $job=$guard->repair($defect,(int)$admin->id,
            (int)$data['repair_staff_id'],(string)$data['repair_note']);
        return redirect()->route('erp.job-cards.inspection-defects',$job)
            ->with('success','Repair recorded. Independent verification is still required.');
    }

    public function verify(Request $request,int $defect,InspectionQcGuard $guard)
    {
        $admin=$this->admin($request);
        $data=$request->validate([
            'result'=>['required','in:PASS,FAIL'],
            'verification_note'=>['required','string','min:8','max:2000'],
            'reviewer_staff_id'=>['nullable','integer','min:1'],
            'independent_attestation'=>['sometimes','accepted'],
        ]);
        $job=$guard->verify($defect,(int)$admin->id,(int)($admin->staff_id??0),
            $data['result']==='PASS',(string)$data['verification_note'],
            isset($data['reviewer_staff_id']) ? (int)$data['reviewer_staff_id'] : null,
            $request->boolean('independent_attestation'));
        return redirect()->route('erp.job-cards.inspection-defects',$job)
            ->with('success',$data['result']==='PASS'
                ?'Re-inspection VERIFIED. Original defect and audit preserved.'
                :'Re-inspection failed. Defect returned to OPEN.');
    }
    public function decline(Request $request,int $defect,InspectionQcGuard $guard)
    {
        $admin=$this->admin($request);
        $data=$request->validate([
            'decline_reason'=>['required','string','min:12','max:2000'],
            'decline_acknowledgement'=>['required','in:WRITTEN,VERBAL,REFUSED_TO_SIGN,PENDING'],
            'decline_evidence_ref'=>['nullable','string','max:500'],
        ]);
        $job=$guard->decline($defect,(int)$admin->id,
            (string)$data['decline_reason'],(string)$data['decline_acknowledgement'],
            $data['decline_evidence_ref']??null);
        return redirect()->route('erp.job-cards.inspection-defects',$job)
            ->with('success','Customer refusal recorded with audit. Defect remains unrepaired; Full QC PASS stays blocked.');
    }

    public function reopenDeclined(Request $request,int $defect,InspectionQcGuard $guard)
    {
        $admin=$this->admin($request);
        $data=$request->validate(['reopen_reason'=>['required','string','min:8','max:2000']]);
        $job=$guard->reopenDeclined($defect,(int)$admin->id,(string)$data['reopen_reason']);
        return redirect()->route('erp.job-cards.inspection-defects',$job)
            ->with('success','Customer-declined defect reopened as OPEN. Previous refusal preserved in audit.');
    }

    /**
     * Separately audit QC of approved work. Never modifies Full QC PASS,
     * Job Card lifecycle or vehicle-delivery status.
     */
    public function approvedWorkQc(Request $request,int $job,InspectionQcGuard $guard)
    {
        $admin=$this->admin($request);
        $data=$request->validate([
            'approved_work_result'=>['required','in:PASS,FAIL'],
            'approved_work_findings'=>['required','string','min:12','max:2000'],
            'assessment'=>['required','in:HOLD,RISK_REVIEWED'],
            'risk_assessment'=>['nullable','string','max:2000'],
            'customer_acknowledgement_ref'=>['nullable','string','max:500'],
        ]);
        if (!Schema::hasTable('am_qc_conditional_reviews')
            || !Schema::hasTable('am_qc_conditional_review_audits')) {
            abort(503,'QC conditional review migration is required.');
        }
        $card=DB::table('job_cards')->where('id',$job)->first();
        if (!$card) abort(404);
        if (in_array(strtolower(trim((string)$card->status)),['cancelled','invoiced','closed'],true)) {
            throw ValidationException::withMessages(['status'=>'Closed, invoiced or cancelled Job Card needs an authorized workflow.']);
        }
        $findings=$guard->findings($job);
        $nonDeclined=array_filter($findings,static fn(array $f): bool =>
            !$f['verified'] && $f['status']!=='DECLINED');
        if ($data['approved_work_result']==='PASS' && count($nonDeclined)>0) {
            throw ValidationException::withMessages(['qc'=>'Approved-work QC cannot PASS while non-declined inspection defects remain unresolved.']);
        }
        $declined=array_filter($findings,static fn(array $f): bool => $f['status']==='DECLINED');
        if ($data['assessment']==='RISK_REVIEWED') {
            if (!$declined || $data['approved_work_result']!=='PASS') {
                throw ValidationException::withMessages(['assessment'=>'Risk review requires a recorded declined defect and approved-work QC PASS.']);
            }
            if (mb_strlen(trim((string)($data['risk_assessment']??'')))<20) {
                throw ValidationException::withMessages(['risk_assessment'=>'Record the actual safety/risk assessment (at least 20 characters).']);
            }
            if (trim((string)($data['customer_acknowledgement_ref']??''))==='') {
                throw ValidationException::withMessages(['customer_acknowledgement_ref'=>'Record the reference to a signed customer acknowledgement. Verbal-only refusal is not enough.']);
            }
            foreach ($declined as $finding) {
                $record=$finding['record'];
                if (!$record || ($record->decline_acknowledgement??null)!=='WRITTEN'
                    || trim((string)($record->decline_evidence_ref??''))==='') {
                    throw ValidationException::withMessages(['assessment'=>'Every declined finding must first have documented written acknowledgement and evidence reference.']);
                }
            }
        }
        DB::transaction(function () use ($job,$admin,$data,$guard) {
            $card=DB::table('job_cards')->where('id',$job)->lockForUpdate()->first();
            if (!$card) abort(404);
            if (in_array(strtolower(trim((string)$card->status)),['cancelled','invoiced','closed'],true)) {
                throw ValidationException::withMessages(['status'=>'Job Card state changed during review.']);
            }
            $open=array_filter($guard->findings($job),static fn(array $f): bool => !$f['verified'] && $f['status']!=='DECLINED');
            if ($data['approved_work_result']==='PASS' && $open) {
                throw ValidationException::withMessages(['qc'=>'Inspection findings changed. Reopen review.']);
            }
            $record=[
                'approved_work_result'=>$data['approved_work_result'],
                'approved_work_findings'=>trim($data['approved_work_findings']),
                'assessment'=>$data['assessment'],
                'risk_assessment'=>trim((string)($data['risk_assessment']??'')) ?: null,
                'customer_acknowledgement_ref'=>trim((string)($data['customer_acknowledgement_ref']??'')) ?: null,
                'actor_admin_id'=>(int)$admin->id,'updated_at'=>now(),
            ];
            DB::table('am_qc_conditional_reviews')->updateOrInsert(
                ['job_card_id'=>$job],$record+['created_at'=>now()]);
            DB::table('am_qc_conditional_review_audits')->insert([
                'job_card_id'=>$job,'actor_admin_id'=>(int)$admin->id,
                'review_json'=>json_encode($record,JSON_THROW_ON_ERROR),
                'created_at'=>now(),
            ]);
        });
        return redirect()->route('erp.job-cards.inspection-defects',$job)
            ->with('success','Approved-work QC and conditional assessment recorded separately. Full QC PASS and delivery status have NOT been changed.');
    }

}
