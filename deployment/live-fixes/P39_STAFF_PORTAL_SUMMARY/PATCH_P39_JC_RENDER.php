<?php
declare(strict_types=1);
/**
 * P39: Repair regression from P37 Blade @php variable scope in Job Card summary.
 * Use explicit raw PHP initialization in Blade to ensure variables exist.
 * Guarded: changes exactly two P37 snippets and never touches business records.
 */
$mode=(string)($argv[1]??'check');$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file($root.'/artisan')){fwrite(STDERR,"P39_USAGE check|apply ERP_ROOT\n");exit(2);}
$path="$root/resources/views/job-cards/show.blade.php";
if(!is_file($path)||is_link($path)){fwrite(STDERR,"P39_NO_GO_JC_VIEW\n");exit(3);}
$before=(string)file_get_contents($path);
if(str_contains($before,'P39_JC_RENDER_SCOPE_V1')){echo "P39_ALREADY_INSTALLED\n";exit(0);}
$patternA='~\{\{-- P37_ASSIGNED_INSPECTION_SAFE_LINK:.*?\}\}\s*@php\s*.*?@endphp\s*(?=@if\(\$p37AssignedInspector\))~s';
$replacementA=<<<'BLADE'
{{-- P39_JC_RENDER_SCOPE_V1: explicit PHP variables avoid P37 Blade @php scope regression. --}}
<?php
  $p37CurrentUser=auth()->user();
  $p37IsStaff=$p37CurrentUser instanceof \App\Models\ErpAuthUser
      && $p37CurrentUser->active
      && $p37CurrentUser->isStaff()
      && (int)$p37CurrentUser->staff_id>0;
  $p37AssignedInspector=$p37IsStaff
      && \Illuminate\Support\Facades\Schema::hasTable('job_card_inspection_assignments')
      && \Illuminate\Support\Facades\DB::table('job_card_inspection_assignments')
          ->where('job_card_id',$job->id)
          ->where('assigned_staff_id',(int)$p37CurrentUser->staff_id)
          ->exists();
?>
BLADE;
$patternB='~\{\{-- P37_INSPECTION_REPORT_STAFF_READONLY:.*?\}\}\s*@php\s*.*?@endphp\s*(?=<a class="btn ghost")~s';
$replacementB=<<<'BLADE'
{{-- P39 report access: already linked Staff authorization is enforced by route/controller. --}}
<?php
  $p37ReportUser=auth()->user();
  $p37TechnicianRead=$p37ReportUser instanceof \App\Models\ErpAuthUser
      && $p37ReportUser->active
      && $p37ReportUser->isStaff()
      && (int)$p37ReportUser->staff_id>0
      && (int)$p37ReportUser->staff_id===(int)$job->technician_id;
?>
BLADE;
if(preg_match_all($patternA,$before)!==1||preg_match_all($patternB,$before)!==1){
 fwrite(STDERR,"P39_NO_GO_EXPECTED_P37_MARKERS\n");exit(4);
}
$after=preg_replace($patternA,$replacementA,$before,1);
$after=preg_replace($patternB,$replacementB,$after,1);
if(substr_count($after,'P39_JC_RENDER_SCOPE_V1')!==1 || !str_contains($after,'P37_ASSIGNED_STAFF')){
 // Do not require a second marker: only the audited P37 routes + P39 scope must exist.
 if(substr_count($after,'P39_JC_RENDER_SCOPE_V1')!==1){fwrite(STDERR,"P39_NO_GO_SCOPE_MARKER\n");exit(5);}
}
try{
 chdir($root);require 'vendor/autoload.php';$app=require 'bootstrap/app.php';
 $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 $compiled=app('blade.compiler')->compileString($after);
 token_get_all($compiled,TOKEN_PARSE);
}catch(Throwable $e){fwrite(STDERR,"P39_NO_GO_BLADE_PARSE ".get_class($e)."\n");exit(6);}
if($mode==='check'){echo "P39_JC_RENDER_SCOPE_DRY_RUN_PASS\n";exit(0);}
$backup=$root.'/storage/app/p39_jc_render_backup_'.date('Ymd_His').'_'.getmypid().'.blade.php';
if(!copy($path,$backup)||hash_file('sha256',$backup)!==hash('sha256',$before)){fwrite(STDERR,"P39_NO_GO_BACKUP\n");exit(7);}
if(hash_file('sha256',$path)!==hash('sha256',$before)){fwrite(STDERR,"P39_NO_GO_CONCURRENT_EDIT\n");exit(8);}
$tmp=tempnam(dirname($path),'.p39_');
if(!$tmp||file_put_contents($tmp,$after,LOCK_EX)!==strlen($after)||!chmod($tmp,fileperms($path)&0777)||!rename($tmp,$path)){
 if($tmp)@unlink($tmp);
 fwrite(STDERR,"P39_NO_GO_WRITE\n");exit(9);
}
if(hash_file('sha256',$path)!==hash('sha256',$after)){
 copy($backup,$path);fwrite(STDERR,"P39_CHECKSUM_ROLLBACK\n");exit(10);
}
echo "P39_BACKUP=$backup\nP39_JC_RENDER_SCOPE_APPLIED\n";
