<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use App\Models\JobCard;
use App\Models\JobCardDiagnosisEvent;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** P22_SEQUENTIAL_TECH_DECISION: only logged-in linked deciding technician can decide. */
final class StaffTechnicianDecisionController extends Controller
{
    public function save(Request $request,int $job): RedirectResponse
    {
        $user=$request->user();
        abort_unless($user instanceof ErpAuthUser && $user->active && $user->isStaff() && (int)$user->staff_id>0,403,'Linked Staff login required.');
        $sid=(int)$user->staff_id;
        $person=Staff::query()->findOrFail($sid);
        abort_unless(strtolower(trim((string)$person->status))==='active',403,'Inactive technician cannot submit.');
        $data=$request->validate([
           'technician_decision'=>['required','in:REPAIR,REPLACE,REPAIR_REPLACE,FURTHER_DIAGNOSIS,OUTSIDE_LABOUR,NO_REPAIR,OTHER'],
           'technician_decision_note'=>['nullable','string','max:2000'],
        ]);
        $choice=$data['technician_decision'];
        $note=trim((string)($data['technician_decision_note']??''));
        if(in_array($choice,['OTHER','FURTHER_DIAGNOSIS','NO_REPAIR'],true)&&mb_strlen($note)<8){
            throw ValidationException::withMessages(['technician_decision_note'=>'Reason is required (at least 8 characters) for this decision.']);
        }
        DB::transaction(function() use($job,$user,$sid,$person,$choice,$note){
            $card=JobCard::query()->lockForUpdate()->findOrFail($job);
            if((int)$card->technician_id!==$sid){
                throw ValidationException::withMessages(['technician_decision'=>'Only this Job Card\'s assigned Deciding Technician can submit a decision.']);
            }
            if(strtolower(trim((string)$card->status))!=='open'
               || strtolower((string)$card->inspection_status)!=='completed'
               || strtolower((string)$card->diagnosis_status)!=='completed'
               || !$card->diagnosis_completed_at
               || strtolower((string)($card->decision_status??'pending'))==='completed'){
                throw ValidationException::withMessages(['technician_decision'=>'Manager Inspection and Final Diagnosis must be COMPLETE before a single Technician Decision.']);
            }
            $requiresMore=$choice==='FURTHER_DIAGNOSIS';
            $noRepair=$choice==='NO_REPAIR';
            $enableEstimate=!$requiresMore && !$noRepair;
            $now=now('Asia/Kolkata');
            $card->technician_decision=$choice;
            $card->technician_decision_note=$note ?: null;
            $card->decision_status='completed';
            $card->decision_completed_at=$now;
            $card->decision_completed_by=(int)$user->id;
            $card->estimate_allowed=$enableEstimate ? 1 : 0;
            $card->estimate_allowed_at=$enableEstimate ? $now : null;
            $card->estimate_allowed_by=$enableEstimate ? (int)$user->id : null;
            $card->customer_public_status=$requiresMore ? 'Further Diagnosis Required'
                : ($noRepair ? 'No Repair Advised' : 'Estimate Preparation');
            $card->save();
            JobCardDiagnosisEvent::query()->create([
                'job_card_id'=>$job,
                'event_type'=>'TECHNICIAN_DECISION_COMPLETED',
                'diagnosis_status'=>$card->diagnosis_status,
                'final_diagnosis'=>$card->final_diagnosis,
                'technician_id'=>$sid,
                'technician_name'=>$person->name,
                'technician_decision'=>$choice,
                'technician_decision_note'=>$note ?: null,
                'estimate_allowed'=>$enableEstimate ? 1 : 0,
                'actor_id'=>$user->id,
                'actor_name'=>$person->name,
                'reason'=>'Assigned Technician Decision submitted from Staff Portal.',
            ]);
        },3);
        return redirect()->route('staff.inspection-report',$job)
          ->with('success',$choice==='FURTHER_DIAGNOSIS'
              ? 'Decision saved. Further Diagnosis required; Estimate stays LOCKED.'
              : ($choice==='NO_REPAIR'
                   ? 'No Repair decision saved; Estimate stays LOCKED.'
                   : 'Technician Decision saved. Parts/Labour Estimate Preparation unlocked.'));
    }
}
