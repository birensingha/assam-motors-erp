<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * P21_MANAGER_INSPECTION_WORK
 * Assignment and completion strictly separated from deciding technician.
 * Authenticated Admin may assign an active Manager; only that linked Manager
 * may submit the checklist. No historical completed inspection is overwritten.
 */
final class ManagerInspectionWorkController extends Controller
{
    private function actor(Request $request): array
    {
        $user=$request->user();
        abort_unless($user instanceof ErpAuthUser && $user->active && $user->isAdmin(),403,'Admin permission required.');
        $sid=(int)($user->staff_id??0);
        $staff=$sid>0?DB::table('staff')->where('id',$sid)->first():null;
        return [$user,$sid,$staff];
    }

    private function managerStaff(int $sid): object
    {
        $staff=DB::table('staff')->where('id',$sid)->first();
        if(!$staff || strtolower(trim((string)$staff->status))!=='active'
           || strtolower(trim((string)$staff->role))!=='manager') {
            throw ValidationException::withMessages(['assigned_staff_id'=>'Assign an ACTIVE Staff Master Manager only.']);
        }
        return $staff;
    }

    private function data(Request $request,?int $jobId=null): array
    {
        [$user,$myStaffId,$myStaff]=$this->actor($request);
        abort_unless(Schema::hasTable('job_card_inspection_assignments'),503,'Inspection assignment migration pending.');
        $managers=DB::table('staff')->whereRaw("LOWER(TRIM(COALESCE(status,'')))='active'")
            ->whereRaw("LOWER(TRIM(COALESCE(role,'')))='manager'")
            ->orderBy('name')->get(['id','name','staff_code']);
        $records=DB::table('job_cards as j')
            ->leftJoin('job_card_inspection_assignments as a','a.job_card_id','=','j.id')
            ->leftJoin('staff as m','m.id','=','a.assigned_staff_id')
            ->leftJoin('staff as c','c.id','=','j.inspection_submitted_by')
            ->select(['j.id','j.job_no','j.customer_name','j.vehicle_reg_no','j.status',
                'j.inspection_status','j.inspection_submitted_at','j.inspection_submitted_by',
                'j.diagnosis_status','j.decision_status','j.technician',
                'a.assigned_staff_id','a.assigned_at','a.completed_at',
                'm.name as inspector_name','m.staff_code as inspector_code','c.name as completed_by_name'])
            ->when($jobId,fn($q)=>$q->where('j.id',$jobId))
            ->orderByRaw("CASE WHEN LOWER(COALESCE(j.inspection_status,''))='pending' THEN 0 ELSE 1 END")
            ->orderByDesc('j.id')->limit($jobId?1:125)->get();
        if($jobId && $records->isEmpty())abort(404,'Job Card not found.');
        return compact('records','managers','myStaffId','myStaff','user');
    }

    public function index(Request $request): View
    {
        return view('inspections.manager-work',$this->data($request));
    }

    public function work(Request $request,int $job): View
    {
        return view('inspections.manager-work',$this->data($request,$job));
    }

    public function assign(Request $request,int $job): RedirectResponse
    {
        [$user]=$this->actor($request);
        $data=$request->validate([
            'assigned_staff_id'=>['required','integer','min:1'],
            'reason'=>['required','string','min:8','max:500'],
        ]);
        $selected=$this->managerStaff((int)$data['assigned_staff_id']);
        DB::transaction(function() use($job,$user,$selected,$data){
            $card=DB::table('job_cards')->where('id',$job)->lockForUpdate()->first();
            abort_unless($card,404,'Job Card not found.');
            if(strtolower(trim((string)$card->status))!=='open' ||
                strtolower(trim((string)$card->inspection_status))!=='pending' ||
                strtolower(trim((string)($card->diagnosis_status??'pending')))==='completed'){
                throw ValidationException::withMessages(['inspection'=>'Assignment allowed only for OPEN Job Card with PENDING Inspection and no completed Diagnosis.']);
            }
            $exists=DB::table('job_card_inspection_assignments')->where('job_card_id',$job)->lockForUpdate()->first();
            if($exists && $exists->completed_at){
                throw ValidationException::withMessages(['inspection'=>'Completed Inspection cannot be reassigned.']);
            }
            $now=now('Asia/Kolkata');
            if($exists){
                DB::table('job_card_inspection_assignments')->where('id',$exists->id)->update([
                    'assigned_staff_id'=>$selected->id,'assigned_by_user_id'=>$user->id,
                    'assigned_at'=>$now,'updated_at'=>$now,
                ]);
            }else{
                DB::table('job_card_inspection_assignments')->insert([
                    'job_card_id'=>$job,'assigned_staff_id'=>$selected->id,
                    'assigned_by_user_id'=>$user->id,'assigned_at'=>$now,
                    'created_at'=>$now,'updated_at'=>$now,
                ]);
            }
            // Keep technician_id unchanged. This assignment has its own identity.
            $this->audit($user->id,$job,(int)$selected->id,'INSPECTION_MANAGER_ASSIGN',
                $data['reason'].'; assigned_manager_staff_id='.$selected->id);
        },3);
        return redirect()->route('erp.inspection-manager.work',$job)
            ->with('success','Vehicle Inspection assigned to '.$selected->name.'. The deciding technician is unchanged.');
    }

