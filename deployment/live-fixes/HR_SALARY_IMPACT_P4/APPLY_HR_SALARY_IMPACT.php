<?php
/** Assam Motors: guarded staging-only source installer for HR fixed salary impact analysis. */
declare(strict_types=1);
function stopImpact(string $s, int $code=2):never { fwrite($code?STDERR:STDOUT,$s."\n"); exit($code); }
$mode=$argv[1]??'';
if(!in_array($mode,['check','apply'],true))stopImpact('Usage: php APPLY_HR_SALARY_IMPACT.php check|apply');
$home=rtrim((string)getenv('HOME'),'/');
$root=$home.'/domains/assammotors.com/assam-erp-staging';
if(!$home || realpath($root)!==$root || !is_file($root.'/artisan')) stopImpact('NO-GO: canonical staging ERP root missing.');
$payload=__DIR__.'/payload';
$creates=[
 'app/Services/HrSalaryImpactCalculator.php',
 'app/Services/HrSalaryImpactService.php',
 'app/Http/Controllers/Web/HrSalaryImpactController.php',
 'app/Http/Controllers/Api/V1/HrSalaryImpactApiController.php',
 'resources/views/hr/salary-impact.blade.php',
 'database/migrations/2026_10_13_060000_create_hr_salary_impact_profiles.php',
];
foreach($creates as $rel){
 $source=$payload.'/'.$rel;
 if(!is_file($source) || is_file($root.'/'.$rel) || is_link($root.'/'.$rel))stopImpact('NO-GO: payload missing or target already exists: '.$rel);
 if(strlen((string)file_get_contents($source))<100)stopImpact('NO-GO: payload source too short: '.$rel);
 if(str_ends_with($rel,'.php') && !str_ends_with($rel,'.blade.php')) {
  try{ token_get_all((string)file_get_contents($source),TOKEN_PARSE); }
  catch(Throwable $e){stopImpact('NO-GO: PHP parser failure in '.$rel);}
 }
}
$readFiles=['routes/web.php','routes/api.php','resources/views/hr/operations.blade.php'];
$current=[];
foreach($readFiles as $rel){
 $file=$root.'/'.$rel;
 if(!is_file($file)||is_link($file))stopImpact('NO-GO: expected existing source missing: '.$rel);
 $current[$rel]=(string)file_get_contents($file);
}
$web=$current['routes/web.php'];$api=$current['routes/api.php'];$view=$current['resources/views/hr/operations.blade.php'];
if(!str_contains($web,'ASSAM_HR_OPERATIONS_P3')||!str_contains($api,'ASSAM_HR_OPERATIONS_P3'))stopImpact('NO-GO: prior HR Operations protection missing.');
if(str_contains($web,'HR_SALARY_IMPACT_PHASE4')||str_contains($api,'HR_SALARY_IMPACT_PHASE4'))stopImpact('ALREADY INSTALLED: no reapply.');
function substituteUnique(string $s,string $needle,string $replacement,string $reason):string {
 if(substr_count($s,$needle)!==1)stopImpact('NO-GO: expected source anchor not unique: '.$reason);
 return str_replace($needle,$replacement,$s);
}
$web=substituteUnique($web,'use App\\Http\\Controllers\\Web\\HrOperationsController;',
 "use App\\Http\\Controllers\\Web\\HrOperationsController;\nuse App\\Http\\Controllers\\Web\\HrSalaryImpactController;",'web import');
$api=substituteUnique($api,'use App\\Http\\Controllers\\Api\\V1\\HrOperationsApiController;',
 "use App\\Http\\Controllers\\Api\\V1\\HrOperationsApiController;\nuse App\\Http\\Controllers\\Api\\V1\\HrSalaryImpactApiController;",'api import');
$webAnchor="        Route::post('/operations/leave', [HrOperationsController::class, 'leave'])->name('operations.leave');";
$webInsert=<<<'ROUTES'
        // HR_SALARY_IMPACT_PHASE4 — Existing verified HR middleware; Admin/workshop routes untouched.
        Route::get('/salary-impact', [HrSalaryImpactController::class,'index'])->name('salary-impact');
        Route::post('/salary-impact/profile', [HrSalaryImpactController::class,'save'])->name('salary-impact.save');
