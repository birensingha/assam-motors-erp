<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * P54: QC is completed by the ONE linked, assigned staff member.
 * Staff can only read its Job Card (not edit Parts, ROT, estimate, rates).
 * Existing QC record/history preserved; new result + audit in one transaction.
 */
final class StaffQcController extends Controller
{
    private function identity(): ErpAuthUser
    {
        $user=Auth::guard('staff_web')->user();
        abort_unless($user instanceof ErpAuthUser && $user->active &&
            $user->isStaff() && (int)$user->staff_id>0,403,'Active linked staff login required.');
        return $user;
    }

    private function latest(int $job): ?object
    {
        return DB::table('workshop_qc_checks')->where('job_card_id',$job)->orderByDesc('id')->first();
    }

    private function assigned(int $job, int $staffId): array
    {
        $card=DB::table('job_cards')->where('id',$job)->first();
        abort_unless($card,404);
        $qc=$this->latest($job);
        abort_unless($qc && (int)$qc->assigned_staff_id===$staffId,403,'This Quality Check is not assigned to your Staff ID.');
        return [$card,$qc];
    }

    public function show(Request $request, int $job): View
    {
        $user=$this->identity();
        [$card,$qc]=$this->assigned($job,(int)$user->staff_id);
        $parts=DB::table('job_card_parts')->where('job_card_id',$job)->orderBy('id')->get();
        $labour=DB::table('job_card_labour')->where('job_card_id',$job)->orderBy('id')->get();
        $previous=DB::table('workshop_qc_checks')
            ->where('job_card_id',$job)->where('id','<',$qc->id)
            ->orderByDesc('id')->limit(6)->get();
        $pending=strtoupper(trim((string)$qc->result))==='PENDING'
            && strtolower(trim((string)$card->status))==='completed'
            && str_contains(strtolower((string)$card->customer_public_status),'quality check');
        return view('staff.qc-review',compact('card','qc','parts','labour','previous','pending','user'));
    }

    public function submit(Request $request, int $job): RedirectResponse
    {
        $user=$this->identity();
        $staffId=(int)$user->staff_id;
        $data=$request->validate([
            'result'=>['required','in:PASS,FAIL'],
            'reviewed'=>['required','accepted'],
            'failed_item'=>['nullable','string','max:2000'],
            'notes'=>['nullable','string','max:3000'],
        ]);
        if($data['result']==='FAIL' && trim((string)($data['failed_item']??''))==='') {
            throw ValidationException::withMessages(['failed_item'=>'QC FAIL requires the failed item or defect details.']);
        }
        DB::transaction(function() use($job,$data,$user,$staffId){
            // Lock Job Card then QC record: concurrent submission cannot double-finalize.
            $card=DB::table('job_cards')->where('id',$job)->lockForUpdate()->first();
            abort_unless($card,404);
            $qc=DB::table('workshop_qc_checks')->where('job_card_id',$job)
                ->orderByDesc('id')->lockForUpdate()->first();
            abort_unless($qc && (int)$qc->assigned_staff_id===$staffId,403,'QC was assigned to a different staff member.');
            if(strtoupper(trim((string)$qc->result))!=='PENDING'
               || strtolower(trim((string)$card->status))!=='completed'
               || !str_contains(strtolower((string)$card->customer_public_status),'quality check')){
                throw ValidationException::withMessages(['status'=>'QC no longer pending. Refresh the assigned QC page.']);
            }
            $result=$data['result'];
            $failed=$result==='FAIL' ? [['item'=>trim((string)$data['failed_item']),'staff_id'=>$staffId]] : [];
            $date=now('Asia/Kolkata');
            DB::table('workshop_qc_checks')->insert([
                'job_card_id'=>$job,'assigned_staff_id'=>$staffId,
                'result'=>$result,
                'notes'=>trim((string)($data['notes']??''))?:null,
                'failed_items_json'=>json_encode($failed,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                // The staff actor is recorded in the change audit. Never impersonate an Admin.
                'checked_by_admin_user_id'=>null,'checked_at'=>$date,
                'created_at'=>$date,'updated_at'=>$date,
            ]);
            $afterStatus=$result==='PASS'?'completed':'in-progress';
            $afterPublic=$result==='PASS'?'Quality Check Passed':'Rework / QC Failed';
            $changes=['status'=>$afterStatus,'customer_public_status'=>$afterPublic];
            if(Schema::hasColumn('job_cards','completed_at')){
                $changes['completed_at']=$result==='PASS' ? ($card->completed_at?:$date) : null;
            }
            DB::table('job_cards')->where('id',$job)->update($changes);
            if(Schema::hasTable('job_card_change_audits')){
                DB::table('job_card_change_audits')->insert([
                    'job_card_id'=>$job,'entity_type'=>'JOB_CARD','entity_id'=>$job,
                    'action'=>'QC_'.$result,
                    'before_json'=>json_encode((array)$card,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                    'after_json'=>json_encode((array)DB::table('job_cards')->where('id',$job)->first(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                    'reason'=>'QC '.$result.' submitted by assigned staff '.$staffId.
                        (($data['notes']??'')!==''?' · '.trim((string)$data['notes']):''),
                    'actor'=>'STAFF:'.$staffId.' / '.((string)$user->name),
                    'created_at'=>$date,'updated_at'=>$date
                ]);
            }
        },3);
        return redirect()->route('staff.qc.show',$job)->with('success',
            $data['result']==='PASS'?
                'QC PASS submitted. Admin can continue to Ready for Delivery.':
                'QC FAIL recorded; Job Card returned to WIP / Rework.');
    }
}
