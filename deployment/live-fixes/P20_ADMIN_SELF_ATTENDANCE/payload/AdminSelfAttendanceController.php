<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * P20_OWNER_ADMIN_SELF_ATTENDANCE
 * Authenticated Owner/Admin marks THEIR OWN linked Staff attendance.
 * No impersonation, no mock GPS. Workshop geofence remains required for
 * ordinary Staff attendance via existing Android API.
 */
final class AdminSelfAttendanceController extends Controller
{
    private function actor(Request $request): array
    {
        $user = $request->user();
        abort_unless($user instanceof ErpAuthUser
            && (bool)$user->active
            && strtolower(trim((string)$user->role)) === 'admin', 403,
            'Active Owner/Admin login required for self-attendance.');

        $staffId=(int)($user->staff_id ?? 0);
        if($staffId < 1){
            throw ValidationException::withMessages(['attendance'=>'Your Admin login is not linked to Staff Master. Ask HR to link the correct staff account.']);
        }

        $staff=DB::table('staff')->where('id',$staffId)->first();
        if(!$staff || strtolower(trim((string)($staff->status??'')))!=='active'
             || !(bool)($staff->attendance_enabled??false)){
            throw ValidationException::withMessages(['attendance'=>'Linked staff must be ACTIVE with Attendance Enabled in Staff Master.']);
        }
        return [$user,$staffId];
    }

    public function checkIn(Request $request): RedirectResponse
    {
        [$user,$staffId]=$this->actor($request);
        $clock=CarbonImmutable::now('Asia/Kolkata');
        $day=$clock->toDateString();

        DB::transaction(function() use($staffId,$user,$clock,$day){
            // Lock the staff row to serialize concurrent self Check-In/Out requests.
            $staff=DB::table('staff')->where('id',$staffId)->lockForUpdate()->first();
            abort_unless($staff && strtolower((string)$staff->status)==='active'
                && (bool)$staff->attendance_enabled,403,'Staff attendance permission changed.');

            $prior=DB::table('attendance')
                ->where('staff_id',$staffId)->where('date',$day)
                ->lockForUpdate()->get(['id','check_in','check_out','status']);
            if($prior->count()>1) {
                throw ValidationException::withMessages(['attendance'=>'Duplicate attendance rows detected. HR must reconcile these before Check-In.']);
            }
            if($prior->count()) {
                throw ValidationException::withMessages(['attendance'=>'Today already has an attendance record. Check-In cannot overwrite it.']);
            }

            $id=DB::table('attendance')->insertGetId([
                'staff_id'=>$staffId,
                'staff_code'=>$staff->staff_code,
                'staff_name'=>$staff->name,
                'date'=>$day,
                'check_in'=>$clock->format('Y-m-d H:i:s'),
                'check_out'=>null,
                'worked_hours'=>0,
                'status'=>'present',
                'late'=>$clock->format('H:i:s')>='10:00:00'?1:0,
                'notes'=>'Owner/Admin self Check-In via ERP Web (geofence exempt; no GPS collected)',
                'created_at'=>$clock->format('Y-m-d H:i:s'),
            ]);
            $this->audit($staffId,(int)$user->id,(int)$id,'OWNER_ADMIN_SELF_CHECK_IN',$clock);
        },3);

        return redirect()->route('erp.attendance')
            ->with('success','Your Check-In was recorded using server time (IST).');
    }

    public function checkOut(Request $request): RedirectResponse
    {
        [$user,$staffId]=$this->actor($request);
        $clock=CarbonImmutable::now('Asia/Kolkata');
        $day=$clock->toDateString();

        DB::transaction(function() use($staffId,$user,$clock,$day){
            $staff=DB::table('staff')->where('id',$staffId)->lockForUpdate()->first();
            abort_unless($staff && strtolower((string)$staff->status)==='active'
                && (bool)$staff->attendance_enabled,403,'Staff attendance permission changed.');

            $rows=DB::table('attendance')->where('staff_id',$staffId)
                ->where('date',$day)->lockForUpdate()->get();
            if($rows->count()!==1 || !$rows[0]->check_in){
                throw ValidationException::withMessages(['attendance'=>'No single valid Check-In was found for today. Ask HR to correct the record.']);
            }
            $a=$rows[0];
            if($a->check_out){
                throw ValidationException::withMessages(['attendance'=>'Today is already checked out.']);
            }

            // Same active-ROT check as Staff attendance: never silently stop a ROT.
            if(DB::table('rot_sessions')->where('staff_id',$staffId)->where('status','Running')->exists()){
                throw ValidationException::withMessages(['attendance'=>'Pause or Complete your Running ROT before Check-Out.']);
            }

            $in=CarbonImmutable::parse((string)$a->check_in,'Asia/Kolkata');
            if($clock->getTimestamp()<$in->getTimestamp()){
                throw ValidationException::withMessages(['attendance'=>'Check-Out time cannot be earlier than Check-In.']);
            }
            $worked=round(($clock->getTimestamp()-$in->getTimestamp())/3600,2);
            $updated=DB::table('attendance')->where('id',(int)$a->id)
                ->whereNull('check_out')->update([
                    'check_out'=>$clock->format('Y-m-d H:i:s'),
                    'worked_hours'=>$worked,
                ]);
            if($updated!==1)throw ValidationException::withMessages([
                'attendance'=>'Attendance changed during Check-Out. Refresh and retry.',
            ]);
            $this->audit($staffId,(int)$user->id,(int)$a->id,'OWNER_ADMIN_SELF_CHECK_OUT',$clock);
        },3);

        return redirect()->route('erp.attendance')
            ->with('success','Your Check-Out and worked hours were saved using server time (IST).');
    }

    private function audit(int $staffId,int $userId,int $attendanceId,string $action,CarbonImmutable $clock): void
    {
        DB::table('hr_audit_logs')->insert([
            'staff_id'=>$staffId,
            'action'=>$action,
            'subject_type'=>'attendance',
            'subject_id'=>$attendanceId,
            'details'=>json_encode([
                'erp_auth_user_id'=>$userId,
                'source'=>'ERP_WEB_SELF_ATTENDANCE',
                'policy'=>'OWNER_ADMIN_REMOTE_EXEMPT_NO_GPS',
                'server_time_ist'=>$clock->format('Y-m-d H:i:s'),
            ],JSON_THROW_ON_ERROR),
            'created_at'=>$clock->format('Y-m-d H:i:s'),
        ]);
    }
}
