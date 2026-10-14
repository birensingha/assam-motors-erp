<?php
declare(strict_types=1);
/**
 * P22 sequential workshop gate:
 * 1 Manager Inspection -> 2 Admin Final Diagnosis -> 3 Assigned Staff Technician
 * Decision -> 4 Parts/Labour Estimate. Only source; no business record writes.
 */
$mode=$argv[1]??'check';$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file($root.'/artisan')){
 fwrite(STDERR,"Usage: php PATCH_P22.php check|apply /staging-laravel-root\n");exit(2);
}
$edit=[
 'routes/web.php',
 'app/Http/Controllers/Web/AdminJobCardPageController.php',
 'app/Http/Controllers/Web/StaffPortalController.php',
 'resources/views/job-cards/show.blade.php',
 'resources/views/staff/inspection-report.blade.php',
 'resources/views/staff/portal.blade.php',
];
$new=[
 'app/Http/Controllers/Web/AdminDiagnosisStageController.php'=>__DIR__.'/payload/AdminDiagnosisStageController.php',
 'app/Http/Controllers/Web/StaffTechnicianDecisionController.php'=>__DIR__.'/payload/StaffTechnicianDecisionController.php',
];
$src=[];
foreach($edit as $p){
 if(!is_file($root.'/'.$p)||is_link($root.'/'.$p)){fwrite(STDERR,"P22_NO_GO_SOURCE ".$p."\n");exit(3);}
 $src[$p]=(string)file_get_contents($root.'/'.$p);
 if(str_contains($src[$p],'P22_SEQUENTIAL_WORKFLOW')){fwrite(STDERR,"P22_NO_GO_PARTIAL_SOURCE\n");exit(3);}
}
foreach($new as $target=>$from){
 if(!is_file($from)||is_link($from)||file_exists($root.'/'.$target)){
   fwrite(STDERR,"P22_NO_GO_NEW_SOURCE_COLLISION ".$target."\n");exit(4);
 }
}
function p22Once(string $data,string $old,string $fresh,string $name):string {
 $n=substr_count($data,$old);
 if($n!==1)throw new RuntimeException('P22_NO_GO_ANCHOR_'.$name.'_'.$n);
 return str_replace($old,$fresh,$data);
}
try{
 $routes=$src['routes/web.php'];
 $routeDiag="Route::post('/job-cards/{job}/diagnosis/complete', [AdminJobCardPageController::class,'completeDiagnosis'])->name('erp.job-cards.diagnosis.complete');";
 $routeNew="Route::post('/job-cards/{job}/diagnosis/complete', [\\App\\Http\\Controllers\\Web\\AdminDiagnosisStageController::class,'complete'])->name('erp.job-cards.diagnosis.complete'); // P22_SEQUENTIAL_WORKFLOW";
 $routes=p22Once($routes,$routeDiag,$routeNew,'diagnosis_route');
 $staffRoute="Route::get('/inspections/{job}/report', [StaffPortalController::class,'inspectionReport'])->whereNumber('job')->name('staff.inspection-report');";
 $staffNew=<<<'ROUTE'

    // P22_SEQUENTIAL_WORKFLOW: ONLY assigned linked Technician may record the decision.
    Route::post('/job-cards/{job}/technician-decision', [\App\Http\Controllers\Web\StaffTechnicianDecisionController::class,'save'])
        ->whereNumber('job')->name('staff.technician-decision.save');
ROUTE;
 $routes=p22Once($routes,$staffRoute,$staffRoute.$staffNew,'tech_decision_route');

 $admin=$src['app/Http/Controllers/Web/AdminJobCardPageController.php'];
 $admin=p22Once($admin,
 "if (strtolower((string)\$card->diagnosis_status)==='completed' && strtolower((string)(\$card->decision_status ?? 'pending'))==='completed') {",
 "if (strtolower((string)\$card->diagnosis_status)==='completed') {",
 'block_tech_reassign_after_diagnosis');
 $admin=p22Once($admin,
 "Diagnosis is already completed. Re-open Diagnosis first if the Deciding Technician must be changed.",
 "Final Diagnosis is completed. Re-open Diagnosis with audit reason before changing the Deciding Technician.",
 'tech_reassign_message');

 $staffCtrl=$src['app/Http/Controllers/Web/StaffPortalController.php'];
 $needle="        return view('staff.portal',[\n";
 $insert=<<<'PHP'
        // P22_SEQUENTIAL_WORKFLOW: keep Technician Decision tasks visible even after diagnosis.
        $pendingTechnicianDecisions=JobCard::query()
            ->where('technician_id',(int)$user->staff_id)
            ->where('status','open')
            ->where('inspection_status','completed')
            ->where('diagnosis_status','completed')
            ->where('decision_status','pending')
            ->orderByDesc('id')->limit(75)->get();
        return view('staff.portal',[
            'pendingTechnicianDecisions'=>$pendingTechnicianDecisions,
PHP;
 $staffCtrl=p22Once($staffCtrl,$needle,$insert,'staff_portal_pending_decisions');

 $staffReport=$src['resources/views/staff/inspection-report.blade.php'];
 $needle='  <div class="staff-report-footer"><a class="staff-primary-link" href="{{ route(\'staff.portal\') }}">✓ Inspection Reviewed — Back to Dashboard</a></div>';
 $form=<<<'BLADE'
  {{-- P22_SEQUENTIAL_WORKFLOW: technician decision follows completed Final Diagnosis --}}
  @if(session('success'))<div class="staff-report-notice" style="background:#e5f7ed;border:1px solid #9dd8b9;color:#145d3d">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="staff-report-notice" style="background:#fff0ef;border:1px solid #eca6a2;color:#9b2222">{{ $errors->first() }}</div>@endif
  @if(strtolower((string)$job->diagnosis_status)==='completed' && strtolower((string)($job->decision_status??'pending'))!=='completed' && strtolower((string)$job->status)==='open')
  <section style="border:2px solid #317db6;padding:16px;border-radius:13px;background:#f1f8ff;margin:18px 0">
    <h2 style="color:#104979">Step 3 — Technician Decision</h2>
    <p><strong>Final Diagnosis:</strong> {{ $job->final_diagnosis ?: 'Not available' }}</p>
    <p>Inspection and Final Diagnosis are COMPLETE. As the assigned technician, decide the required work. Estimate remains locked until decision is saved.</p>
    <form method="post" action="{{ route('staff.technician-decision.save',$job->id) }}" onsubmit="return confirm('Save your Technician Decision for this Job Card?')">
      @csrf
      <label style="display:block;margin:12px 0;font-weight:700">What work is required?
       <select name="technician_decision" required style="display:block;width:100%;padding:11px;margin-top:6px;border:1px solid #b7cbe0;border-radius:8px;background:white">
        <option value="">Choose decision…</option>
        <option value="REPAIR">Repair / Repair Work</option>
        <option value="REPLACE">Replace Part / Assembly</option>
        <option value="REPAIR_REPLACE">Repair + Replace</option>
        <option value="FURTHER_DIAGNOSIS">Further Diagnosis Required — Estimate Stays Locked</option>
        <option value="OUTSIDE_LABOUR">Outside Labour / OSL</option>
        <option value="NO_REPAIR">No Repair Required — Estimate Stays Locked</option>
        <option value="OTHER">Other (Reason Required)</option>
       </select>
      </label>
      <label style="display:block;margin:12px 0;font-weight:700">Decision / Recommendation Notes
       <textarea name="technician_decision_note" rows="3" maxlength="2000" style="display:block;width:100%;padding:11px;margin-top:6px;border:1px solid #b7cbe0;border-radius:8px" placeholder="Recommended labour, required parts, checks, reason..."></textarea>
      </label>
      <button type="submit" class="staff-primary-link" style="border:0;cursor:pointer">✓ Submit Technician Decision</button>
    </form>
  </section>
  @elseif(strtolower((string)($job->decision_status??'pending'))==='completed')
   <div class="staff-report-notice" style="background:#e5f7ed">✓ Technician Decision saved: {{ $job->technician_decision }}. Estimate: {{ (int)$job->estimate_allowed===1?'UNLOCKED':'LOCKED' }}.</div>
  @else
   <div class="staff-report-notice">Final Diagnosis pending — Technician Decision remains LOCKED.</div>
  @endif

BLADE;
 $staffReport=p22Once($staffReport,$needle,$form.$needle,'staff_decision_form');

 $portal=$src['resources/views/staff/portal.blade.php'];
 $needle='<section class="staff-panel inspection-review-panel">';
 $task=<<<'BLADE'
{{-- P22_SEQUENTIAL_WORKFLOW: separately visible assigned-technician decisions --}}
@if(($pendingTechnicianDecisions??collect())->isNotEmpty())
<section class="staff-panel" style="border-left:5px solid #2376be">
  <div class="panel-head"><h2>🧰 Technician Decisions Pending</h2><span>After Final Diagnosis · Before Estimate</span></div>
  <div class="inspection-review-list">
    @foreach($pendingTechnicianDecisions as $pending)
    <article class="inspection-review-row">
      <div><strong>{{ $pending->job_no }}</strong> · {{ $pending->vehicle_reg_no }}
        <p><b>Final Diagnosis:</b> {{ $pending->final_diagnosis ?: '—' }}</p>
        <small>Inspection COMPLETED · Diagnosis COMPLETED · Decision PENDING</small>
      </div>
      <a class="staff-primary-link" href="{{ route('staff.inspection-report',$pending->id) }}">Open Report &amp; Decide →</a>
    </article>
    @endforeach
  </div>
</section>
@endif
BLADE;
 $portal=p22Once($portal,$needle,$task."\n".$needle,'staff_pending_decisions_task');

 $show=$src['resources/views/job-cards/show.blade.php'];
 $formAnchor='      <form method="post" action="{{ route(\'erp.job-cards.diagnosis.complete\',$job->id) }}" class="diagnosis-form">';
 $start=strpos($show,$formAnchor);
 if($start===false||substr_count($show,$formAnchor)!==1)throw new RuntimeException('P22_NO_GO_DIAG_FORM_START');
 $end=strpos($show,"\n      </form>",$start);
 if($end===false||$end-$start>6000)throw new RuntimeException('P22_NO_GO_DIAG_FORM_END');
 $end+=strlen("\n      </form>");
 $oldForm=substr($show,$start,$end-$start);
 if(!str_contains($oldForm,'technician_decision') || !str_contains($oldForm,'Complete & Open Estimate'))
    throw new RuntimeException('P22_NO_GO_FORM_SIGNATURE');
 $replacement=<<<'BLADE'
      {{-- P22_SEQUENTIAL_WORKFLOW: Final Diagnosis first, Technician Decision separately --}}
      @if(strtolower((string)$job->diagnosis_status)!=='completed')
      <form method="post" action="{{ route('erp.job-cards.diagnosis.complete',$job->id) }}" class="diagnosis-form">
        @csrf
        <input type="hidden" name="technician_id" value="{{ $job->technician_id }}">
        <div class="diagnosis-form-grid">
          <label class="diag-wide">Step 2 — Final Diagnosis
            <textarea name="final_diagnosis" rows="4" required minlength="8" placeholder="Confirmed diagnosis after Manager Inspection Report...">{{ old('final_diagnosis',$job->final_diagnosis) }}</textarea>
          </label>
          <label>Assigned Deciding Technician<input value="{{ $job->technician }}" readonly></label>
        </div>
        <div class="diagnosis-actions">
          <a class="btn ghost" href="{{ route('erp.job-cards.inspection-report',$job->id) }}">Review Manager Inspection</a>
          <button class="btn success-btn">✓ Complete Final Diagnosis (Decision Still Pending)</button>
        </div>
      </form>
      @else
      <div class="workflow-message waiting" style="border-left:5px solid #287ab9">
       <strong>✓ Final Diagnosis COMPLETED — Waiting for Technician Decision</strong>
       <p><b>Diagnosis:</b> {{ $job->final_diagnosis }}</p>
       <p><b>Deciding Technician:</b> {{ $job->technician }}. Assigned Technician apne Staff Portal login mein Inspection Report kholkar Decision submit kare. Usse pehle Parts/Labour Estimate LOCKED rahega.</p>
       <a class="btn primary" href="{{ route('staff.login') }}">Open Staff Login →</a>
      </div>
      @endif
BLADE;
 $show=substr_replace($show,$replacement,$start,$end-$start);
 $needle='<strong>🩺 Inspection Review → Deciding Technician → Diagnosis</strong>';
 $show=p22Once($show,$needle,'<strong>Step 1 Manager Inspection → Step 2 Final Diagnosis → Step 3 Technician Decision → Step 4 Estimate</strong>','workflow_stage_heading');
 $needle='Report review ke baad Final Diagnosis + Technician Decision complete kijiye.';
 $show=p22Once($show,$needle,'Report review ke baad Final Diagnosis save karein; uske baad assigned technician Staff Portal se alag Decision dega.','workflow_stage_note');
 $modified=[
   'routes/web.php'=>$routes,
   'app/Http/Controllers/Web/AdminJobCardPageController.php'=>$admin,
   'app/Http/Controllers/Web/StaffPortalController.php'=>$staffCtrl,
   'resources/views/job-cards/show.blade.php'=>$show,
   'resources/views/staff/inspection-report.blade.php'=>$staffReport,
   'resources/views/staff/portal.blade.php'=>$portal,
 ];
 chdir($root);require 'vendor/autoload.php';
 $app=require 'bootstrap/app.php';
 $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 foreach($new as $dest=>$payload){token_get_all((string)file_get_contents($payload),TOKEN_PARSE);}
 token_get_all($routes,TOKEN_PARSE);token_get_all($admin,TOKEN_PARSE);token_get_all($staffCtrl,TOKEN_PARSE);
 foreach(['resources/views/job-cards/show.blade.php','resources/views/staff/inspection-report.blade.php','resources/views/staff/portal.blade.php'] as $v){
    token_get_all(app('blade.compiler')->compileString($modified[$v]),TOKEN_PARSE);
 }
 echo "P22_PHP_AND_ALL_COMPILED_BLADE_CHECK_PASS\n";
 foreach($edit as $p)echo 'P22_CHECK '.$p.' OLD='.hash('sha256',$src[$p]).' NEW='.hash('sha256',$modified[$p])."\n";
}catch(Throwable $e){fwrite(STDERR,"P22_NO_GO_PREFLIGHT ".$e->getMessage()."\n");exit(5);}
if($mode==='check'){echo "P22_DRY_RUN_NO_SOURCE_OR_DATA_CHANGES\n";exit(0);}
$bak=$root.'/storage/app/p22_workflow_backup_'.date('Ymd_His').'_'.getmypid();
if(!mkdir($bak,0700,true))exit(6);
foreach($edit as $p){
 $dest=$bak.'/'.str_replace('/','__',$p);
 if(!copy($root.'/'.$p,$dest)||hash_file('sha256',$dest)!==hash('sha256',$src[$p])){
   fwrite(STDERR,"P22_NO_GO_BACKUP_VERIFICATION\n");exit(6);
 }
}
$written=[];
try{
 foreach($edit as $p){
   $dest=$root.'/'.$p;
   if(hash_file('sha256',$dest)!==hash('sha256',$src[$p]))throw new RuntimeException('Concurrent source change '.$p);
   $tmp=tempnam(dirname($dest),'.p22_');
   if(!$tmp||file_put_contents($tmp,$modified[$p],LOCK_EX)!==strlen($modified[$p]))throw new RuntimeException('Temporary write '.$p);
   chmod($tmp,fileperms($dest)&0777);
   if(!rename($tmp,$dest))throw new RuntimeException('Atomic replace '.$p);
   $written[]=$p;
   if(hash_file('sha256',$dest)!==hash('sha256',$modified[$p]))throw new RuntimeException('Hash verify '.$p);
 }
 foreach($new as $p=>$payload){
   $dest=$root.'/'.$p;
   if(file_exists($dest)||!copy($payload,$dest))throw new RuntimeException('New file '.$p);
   $written[]=$p;
   if(hash_file('sha256',$dest)!==hash_file('sha256',$payload))throw new RuntimeException('Checksum '.$p);
 }
 echo "P22_APPLIED_BACKUP=".$bak."\n";
 echo "P22_SOURCE_ONLY_NO_BUSINESS_RECORDS_CHANGED\n";
}catch(Throwable $e){
 foreach(array_reverse($written) as $p){
   if(isset($new[$p]))@unlink($root.'/'.$p);
   else copy($bak.'/'.str_replace('/','__',$p),$root.'/'.$p);
 }
 fwrite(STDERR,'P22_SOURCE_ROLLBACK '.$e->getMessage()."\n");exit(7);
}
