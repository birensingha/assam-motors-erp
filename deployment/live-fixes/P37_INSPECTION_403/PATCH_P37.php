<?php
declare(strict_types=1);
/**
 * P37_INSPECTION_VIEW_403: Replace wrong admin-only links with staff-specific,
 * assignment-checked GET routes. Never broaden EnsureErpAdmin or Staff rights.
 * No accounting, Job Card, Staff, or inspection DB writes.
 */
$mode=(string)($argv[1]??'check');$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file($root.'/artisan')){fwrite(STDERR,"P37_USAGE check|apply ERP_ROOT\n");exit(2);}
$file=$root.'/resources/views/job-cards/show.blade.php';
if(!is_file($file)||is_link($file)){fwrite(STDERR,"P37_NO_GO_JOB_CARD_VIEW\n");exit(3);}
$source=(string)file_get_contents($file);
$marker='P37_ASSIGNED_INSPECTION_SAFE_LINK';
if(str_contains($source,$marker)){
 if(!str_contains($source,"route('staff.inspection-report'")||
 !str_contains($source,"route('staff.assigned-inspection.show'")){
  fwrite(STDERR,"P37_NO_GO_PARTIAL_PATCH\n");exit(4);
 }
 echo "P37_ALREADY_INSTALLED\n";exit(0);
}
$oldInspect=<<<'BLADE'
<a href="{{ route('erp.inspection-manager.work',$job->id) }}" class="btn small primary" style="margin-left:8px">Assign / Open Manager Inspection →</a>
BLADE;
$oldReview=<<<'BLADE'
<a class="btn ghost" href="{{ route('erp.job-cards.inspection-report',$job->id) }}">Review Manager Inspection</a>
BLADE;
if(substr_count($source,$oldInspect)!==1||substr_count($source,$oldReview)!==1){
 fwrite(STDERR,"P37_NO_GO_EXPECTED_BUTTON_ANCHORS_NOT_UNIQUE\n");exit(5);
}
$newInspect=<<<'BLADE'
{{-- P37_ASSIGNED_INSPECTION_SAFE_LINK: staff accesses only its own assigned inspection, Admin controls unchanged. --}}
@php
  $p37CurrentUser=auth()->user();
  $p37IsStaff=$p37CurrentUser instanceof \App\Models\ErpAuthUser && $p37CurrentUser->active && $p37CurrentUser->isStaff() && (int)$p37CurrentUser->staff_id>0;
  $p37AssignedInspector=$p37IsStaff && \Illuminate\Support\Facades\Schema::hasTable('job_card_inspection_assignments')
    && \Illuminate\Support\Facades\DB::table('job_card_inspection_assignments')
      ->where('job_card_id',$job->id)->where('assigned_staff_id',(int)$p37CurrentUser->staff_id)->exists();
@endphp
@if($p37AssignedInspector)
  <a href="{{ route('staff.assigned-inspection.show',$job->id) }}" class="btn small primary" style="margin-left:8px">🔍 Open My Assigned Inspection →</a>
@endif
@if($p37CurrentUser instanceof \App\Models\ErpAuthUser && $p37CurrentUser->active && $p37CurrentUser->isAdmin())
  <a href="{{ route('erp.inspection-manager.work',$job->id) }}" class="btn small" style="margin-left:8px">Admin: Manage Inspection Assignment →</a>
@endif
BLADE;
$newReview=<<<'BLADE'
{{-- P37_INSPECTION_REPORT_STAFF_READONLY: technician report GET checks own Staff ID in StaffPortalController. --}}
@php
  $p37ReportUser=auth()->user();
  $p37TechnicianRead=$p37ReportUser instanceof \App\Models\ErpAuthUser && $p37ReportUser->active
    && $p37ReportUser->isStaff() && (int)$p37ReportUser->staff_id>0
    && (int)$p37ReportUser->staff_id===(int)$job->technician_id;
@endphp
<a class="btn ghost" href="{{ $p37TechnicianRead ? route('staff.inspection-report',$job->id) : route('erp.job-cards.inspection-report',$job->id) }}">Review Manager Inspection</a>
BLADE;
$updated=str_replace($oldInspect,$newInspect,$source);
$updated=str_replace($oldReview,$newReview,$updated);
if(substr_count($updated,$marker)!==1 ||
 substr_count($updated,'P37_INSPECTION_REPORT_STAFF_READONLY')!==1){
 fwrite(STDERR,"P37_NO_GO_MARKER_COUNT\n");exit(6);
}
chdir($root);require 'vendor/autoload.php';$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach(['staff.inspection-report','staff.assigned-inspection.show','erp.inspection-manager.work','erp.job-cards.inspection-report'] as $name){
 $route=\Illuminate\Support\Facades\Route::getRoutes()->getByName($name);
 if(!$route || !in_array('GET',$route->methods(),true)){fwrite(STDERR,"P37_NO_GO_ROUTE_MISSING $name\n");exit(7);}
 if(str_starts_with($name,'staff.') && !str_contains(implode(',',$route->gatherMiddleware()),'EnsureStaffUser')){
  fwrite(STDERR,"P37_NO_GO_STAFF_ROUTE_GUARD $name\n");exit(7);
 }
}
try{
 token_get_all(app('blade.compiler')->compileString($updated),TOKEN_PARSE);
 echo "P37_BLADE_COMPILE_PASS\n";
}catch(Throwable $e){fwrite(STDERR,"P37_NO_GO_COMPILE ".$e->getMessage()."\n");exit(8);}
if($mode==='check'){echo "P37_DRY_RUN_AUTH_SCOPED_STAFF_INSPECTION_LINKS_PASS\n";exit(0);}
$bak=$root.'/storage/app/p37_inspection_links_backup_'.date('Ymd_His').'_'.getmypid().'.blade.php';
if(!copy($file,$bak)||hash_file('sha256',$bak)!==hash('sha256',$source)){
 fwrite(STDERR,"P37_NO_GO_BACKUP\n");exit(9);
}
if(hash_file('sha256',$file)!==hash('sha256',$source)){fwrite(STDERR,"P37_NO_GO_CONCURRENT_CHANGE\n");exit(10);}
$tmp=tempnam(dirname($file),'.p37_');
if(!$tmp||file_put_contents($tmp,$updated,LOCK_EX)!==strlen($updated)||!chmod($tmp,fileperms($file)&0777)||!rename($tmp,$file)){
 if($tmp)@unlink($tmp);fwrite(STDERR,"P37_NO_GO_WRITE\n");exit(11);
}
if(hash_file('sha256',$file)!==hash('sha256',$updated)){
 copy($bak,$file);fwrite(STDERR,"P37_NO_GO_VERIFY_ROLLBACK\n");exit(12);
}
echo "P37_BACKUP=$bak\nP37_ASSIGNED_STAFF_REPORT_AND_MANAGER_LINKS_APPLIED\n";
