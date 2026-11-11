<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\HrOperationsService;
use App\Support\HrAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Routes also require Sanctum + EnsureHrManager; recheck HR role before every operation. */
final class HrOperationsApiController extends Controller
{
    public function overview(Request $request,HrOperationsService $service): JsonResponse
    {
        abort_unless(HrAccess::permitted($request->user()),403);
        $month=$service->month((string)$request->query('month',now('Asia/Kolkata')->format('Y-m')));
        $id=(int)$request->query('staff_id',0);
        $r=$service->report($month,$id);
        return response()->json(['ok'=>true,'month'=>$month,'attendance'=>$r['attendance'],
            'performance'=>$r['performance'],'rot_rows'=>$r['rows'],
            'leave_balances'=>$r['balances'],'leave_entries'=>$r['ledgers'],
            'payroll_calculation_status'=>'rate_and_leave_policy_not_configured']);
    }
    public function attendance(Request $request,HrOperationsService $service): JsonResponse
    {
        abort_unless(HrAccess::permitted($request->user()),403);
        $data=$request->validate([
            'staff_id'=>['required','integer','min:1'],'date'=>['required','date_format:Y-m-d'],
            'status'=>['required','in:present,late,absent,leave,off'],
            'check_in'=>['nullable','date_format:H:i'],'check_out'=>['nullable','date_format:H:i'],
            'reason'=>['required','string','min:12','max:1000'],
        ]);
        $id=$service->correctAttendance($data,(int)$request->user()->staff_id);
        return response()->json(['ok'=>true,'attendance_id'=>$id,'audited'=>true]);
    }
    public function leave(Request $request,HrOperationsService $service): JsonResponse
    {
        abort_unless(HrAccess::permitted($request->user()),403);
        $data=$request->validate([
            'staff_id'=>['required','integer','min:1'],'entry_date'=>['required','date_format:Y-m-d'],
            'kind'=>['required','in:earned,used'],'days'=>['required','numeric','min:0.5','max:31'],
            'approval_status'=>['required_unless:kind,earned','nullable','in:approved,pending,unapproved'],
            'application_status'=>['required_unless:kind,earned','nullable','in:submitted,not_submitted'],
            'reason'=>['required','string','min:12','max:1000'],
        ]);
        $id=$service->recordLeave($data,(int)$request->user()->staff_id);
        return response()->json(['ok'=>true,'entry_id'=>$id,'audited'=>true],201);
    }
}