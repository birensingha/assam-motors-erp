<?php
/** Staging-only fail-closed HR Operations source installer. No DB migration performed. */
declare(strict_types=1);
function hrOpsStop(string $msg,int $exit=2):never { fwrite($exit?STDERR:STDOUT,$msg."\n"); exit($exit); }
$mode=$argv[1]??'';
if(!in_array($mode,['check','apply'],true)) hrOpsStop('usage: php APPLY_HR_OPERATIONS.php check|apply');
$home=rtrim((string)getenv('HOME'),'/');
$root=$home.'/domains/assammotors.com/assam-erp-staging';
if (!$home||realpath($root)!==$root||!is_file($root.'/artisan')) hrOpsStop('NO-GO: canonical Laravel staging root mismatch');
$payload=__DIR__.'/payload';
$new=[
 'app/Services/HrOperationsService.php',
 'app/Http/Controllers/Web/HrOperationsController.php',
 'app/Http/Controllers/Api/V1/HrOperationsApiController.php',
 'resources/views/hr/operations.blade.php',
 'database/migrations/2026_10_13_030000_create_hr_leave_ledger.php',
];
foreach($new as $rel){
 if(!is_file($payload.'/'.$rel)||is_file($root.'/'.$rel)) hrOpsStop('NO-GO: target missing/exists: '.$rel);
 $source=(string)file_get_contents($payload.'/'.$rel);
 if(strlen($source)<200)hrOpsStop('NO-GO: source empty: '.$rel);
 if(str_ends_with($rel,'.php')){try{token_get_all($source,TOKEN_PARSE);}catch(Throwable $e){hrOpsStop('NO-GO: php parse '.$rel.': '.$e->getMessage());}}
}
$webFile=$root.'/routes/web.php';$apiFile=$root.'/routes/api.php';
$portalFile=$root.'/resources/views/staff/portal.blade.php';
$dashFile=$root.'/resources/views/hr/dashboard.blade.php';
foreach([$webFile,$apiFile,$portalFile,$dashFile] as $path){if(!is_file($path)||is_link($path))hrOpsStop('NO-GO: source missing/symlink: '.$path);}
$web=(string)file_get_contents($webFile);$api=(string)file_get_contents($apiFile);
$portal=(string)file_get_contents($portalFile);$dash=(string)file_get_contents($dashFile);
if(str_contains($web,'ASSAM_HR_OPERATIONS_P3')||str_contains($api,'ASSAM_HR_OPERATIONS_P3')) hrOpsStop('NO-GO: HR Operations already installed; no re-apply');
if(!str_contains($web,"['auth', EnsureStaffUser::class, EnsureHrManager::class]") || !str_contains($api,'Route::middleware(EnsureHrManager::class)->prefix(\'hr\')'))hrOpsStop('NO-GO: HR server-side access boundaries not found');
// These are hashes of the exact Stage Theme V1 files, preventing race overwrites.
$portalExpected='95f7f2e67d15e4e88a5f6645581e5f261ba3486a9af6a8e5b5379aed57a200f3';
$dashExpected='998dd9aded99209c7e02bb1ee3c5c2b5b4bd09c6be84fd9dccd04a4d0b5b2f7d';
if(!hash_equals($portalExpected,hash('sha256',$portal)) || !hash_equals($dashExpected,hash('sha256',$dash)))hrOpsStop('NO-GO: view hash changed since theme V1; no overwrite');
function onlyOnce(string $text,string $from,string $to,string $name):string{
 if(substr_count($text,$from)!==1)hrOpsStop('NO-GO: missing/duplicate anchor: '.$name);
 return str_replace($from,$to,$text);
}
$web=onlyOnce($web,'use App\\Http\\Controllers\\Web\\HrManagerController;',
 "use App\\Http\\Controllers\\Web\\HrManagerController;\nuse App\\Http\\Controllers\\Web\\HrOperationsController;",'web controller import');
