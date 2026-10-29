<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use App\Services\InspectionQcGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        ]);
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
        ]);
        $job=$guard->verify($defect,(int)$admin->id,(int)($admin->staff_id??0),
            $data['result']==='PASS',(string)$data['verification_note']);
        return redirect()->route('erp.job-cards.inspection-defects',$job)
            ->with('success',$data['result']==='PASS'
                ?'Re-inspection VERIFIED. Original defect and audit preserved.'
                :'Re-inspection failed. Defect returned to OPEN.');
    }
}