    public function complete(Request $request,int $job): RedirectResponse
    {
        [$user,$myStaffId,$myStaff]=$this->actor($request);
        if(!$myStaff || strtolower(trim((string)$myStaff->role))!=='manager')abort(403,'Linked Manager Staff account required to inspect.');
        $data=$request->validate([
            'items'=>['required','array','min:5','max:35'],
            'items.*.item_name'=>['required','string','min:3','max:100'],
            'items.*.status'=>['required','in:good,fair,poor'],
            'items.*.remarks'=>['nullable','string','max:1000'],
            'remarks'=>['nullable','string','max:2000'],
        ]);
        abort_unless(Schema::hasTable('job_card_inspection'),503,'Inspection items table missing.');
        $validNames=[
            'Exterior / Body','Engine Bay','Brake System','Tyres / Wheels','Suspension',
            'Lights / Electrical','Battery','Interior / Dashboard','Fluid Levels','Underbody',
        ];
        $submitted=array_values($data['items']);
        $names=array_map(fn($x)=>trim((string)$x['item_name']),$submitted);
        if(count($submitted)!==count($validNames) || count(array_unique($names))!==count($validNames)
           || array_diff($names,$validNames) || array_diff($validNames,$names)) {
            throw ValidationException::withMessages(['items'=>'Complete all ten standard inspection checklist items.']);
        }
        DB::transaction(function() use($job,$myStaffId,$user,$submitted,$data){
            $card=DB::table('job_cards')->where('id',$job)->lockForUpdate()->first();
            abort_unless($card,404);
            $assignee=DB::table('job_card_inspection_assignments')
                ->where('job_card_id',$job)->lockForUpdate()->first();
            if(!$assignee || (int)$assignee->assigned_staff_id!==$myStaffId) {
                throw ValidationException::withMessages(['inspection'=>'This inspection is not assigned to your linked Manager staff account.']);
            }
            if(strtolower(trim((string)$card->status))!=='open'
               || strtolower(trim((string)$card->inspection_status))!=='pending'
               || strtolower(trim((string)($card->diagnosis_status??'pending')))==='completed'
               || $assignee->completed_at) {
                throw ValidationException::withMessages(['inspection'=>'Inspection cannot be resubmitted after completion or diagnosis/work start.']);
            }
            if(DB::table('job_card_inspection')->where('job_card_id',$job)->exists()){
                throw ValidationException::withMessages(['inspection'=>'Existing inspection detail is present. Review before overwriting.']);
            }
            $now=now('Asia/Kolkata');
            foreach($submitted as $i){
                DB::table('job_card_inspection')->insert([
                    'job_card_id'=>$job,'item_name'=>trim($i['item_name']),
                    'status'=>$i['status'],'remarks'=>trim((string)($i['remarks']??'')),
                    'photo'=>'','created_at'=>$now,
                ]);
            }
            DB::table('job_cards')->where('id',$job)->update([
                'inspection_status'=>'completed','inspection_submitted_at'=>$now,
                'inspection_submitted_by'=>$myStaffId,
                'diagnosis_status'=>'pending',
                'decision_status'=>'pending',
                'estimate_allowed'=>0,
                'customer_public_status'=>'Inspection Completed',
            ]);
            DB::table('job_card_inspection_assignments')->where('id',$assignee->id)->update([
                'completed_by_staff_id'=>$myStaffId,'completed_at'=>$now,'updated_at'=>$now,
            ]);
            $this->audit($user->id,$job,$myStaffId,'INSPECTION_MANAGER_COMPLETED',
                 'Ten-item Manager Inspection submitted; diagnosis must follow.');
        },3);
        return redirect()->route('erp.inspection-manager.work',$job)
           ->with('success','Vehicle Inspection COMPLETED. Final Diagnosis is now the next stage.');
    }

    private function audit(int $userId,int $job,int $staffId,string $action,string $reason): void
    {
        if(!Schema::hasTable('job_card_diagnosis_events'))return;
        DB::table('job_card_diagnosis_events')->insert([
            'job_card_id'=>$job,
            'event_type'=>$action,
            'diagnosis_status'=>'pending',
            'final_diagnosis'=>null,
            'technician_id'=>$staffId,
            'technician_name'=>DB::table('staff')->where('id',$staffId)->value('name'),
            'technician_decision'=>null,
            'technician_decision_note'=>null,
            'estimate_allowed'=>0,
            'actor_id'=>$userId,
            'actor_name'=>'ERP Owner/Admin',
            'reason'=>$reason,
            'created_at'=>now('Asia/Kolkata'),
        ]);
    }
}
