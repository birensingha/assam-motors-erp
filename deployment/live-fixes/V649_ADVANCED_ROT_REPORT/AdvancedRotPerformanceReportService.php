<?php
declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * V649 report-only advanced ROT performance. No database writes.
 * Operational times, historical corrections and financial values remain separate.
 */
final class AdvancedRotPerformanceReportService
{
    private const TZ='Asia/Kolkata';

    private function ts($date): ?int
    {
        if(!$date)return null;
        try{return CarbonImmutable::parse((string)$date,self::TZ)->getTimestamp();}
        catch(\Throwable){return null;}
    }

    private function pretty($date): string
    {
        $stamp=$this->ts($date);
        return $stamp ? CarbonImmutable::createFromTimestamp($stamp,self::TZ)->format('d-m-Y h:i:s A'):'Not Recorded';
    }

    private static function duration(int $sec): string
    {
        $sec=max(0,$sec);
        return sprintf('%02d:%02d:%02d',intdiv($sec,3600),intdiv($sec%3600,60),$sec%60);
    }

    public function rotCodes(): array
    {
        return DB::table('rot_sessions')->select('rot_code','rot_desc')
            ->whereNotNull('rot_code')->distinct()
            ->orderBy('rot_code')->limit(300)->get()->all();
    }

    private function dateValue($raw,string $field): string
    {
        $value=trim((string)($raw??''));
        if($value==='')return '';
        $parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        if(!$parsed || $parsed->format('Y-m-d')!==$value){
            throw ValidationException::withMessages([$field=>'Enter a valid YYYY-MM-DD date.']);
        }
        return $value;
    }

