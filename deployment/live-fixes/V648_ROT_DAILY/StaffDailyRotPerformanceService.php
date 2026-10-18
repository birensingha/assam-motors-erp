<?php
declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * V648 Staff Daily ROT Register.
 * Uses immutable ROT events for date-scoped productive activity, rather than
 * attributing an entire lifetime ROT total to its completion date.
 * Server-time Asia/Kolkata; no writes, no guessing when event history is absent.
 */
final class StaffDailyRotPerformanceService
{
    private const TZ='Asia/Kolkata';

    public function staffList(): Collection
    {
        return DB::table('staff')->whereRaw("LOWER(COALESCE(status,''))='active'")
            ->orderBy('name')->get(['id','staff_code','name']);
    }

    public function pending(int $limit=18): Collection
    {
        return DB::table('rot_sessions as rs')
            ->leftJoin('job_cards as jc','jc.id','=','rs.job_card_id')
            ->select('rs.id','rs.rot_code','rs.rot_desc','rs.job_card_no','rs.job_card_id','rs.staff_id',
                'rs.staff_name','rs.staff_code','rs.status','rs.std_hours','rs.total_seconds',
                'rs.updated_at','jc.vehicle_reg_no','jc.status as jc_status','jc.invoice_id')
            ->whereIn('rs.status',['Assigned','Paused'])
            ->orderByRaw("FIELD(rs.status,'Paused','Assigned')")
            ->orderBy('rs.updated_at')->limit($limit)->get();
    }

    public function pendingCount(): array
    {
        $counts=DB::table('rot_sessions')->whereIn('status',['Assigned','Paused','Running'])
            ->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c','status');
        return ['assigned'=>(int)($counts['Assigned']??0),
            'paused'=>(int)($counts['Paused']??0),
            'running'=>(int)($counts['Running']??0)];
    }