$api=onlyOnce($api,'use App\\Http\\Controllers\\Api\\V1\\HrApiController;',
 "use App\\Http\\Controllers\\Api\\V1\\HrApiController;\nuse App\\Http\\Controllers\\Api\\V1\\HrOperationsApiController;",'api controller import');
$webAnchor="        Route::post('/policies/{policy}/publish', [HrManagerController::class, 'publishPolicy'])->whereNumber('policy')->name('policies.publish');";
$hrWeb=<<<'ROUTES'
        // ASSAM_HR_OPERATIONS_P3: HR only; Admin retains its own existing /erp/attendance controls.
        Route::get('/operations', [HrOperationsController::class, 'index'])->name('operations');
        Route::post('/operations/attendance', [HrOperationsController::class, 'attendance'])->name('operations.attendance');
        Route::post('/operations/leave', [HrOperationsController::class, 'leave'])->name('operations.leave');
ROUTES;
$web=onlyOnce($web,$webAnchor,$webAnchor."\n".$hrWeb,'web HR routes');
$apiAnchor="            Route::post('/policies/{policy}/publish', [HrApiController::class, 'publishPolicy'])->whereNumber('policy');";
$hrApi=<<<'ROUTES'
            // ASSAM_HR_OPERATIONS_P3 — HR token API, separated from staff self-service.
            Route::get('/operations', [HrOperationsApiController::class, 'overview']);
            Route::post('/operations/attendance', [HrOperationsApiController::class, 'attendance']);
            Route::post('/operations/leave', [HrOperationsApiController::class, 'leave']);
ROUTES;
$api=onlyOnce($api,$apiAnchor,$apiAnchor."\n".$hrApi,'API HR routes');
$panel='<section class="staff-panel inspection-review-panel">';
$pos=strpos($portal,$panel);
$rotPos=strpos($portal,'<h2>🔧 My ROT Work</h2>');
$scriptPos=strpos($portal,'async function staffAttendance(url)');
if($pos===false||$rotPos===false||$scriptPos===false||!($pos<$rotPos&&$rotPos<$scriptPos))hrOpsStop('NO-GO: Staff Portal work panels changed');
$tag=strrpos(substr($portal,0,$scriptPos),'<script');
if($tag===false||$tag<$rotPos)hrOpsStop('NO-GO: Staff Portal script start changed');
// End the conditional before the working Attendance JavaScript; do not block HR check-in/out.
$portal=substr_replace($portal,"@endunless\n",$tag,0);
$portal=onlyOnce($portal,$panel,"@unless(\\App\\Support\\HrAccess::permitted(auth()->user()))\n".$panel,'HR-only work panels');
$oldNav='<a href="{{ route(\'erp.hr.dashboard\') }}" class="staff-hr-shortcut">👥 HR Management</a>';
$portal=onlyOnce($portal,$oldNav,$oldNav."\n    <a href=\"{{ route('erp.hr.operations') }}\">🕒 HR Attendance &amp; Staff Ledger</a>",'HR portal nav');
$dash=onlyOnce($dash,'<a href="{{ route(\'staff.portal\') }}">Staff Portal</a>',
 '<a href="{{ route(\'staff.portal\') }}">Staff Portal</a><a href="{{ route(\'erp.hr.operations\') }}">Attendance · Production · Leave</a>','HR desktop nav');
