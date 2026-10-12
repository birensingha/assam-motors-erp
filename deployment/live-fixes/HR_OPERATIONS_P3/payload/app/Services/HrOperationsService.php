<?php
namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Native ERP HR operational read models and audited corrections. */
final class HrOperationsService
{
    public function month(string $raw): string
    {
        if (!preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/', $raw)) {
            throw ValidationException::withMessages(['month' => 'Valid month (YYYY-MM) required.']);
        }
        $selected = Carbon::createFromFormat('!Y-m', $raw, 'Asia/Kolkata')->startOfMonth();
        $oldest = now('Asia/Kolkata')->startOfMonth()->subMonths(24);
        if ($selected->lt($oldest) || $selected->gt(now('Asia/Kolkata')->startOfMonth())) {
            throw ValidationException::withMessages(['month' => 'Select the current or one of the previous 24 months.']);
        }
        return $raw;
    }

    public function report(string $month, int $staffId = 0): array
    {
        $month = $this->month($month);
        $start = $month.'-01';
        $end = Carbon::parse($start)->endOfMonth()->toDateString();
        $staff = DB::table('staff')->select('id', 'staff_code', 'name', 'role', 'department', 'status')
            ->where('status', 'active')->orderBy('name')->get();
        if ($staffId && !$staff->contains('id', $staffId)) {
            throw ValidationException::withMessages(['staff_id' => 'Active staff not found.']);
        }
        $pick = static function ($q) use ($staffId) { return $staffId ? $q->where('staff_id', $staffId) : $q; };
        $attendance = $pick(DB::table('attendance')->whereBetween('date', [$start,$end]))
            ->select('id', 'staff_id', 'date', 'status', 'check_in', 'check_out', 'worked_hours', 'late', 'notes')
            ->orderByDesc('date')->orderBy('staff_id')->limit(1400)->get();
        $byId = $staff->keyBy('id');
        $attendance = $attendance->map(function ($row) use ($byId) {
            $person = $byId->get($row->staff_id);
            $row->staff_name = $person->name ?? 'Inactive / historical staff';
            $row->staff_code = $person->staff_code ?? '';
            return $row;
        });
        $hours = [];
        foreach ($attendance as $row) {
            $hours[$row->staff_id] = ($hours[$row->staff_id] ?? 0.0) + (float)($row->worked_hours ?? 0);
        }
        $segments = DB::table('rot_mechanic_segments as seg')
            ->leftJoin('rot_sessions as rot', 'rot.id', '=', 'seg.rot_session_id')
            ->whereRaw('COALESCE(seg.first_started_at, seg.assigned_at) >= ?', [$start.' 00:00:00'])
            ->whereRaw('COALESCE(seg.first_started_at, seg.assigned_at) < ?', [Carbon::parse($end)->addDay()->toDateString().' 00:00:00'])
            ->select('seg.id','seg.staff_id','seg.staff_code','seg.staff_name','seg.rot_session_id',
                'seg.first_started_at','seg.ended_at','seg.productive_seconds','seg.end_reason',
                'rot.job_card_no','rot.rot_code','rot.rot_desc','rot.std_hours','rot.status')
            ->when($staffId > 0, fn($q) => $q->where('seg.staff_id', $staffId))
            ->orderByDesc('seg.first_started_at')->limit(1500)->get();
        $sessionIds = $segments->pluck('rot_session_id')->filter()->unique()->values()->all();
        $teamCounts = [];
        if ($sessionIds) {
            $teamCounts = DB::table('rot_mechanic_segments')->whereIn('rot_session_id', $sessionIds)
                ->select('rot_session_id')->selectRaw('COUNT(DISTINCT staff_id) AS mechanics')
                ->groupBy('rot_session_id')->pluck('mechanics','rot_session_id')->all();
        }
        $productive = []; $standard = []; $tat = []; $rows = [];
        foreach ($segments as $s) {
            $seconds = max(0, (int)($s->productive_seconds ?? 0));
            $tatSeconds = null;
            if ($s->first_started_at && $s->ended_at) {
                $tatSeconds = max(0, Carbon::parse($s->first_started_at)->diffInSeconds(Carbon::parse($s->ended_at), false));
            }
            $shared = (int)($teamCounts[$s->rot_session_id] ?? 0) > 1;
            $prodH = round($seconds/3600, 2);
            $stdH = !$shared && $s->ended_at && $s->std_hours !== null ? max(0,(float)$s->std_hours) : null;
            $row = [
                'staff_id'=>(int)$s->staff_id,'staff_name'=>$s->staff_name ?: ($byId->get($s->staff_id)->name ?? 'Staff'),
                'job_no'=>$s->job_card_no,'rot_code'=>$s->rot_code,'description'=>$s->rot_desc,
                'status'=>$s->status,'productive_hours'=>$prodH,'tat_hours'=>$tatSeconds === null?null:round($tatSeconds/3600,2),
                'standard_hours'=>$stdH,'shared_rot'=>$shared,'started_at'=>$s->first_started_at,'ended_at'=>$s->ended_at,
            ];
            $rows[] = $row;
            $productive[$s->staff_id] = ($productive[$s->staff_id] ?? 0) + $prodH;
            if ($stdH !== null) $standard[$s->staff_id] = ($standard[$s->staff_id] ?? 0) + $stdH;
            if ($tatSeconds !== null) $tat[$s->staff_id] = ($tat[$s->staff_id] ?? 0) + $tatSeconds/3600;
        }
        $performance = [];
        foreach ($staff as $person) {
            if ($staffId && (int)$person->id !== $staffId) continue;
            $sid=(int)$person->id;
            $worked = round((float)($hours[$sid] ?? 0),2);
            $prod = round((float)($productive[$sid] ?? 0),2);
            $std=round((float)($standard[$sid] ?? 0),2);
            $performance[] = [
                'staff_id'=>$sid, 'staff_code'=>$person->staff_code,'name'=>$person->name,
                'duty_hours'=>$worked,'productive_hours'=>$prod,'standard_hours_sole_staff'=>$std,
                'tat_hours'=>round((float)($tat[$sid] ?? 0),2),
                'productivity_percent'=>$worked>0 ? round(100*$prod/$worked,1) : null,
                'efficiency_percent'=>$prod>0 && $std>0 ? round(100*$std/$prod,1) : null,
            ];
        }
        $ledgers = $pick(DB::table('hr_leave_entries'))
            ->select('id','staff_id','entry_date','kind','days','approval_status','application_status','reason','created_at')
            ->orderByDesc('id')->limit(120)->get();
        $leaveTotals = DB::table('hr_leave_entries')
            ->select('staff_id')->selectRaw('SUM(days) AS balance, SUM(CASE WHEN kind = ? THEN days ELSE 0 END) AS earned, SUM(CASE WHEN kind = ? THEN -days ELSE 0 END) AS used, SUM(CASE WHEN kind = ? AND application_status = ? THEN -days ELSE 0 END) AS unfiled', ['earned','used','used','not_submitted'])
            ->groupBy('staff_id')->get()->keyBy('staff_id');
        $balances=[];
        foreach ($staff as $person) {
            if ($staffId && (int)$person->id!==$staffId) continue;
            $x=$leaveTotals->get($person->id);
            $balances[]=['staff_id'=>(int)$person->id,'staff_code'=>$person->staff_code,'name'=>$person->name,
                'earned'=>round((float)($x->earned ?? 0),2),'used'=>round((float)($x->used ?? 0),2),
                'balance'=>round((float)($x->balance ?? 0),2),'without_application'=>round((float)($x->unfiled ?? 0),2)];
        }
        return compact('month','staffId','staff','attendance','rows','performance','ledgers','balances');
    }

