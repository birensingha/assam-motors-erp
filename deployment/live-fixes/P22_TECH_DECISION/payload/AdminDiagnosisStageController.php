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

/** P22_SEQUENTIAL_DIAGNOSIS: Final Diagnosis NEVER marks technician decision complete. */
final class AdminDiagnosisStageController extends Controller
{
    public function complete(Request $request,int $job): RedirectResponse
    {
        $user=$request->user();
        abort_unless($user instanceof ErpAuthUser && $user->active && $user->isAdmin(),403,'Admin required.');
        $data=$request->validate([
           'final_diagnosis'=>['required','string','min:8','max:4000'],
           'technician_id'=>['required','integer','min:1'],
        ]);
        DB::transaction(function() use($user,$data,$job){
            $card=JobCard::query()->lockForUpdate()->findOrFail($job);
            if(strtolower(trim((string)$card->status))!=='open'){
                throw ValidationException::withMessages(['diagnosis'=>'Final Diagnosis only on OPEN pre-work Job Card. Completed/Rework needs authorized reopen workflow.']);
            }
            if(strtolower(trim((string)$card->inspection_status))!=='completed'
               || !$card->inspection_submitted_at || !$card->inspection_submitted_by){
                throw ValidationException::withMessages(['diagnosis'=>'Manager Vehicle Inspection must be COMPLETED and officially submitted first.']);
            }
            if(strtolower((string)$card->diagnosis_status)==='completed'){
                throw ValidationException::withMessages(['diagnosis'=>'Final Diagnosis is already completed. Technician Decision is the next stage.']);
            }
            $tech=Staff::query()->whereKey((int)$data['technician_id'])->first();
            if(!$tech || strtolower(trim((string)$tech->status))!=='active'
               || (int)$card->technician_id!==(int)$tech->id){
                throw ValidationException::withMessages(['technician_id'=>'First assign an ACTIVE Deciding Technician to this Job Card.']);
            }
            $card->final_diagnosis=trim($data['final_diagnosis']);
            $card->diagnosis_status='completed';
            $card->diagnosis_completed_at=now('Asia/Kolkata');
            $card->diagnosis_completed_by=(int)$user->id;
            $card->technician_decision=null;
            $card->technician_decision_note=null;
            $card->decision_status='pending';
            $card->decision_completed_at=null;
            $card->decision_completed_by=null;
            $card->estimate_allowed=0;
            $card->estimate_allowed_at=null;
            $card->estimate_allowed_by=null;
            $card->customer_public_status='Awaiting Technician Decision';
            $card->save();

            JobCardDiagnosisEvent::query()->create([
                'job_card_id'=>$job,
                'event_type'=>'FINAL_DIAGNOSIS_COMPLETED',
                'diagnosis_status'=>'completed',
                'final_diagnosis'=>$card->final_diagnosis,
                'technician_id'=>$card->technician_id,
                'technician_name'=>$card->technician,
                'technician_decision'=>null,
                'technician_decision_note'=>null,
                'estimate_allowed'=>0,
                'actor_id'=>$user->id,
                'actor_name'=>$user->name ?: $user->email,
                'reason'=>'After Manager Inspection; waiting for assigned Technician Decision.',
            ]);
        },3);
        return redirect()->route('erp.job-cards.show',$job)
          ->with('success','Final Diagnosis COMPLETED. Technician Decision is now pending; Estimate remains LOCKED.');
    }
}
