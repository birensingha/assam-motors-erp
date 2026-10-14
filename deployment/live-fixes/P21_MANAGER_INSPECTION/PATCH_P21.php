<?php
declare(strict_types=1);
/**
 * P21_MANAGER_INSPECTION_WORK UI + routes. Additive migration is deployed separately
 * by guarded staging workflow after source validation. No user action or DB work
 * record is changed by the installer.
 */
$mode=$argv[1]??'check';
$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file($root.'/artisan')){
 fwrite(STDERR,"Usage: php PATCH_P21.php check|apply /staging-laravel-root\n");exit(2);
}
$sourceFiles=[
 'routes/web.php',
 'resources/views/layouts/erp.blade.php',
 'resources/views/job-cards/show.blade.php',
];
$payload=[
 'app/Http/Controllers/Web/ManagerInspectionWorkController.php'=>__DIR__.'/payload/ManagerInspectionWorkController.php',
 'resources/views/inspections/manager-work.blade.php'=>__DIR__.'/payload/manager-work.blade.php',
 'database/migrations/2026_10_14_140000_create_job_card_inspection_assignments.php'=>__DIR__.'/payload/2026_10_14_140000_create_job_card_inspection_assignments.php',
];
$old=[];
foreach($sourceFiles as $f){
 if(!is_file($root.'/'.$f)||is_link($root.'/'.$f)) {fwrite(STDERR,"P21_NO_GO_SOURCE_MISSING ".$f."\n");exit(3);}
 $old[$f]=(string)file_get_contents($root.'/'.$f);
 if(str_contains($old[$f],'P21_MANAGER_INSPECTION_WORK')) {fwrite(STDERR,"P21_NO_GO_INSTALLED_OR_PARTIAL\n");exit(3);}
}
foreach($payload as $f=>$src){
 if(!is_file($src)||is_link($src)||file_exists($root.'/'.$f)){fwrite(STDERR,"P21_NO_GO_PAYLOAD_OR_DESTINATION_EXISTS ".$f."\n");exit(4);}
}
function p21Once(string $s,string $needle,string $replacement,string $name):string{
 $count=substr_count($s,$needle);
 if($count!==1)throw new RuntimeException("P21_NO_GO_ANCHOR_".$name."_".$count);
 return str_replace($needle,$replacement,$s);
}
try{
 $routes=$old['routes/web.php'];
 $anchor="Route::get('/vehicle-inspection', [AdminDashboardPageController::class,'inspections'])->name('erp.vehicle-inspection');";
 $extra=<<<'PHP'

    // P21_MANAGER_INSPECTION_WORK: separate Manager assignee from deciding technician.
    Route::get('/inspection-manager', [\App\Http\Controllers\Web\ManagerInspectionWorkController::class,'index'])->name('erp.inspection-manager.index');
    Route::get('/inspection-manager/{job}', [\App\Http\Controllers\Web\ManagerInspectionWorkController::class,'work'])->whereNumber('job')->name('erp.inspection-manager.work');
    Route::post('/inspection-manager/{job}/assign', [\App\Http\Controllers\Web\ManagerInspectionWorkController::class,'assign'])->whereNumber('job')->name('erp.inspection-manager.assign');
    Route::post('/inspection-manager/{job}/complete', [\App\Http\Controllers\Web\ManagerInspectionWorkController::class,'complete'])->whereNumber('job')->name('erp.inspection-manager.complete');
PHP;
 $routes=p21Once($routes,$anchor,$anchor.$extra,'inspection_routes');

 $layout=$old['resources/views/layouts/erp.blade.php'];
 $regex="/^([ \\t]*<a href=\"\\{\\{ route\\('erp\\.vehicle-inspection'\\) \\}\\}\"[^\\n]*<\\/a>)$/m";
 if(preg_match_all($regex,$layout,$matches)!==1)throw new RuntimeException('ERP sidebar inspection link missing/ambiguous');
 $nav=<<<'BLADE'

        <a href="{{ route('erp.inspection-manager.index') }}" class="{{ request()->routeIs('erp.inspection-manager.*')?'active':'' }}"><span>🩺</span> Manager Inspection Work</a>
BLADE;
 $layout=p21Once($layout,$matches[1][0],$matches[1][0].$nav,'inspection_navigation');

 $show=$old['resources/views/job-cards/show.blade.php'];
 $needle='$latestDiagnosisEvent=$diagnosisEvents->first();';
 $inject=<<<'BLADE'
// P21_MANAGER_INSPECTION_WORK
$p21InspectionAssignment=\Illuminate\Support\Facades\Schema::hasTable('job_card_inspection_assignments')
  ? \Illuminate\Support\Facades\DB::table('job_card_inspection_assignments as ia')
       ->leftJoin('staff as s','s.id','=','ia.assigned_staff_id')
       ->where('ia.job_card_id',$job->id)
       ->select('ia.assigned_at','ia.assigned_staff_id','s.name as manager_name','s.staff_code as manager_code')->first()
  : null;
BLADE;
 $show=p21Once($show,$needle,$inject."\n".$needle,'job_details_assignment_read');
 $header='<h2 class="section-title">Inspection / Final Diagnosis / Technician Decision</h2>';
 $replace=<<<'BLADE'
<h2 class="section-title">Inspection / Final Diagnosis / Technician Decision</h2>
      <div style="font-size:12px;padding:9px 11px;background:#f0f8ff;border-left:3px solid #2b81c2;border-radius:7px;margin:8px 0">
        <strong>Inspection Assigned To:</strong>
        {{ $p21InspectionAssignment->manager_name ?? 'NOT ASSIGNED' }}
        @if($p21InspectionAssignment?->manager_code) ({{ $p21InspectionAssignment->manager_code }}) @endif
        · <strong>Inspection Completed By:</strong>
        {{ $job->inspection_submitted_by ? (\Illuminate\Support\Facades\DB::table('staff')->where('id',$job->inspection_submitted_by)->value('name') ?: 'Staff #'.$job->inspection_submitted_by) : 'Pending' }}
        <a href="{{ route('erp.inspection-manager.work',$job->id) }}" class="btn small primary" style="margin-left:8px">Assign / Open Manager Inspection →</a>
      </div>
BLADE;
 $show=p21Once($show,$header,$replace,'job_card_status_strip');
 $out=['routes/web.php'=>$routes,'resources/views/layouts/erp.blade.php'=>$layout,'resources/views/job-cards/show.blade.php'=>$show];
 chdir($root);
 require 'vendor/autoload.php';
 $app=require 'bootstrap/app.php';
 $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 foreach($payload as $target=>$src){
   $content=(string)file_get_contents($src);
   if(str_ends_with($target,'.php')) token_get_all($content,TOKEN_PARSE);
   else token_get_all(app('blade.compiler')->compileString($content),TOKEN_PARSE);
 }
 token_get_all($routes,TOKEN_PARSE);
 token_get_all(app('blade.compiler')->compileString($layout),TOKEN_PARSE);
 token_get_all(app('blade.compiler')->compileString($show),TOKEN_PARSE);
 echo "P21_PRECHECK_ALL_SOURCE_AND_BLADE_SYNTAX_PASS\n";
 foreach($sourceFiles as $f)echo 'P21_FILE_CHECK '.$f.' OLD='.hash('sha256',$old[$f]).' NEW='.hash('sha256',$out[$f])."\n";
}catch(Throwable $e){
 fwrite(STDERR,"P21_NO_GO_PRECHECK ".$e->getMessage()."\n");exit(5);
}
if($mode==='check'){echo "P21_DRY_RUN_NO_SOURCE_OR_DATA_MODIFIED\n";exit(0);}
$bak=$root.'/storage/app/p21_manager_inspection_backup_'.date('Ymd_His').'_'.getmypid();
if(!mkdir($bak,0700,true))exit(6);
foreach($sourceFiles as $f){
 $backup=$bak.'/'.str_replace('/','__',$f);
 if(!copy($root.'/'.$f,$backup)||hash_file('sha256',$backup)!==hash('sha256',$old[$f])){
   fwrite(STDERR,"P21_NO_GO_BACKUP\n");exit(6);
 }
}
$writes=[];
try{
 foreach($sourceFiles as $f){
   $dest=$root.'/'.$f;
   if(hash_file('sha256',$dest)!==hash('sha256',$old[$f]))throw new RuntimeException('Concurrent source update '.$f);
   $tmp=tempnam(dirname($dest),'.p21_');
   if(!$tmp||file_put_contents($tmp,$out[$f],LOCK_EX)!==strlen($out[$f]))throw new RuntimeException('Temp write '.$f);
   chmod($tmp,fileperms($dest)&0777);
   if(!rename($tmp,$dest))throw new RuntimeException('Atomic update '.$f);
   $writes[]=$f;
   if(hash_file('sha256',$dest)!==hash('sha256',$out[$f]))throw new RuntimeException('Checksum '.$f);
 }
 foreach($payload as $f=>$src){
   $dest=$root.'/'.$f;
   if(!is_dir(dirname($dest)) && !mkdir(dirname($dest),0755,true))throw new RuntimeException('Create directory '.$f);
   if(file_exists($dest))throw new RuntimeException('Another process created '.$f);
   if(!copy($src,$dest))throw new RuntimeException('Copy '.$f);
   $writes[]=$f;
   if(hash_file('sha256',$dest)!==hash_file('sha256',$src))throw new RuntimeException('Checksum '.$f);
 }
 echo 'P21_APPLIED_BACKUP='.$bak."\n";
 echo "P21_UI_AND_ROUTE_SOURCE_ONLY_NO_INSPECTION_MUTATIONS\n";
}catch(Throwable $e){
 foreach(array_reverse($writes) as $f){
   if(isset($payload[$f]))@unlink($root.'/'.$f);
   else copy($bak.'/'.str_replace('/','__',$f),$root.'/'.$f);
 }
 fwrite(STDERR,"P21_INSTALL_ROLLBACK ".$e->getMessage()."\n");exit(7);
}
