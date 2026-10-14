<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\StaffExtraController;
use App\Models\ErpAuthUser;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

/** P29_ANY_STAFF_INSPECTION: assigned staff completes inspection in Staff Portal. */
final class StaffAssignedInspectionController extends Controller
{
    private const CHECKS=[
        'Exterior / Body','Engine Bay','Brake System','Tyres / Wheels','Suspension',
        'Lights / Electrical','Battery','Interior / Dashboard','Fluid Levels','Underbody',
    ];

    private function staff(Request $request): ErpAuthUser
    {
        $u=$request->user();
        abort_unless($u instanceof ErpAuthUser && $u->active && $u->isStaff() && (int)$u->staff_id>0,403,'Linked Staff login required.');
        $s=DB::table('staff')->where('id',(int)$u->staff_id)->first();
        abort_unless($s && strtolower(trim((string)$s->status))==='active',403,'Active staff required.');
        return $u;
    }

    public function show(Request $request,int $job,StaffExtraController $api): View
    {
        $this->staff($request);
        $data=$api->inspection($request,$job)->getData(true);
        $card=$data['job']??null;
        abort_unless($card,404,'Assigned inspection not found.');
        $assignment=DB::table('job_card_inspection_assignments')->where('job_card_id',$job)->first();
        $pending=strtolower(trim((string)($card['status']??'')))==='open'
            && strtolower(trim((string)($card['inspection_status']??'')))==='pending'
            && strtolower(trim((string)($card['diagnosis_status']??'')))!=='completed'
            && !($assignment->completed_at??null);
        return view('staff.assigned-inspection',[
            'card'=>$card,'assignment'=>$assignment,'items'=>$data['items']??[],
            'checks'=>self::CHECKS,'pending'=>$pending,
        ]);
    }

    public function complete(Request $request,int $job,StaffExtraController $api): RedirectResponse
    {
        $this->staff($request);
        $data=$request->validate([
          'items'=>['required','array','size:10'],
          'items.*.item_name'=>['required','string','min:3','max:100'],
          'items.*.status'=>['required','in:good,fair,poor'],
          'items.*.remarks'=>['nullable','string','max:1000'],
        ]);
        $names=array_map(static fn($it)=>trim((string)$it['item_name']),array_values($data['items']));
        if(count(array_unique($names))!==10 || array_diff($names,self::CHECKS) || array_diff(self::CHECKS,$names)){
            throw ValidationException::withMessages(['items'=>'All ten official Inspection checklist items must be completed.']);
        }
        // Delegate to the same guarded Staff API transaction (assignment lock, immutable
        // completed history, inspection/diagnosis/estimate gates); no duplicate write logic.
        $api->saveInspection($request,$job);
        return redirect()->route('staff.assigned-inspection.show',$job)
             ->with('success','Your assigned Vehicle Inspection has been completed. Final Diagnosis is next.');
    }
}
