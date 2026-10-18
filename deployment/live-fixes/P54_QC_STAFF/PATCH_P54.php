<?php
declare(strict_types=1);
/**
 * P54: clean index + remove redundant Next Step box + assigned STAFF QC.
 * Guarded source-only change. Historical QC/ledger/stock rows untouched.
 */
$mode=(string)($argv[1]??'check');
$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file($root.'/artisan')){fwrite(STDERR,"P54_USAGE check|apply STAGING_ROOT\n");exit(2);}
$base=__DIR__;
$files=[
 'resources/views/job-cards/index.blade.php',
 'resources/views/job-cards/lifecycle-panel.blade.php',
 'app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php',
 'app/Http/Controllers/Web/StaffPortalController.php',
 'resources/views/staff/portal.blade.php',
 'routes/web.php'
];
$payload=[
 'app/Http/Controllers/Web/StaffQcController.php'=>'payload/StaffQcController.php',
 'resources/views/staff/qc-review.blade.php'=>'payload/qc-review.blade.php'
];
$before=[];
foreach($files as $file){
 $full="$root/$file";if(!is_file($full)||is_link($full)){fwrite(STDERR,"P54_MISSING_BASE ".basename($full)."\n");exit(3);}
 $before[$file]=(string)file_get_contents($full);
}
foreach($payload as $target=>$source)if(!is_file("$base/$source")||is_file("$root/$target")){
 fwrite(STDERR,"P54_MISSING_OR_EXISTING_PAYLOAD ".basename($target)."\n");exit(3);
}
if(str_contains($before[$files[0]],'P54_CLEAN_JC_ACTIONS')){
 echo "P54_ALREADY_INSTALLED\n";exit(0);
}
function p54Replace(string &$source,string $old,string $new,string $label):void{
 $n=substr_count($source,$old);
 if($n!==1)throw new RuntimeException("P54_ANCHOR_".$label."_".$n);
 $source=str_replace($old,$new,$source);
}
$after=$before;
try{
 // 1: exactly two actions on the Job Card dashboard.
 $idx=&$after[$files[0]];
 $actionStart=strpos($idx,'<td><div class="jc-row-actions">');
 $actionEnd=$actionStart===false?false:strpos($idx,'</div></td>',$actionStart);
 if($actionStart===false||$actionEnd===false||substr_count($idx,'<div class="jc-row-actions">')!==1)throw new RuntimeException('P54_UNEXPECTED_ACTION_CELL');
 $actions=<<<'BLADE'
<td><div class="jc-row-actions">
  {{-- P54_CLEAN_JC_ACTIONS: all edits/inspection/purchase live inside Job Card. --}}
  <a class="btn primary" href="{{ route('erp.job-cards.show',$r->id) }}">↗ Open</a>
  <a class="btn" href="{{ route('erp.job-cards.show',$r->id) }}">▣ JC Summary</a>
</div></td>
BLADE;
 $idx=substr_replace($idx,$actions,$actionStart,$actionEnd+strlen('</div></td>')-$actionStart);
 unset($idx);
 // 2: remove visually redundant P30 Next Step banner, not the actual lifecycle
 // controls or important P32 status mismatch warning.
 $life=&$after[$files[1]];
 $st=strpos($life,'{{-- P30_LIFECYCLE_NEXT_ACTIONS --}}');
 $en=$st===false?false:strpos($life,'{{-- P32_QC_PASS_WIP_STATE_GUARD',$st);
 if($st===false||$en===false||$en<=$st)throw new RuntimeException('P54_NEXT_STEP_ANCHOR');
 $life=substr_replace($life,"{{-- P54_REMOVE_DUPLICATE_NEXT_STEP: actual actions remain in Current Stage below. --}}\n",$st,$en-$st);
 // New QC assignment must be chosen once and linked to active staff login.
 p54Replace($life,'<label>QC Staff<select name="assigned_staff_id"><option value="">Not assigned</option>',
               '<label>Assign QC Staff *<select name="assigned_staff_id" required><option value="">Select active QC Staff</option>',
               'start_qc_staff_required');
 // Once QC is pending, no Admin PASS/FAIL staff selector; staff must inspect
 // read-only Job Card and submit via its protected staff portal.
 $qStart=strpos($life,"@if(\$amLifeStage==='QC_PENDING')");
 $qEnd=$qStart===false?false:strpos($life,"@if(\$amLifeStage==='QC_PASSED')",$qStart);
 if($qStart===false||$qEnd===false||$qEnd<=$qStart)throw new RuntimeException('P54_QC_VIEW_BLOCK_NOT_FOUND');
 $newQc=<<<'BLADE'
@if($amLifeStage==='QC_PENDING')
  {{-- P54_STAFF_QC_PENDING: no second QC staff picker, no Admin QC PASS/FAIL buttons. --}}
  @if($amLifeQc && (int)$amLifeQc->assigned_staff_id>0)
    @php($p54QcStaff=collect($staff)->firstWhere('id',(int)$amLifeQc->assigned_staff_id))
    <div style="border:1px solid #b5dbf2;background:#f0f9ff;border-left:4px solid #1880bb;border-radius:11px;padding:14px;max-width:720px">
      <strong>QC Assigned: {{ $p54QcStaff->name??('Staff #'.$amLifeQc->assigned_staff_id) }}</strong>
      <p style="margin:5px 0 9px;font-size:12px">QC Staff ke portal mein read-only Job Card Summary open hoga. Wahi assigned staff PASS / FAIL submit karega. Admin yahaan staff dobara select nahi karega.</p>
      <a class="btn primary" href="{{ route('staff.qc.show',$job->id) }}" target="_blank" rel="noopener">↗ Open Staff QC Review (staff login required)</a>
    </div>
  @else
    <div role="alert" style="border:1px solid #e4c579;border-left:4px solid #bb8414;background:#fff8e8;border-radius:9px;padding:12px">
      <strong>Legacy QC has no assigned staff.</strong>
      <p>Use audited Return to WIP, finish work if needed, then Start QC with an active staff member. No fake QC PASS is allowed.</p>
    </div>
  @endif
@endif

BLADE;
 $life=substr_replace($life,$newQc,$qStart,$qEnd-$qStart);
 unset($life);
 // 3: Admin may ASSIGN QC; QC submission thereafter belongs to linked staff.
 $ctrl=&$after[$files[2]];
 $qcStart=strpos($ctrl,'public function qcPending(');
 $qcMid=strpos($ctrl,'public function qcResult(',$qcStart);
 $qcEnd=strpos($ctrl,'public function readyForDelivery(',$qcMid);
 if($qcStart===false||$qcMid===false||$qcEnd===false)throw new RuntimeException('P54_CONTROLLER_QC_METHODS');
 $assign=substr($ctrl,$qcStart,$qcMid-$qcStart);
 p54Replace($assign,"'assigned_staff_id'=>['nullable','integer','min:1']",
                     "'assigned_staff_id'=>['required','integer','min:1']",
                     'qc_assign_required_server');
 p54Replace($assign,<<<'PHP'
            if(!empty($data['assigned_staff_id'])){
                $this->activeStaff((int)$data['assigned_staff_id']);
            }
PHP,<<<'PHP'
            // P54_QC_ASSIGNEE_LOGIN: assignment is once, to active staff
            // with a working ERP Staff Portal identity.
            $this->activeStaff((int)$data['assigned_staff_id']);
            $linked=DB::table('erp_auth_users')
                ->where('staff_id',(int)$data['assigned_staff_id'])
                ->where('active',1)->exists();
            if(!$linked){
                throw ValidationException::withMessages(['assigned_staff_id'=>'This staff member has no active Staff Portal login. Link the staff account before assigning QC.']);
            }
PHP,'qc_assignee_login');
 $ctrl=substr($ctrl,0,$qcStart).$assign.substr($ctrl,$qcMid);
 // Explicit server guard: Admin QC PASS/FAIL cannot bypass the assigned
 // staff portal by crafting a POST. Historical unassigned QC remains audited.
 $qcMid=strpos($ctrl,'public function qcResult(');
 $qcEnd=strpos($ctrl,'public function readyForDelivery(',$qcMid);
 $qc=substr($ctrl,$qcMid,$qcEnd-$qcMid);
 $needle=<<<'PHP'
            if(!empty($data['assigned_staff_id'])) $this->activeStaff((int)$data['assigned_staff_id']);
PHP;
 $replacement=<<<'PHP'
            // P54_QC_STAFF_ONLY_RESULT: no Admin impersonation of selected
            // QC Staff. The assigned staff must open /staff/qc/{job}.
            $latestQc=DB::table('workshop_qc_checks')->where('job_card_id',$job)
                ->orderByDesc('id')->lockForUpdate()->first();
            if($latestQc && (int)$latestQc->assigned_staff_id>0){
                throw ValidationException::withMessages(['status'=>'QC is assigned to a Staff member. PASS/FAIL must be submitted from that linked Staff Portal, not from Admin.']);
            }
            if(!empty($data['assigned_staff_id'])){
                throw ValidationException::withMessages(['assigned_staff_id'=>'Re-selecting a QC Staff during PASS/FAIL is not allowed.']);
            }
PHP;
 p54Replace($qc,$needle,$replacement,'admin_qc_no_second_picker_server');
 $ctrl=substr($ctrl,0,$qcMid).$qc.substr($ctrl,$qcEnd);
 unset($ctrl);
 // 4: Staff Portal displays QC assigned by Admin in a visible independent card.
 $staff=&$after[$files[3]];
 $pos=<<<'PHP'
'pendingTechnicianDecisions'=>$pendingTechnicianDecisions,
PHP;
 if(substr_count($staff,$pos)!==1)throw new RuntimeException('P54_STAFF_PORTAL_CONTROLLER_DATA_ANCHOR');
 $collect=<<<'PHP'
$assignedQcJobs=DB::table('workshop_qc_checks as q')
  ->join('job_cards as j','j.id','=','q.job_card_id')
  ->where('q.assigned_staff_id',(int)$user->staff_id)
  ->where('q.result','PENDING')
  ->whereRaw('q.id = (SELECT MAX(q2.id) FROM workshop_qc_checks q2 WHERE q2.job_card_id=q.job_card_id)')
  ->whereRaw("LOWER(j.status)='completed'")
  ->whereRaw("LOWER(COALESCE(j.customer_public_status,'')) LIKE '%quality check%'")
  ->select('j.id','j.job_no','j.vehicle_reg_no','j.customer_name','q.checked_at')
  ->orderByDesc('q.id')->limit(75)->get();
PHP;
 p54Replace($staff,'return view(\'staff.portal\',[',$collect."\nreturn view('staff.portal',[",
            'portal_qc_assignment_collection');
 p54Replace($staff,$pos,$pos."        'assignedQcJobs'=>\$assignedQcJobs,\n",
            'portal_qc_assigned_viewdata');
 unset($staff);
 $portal=&$after[$files[4]];
 $anchor="@if(\$errors->any())<div class=\"staff-alert danger\">{{ \$errors->first() }}</div>@endif";
 $staffQc=<<<'BLADE'
{{-- P54_STAFF_QC_PORTAL: PENDING QC assignments for logged-in linked staff only --}}
@if(($assignedQcJobs??collect())->isNotEmpty())
<section class="staff-panel" style="border-left:5px solid #087eab;background:#f7fcff!important">
 <div class="panel-head"><h2>✅ My Assigned Quality Checks</h2><span class="status pending">{{ $assignedQcJobs->count() }} pending</span></div>
 <p style="font-size:12px;color:#48657d">Open the read-only Job Card Summary, check the vehicle, then submit Quality PASS or FAIL. No staff re-selection.</p>
 <div class="inspection-review-list">
 @foreach($assignedQcJobs as $qcTask)
  <article class="inspection-review-row">
   <div><strong>{{ $qcTask->job_no }}</strong> · {{ $qcTask->vehicle_reg_no }}
    <p>{{ $qcTask->customer_name }} · Quality Check Pending</p></div>
   <a class="staff-primary-link" href="{{ route('staff.qc.show',$qcTask->id) }}">Open QC Review →</a>
  </article>
 @endforeach
 </div>
</section>
@endif
BLADE;
 p54Replace($portal,$anchor,$anchor."\n".$staffQc,'staff_portal_qc_widget');
 unset($portal);
 // 5: staff auth + linked ID guard stays in the existing Staff routes.
 $routes=&$after[$files[5]];
 $routeAnchor="    Route::get('/inspections/{job}/report', [StaffPortalController::class,'inspectionReport'])->whereNumber('job')->name('staff.inspection-report');";
 $newRoutes=<<<'PHP'

    // P54_STAFF_QC_ROUTES: Staff Portal session + linked staff permission inherited.
    Route::get('/qc/{job}', [\App\Http\Controllers\Web\StaffQcController::class,'show'])
        ->whereNumber('job')->name('staff.qc.show');
    Route::post('/qc/{job}/result', [\App\Http\Controllers\Web\StaffQcController::class,'submit'])
        ->whereNumber('job')->name('staff.qc.submit');
PHP;
 p54Replace($routes,$routeAnchor,$routeAnchor.$newRoutes,'staff_auth_route_anchor');
 unset($routes);
 // 6: Validate parsed PHP and Blade without writing any business data.
 chdir($root);
 require 'vendor/autoload.php';
 $app=require 'bootstrap/app.php';
 $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 foreach($files as $file){
  if(str_ends_with($file,'.blade.php'))$code=app('blade.compiler')->compileString($after[$file]);
  else $code=$after[$file];
  token_get_all($code,TOKEN_PARSE);
  echo "P54_COMPILE_OK=".basename($file)."\n";
 }
 foreach($payload as $file=>$source){
  $data=(string)file_get_contents("$base/$source");
  $code=str_ends_with($file,'.blade.php')?app('blade.compiler')->compileString($data):$data;
  token_get_all($code,TOKEN_PARSE);
  echo "P54_PAYLOAD_COMPILE_OK=".basename($file)."\n";
 }
 if(!\Illuminate\Support\Facades\Route::has('staff.portal')
    || !\Illuminate\Support\Facades\Route::has('erp.job-cards.index'))
    throw new RuntimeException('P54_BASE_ROUTE_MISSING');
 echo "P54_GUARDED_SOURCE_AND_BLADE_PREFLIGHT_PASS\n";
} catch(Throwable $e){fwrite(STDERR,"P54_NO_GO_".$e->getMessage()."\n");exit(5);}
if($mode==='check'){echo "P54_DRY_RUN_NO_DATA_CHANGES\n";exit(0);}
$backup="$root/storage/app/p54_qc_staff_backup_".date('Ymd_His').'_'.getmypid();
if(!mkdir($backup,0700,true)){fwrite(STDERR,"P54_BACKUP_NO_GO\n");exit(6);}
foreach($files as $file){
 $out=$backup.'/'.str_replace('/','__',$file);
 if(!copy("$root/$file",$out)||hash_file('sha256',$out)!==hash('sha256',$before[$file])){
  fwrite(STDERR,"P54_BACKUP_VERIFY_NO_GO\n");exit(6);
 }
}
foreach($files as $file)if(hash_file('sha256',"$root/$file")!==hash('sha256',$before[$file])){
 fwrite(STDERR,"P54_CONCURRENT_CHANGE_NO_GO\n");exit(7);
}
try{
 foreach($files as $file){
  $full="$root/$file";$tmp=tempnam(dirname($full),'.p54_');
  if(!$tmp || file_put_contents($tmp,$after[$file],LOCK_EX)!==strlen($after[$file])
    || !chmod($tmp,fileperms($full)&0777) || !rename($tmp,$full))throw new RuntimeException('P54_WRITE_'.basename($file));
 }
 foreach($payload as $file=>$source){
  if(!copy("$base/$source","$root/$file"))throw new RuntimeException("P54_PAYLOAD_COPY_".basename($file));
 }
}catch(Throwable $e){
 foreach($files as $file)copy($backup.'/'.str_replace('/','__',$file),"$root/$file");
 foreach($payload as $file=>$source)@unlink("$root/$file");
 fwrite(STDERR,"P54_PATCH_ROLLBACK ".$e->getMessage()."\n");exit(8);
}
echo "P54_FILES_INSTALLED_WITH_BACKUP=$backup\nP54_NO_OLD_ERP_OR_INVOICE_CHANGES\n";