    public function correctAttendance(array $data, int $actorStaffId): int
    {
        $sid = (int)$data['staff_id']; $date=(string)$data['date'];
        $today=now('Asia/Kolkata')->startOfDay();
        $day=Carbon::createFromFormat('!Y-m-d',$date,'Asia/Kolkata');
        if (!$day || $day->gte($today) || $day->lt($today->copy()->subMonths(24))) {
            throw ValidationException::withMessages(['date'=>'Manual corrections must be for a past date within 24 months. Today requires actual GPS check-in/out.']);
        }
        $person=DB::table('staff')->where('id',$sid)->where('status','active')->first(['staff_code','name']);
        if(!$person) throw ValidationException::withMessages(['staff_id'=>'Active employee not found.']);
        $status=$data['status'];
        $checkIn=null; $checkOut=null; $hours=0;
        if (in_array($status,['present','late'],true)) {
            if (empty($data['check_in'])||empty($data['check_out'])) {
                throw ValidationException::withMessages(['check_in'=>'Present/Late corrections require both check-in and check-out times.']);
            }
            $checkIn=$date.' '.$data['check_in'].':00';
            $checkOut=$date.' '.$data['check_out'].':00';
            $seconds=Carbon::parse($checkIn)->diffInSeconds(Carbon::parse($checkOut),false);
            if ($seconds <= 0 || $seconds > 18*3600) throw ValidationException::withMessages(['check_out'=>'Check-out must be after check-in and within 18 hours.']);
            $hours=round($seconds/3600,2);
        }
        return DB::transaction(function () use($sid,$date,$person,$status,$checkIn,$checkOut,$hours,$data,$actorStaffId){
            $found=DB::table('attendance')->where('staff_id',$sid)->where('date',$date)->lockForUpdate()->get();
            if($found->count()>1) abort(409,'Multiple attendance records exist. Correct duplicates before HR editing.');
            $before=$found->first();
            $after=['staff_id'=>$sid,'staff_code'=>$person->staff_code,'staff_name'=>$person->name,
                'date'=>$date,'status'=>$status,'check_in'=>$checkIn,'check_out'=>$checkOut,
                'worked_hours'=>$hours,'late'=>$status==='late'?1:0,
                'notes'=>'HR correction: '.trim((string)$data['reason'])];
            if($before){$id=(int)$before->id;DB::table('attendance')->where('id',$id)->update($after);}
            else{$after['created_at']=now();$id=(int)DB::table('attendance')->insertGetId($after);}
            DB::table('hr_audit_logs')->insert(['staff_id'=>$actorStaffId,'action'=>'ATTENDANCE_MANUAL_CORRECTION',
                'subject_type'=>'attendance','subject_id'=>$id,
                'details'=>json_encode(['reason'=>trim((string)$data['reason']),'date'=>$date,'staff_id'=>$sid,
                    'before'=>$before,'after'=>$after],JSON_THROW_ON_ERROR),'created_at'=>now()]);
            return $id;
        });
    }

