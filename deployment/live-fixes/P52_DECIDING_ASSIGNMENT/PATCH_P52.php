<?php
declare(strict_types=1);
/**
 * P52: Inspection Staff != formally assigned Deciding Technician.
 * Existing JC technician_id may come from JC creation, so its presence alone
 * cannot prove an Admin Decision assignment.
 * Only explicit DECIDING_TECHNICIAN_ASSIGNED audit establishes that intent.
 * No database migration, no historical data writes, no changes to OLD ERP.
 */
$mode=(string)($argv[1]??'check');
$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file($root.'/artisan')){
    fwrite(STDERR,"P52_NO_GO_USAGE check|apply STAGING_APP_ROOT\n");exit(2);
}
$names=[
 'app/Http/Controllers/Web/ManagerInspectionWorkController.php',
 'resources/views/inspections/manager-work.blade.php',
 'app/Http/Controllers/Web/AdminJobCardPageController.php',
 'resources/views/job-cards/show.blade.php',
 'app/Http/Controllers/Web/AdminDiagnosisStageController.php',
];
$original=[];foreach($names as $file){
 if(!is_file($root.'/'.$file)||is_link($root.'/'.$file)){
  fwrite(STDERR,"P52_NO_GO_MISSING ".basename($file)."\n");exit(3);
 }
 $original[$file]=(string)file_get_contents($root.'/'.$file);
}
if(str_contains($original[$names[0]],'P52_EXPLICIT_DECIDER_SELECT')){
 if(str_contains($original[$names[1]],'P52_ADMIN_DECIDER_BADGE')
    && str_contains($original[$names[2]],'P52_ALWAYS_AUDIT_EXPLICIT_SAVE')
    && str_contains($original[$names[3]],'P52_FORMAL_DECIDER_STATUS')
    && str_contains($original[$names[4]],'P52_DIAGNOSIS_REQUIRE_ADMIN_DECIDER')){
    echo "P52_ALREADY_INSTALLED\n";exit(0);
 }
 fwrite(STDERR,"P52_NO_GO_PARTIAL_PATCH\n");exit(4);
}
$updated=$original;
function p52once(string &$source,string $old,string $replacement,string $label):void{
 $n=substr_count($source,$old);
 if($n!==1)throw new RuntimeException("P52_NO_GO_ANCHOR_".$label."_".$n);
 $source=str_replace($old,$replacement,$source);
}
try{
 $p=$names[0];$s=&$updated[$p];
 p52once($s,"'j.diagnosis_status','j.decision_status','j.technician',",
          "'j.diagnosis_status','j.decision_status','j.technician','j.technician_id',",
          'manager_add_technician_id');
 p52once($s,"            ->when(\$jobId,fn(\$q)=>\$q->where('j.id',\$jobId))",
<<<'PHP'
            // P52_EXPLICIT_DECIDER_SELECT: the JC technician is NOT an Admin
            // deciding assignment unless the latest explicit audit matches it.
            ->selectSub(function($q){
                $q->from('job_card_diagnosis_events as p52e')
                  ->select('p52e.technician_id')
                  ->whereColumn('p52e.job_card_id','j.id')
                  ->where('p52e.event_type','DECIDING_TECHNICIAN_ASSIGNED')
                  ->orderByDesc('p52e.id')->limit(1);
            },'p52_deciding_staff_id')
            ->when($jobId,fn($q)=>$q->where('j.id',$jobId))
PHP
,'manager_confirmation_query');
 unset($s);
 $p=$names[1];$s=&$updated[$p];
 p52once($s,"<td>{{ \$jc->technician ?: 'Not assigned yet' }}</td>",
<<<'BLADE'
<td>{{-- P52_ADMIN_DECIDER_BADGE --}}
@if((int)($jc->p52_deciding_staff_id??0)>0 && (int)$jc->p52_deciding_staff_id===(int)($jc->technician_id??0))
 <strong>{{ $jc->technician }}</strong><div><small>✓ Assigned by Admin</small></div>
@else
 <strong style="color:#9a581a">NOT ASSIGNED</strong>
 <div><small>Admin must select separately after Inspection.</small></div>
@endif
</td>
BLADE
,'manager_decider_cell');
 p52once($s,'<td><a class="p21-btn" href="{{ route(\'erp.inspection-manager.work\',$jc->id) }}">{{ strtolower((string)$jc->inspection_status)===\'completed\'?\'View\':\'Assign / Inspect\' }}</a></td>',
<<<'BLADE'
<td><a class="p21-btn" href="{{ route('erp.inspection-manager.work',$jc->id) }}">{{ strtolower((string)$jc->inspection_status)==='completed'?'View':'Assign / Inspect' }}</a>
@if(strtolower((string)$jc->inspection_status)==='completed')
 <div style="margin-top:8px"><a class="p21-link" href="{{ route('erp.job-cards.show',$jc->id) }}#deciding-technician">Admin: Select Deciding Technician →</a></div>
@endif</td>
BLADE
,'manager_decider_action');
 unset($s);
 $p=$names[2];$s=&$updated[$p];
 // P52_ALWAYS_AUDIT_EXPLICIT_SAVE: every explicit Admin click counts,
 // including selecting the same staff as an initially prefilled JC technician.
 p52once($s,'if ($changed) {',
            "/* P52_ALWAYS_AUDIT_EXPLICIT_SAVE: Admin confirms even existing JC technician */\n        {",
            'assignment_explicit_audit');
 p52once($s,'if ((int)$card->technician_id !== (int)$staff->id) {',
   "if ((int)\$card->technician_id !== (int)\$staff->id ||\n".
   "            !JobCardDiagnosisEvent::query()->where('job_card_id',\$job)\n".
   "              ->where('event_type','DECIDING_TECHNICIAN_ASSIGNED')\n".
   "              ->where('technician_id',\$staff->id)->exists()) {",
   'legacy_diagnosis_guard');
 p52once($s,'Assign the Deciding Technician first so the technician can review the Inspection Report before completing Diagnosis.',
         'Admin must explicitly select the Deciding Technician after Inspection; a Job Card technician is not automatically the Deciding Officer.',
         'legacy_diagnosis_guard_message');
 unset($s);
 $p=$names[3];$s=&$updated[$p];
 p52once($s,'<div class="deciding-tech-box">',<<<'BLADE'
{{-- P52_FORMAL_DECIDER_STATUS: do not convert JC creation default into deciding assignment. --}}
@php
$p52LatestDeciderId=\Illuminate\Support\Facades\DB::table('job_card_diagnosis_events')
   ->where('job_card_id',$job->id)->where('event_type','DECIDING_TECHNICIAN_ASSIGNED')
   ->orderByDesc('id')->value('technician_id');
$p52ConfirmedDecider=(int)$p52LatestDeciderId>0
   && (int)$p52LatestDeciderId===(int)$job->technician_id;
@endphp
<div class="deciding-tech-box" id="deciding-technician">
BLADE
,'job_form_decider_status');
 p52once($s,"<strong>{{ \$job->technician ?: 'Not Assigned' }}</strong>",
          "<strong>{{ \$p52ConfirmedDecider ? \$job->technician : 'NOT ASSIGNED — Admin selection pending' }}</strong>",
          'job_form_decider_label');
 // Only replace two guards inside the deciding-technician section.
 $start=strpos($s,'<div class="deciding-tech-box" id="deciding-technician">');
 $end=strpos($s,'<div class="workflow-message locked">',$start);
 if($start===false||$end===false||$end<=$start)throw new RuntimeException('P52_NO_GO_JOB_FORM_BOUNDARY');
 $section=substr($s,$start,$end-$start);
 if(substr_count($section,'@if($job->technician_id)')!==2)
     throw new RuntimeException('P52_NO_GO_JOB_FORM_GUARDS_'.substr_count($section,'@if($job->technician_id)'));
 $section=str_replace('@if($job->technician_id)','@if($p52ConfirmedDecider)',$section);
 $section=str_replace("old('technician_id',\$job->technician_id)",
                      "old('technician_id',\$p52ConfirmedDecider ? \$job->technician_id : null)",$section);
 if(!str_contains($section,"old('technician_id',\$p52ConfirmedDecider ? \$job->technician_id : null)"))
     throw new RuntimeException('P52_NO_GO_DROPDOWN_NOT_CLEARED');
 $s=substr_replace($s,$section,$start,$end-$start);
 unset($s);
 $p=$names[4];$s=&$updated[$p];
 $old=<<<'PHP'
            if(!$tech || strtolower(trim((string)$tech->status))!=='active'
               || (int)$card->technician_id!==(int)$tech->id){
                throw ValidationException::withMessages(['technician_id'=>'First assign an ACTIVE Deciding Technician to this Job Card.']);
            }
PHP;
 $new=<<<'PHP'
            // P52_DIAGNOSIS_REQUIRE_ADMIN_DECIDER: JC default technician
            // is not sufficient. A recorded explicit Admin assignment is required.
            $confirmed=JobCardDiagnosisEvent::query()
                ->where('job_card_id',$job)
                ->where('event_type','DECIDING_TECHNICIAN_ASSIGNED')
                ->where('technician_id',(int)$data['technician_id'])
                ->exists();
            if(!$tech || strtolower(trim((string)$tech->status))!=='active'
               || (int)$card->technician_id!==(int)$tech->id || !$confirmed){
                throw ValidationException::withMessages(['technician_id'=>'Admin must explicitly assign the ACTIVE Deciding Technician after completed Inspection before Final Diagnosis.']);
            }
PHP;
 p52once($s,$old,$new,'diagnosis_guard');
 unset($s);
 // Source preflight and route/auth sanity, no business writes.
 chdir($root);require 'vendor/autoload.php';
 $app=require 'bootstrap/app.php';
 $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 if(!\Illuminate\Support\Facades\Schema::hasTable('job_card_diagnosis_events'))throw new RuntimeException('P52_EVENT_TABLE_MISSING');
 foreach(['erp.inspection-manager.work','erp.job-cards.diagnosis.assign-technician','erp.job-cards.diagnosis.complete'] as $route){
  if(!\Illuminate\Support\Facades\Route::has($route))throw new RuntimeException('P52_MISSING_ROUTE_'.$route);
 }
 foreach($names as $i=>$file){
  echo 'P52_CHECKING_FILE='.basename($file)."\n";
  if(str_ends_with($file,'.blade.php')){
   token_get_all(app('blade.compiler')->compileString($updated[$file]),TOKEN_PARSE);
  }else{token_get_all($updated[$file],TOKEN_PARSE);}
 }
 echo "P52_PREFLIGHT_SOURCE_AND_BLADE_COMPILE_PASS\n";
} catch(Throwable $e){
 fwrite(STDERR,"P52_NO_GO ".$e->getMessage()."\n");exit(5);
}
if($mode==='check'){
 echo "P52_DRY_RUN_NO_BUSINESS_DATA_WRITES\n";exit(0);
}
$backup=$root.'/storage/app/p52_deciding_assignment_backup_'.date('Ymd_His').'_'.getmypid();
if(!mkdir($backup,0700,true))exit(6);
foreach($names as $file){
 $dest=$backup.'/'.str_replace('/','__',$file);
 if(!copy($root.'/'.$file,$dest)||hash_file('sha256',$dest)!==hash('sha256',$original[$file])){
  fwrite(STDERR,"P52_BACKUP_ERROR\n");exit(6);
 }
}
foreach($names as $file)if(hash_file('sha256',$root.'/'.$file)!==hash('sha256',$original[$file])){
 fwrite(STDERR,"P52_CONCURRENT_CHANGE_ABORT\n");exit(7);
}
try{
 foreach($names as $file){
  $p=$root.'/'.$file;$temp=tempnam(dirname($p),'.p52_');
  if(!$temp||file_put_contents($temp,$updated[$file],LOCK_EX)!==strlen($updated[$file])
      ||!chmod($temp,fileperms($p)&0777)||!rename($temp,$p))throw new RuntimeException('Write failed '.basename($file));
 }
}catch(Throwable $e){
 foreach($names as $file)copy($backup.'/'.str_replace('/','__',$file),$root.'/'.$file);
 fwrite(STDERR,"P52_ROLLED_BACK ".$e->getMessage()."\n");exit(8);
}
echo "P52_APPLIED_ALL_FIVE_SOURCE_FILES\nP52_BACKUP=".$backup."\n";