ROUTES;
$web=substituteUnique($web,$webAnchor,$webAnchor."\n".$webInsert,'Web HR protected routes');
$apiAnchor="            Route::post('/operations/leave', [HrOperationsApiController::class, 'leave']);";
$apiInsert=<<<'ROUTES'
            // HR_SALARY_IMPACT_PHASE4 — HR token required. Salary information never exposed to staff endpoints.
            Route::get('/salary-impact', [HrSalaryImpactApiController::class,'index']);
            Route::post('/salary-impact/profile', [HrSalaryImpactApiController::class,'save']);
ROUTES;
$api=substituteUnique($api,$apiAnchor,$apiAnchor."\n".$apiInsert,'API HR protected routes');
$view=substituteUnique($view,'<a href="#payroll">Salary</a>',
 '<a href="{{ route(\'erp.hr.salary-impact\') }}">Salary &amp; Absence Analysis</a>','HR operations salary nav');
$old='<section id="payroll" class="card"><h2>💰 Salary & Payroll Calculation</h2>';
$view=substituteUnique($view,$old,'<section id="payroll" class="card"><h2>💰 Fixed Salary & Absence Impact</h2><p><a href="{{ route(\'erp.hr.salary-impact\') }}">Open HR Fixed Salary &amp; Absence Analysis →</a></p>', 'Salary panel link');
foreach(['routes/web.php'=>$web,'routes/api.php'=>$api] as $name=>$source) {
 try{ token_get_all($source,TOKEN_PARSE); }catch(Throwable $e){stopImpact('NO-GO: unsafe PHP patch for '.$name);}
}
$updates=['routes/web.php'=>$web,'routes/api.php'=>$api,'resources/views/hr/operations.blade.php'=>$view];
echo "HR_SALARY_IMPACT_PHASE4 CHECK PASS: six additive files and three guarded route/view updates.\n";
if($mode==='check')stopImpact('READ ONLY: no files, payroll or attendance values modified.',0);
$backup=$root.'/storage/app/hr_salary_impact_p4_backup_'.date('Ymd_His').'_'.getmypid();
if(!@mkdir($backup,0700,true))stopImpact('NO-GO: cannot create protected backup');
foreach($readFiles as $rel){if(!copy($root.'/'.$rel,$backup.'/'.str_replace('/','_',$rel)))stopImpact('NO-GO: backup failure');}
$created=[];$replaced=[];
try {
 foreach($creates as $rel){
  $f=$root.'/'.$rel;
  if(!is_dir(dirname($f))&&!mkdir(dirname($f),0750,true))throw new RuntimeException('directory creation failed');
  $src=(string)file_get_contents($payload.'/'.$rel);
  $fh=fopen($f,'x');if(!$fh)throw new RuntimeException('target creation failed');
  $n=fwrite($fh,$src);fclose($fh);$created[]=$f;
  if($n!==strlen($src) || hash_file('sha256',$f)!==hash('sha256',$src)) throw new RuntimeException('incomplete source write');
 }
 foreach($updates as $rel=>$content){
  $f=$root.'/'.$rel;
  if(hash_file('sha256',$f)!==hash_file('sha256',$backup.'/'.str_replace('/','_',$rel)))throw new RuntimeException('concurrent route/view edit detected');
  $tmp=tempnam(dirname($f),'.hrp4_');
  if(!$tmp || file_put_contents($tmp,$content,LOCK_EX)!==strlen($content))throw new RuntimeException('staging write failed');
  chmod($tmp,fileperms($f)&0777);
  if(!rename($tmp,$f))throw new RuntimeException('atomic rename failed');
  $replaced[]=$rel;
  if(hash_file('sha256',$f)!==hash('sha256',$content))throw new RuntimeException('post-write mismatch');
 }
}catch(Throwable $e){
 foreach($replaced as $rel)copy($backup.'/'.str_replace('/','_',$rel),$root.'/'.$rel);
 foreach($created as $file)@unlink($file);
 stopImpact('INSTALL BLOCKED, rollback attempted: '.$e->getMessage());
}
echo "HR_SALARY_IMPACT_PHASE4 SOURCE APPLIED; BACKUP={$backup}\n";
echo "NO MIGRATIONS, NO SALARY VALUES, NO PAYROLL/ATTENDANCE DATA CHANGED.\n";