    public function recordLeave(array $data,int $actorStaffId): int
    {
        $sid=(int)$data['staff_id'];$days=(float)$data['days'];
        if(!DB::table('staff')->where('id',$sid)->where('status','active')->exists()) {
            throw ValidationException::withMessages(['staff_id'=>'Active employee not found.']);
        }
        $date=Carbon::createFromFormat('!Y-m-d',$data['entry_date'],'Asia/Kolkata');
        if(!$date || $date->gt(now('Asia/Kolkata')->addDay()) || $date->lt(now('Asia/Kolkata')->subYears(3))) {
            throw ValidationException::withMessages(['entry_date'=>'Leave date outside allowed correction period.']);
        }
        $signed=$data['kind']==='earned'?$days:-$days;
        $application=$data['kind']==='earned'?'not_applicable':$data['application_status'];
        $approval=$data['kind']==='earned'?'approved':$data['approval_status'];
        return DB::transaction(function ()use($sid,$signed,$date,$application,$approval,$data,$actorStaffId){
            $id=(int)DB::table('hr_leave_entries')->insertGetId([
                'staff_id'=>$sid,'entry_date'=>$date->toDateString(),'kind'=>$data['kind'],
                'days'=>$signed,'approval_status'=>$approval,'application_status'=>$application,
                'reason'=>trim($data['reason']),'created_by_staff_id'=>$actorStaffId,'created_at'=>now(),
            ]);
            DB::table('hr_audit_logs')->insert(['staff_id'=>$actorStaffId,'action'=>'LEAVE_LEDGER_ENTRY',
                'subject_type'=>'hr_leave_entry','subject_id'=>$id,
                'details'=>json_encode(['staff_id'=>$sid,'days'=>$signed,'kind'=>$data['kind'],
                    'approval_status'=>$approval,'application_status'=>$application,'reason'=>$data['reason']],JSON_THROW_ON_ERROR),
                'created_at'=>now()]);
            return $id;
        });
    }
}