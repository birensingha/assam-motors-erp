<?php
declare(strict_types=1);
// P29_ACTIVE_STAFF_INSPECTION: guarded source-only changes.
$mode=$argv[1]??'check';$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file($root.'/artisan'))exit(2);
$edit=[
 'app/Http/Controllers/Web/ManagerInspectionWorkController.php',
 'resources/views/inspections/manager-work.blade.php',
 'app/Http/Controllers/Api/V1/StaffExtraController.php',
 'app/Http/Controllers/Api/V1/OwnerWorkshopController.php',
 'app/Http/Controllers/Api/V1/OwnerSelfAttendanceInspectionController.php',
 'app/Http/Controllers/Web/StaffPortalController.php',
 'resources/views/staff/portal.blade.php',
 'routes/web.php',
];
$new=[
 'app/Http/Controllers/Web/StaffAssignedInspectionController.php'=>__DIR__.'/payload/StaffAssignedInspectionController.php',
 'resources/views/staff/assigned-inspection.blade.php'=>__DIR__.'/payload/assigned-inspection.blade.php'
];
$src=[];
foreach($edit as $p){
 if(!is_file($root.'/'.$p)||is_link($root.'/'.$p))throw new RuntimeException('MISSING_'.$p);
 $src[$p]=(string)file_get_contents($root.'/'.$p);
 if(str_contains($src[$p],'P29_ACTIVE_STAFF_INSPECTION'))throw new RuntimeException('ALREADY_INSTALLED_'.$p);
}
foreach($new as $p=>$f)if(file_exists($root.'/'.$p)||!is_file($f))throw new RuntimeException('DEST_OR_PAYLOAD_'.$p);
function replace29($s,$old,$new,$name,$count=1){
 $n=substr_count($s,$old);
 if($n!==$count)throw new RuntimeException('P29_NO_GO_ANCHOR_'.$name.'_'.$n);
 return str_replace($old,$new,$s);
}
try{
 $a=$src[$edit[0]];
 $a=replace29($a,"|| strtolower(trim((string)\$staff->role))!=='manager'","",'role_restriction');
 $a=replace29($a,"Assign an ACTIVE Staff Master Manager only.","Assign an active Staff Master member; that staff needs an active Staff login to view the assignment.",'active_text');
 $a=replace29($a,"            ->whereRaw(\"LOWER(TRIM(COALESCE(role,'')))='manager'\")\n","            // P29_ACTIVE_STAFF_INSPECTION: all active employees.\n",'active_staff_dropdown');
 $a=replace29($a,"if(!\$myStaff || strtolower(trim((string)\$myStaff->role))!=='manager')","if(!\$myStaff || strtolower(trim((string)\$myStaff->status))!=='active')",'self_complete');
 $a=replace29($a,"Linked Manager Staff account required to inspect.","Linked active Staff account required to inspect.",'self_complete_msg');
 $out[$edit[0]]=$a;
 $a=$src[$edit[1]];
 $changes=[
   'Manager Vehicle Inspection'=>'Vehicle Inspection Assignment',
   'Vehicle Inspection — Manager'=>'Vehicle Inspection — Staff',
   'Assign Manager → Complete Inspection'=>'Assign Staff → Complete Inspection',
   'Assigned Inspection Manager'=>'Assigned Inspection Staff',
   'Assign Inspection Manager'=>'Assign Inspection Staff',
   'Active Manager<select'=>'Active Staff Member<select',
   'Choose Manager'=>'Choose Staff Member',
   'Assign Manager</button>'=>'Assign Staff</button>',
   'This inspection is not assigned to your linked Manager Staff ID.'=>'This inspection is not assigned to your linked Staff ID.',
   'Only the assigned Manager can submit.'=>'Only the assigned Staff member can submit.',
 ];
 foreach($changes as $from=>$to)$a=str_replace($from,$to,$a);
 $a=replace29($a,"&& strtolower(trim((string)(\$myStaff->role??'')))==='manager'","&& strtolower(trim((string)(\$myStaff->status??'')))==='active'",'web_submit_id');
 $out[$edit[1]]=$a;
 $a=$src[$edit[2]];
 $gate=<<<'G'
        $manager=DB::table('staff')->where('id',$staffId)->first();
        abort_unless($manager && strtolower(trim((string)$manager->role))==='manager'
            && strtolower(trim((string)$manager->status))==='active',403,'Assigned active Manager only.');
G;
 $replace=<<<'G'
        // P29_ACTIVE_STAFF_INSPECTION: assignment already matches authenticated Staff ID.
        $inspector=DB::table('staff')->where('id',$staffId)->first();
        abort_unless($inspector && strtolower(trim((string)$inspector->status))==='active',
            403,'Assigned active Staff member only.');
G;
 $a=replace29($a,$gate,$replace,'android_staff_role',2);
 $a=replace29($a,'Inspection must be assigned to this Manager and pending before Diagnosis.',
                  'Inspection must be assigned to this Staff member and pending before Diagnosis.','save_error');
 $out[$edit[2]]=$a;
 $a=$src[$edit[3]];
 $owner=<<<'G'
        if(strtolower(trim((string)$staff->role))!=='manager'){
            throw ValidationException::withMessages(['staff_id'=>'Vehicle Inspection can only be assigned to an ACTIVE Manager.']);
        }
G;
 $a=replace29($a,$owner,'        // P29_ACTIVE_STAFF_INSPECTION: any active Staff member may be assigned by Admin.','owner_staff');
 $a=replace29($a,'Inspection assigned to Manager without changing the Deciding Technician.','Inspection assigned to Staff without changing the Deciding Technician.','owner_msg');
 $out[$edit[3]]=$a;
 $a=$src[$edit[4]];
 $a=replace29($a,"strtolower(trim((string)(\$data['myStaff']->role??'')))==='manager'",
                "strtolower(trim((string)(\$data['myStaff']->status??'')))==='active'",'owner_mobile_existing_client_compat',2);
 $out[$edit[4]]=$a;
 $a=$src[$edit[5]];
 $add=<<<'G'
        // P29_ACTIVE_STAFF_INSPECTION: assigned jobs reflected on every Staff Portal GET.
        $assignedInspections=DB::table('job_card_inspection_assignments as a')
            ->join('job_cards as j','j.id','=','a.job_card_id')
            ->where('a.assigned_staff_id',(int)$user->staff_id)
            ->select('j.id','j.job_no','j.vehicle_reg_no','j.customer_name',
                     'j.inspection_status','j.status','a.assigned_at')
            ->orderByRaw("CASE WHEN LOWER(COALESCE(j.inspection_status,''))='pending' THEN 0 ELSE 1 END")
            ->orderByDesc('a.assigned_at')->limit(100)->get();
        return view('staff.portal',[
            'assignedInspections'=>$assignedInspections,
G;
 $a=replace29($a,"        return view('staff.portal',[",$add,'staff_controller_index');
 $out[$edit[5]]=$a;
 $a=$src[$edit[6]];
 $html=<<<'BLADE'
{{-- P29_ACTIVE_STAFF_INSPECTION: independent Staff Inspection assignments, not Deciding Technician. --}}
@if(($assignedInspections??collect())->isNotEmpty())
<section class="staff-panel" style="border-left:5px solid #157c60">
 <div class="panel-head"><h2>🔍 My Assigned Inspections</h2><span>Live Job Card assignments</span></div>
 <div class="inspection-review-list">
 @foreach($assignedInspections as $task)
  <article class="inspection-review-row">
   <div><strong>{{ $task->job_no }}</strong> · {{ $task->vehicle_reg_no }}
     <p>{{ $task->customer_name }} · Inspection: <b>{{ strtoupper((string)$task->inspection_status) }}</b></p>
     <small>Assigned: {{ \Carbon\Carbon::parse($task->assigned_at)->format('d-m-Y h:i A') }}</small>
   </div>
   <a class="staff-primary-link" href="{{ route('staff.assigned-inspection.show',$task->id) }}">
     {{ strtolower((string)$task->inspection_status)==='completed'?'View Completed Inspection':'Open & Complete Inspection' }} →
   </a>
  </article>
 @endforeach
 </div>
</section>
@endif
BLADE;
 $a=replace29($a,'@unless(\App\Support\HrAccess::permitted(auth()->user()))',
                   $html."\n".'@unless(\App\Support\HrAccess::permitted(auth()->user()))','staff_task_view');
 $out[$edit[6]]=$a;
 $a=$src[$edit[7]];
 $anchor="Route::get('/inspections/{job}/report', [StaffPortalController::class,'inspectionReport'])->whereNumber('job')->name('staff.inspection-report');";
 $newRoutes=<<<'G'

    // P29_ACTIVE_STAFF_INSPECTION: linked active Staff inspection form.
    Route::get('/inspections/{job}/work', [\App\Http\Controllers\Web\StaffAssignedInspectionController::class,'show'])
        ->whereNumber('job')->name('staff.assigned-inspection.show');
    Route::post('/inspections/{job}/work/complete', [\App\Http\Controllers\Web\StaffAssignedInspectionController::class,'complete'])
        ->whereNumber('job')->name('staff.assigned-inspection.complete');
G;
 $a=replace29($a,$anchor,$anchor.$newRoutes,'staff_route');
 $out[$edit[7]]=$a;
 chdir($root);require 'vendor/autoload.php';$app=require 'bootstrap/app.php';
 $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 foreach($out as $p=>$code){
   if(str_ends_with($p,'.blade.php'))$code=app('blade.compiler')->compileString($code);
   token_get_all($code,TOKEN_PARSE);
 }
 foreach($new as $p=>$path){
   $code=(string)file_get_contents($path);
   if(str_ends_with($p,'.blade.php'))$code=app('blade.compiler')->compileString($code);
   token_get_all($code,TOKEN_PARSE);
 }
 echo "P29_PHP_ALL_MODIFIED_CONTROLLERS_PASS\nP29_BLADE_ALL_VIEWS_COMPILE_PASS\n";
}catch(Throwable $e){fwrite(STDERR,"P29_NO_GO_PREFLIGHT ".$e->getMessage()."\n");exit(5);}
if($mode==='check'){echo "P29_DRY_RUN_NO_SOURCE_OR_BUSINESS_ROWS_CHANGED\n";exit(0);}
$bak=$root.'/storage/app/p29_assigned_inspection_backup_'.date('Ymd_His').'_'.getmypid();
if(!mkdir($bak,0700,true))exit(6);
foreach($edit as $p){
 $copy=$bak.'/'.str_replace('/','__',$p);
 if(!copy($root.'/'.$p,$copy)||hash_file('sha256',$copy)!==hash('sha256',$src[$p]))exit(6);
}
$written=[];
try {
 foreach($edit as $p){
  $dest=$root.'/'.$p;
  if(hash_file('sha256',$dest)!==hash('sha256',$src[$p]))throw new RuntimeException('Concurrent file edit '.$p);
  $tmp=tempnam(dirname($dest),'.p29_');
  if(!$tmp||file_put_contents($tmp,$out[$p],LOCK_EX)!==strlen($out[$p]))throw new RuntimeException('Temp write '.$p);
  chmod($tmp,fileperms($dest)&0777);
  if(!rename($tmp,$dest))throw new RuntimeException('Atomic replace '.$p);
  $written[]=$p;
 }
 foreach($new as $p=>$f){
   if(!copy($f,$root.'/'.$p))throw new RuntimeException('New file '.$p);
   $written[]=$p;
 }
 echo 'P29_APPLIED_BACKUP='.$bak."\n";
 echo "P29_ONLY_APPLICATION_SOURCE_FILES_CHANGED\n";
}catch(Throwable $e){
 foreach(array_reverse($written) as $p){
  if(isset($new[$p]))@unlink($root.'/'.$p);
  else copy($bak.'/'.str_replace('/','__',$p),$root.'/'.$p);
 }
 fwrite(STDERR,"P29_AUTO_SOURCE_ROLLBACK ".$e->getMessage()."\n");exit(7);
}