    /** @return array{date:string,staff:object,attendance:mixed,summary:array,rot_rows:Collection,timeline:Collection,warnings:array} */
    public function daily(int $staffId,string $date): array
    {
        $staff=DB::table('staff')->where('id',$staffId)->first(['id','staff_code','name','status']);
        if(!$staff)throw new \InvalidArgumentException('Staff not found');
        $start=CarbonImmutable::parse($date.' 00:00:00',self::TZ);
        $tomorrow=$start->addDay();
        $now=CarbonImmutable::now(self::TZ);
        $cutoff=$tomorrow->lessThan($now)?$tomorrow:$now;
        $sDay=$start->getTimestamp();$eDay=$cutoff->getTimestamp();
        $attendance=DB::table('attendance')->where('staff_id',$staffId)
            ->whereDate('date',$date)->orderByDesc('id')->first();
        $checkIn=$attendance && $attendance->check_in
            ? CarbonImmutable::parse($attendance->check_in,self::TZ)->getTimestamp() : null;
        $checkOut=$attendance && $attendance->check_out
            ? CarbonImmutable::parse($attendance->check_out,self::TZ)->getTimestamp() : null;

        // Include earlier events to rebuild state of a ROT that spans midnight.
        // Bounded to avoid locking the dashboard on pathological old histories.
        $all=DB::table('rot_session_events')->where('staff_id',$staffId)
            ->where('event_at','<',$tomorrow->format('Y-m-d H:i:s'))
            ->orderByDesc('event_at')->orderByDesc('id')->limit(6000)->get()
            ->reverse()->values();
        $active=[];$paused=[];$durations=[];$pauses=[];$pausesCount=[];$resumes=[];$autoPause=[];
        $timeline=[];$rotIds=[];
        $warnings=[];
        if($all->count()>=6000)$warnings[]='Event history exceeds 6000 entries; older segments may require an archived report.';

        $overlap=function(int $a,int $b)use($sDay,$eDay,$checkIn,$checkOut):int{
            $from=max($a,$sDay,$checkIn??$sDay);
            $until=min($b,$eDay,$checkOut??$eDay);
            return max(0,$until-$from);
        };
        $sessionsForPauses=[];
        foreach($all as $e)$rotIds[(string)$e->session_id]=true;
        if($rotIds){
            $sessionsForPauses=DB::table('rot_sessions')->whereIn('id',array_keys($rotIds))
                ->get(['id','pauses'])->keyBy('id')->all();
        }
        $pauseSource=function(string $sessionId,string $stamp)use($sessionsForPauses):string{
            $rot=$sessionsForPauses[$sessionId]??null;
            if(!$rot)return 'MANUAL';
            $parsed=json_decode((string)($rot->pauses??'[]'),true);
            foreach(is_array($parsed)?$parsed:[] as $pause){
                if(empty($pause['paused_at']))continue;
                try{
                    $distance=abs(CarbonImmutable::parse($pause['paused_at'],'Asia/Kolkata')->getTimestamp()
                        -CarbonImmutable::parse($stamp,'Asia/Kolkata')->getTimestamp());
                    if($distance<=5){
                        return strtoupper(trim((string)($pause['reason']??'')))==='AUTO_CHECK_OUT'
                            ? 'AUTO_CHECK_OUT':'MANUAL';
                    }
                }catch(\Throwable){}
            }
            return 'MANUAL';
        };

        foreach($all as $e){
            $session=(string)$e->session_id;
            $action=strtolower(trim((string)$e->action));
            $when=CarbonImmutable::parse((string)$e->event_at,self::TZ)->getTimestamp();
            if($action==='start'||$action==='resume'){
                if(isset($paused[$session])){
                    $pauses[$session]=($pauses[$session]??0)+$overlap($paused[$session],$when);
                    unset($paused[$session]);
                }
                if(!isset($active[$session]))$active[$session]=$when;
                if($action==='resume' && $when >= $sDay && $when < $eDay){
                    $resumes[$session]=($resumes[$session]??0)+1;
                }
            } elseif(in_array($action,['pause','complete','change_mechanic','mechanic_change','reassign'],true)){
                if(isset($active[$session])){
                    $durations[$session]=($durations[$session]??0)+$overlap($active[$session],$when);
                    unset($active[$session]);
                }
                if($action==='pause'){
                    $paused[$session]=$when;
                    if($when >= $sDay && $when < $eDay){
                        $pausesCount[$session]=($pausesCount[$session]??0)+1;
                        if($pauseSource($session,(string)$e->event_at)==='AUTO_CHECK_OUT'){
                            $autoPause[$session]=($autoPause[$session]??0)+1;
                        }
                    }
                }else{
                    if(isset($paused[$session])){
                        $pauses[$session]=($pauses[$session]??0)+$overlap($paused[$session],$when);
                        unset($paused[$session]);
                    }
                }
            }
            if($when >= $sDay && $when < $eDay){
                $timeline[]=[
                    'at'=>$when,'source'=>'ROT','action'=>strtoupper($action),
                    'session_id'=>$session,
                    'note'=>$action==='pause'?$pauseSource($session,(string)$e->event_at):null,
                ];
            }
        }
        foreach($active as $session=>$from){
            $durations[$session]=($durations[$session]??0)+$overlap($from,$eDay);
        }
        foreach($paused as $session=>$from){
            $pauses[$session]=($pauses[$session]??0)+$overlap($from,$eDay);
        }

        // Assigned but untouched ROTs still appear in the day's work list when relevant.
        $segments=DB::table('rot_mechanic_segments')
            ->where('staff_id',$staffId)
            ->where('assigned_at','<',$tomorrow->format('Y-m-d H:i:s'))
            ->where(function($q)use($start){
                $q->whereNull('ended_at')->orWhere('ended_at','>=',$start->format('Y-m-d H:i:s'));
            })->get(['rot_session_id','assigned_at']);
        foreach($segments as $segment){
            $rotIds[(string)$segment->rot_session_id]=true;
            $ts=CarbonImmutable::parse($segment->assigned_at,self::TZ)->getTimestamp();
            if($ts>=$sDay && $ts<$eDay)$timeline[]=[
                'at'=>$ts,'source'=>'ROT','action'=>'ASSIGNED','session_id'=>(string)$segment->rot_session_id,'note'=>null,
            ];
        }
        $current=DB::table('rot_sessions')->where('staff_id',$staffId)
            ->whereIn('status',['Assigned','Running','Paused'])
            ->where('created_at','<',$tomorrow->format('Y-m-d H:i:s'))
            ->get(['id']);
        foreach($current as $row)$rotIds[(string)$row->id]=true;

        $rotRows=collect();
        if($rotIds){
            $rotRows=DB::table('rot_sessions as rs')
                ->leftJoin('job_cards as jc','jc.id','=','rs.job_card_id')
                ->whereIn('rs.id',array_keys($rotIds))
                ->select('rs.id','rs.rot_code','rs.rot_desc','rs.job_card_no','rs.staff_id','rs.status',
                    'rs.std_hours','rs.total_seconds','rs.first_start_at','rs.completed_at',
                    'jc.vehicle_reg_no','jc.status as jc_status')
                ->orderBy('rs.job_card_no')->get()
                ->map(function($r)use($durations,$pauses,$resumes,$autoPause,&$pausesCount){
                    $id=(string)$r->id;
                    $r->daily_productive_seconds=(int)($durations[$id]??0);
                    $r->daily_paused_seconds=(int)($pauses[$id]??0);
                    $r->daily_pause_count=(int)($pausesCount[$id]??0);
                    $r->daily_resume_count=(int)($resumes[$id]??0);
                    $r->daily_auto_pause_count=(int)($autoPause[$id]??0);
                    return $r;
                });
        }
        $meta=$rotRows->keyBy('id');
        foreach($timeline as &$entry){
            $rot=$meta[$entry['session_id']]??null;
            $entry['rot_code']=$rot->rot_code??$entry['session_id'];
            $entry['job_no']=$rot->job_card_no??'—';
        }unset($entry);
        if($checkIn)$timeline[]=['at'=>$checkIn,'source'=>'ATTENDANCE','action'=>'CHECK-IN','session_id'=>null,'rot_code'=>'—','job_no'=>'—','note'=>null];
        if($checkOut)$timeline[]=['at'=>$checkOut,'source'=>'ATTENDANCE','action'=>'CHECK-OUT','session_id'=>null,'rot_code'=>'—','job_no'=>'—','note'=>null];
        usort($timeline,fn($a,$b)=>$a['at']<=>$b['at']);
        $duty=$checkIn?max(0,min($checkOut??$eDay,$eDay)-max($checkIn,$sDay)):0;
        if(!$attendance)$warnings[]='No Check-In / Attendance record on this date.';
        $productive=(int)$rotRows->sum('daily_productive_seconds');
        $pausedSeconds=(int)$rotRows->sum('daily_paused_seconds');
        return [
            'date'=>$date,'staff'=>$staff,'attendance'=>$attendance,
            'summary'=>[
                'duty_seconds'=>$duty,'productive_seconds'=>$productive,'paused_seconds'=>$pausedSeconds,
                'unaccounted_seconds'=>max(0,$duty-$productive),
                'rot_count'=>$rotRows->count(),'pause_count'=>$rotRows->sum('daily_pause_count'),
                'resume_count'=>$rotRows->sum('daily_resume_count'),
                'auto_pause_count'=>$rotRows->sum('daily_auto_pause_count'),
                'pending_count'=>$rotRows->filter(fn($r)=>in_array($r->status,['Assigned','Paused'],true))->count(),
            ],
            'rot_rows'=>$rotRows,'timeline'=>collect($timeline),'warnings'=>$warnings,
        ];
    }
}