    public function report(Request $request): array
    {
        $from=$this->dateValue($request->query('from'),'from');
        $to=$this->dateValue($request->query('to'),'to');
        $now=CarbonImmutable::now(self::TZ);
        if($to!=='' && $to>$now->toDateString()){
            throw ValidationException::withMessages(['to'=>'Report To date cannot be in future.']);
        }
        if($from!=='' && $to!=='' && $from>$to){
            throw ValidationException::withMessages(['from'=>'From date must be before or equal to To date.']);
        }
        $staffId=(int)($request->query('staff_id')??0);
        $staffId=max(0,$staffId);
        $rotCode=mb_substr(trim((string)$request->query('rot_code','')),0,30);
        $vehicle=mb_substr(trim((string)$request->query('vehicle','')),0,30);
        $jobNo=mb_substr(trim((string)$request->query('job_card','')),0,35);
        $filter=['from'=>$from,'to'=>$to,'staff_id'=>$staffId,
            'rot_code'=>$rotCode,'vehicle'=>$vehicle,'job_card'=>$jobNo];
        $fromStamp=$from!==''?$this->ts($from.' 00:00:00'):null;
        $toStamp=$to!==''?$this->ts($to.' 23:59:59'):null;
        $startSql=$from!==''?$from.' 00:00:00':null;
        $endSql=$to!==''?$to.' 23:59:59':null;

        $query=DB::table('rot_sessions as rs')
            ->join('job_cards as jc','jc.id','=','rs.job_card_id')
            ->select('rs.*','jc.vehicle_reg_no','jc.vehicle_make','jc.vehicle_model',
                'jc.customer_name','jc.vehicle_inward_at','jc.created_at as jc_opened_at',
                'jc.closed_at as jc_closed_at','jc.status as jc_status','jc.invoice_id as jc_invoice_id');

        if($staffId){
            $query->where(function($q) use($staffId){
                $q->where('rs.staff_id',$staffId)
                  ->orWhereExists(function($sub)use($staffId){
                      $sub->selectRaw('1')->from('rot_mechanic_segments as seg')
                          ->whereRaw('BINARY seg.rot_session_id = BINARY rs.id')->where('seg.staff_id',$staffId);
                  })
                  ->orWhereExists(function($sub)use($staffId){
                      $sub->selectRaw('1')->from('rot_session_events as evt')
                          ->whereRaw('BINARY evt.session_id = BINARY rs.id')->where('evt.staff_id',$staffId);
                  });
            });
        }
        if($rotCode)$query->where('rs.rot_code',$rotCode);
        if($vehicle)$query->where('jc.vehicle_reg_no','like','%'.$vehicle.'%');
        if($jobNo)$query->where('jc.job_no','like','%'.$jobNo.'%');

        // Date selection includes historic multi-day tasks overlapping the window
        // and assignments or actual ROT events occurring during the window.
        if($startSql||$endSql){
            $startSql=$startSql??'2000-01-01 00:00:00';
            $endSql=$endSql??$now->format('Y-m-d H:i:s');
            $query->where(function($q)use($startSql,$endSql){
                $q->whereBetween('rs.created_at',[$startSql,$endSql])
                    ->orWhereBetween('rs.first_start_at',[$startSql,$endSql])
                    ->orWhereBetween('rs.completed_at',[$startSql,$endSql])
                    ->orWhere(function($span)use($startSql,$endSql){
                        $span->whereNotNull('rs.first_start_at')
                            ->where('rs.first_start_at','<=',$endSql)
                            ->where(function($last)use($startSql){
                                $last->whereNull('rs.completed_at')->orWhere('rs.completed_at','>=',$startSql);
                            });
                    })
                    ->orWhereExists(function($ev)use($startSql,$endSql){
                        $ev->selectRaw('1')->from('rot_session_events as de')
                            ->whereRaw('BINARY de.session_id = BINARY rs.id')
                            ->whereBetween('de.event_at',[$startSql,$endSql]);
                    });
            });
        }
        $raw=$query->orderByDesc('jc.id')->orderBy('rs.id')->limit(151)->get();
        $truncated=$raw->count()>150;
        $rows=$raw->take(150);
        $ids=$rows->pluck('id')->all();
        $events=$ids?DB::table('rot_session_events')->whereIn('session_id',$ids)
            ->orderBy('event_at')->orderBy('id')->get()
            ->groupBy('session_id') : collect();
        $segments=$ids?DB::table('rot_mechanic_segments')->whereIn('rot_session_id',$ids)
            ->orderBy('assigned_at')->get()->groupBy('rot_session_id'):collect();
        $staffIds=[];
        foreach($rows as $rot)if($rot->staff_id)$staffIds[(int)$rot->staff_id]=true;
        foreach($segments as $collection)foreach($collection as $seg)if($seg->staff_id)$staffIds[(int)$seg->staff_id]=true;
        foreach($events as $collection)foreach($collection as $e)if($e->staff_id)$staffIds[(int)$e->staff_id]=true;
        $staffMap=$staffIds?DB::table('staff')->whereIn('id',array_keys($staffIds))
            ->get(['id','name','staff_code'])->keyBy('id'):collect();

        // Never invent a vehicle gate-out timestamp. It does not exist in
        // job_cards; JC closure is shown separately and labelled as such.
        $list=[];
        $totals=['rot_count'=>0,'standard_seconds'=>0,'actual_seconds'=>0,
            'period_seconds'=>0,'elapsed_seconds'=>0,'nonproductive_seconds'=>0,
            'over_standard_seconds'=>0,'pause_count'=>0,'resume_count'=>0];
        foreach($rows as $rot){
            $id=(string)$rot->id;
            $evs=$events->get($id,collect());
            $segs=$segments->get($id,collect());
            $pauseJson=json_decode((string)($rot->pauses??'[]'),true);
            $pauseJson=is_array($pauseJson)?$pauseJson:[];
            $pauseRecords=[];$pauseSeconds=0;
            foreach($pauseJson as $pause){
                if(!is_array($pause)||empty($pause['paused_at']))continue;
                $pStart=$this->ts($pause['paused_at']);
                $pEnd=$this->ts($pause['resumed_at']??null);
                if(!$pEnd && $rot->status==='Completed'){
                    $pEnd=$this->ts($rot->completed_at?:$rot->end_time);
                }
                if(!$pEnd)$pEnd=$now->getTimestamp();
                $duration=$pStart?max(0,$pEnd-$pStart):0;
                $pauseSeconds+=$duration;
                $pauseRecords[]=[
                    'at'=>$pStart,'time'=>$this->pretty($pause['paused_at']),
                    'resumed_at'=>$this->pretty($pause['resumed_at']??null),
                    'duration'=>self::duration($duration),
                    'reason'=>trim((string)($pause['reason']??'Reason not recorded')),
                    'note'=>trim((string)($pause['note']??'')),
                ];
            }
            $history=[];$pauseCount=0;$resumeCount=0;$staffActions=[];
            $periodSeconds=0;$lastStart=null;$runningOwner=null;
            $periodFrom=$fromStamp??0;
            $periodTo=($toStamp??$now->getTimestamp());
            foreach($evs as $e){
                $action=strtolower(trim((string)$e->action));
                $stamp=$this->ts($e->event_at);
                if(!$stamp)continue;
                if(in_array($action,['start','resume'],true)){
                    if($lastStart===null){$lastStart=$stamp;$runningOwner=(int)$e->staff_id;}
                }elseif(in_array($action,['pause','complete','mechanic_change','reassign'],true)){
                    if($lastStart!==null){
                        // Only count selected staff's work in period when staff filter chosen.
                        if(!$staffId || $staffId===$runningOwner){
                            $periodSeconds+=max(0,min($stamp,$periodTo)-max($lastStart,$periodFrom));
                        }
                        $lastStart=null;$runningOwner=null;
                    }
                }
                if($action==='pause')$pauseCount++;
                if($action==='resume')$resumeCount++;
                $reason='';$note='';
                if($action==='pause'){
                    foreach($pauseRecords as $p)if($p['at']!==null && abs($p['at']-$stamp)<=5){
                        $reason=$p['reason'];$note=$p['note'];break;
                    }
                    if(!$reason)$reason='Not recorded';
                }
                $actor=$staffMap->get((int)$e->staff_id);
                $who=(int)($e->staff_id??0);
                if(!isset($staffActions[$who])){
                    $staffActions[$who]=[
                        'name'=>$actor->name??('Staff ID '.$who),
                        'code'=>$actor->staff_code??'—','start'=>0,'pause'=>0,
                        'resume'=>0,'complete'=>0,'reasons'=>[],
                    ];
                }
                if(isset($staffActions[$who][$action])){
                    $staffActions[$who][$action]++;
                }
                if($action==='pause' && $reason!==''){
                    $staffActions[$who]['reasons'][]=$reason;
                }
                $history[]=['time'=>$this->pretty($e->event_at),'date'=>$e->event_at,
                    'timestamp'=>$stamp,'action'=>strtoupper($action),
                    'staff_name'=>$actor->name??('Staff ID '.(int)$e->staff_id),
                    'reason'=>$reason,'note'=>$note];
            }
            if($lastStart!==null && (!$staffId || $staffId===$runningOwner)){
                $periodSeconds+=max(0,min($now->getTimestamp(),$periodTo)-max($lastStart,$periodFrom));
            }
            $fullActual=max(0,(int)$rot->total_seconds);
            if($rot->status==='Running' && $rot->start_time){
                $fullActual+=max(0,$now->getTimestamp()-($this->ts($rot->start_time)??$now->getTimestamp()));
            }
            $standard=max(0,(int)round((float)$rot->std_hours*3600));
            $begin=$this->ts($rot->first_start_at);
            $end=$this->ts($rot->completed_at?:($rot->status==='Completed'?$rot->end_time:null))
                ??$now->getTimestamp();
            $elapsed=$begin?max(0,$end-$begin):0;
            $nonProductive=max(0,$elapsed-$fullActual);
            $overStandard=max(0,$fullActual-$standard);
            $staffWork=[];
            foreach($segs as $seg){
                $staff=$staffMap->get((int)$seg->staff_id);
                $sec=max(0,(int)$seg->productive_seconds);
                if($seg->running_started_at){
                    $sec+=max(0,$now->getTimestamp()-($this->ts($seg->running_started_at)??$now->getTimestamp()));
                }
                $staffWork[]=[
                    'name'=>$staff->name??(string)$seg->staff_name,
                    'code'=>$staff->staff_code??(string)$seg->staff_code,
                    'assigned_at'=>$this->pretty($seg->assigned_at),
                    'first_started_at'=>$this->pretty($seg->first_started_at),
                    'ended_at'=>$this->pretty($seg->ended_at),
                    'productive'=>self::duration($sec),
                    'seconds'=>$sec,'reason'=>$seg->end_reason??'',
                ];
            }
            $inRangeEvents=array_values(array_filter($history,
                fn($h)=>$h['timestamp'] >= $periodFrom && $h['timestamp'] <= $periodTo));
            $selectedWork=$staffId ? (int)array_sum(array_map(
                fn($x)=>in_array($x['code'],[$staffMap->get($staffId)->staff_code??''],true)?$x['seconds']:0,
                $staffWork)) : $fullActual;
            // Civil calendar days (IST), not blocks of 24h. Unstarted Assigned
            // ROTs must show how many days they have remained pending.
            $dayOrigin=$begin??$this->ts($rot->created_at);
            $spanDays=$dayOrigin?((int)CarbonImmutable::createFromTimestamp($dayOrigin,self::TZ)
                ->startOfDay()->diffInDays(CarbonImmutable::createFromTimestamp($end,self::TZ)
                    ->startOfDay())+1):0;
            $line=[
                'id'=>$id,'job_card_id'=>(int)$rot->job_card_id,
                'job_no'=>$rot->job_card_no,'rot_code'=>$rot->rot_code,
                'rot_desc'=>$rot->rot_desc,'vehicle'=>$rot->vehicle_reg_no,
                'staff'=>$rot->staff_name,'staff_id'=>$rot->staff_id,
                'current_status'=>$rot->status,'jc_status'=>$rot->jc_status,
                'std'=>self::duration($standard),'std_seconds'=>$standard,
                'actual'=>self::duration($fullActual),'actual_seconds'=>$fullActual,
                'period'=>self::duration($periodSeconds),'period_seconds'=>$periodSeconds,
                'selected_staff_work'=>self::duration($selectedWork),
                'elapsed'=>self::duration($elapsed),'elapsed_seconds'=>$elapsed,
                'nonproductive'=>self::duration($nonProductive),'nonproductive_seconds'=>$nonProductive,
                'over_standard'=>self::duration($overStandard),'over_standard_seconds'=>$overStandard,
                'pause_duration'=>self::duration($pauseSeconds),
                'pause_count'=>$pauseCount,'resume_count'=>$resumeCount,
                'period_pause_count'=>count(array_filter($inRangeEvents,fn($h)=>$h['action']==='PAUSE')),
                'period_resume_count'=>count(array_filter($inRangeEvents,fn($h)=>$h['action']==='RESUME')),
                'start'=>$this->pretty($rot->first_start_at),
                'end'=>$this->pretty($rot->completed_at?:$rot->end_time),
                'days_since_start'=>$spanDays,
                'history'=>$history,'pause_records'=>$pauseRecords,'staff_segments'=>$staffWork,
                'staff_activity'=>array_values($staffActions),
                'jc_opened'=>$this->pretty($rot->jc_opened_at),
                'vehicle_in'=>$this->pretty($rot->vehicle_inward_at),
                'jc_closed'=>$this->pretty($rot->jc_closed_at),
                'vehicle_out'=>'Not Recorded',
            ];
            $list[]=$line;
            $totals['rot_count']++;
            $totals['standard_seconds']+=$standard;
            $totals['actual_seconds']+=$fullActual;
            $totals['period_seconds']+=$periodSeconds;
            $totals['elapsed_seconds']+=$elapsed;
            $totals['nonproductive_seconds']+=$nonProductive;
            $totals['over_standard_seconds']+=$overStandard;
            $totals['pause_count']+=$pauseCount;
            $totals['resume_count']+=$resumeCount;
        }
        $groups=[];
        foreach($list as $row){
            $number=(string)$row['job_no'];
            if(!isset($groups[$number])){
                $groups[$number]=[
                    'job_no'=>$number,'job_card_id'=>$row['job_card_id'],
                    'vehicle'=>$row['vehicle'],'customer'=>'',
                    'vehicle_in'=>$row['vehicle_in'],'vehicle_out'=>$row['vehicle_out'],
                    'jc_opened'=>$row['jc_opened'],'jc_closed'=>$row['jc_closed'],
                    'jc_status'=>$row['jc_status'],'rots'=>[],
                ];
            }
            $groups[$number]['rots'][]=$row;
        }
        $staffIds=array_keys($staffIds);
        $attendances=$staffIds?DB::table('attendance')
            ->whereIn('staff_id',$staffIds)
            ->when($from,fn($q)=>$q->whereDate('date','>=',$from))
            ->when($to,fn($q)=>$q->whereDate('date','<=',$to))
            ->orderByDesc('date')->limit(1800)->get():collect();
        $attendanceList=[];
        foreach($attendances as $att){
            if($staffId && (int)$att->staff_id!==$staffId)continue;
            $staff=$staffMap->get((int)$att->staff_id);
            $attendanceList[]=[
                'staff'=>$staff->name??$att->staff_name,
                'code'=>$staff->staff_code??$att->staff_code,
                'date'=>(string)$att->date,
                'checkin'=>$this->pretty($att->check_in),
                'checkout'=>$this->pretty($att->check_out),
                'duty_hours'=>$att->worked_hours,
            ];
        }
        foreach($totals as $key=>$value){
            if(str_ends_with($key,'_seconds'))$totals[substr($key,0,-8).'_formatted']=self::duration((int)$value);
        }
        return [
            'filters'=>$filter,'groups'=>array_values($groups),
            'totals'=>$totals,'limited'=>$truncated,
            'attendance'=>$attendanceList,'count'=>count($list),
            'note'=>'Actual = full saved ROT productive time; Selected period = Start/Resume activity within date range. Nonproductive elapsed includes waiting, nights and off-duty; not all is staff waste.',
        ];
    }
}
