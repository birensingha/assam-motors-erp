<?php
if ($argc < 2) { fwrite(STDERR,"Usage: php PATCH_FIX28.php /path/to/app\n"); exit(2); }
$root=rtrim($argv[1],'/');
$path="$root/public/legacy/api/staff-rot-v3.php";
$c=file_get_contents($path);

$old=<<<'OLD'
function s3public(PDO $db,array $r):array{
    $first=s3firstStart($db,$r);$complete=s3completedAt($db,$r);
    return [
        'id'=>$r['id'],'job_card_no'=>$r['job_card_no'],'rot_code'=>$r['rot_code'],'rot_desc'=>$r['rot_desc'],'rot_note'=>$r['rot_note']??'', 'std_hours'=>(float)$r['std_hours'],
        'status'=>$r['status'],'start_time'=>$r['start_time'],'first_start_at'=>$first,'end_time'=>$r['end_time'],'completed_at'=>$complete,
        'total_seconds'=>(int)$r['total_seconds'],'live_seconds'=>s3live($r),'created_at'=>$r['created_at'],'updated_at'=>$r['updated_at'],
        'vehicle_reg_no'=>$r['vehicle_reg_no']??null,'vehicle_make'=>$r['vehicle_make']??null,'vehicle_model'=>$r['vehicle_model']??null,
        'job_status'=>$r['job_status']??null,'job_work_status'=>s3normStatus($r['job_status']??''),
        'can_start'=>s3normStatus($r['job_status']??'')==='in-progress'
    ];
}
OLD;
$new=<<<'NEW'
function s3public(PDO $db,array $r):array{
    $first=s3firstStart($db,$r);$complete=s3completedAt($db,$r);
    $live=s3live($r);
    $stdSeconds=max(0,(int)round(((float)($r['std_hours']??0))*3600));
    $remaining=$stdSeconds>0?max(0,$stdSeconds-$live):0;
    $overtime=$stdSeconds>0?max(0,$live-$stdSeconds):0;
    $progress=$stdSeconds>0?(int)round(($live/$stdSeconds)*100):0;
    $status=(string)($r['status']??'');
    $nearThreshold=$stdSeconds>0?max(300,(int)round($stdSeconds*0.10)):0;
    $nearLimit=$status==='Running'&&$stdSeconds>0&&$overtime===0&&$remaining<=$nearThreshold;
    $targetEndAt=$status==='Running'&&$stdSeconds>0?date('Y-m-d H:i:s',time()+$remaining):null;
    $pauses=s3pauses($r['pauses']??'[]');
    $latestPause=$pauses?end($pauses):null;
    $pauseReason=is_array($latestPause)?trim((string)($latestPause['reason']??'')):'';
    $pauseNote=is_array($latestPause)?trim((string)($latestPause['note']??'')):'';
    return [
        'id'=>$r['id'],'job_card_no'=>$r['job_card_no'],'rot_code'=>$r['rot_code'],'rot_desc'=>$r['rot_desc'],'rot_note'=>$r['rot_note']??'', 'std_hours'=>(float)$r['std_hours'],
        'standard_seconds'=>$stdSeconds,'remaining_seconds'=>$remaining,'overtime_seconds'=>$overtime,'progress_percent'=>$progress,
        'near_standard_limit'=>$nearLimit,'target_end_at'=>$targetEndAt,'time_state'=>$overtime>0?'OVERTIME':($stdSeconds>0?'WITHIN_STANDARD':'NO_STANDARD'),
        'status'=>$status,'start_time'=>$r['start_time'],'first_start_at'=>$first,'end_time'=>$r['end_time'],'completed_at'=>$complete,
        'total_seconds'=>(int)$r['total_seconds'],'live_seconds'=>$live,'created_at'=>$r['created_at'],'updated_at'=>$r['updated_at'],
        'pause_count'=>count($pauses),'pause_reason'=>$pauseReason!==''?$pauseReason:null,'pause_note'=>$pauseNote!==''?$pauseNote:null,
        'vehicle_reg_no'=>$r['vehicle_reg_no']??null,'vehicle_make'=>$r['vehicle_make']??null,'vehicle_model'=>$r['vehicle_model']??null,
        'job_status'=>$r['job_status']??null,'job_work_status'=>s3normStatus($r['job_status']??''),
        'can_start'=>s3normStatus($r['job_status']??'')==='in-progress'
    ];
}
NEW;

if (!str_contains($c,$new)) {
    if (substr_count($c,$old)!==1) throw new RuntimeException('s3public baseline marker mismatch');
    $c=str_replace($old,$new,$c);
}

$old2=<<<'OLD'
                $rows[]=['id'=>$r['id'],'activity_date'=>$activity,'job_card_no'=>$r['job_card_no'],'vehicle_reg_no'=>$r['vehicle_reg_no'],'vehicle_make'=>$r['vehicle_make'],'vehicle_model'=>$r['vehicle_model'],'rot_code'=>$r['rot_code'],'rot_desc'=>$r['rot_desc'],'std_hours'=>$r['std_hours'],'status'=>$r['status'],'productive_seconds'=>$r['live_seconds'],'assigned_at'=>$assignedAt,'first_start_at'=>$r['first_start_at'],'completed_at'=>$r['completed_at'],'tat_seconds'=>$tat];
OLD;
$new2=<<<'NEW'
                $rows[]=['id'=>$r['id'],'activity_date'=>$activity,'job_card_no'=>$r['job_card_no'],'vehicle_reg_no'=>$r['vehicle_reg_no'],'vehicle_make'=>$r['vehicle_make'],'vehicle_model'=>$r['vehicle_model'],'rot_code'=>$r['rot_code'],'rot_desc'=>$r['rot_desc'],'std_hours'=>$r['std_hours'],'standard_seconds'=>$r['standard_seconds']??0,'status'=>$r['status'],'productive_seconds'=>$r['live_seconds'],'progress_percent'=>$r['progress_percent']??0,'remaining_seconds'=>$r['remaining_seconds']??0,'overtime_seconds'=>$r['overtime_seconds']??0,'pause_reason'=>$r['pause_reason']??null,'pause_note'=>$r['pause_note']??null,'assigned_at'=>$assignedAt,'first_start_at'=>$r['first_start_at'],'completed_at'=>$r['completed_at'],'tat_seconds'=>$tat];
NEW;

if (!str_contains($c,$new2)) {
    if (substr_count($c,$old2)!==1) throw new RuntimeException('performance row baseline marker mismatch');
    $c=str_replace($old2,$new2,$c);
}

file_put_contents($path,$c);
echo "FIX28 patch applied.\n";
