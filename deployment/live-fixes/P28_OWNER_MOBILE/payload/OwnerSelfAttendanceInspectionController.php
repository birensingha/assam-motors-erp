<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\AdminSelfAttendanceController;
use App\Http\Controllers\Web\ManagerInspectionWorkController;
use App\Models\ErpAuthUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * P28_OWNER_NATIVE_ATTENDANCE_INSPECTION
 * Authenticated native Owner/Admin bearer token only. Reuse the exact web
 * business transactions; do not add another attendance/inspection policy.
 */
final class OwnerSelfAttendanceInspectionController extends Controller
{
    private function admin(Request $request): ErpAuthUser
    {
        $user=$request->user();
        abort_unless($user instanceof ErpAuthUser && (bool)$user->active
           && strtolower(trim((string)$user->role))==='admin',403,'Active Owner/Admin bearer login required.');
        return $user;
    }

    public function myAttendance(Request $request): JsonResponse
    {
        $user=$this->admin($request);
        $sid=(int)$user->staff_id;
        if($sid<=0)throw ValidationException::withMessages(['attendance'=>'Admin account is not linked to Staff Master.']);
        $staff=DB::table('staff')->where('id',$sid)->first();
        if(!$staff || strtolower(trim((string)$staff->status))!=='active'
           || !(bool)$staff->attendance_enabled){
            throw ValidationException::withMessages(['attendance'=>'Linked Staff must be active and Attendance Enabled.']);
        }
        $today=now('Asia/Kolkata')->toDateString();
        $rows=DB::table('attendance')->where('staff_id',$sid)->where('date',$today)->orderBy('id')->limit(3)->get();
        $count=$rows->count();
        $one=$rows->first();
        $state=$count===0?'NOT_CHECKED_IN':
            ($count>1 || !$one->check_in?'HR_REVIEW_REQUIRED':
            ($one->check_out?'COMPLETE':'CHECKED_IN'));
        return response()->json([
            'ok'=>true,'date'=>$today,'timezone'=>'Asia/Kolkata',
            'staff'=>['id'=>$sid,'name'=>$staff->name,'staff_code'=>$staff->staff_code],
            'state'=>$state,'attendance'=>$count===1?$one:null,
            'can_check_in'=>$state==='NOT_CHECKED_IN',
            'can_check_out'=>$state==='CHECKED_IN',
            'policy'=>'OWNER_ADMIN_REMOTE_EXEMPT_NO_GPS'
        ]);
    }

    public function checkIn(Request $request,AdminSelfAttendanceController $service): JsonResponse
    {
        $this->admin($request);
        $service->checkIn($request);
        return $this->myAttendance($request);
    }

    public function checkOut(Request $request,AdminSelfAttendanceController $service): JsonResponse
    {
        $this->admin($request);
        $service->checkOut($request);
        return $this->myAttendance($request);
    }

    public function inspections(Request $request,ManagerInspectionWorkController $service): JsonResponse
    {
        $this->admin($request);
        $data=$service->index($request)->getData();
        return response()->json([
            'ok'=>true,
            'jobs'=>$data['records']->map(fn($row)=>(array)$row)->values()->all(),
            'managers'=>$data['managers']->map(fn($row)=>(array)$row)->values()->all(),
            'my_staff_id'=>(int)$data['myStaffId'],
            'my_is_manager'=>strtolower(trim((string)($data['myStaff']->role??'')))==='manager'
        ]);
    }

    public function inspection(Request $request,int $job,ManagerInspectionWorkController $service): JsonResponse
    {
        $this->admin($request);
        $data=$service->work($request,$job)->getData();
        $card=$data['records']->first();
        $items=DB::table('job_card_inspection')->where('job_card_id',$job)->orderBy('id')->get();
        return response()->json([
            'ok'=>true,'job'=>$card,'items'=>$items,'managers'=>$data['managers'],
            'my_staff_id'=>(int)$data['myStaffId'],
            'my_is_manager'=>strtolower(trim((string)($data['myStaff']->role??'')))==='manager',
        ]);
    }

    public function assignInspection(Request $request,int $job,ManagerInspectionWorkController $service): JsonResponse
    {
        $this->admin($request);
        $service->assign($request,$job);
        return $this->inspection($request,$job,$service);
    }

    public function completeInspection(Request $request,int $job,ManagerInspectionWorkController $service): JsonResponse
    {
        $this->admin($request);
        $service->complete($request,$job);
        return $this->inspection($request,$job,$service);
    }
}