$replacements=[
 ['<section class="card"><h2>Employee Directory</h2>', '<section class="card hr-staff-directory"><h2>Employee Directory</h2>'],
 ['<td>{{ $s->staff_code }}</td>','<td data-label="Staff code">{{ $s->staff_code }}</td>'],
 ['<td>{{ $s->name }}</td>','<td data-label="Name">{{ $s->name }}</td>'],
 ['<td>{{ $s->email }}</td>','<td data-label="Email">{{ $s->email }}</td>'],
 ['<td>{{ $s->role }}</td>','<td data-label="Role">{{ $s->role }}</td>'],
 ['<td>{{ $s->department }}</td>','<td data-label="Department">{{ $s->department }}</td>'],
 ['<td>{{ $s->status }}</td>','<td data-label="Status">{{ $s->status }}</td>'],
 ["route('staff.hr.policies.publish', ['policy' => \$p->id])", "route('erp.hr.policies.publish', ['policy' => \$p->id])"],
];
foreach($replacements as [$needle,$replace])$dash=onlyOnce($dash,$needle,$replace,'HR mobile directory/publish: '.substr($needle,0,45));
$css=(string)file_get_contents(__DIR__.'/hr-mobile-directory.css');
if(strlen($css)<2200||substr_count($css,'{')!==substr_count($css,'}'))hrOpsStop('NO-GO: responsive directory stylesheet invalid');
if(substr_count(strtolower($dash),'</head>')!==1)hrOpsStop('NO-GO: HR dashboard head malformed');
$dash=str_ireplace('</head>',"\n".$css."\n</head>",$dash);
$updates=['routes/web.php'=>$web,'routes/api.php'=>$api,'resources/views/staff/portal.blade.php'=>$portal,'resources/views/hr/dashboard.blade.php'=>$dash];
foreach(['routes/web.php','routes/api.php'] as $r){try{token_get_all($updates[$r],TOKEN_PARSE);}catch(Throwable $e){hrOpsStop('NO-GO: '.$r.': '.$e->getMessage());}}
echo 'HR_OPERATIONS_P3 PREFLIGHT PASS: five new files; four targeted existing files; HR-only protected routes.' . "\n";
foreach($updates as $r=>$src) echo 'READY '.$r.' new_sha256='.hash('sha256',$src)."\n";
if($mode==='check')hrOpsStop('READ ONLY PASS. No data, code, migration or APK changed.',0);
$backup=$root.'/storage/app/hr_operations_p3_backup_'.date('Ymd_His').'_'.getmypid();
if(!@mkdir($backup,0700,true))hrOpsStop('NO-GO: private backup directory creation failed');
foreach($updates as $r=>$src){
 if(!copy($root.'/'.$r,$backup.'/'.str_replace('/','_',$r)))hrOpsStop('NO-GO: backup failed '.$r);
}
$created=[];$replaced=[];
try{
 foreach($new as $r){
  $target=$root.'/'.$r;
  if(!is_dir(dirname($target))&&!mkdir(dirname($target),0755,true))throw new RuntimeException('mkdir failed '.$r);
  $data=(string)file_get_contents($payload.'/'.$r);
  $fh=@fopen($target,'x');if(!$fh)throw new RuntimeException('target exists or cannot create '.$r);
  $count=fwrite($fh,$data);fclose($fh);$created[]=$target;
  if($count!==strlen($data)||hash_file('sha256',$target)!==hash('sha256',$data))throw new RuntimeException('write failed '.$r);
 }
 foreach($updates as $r=>$data){
  $target=$root.'/'.$r;$old=$backup.'/'.str_replace('/','_',$r);
  if(hash_file('sha256',$target)!==hash_file('sha256',$old))throw new RuntimeException('concurrent modification '.$r);
  $tmp=tempnam(dirname($target),'.hr_ops_');
  if(!$tmp||file_put_contents($tmp,$data,LOCK_EX)!==strlen($data))throw new RuntimeException('stage failed '.$r);
  chmod($tmp,fileperms($target)&0777);
  if(!rename($tmp,$target))throw new RuntimeException('rename failed '.$r);
  $replaced[]=$r;
  if(hash_file('sha256',$target)!==hash('sha256',$data))throw new RuntimeException('post-install checksum failed '.$r);
 }
}catch(Throwable $e){
 foreach($replaced as $r)copy($backup.'/'.str_replace('/','_',$r),$root.'/'.$r);
 foreach($created as $p)@unlink($p);
 hrOpsStop('NO-GO: code rollback attempted; '.$e->getMessage());
}
echo 'HR_OPERATIONS_P3 INSTALLED SOURCE ONLY; BACKUP='.$backup."\n";
echo "MIGRATION NOT RUN. No salary values, attendance rows or leave ledger entries modified.\n";