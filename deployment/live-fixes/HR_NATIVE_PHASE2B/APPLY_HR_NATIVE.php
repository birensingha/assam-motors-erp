<?php
/** ASSAM MOTORS HR Native ERP Phase 2B: install from checked Phase2A payload, staging ONLY. */
declare(strict_types=1);
function hrStop(string $message, int $status=1): never { fwrite($status ? STDERR : STDOUT, $message . PHP_EOL); exit($status); }
$action = $argv[1] ?? '';
if (!in_array($action, ['check','apply'], true)) hrStop('Usage: php APPLY_HR_NATIVE.php check|apply');
$home = rtrim((string) getenv('HOME'), '/');
$app = $home . '/domains/assammotors.com/assam-erp-staging';
$source = $home . '/domains/assammotors.com/HR_PHASE2A/payload';
if (realpath($app) !== $app || !is_file($app.'/artisan') || !is_dir($source)) hrStop('NO-GO: canonical staging app or extracted HR source missing');
$expected = [
 'app/Support/HrAccess.php'=>'cf42fd691ec68ce070999c3ca62b32fd55573652bb98a5be2fa4a1831ce83957',
 'app/Http/Middleware/EnsureHrManager.php'=>'ed1fcb80716d3f6c17e96197fedf3cf7113f82d321a2b2d95c85aea7692eff53',
 'app/Http/Controllers/Web/HrManagerController.php'=>'7f94d7ba49c32d6cb17c26c049b09672061ddd173220f99d61e0b0e3ce6c7651',
 'app/Http/Controllers/Web/StaffHrPolicyController.php'=>'e18991e8ea06e148f85c363ae76b3a20b053ac2985ec2639b5be6c6c69c8c7c4',
 'app/Http/Controllers/Api/V1/HrApiController.php'=>'48b0ef8ea7cde1c1bb05ea5b4866febdb78d997547e0a311147c91eab5d54b76',
 'app/Console/Commands/GrantHrManagerAccess.php'=>'96447b03a5a6ac0ba2acd28bf63ed4f869d66dd00865d0435ece88e8267dcfd7',
 'database/migrations/2026_10_12_220000_create_hr_foundation_tables.php'=>'662bf0889fecdc1a6bc9cf03756dd2ff015b5bbdc3fc32196b275e5c247cd573',
 'resources/views/staff/hr/policies.blade.php'=>'9a08162a149c49470b05b1c3b5e55e01f913bc4302bb0a44c37202c7e4f5c1c1',
 'resources/views/staff/hr/dashboard.blade.php'=>'5d770bba4adbba7261761e2eb86dc8981ee1b9a797c86325fbf28c0559ed9167',
];
$files=[];
foreach ($expected as $rel=>$sha) {
    $p=$source.'/'.$rel;
    if (!is_file($p) || !hash_equals($sha, hash_file('sha256',$p))) hrStop('NO-GO: supplied source checksum mismatch: '.$rel);
    $files[$rel]=(string)file_get_contents($p);
}
// Use ERP-native URL and view, not the legacy Admin/Staff HR URL.
$files['app/Http/Controllers/Web/HrManagerController.php'] = str_replace(
    ["view('staff.hr.dashboard'", "route('staff.hr.dashboard')"],
    ["view('hr.dashboard'", "route('erp.hr.dashboard')"],
    $files['app/Http/Controllers/Web/HrManagerController.php']);
$view = $files['resources/views/staff/hr/dashboard.blade.php'];
unset($files['resources/views/staff/hr/dashboard.blade.php']);
$view = str_replace([
    "route('staff.hr.dashboard')", "route('staff.hr.policies.create')", "route('staff.hr.policies.publish')",
    'Phase 2A – HR Foundation.', 'ASSAM MOTORS · HR Control Centre'
], [
    "route('erp.hr.dashboard')", "route('erp.hr.policies.create')", "route('erp.hr.policies.publish')",
    'Native STAGING ERP HR module.', 'ASSAM MOTORS · ERP HR Management'
], $view);
$view = str_replace('Authorized HR Manager ·', 'Native ERP · Authorized HR Manager ·', $view);
$files['resources/views/hr/dashboard.blade.php'] = $view;
$webFile=$app.'/routes/web.php'; $apiFile=$app.'/routes/api.php';
$web=(string)file_get_contents($webFile); $api=(string)file_get_contents($apiFile);
if (!$web || !$api || str_contains($web,'ASSAM_HR_NATIVE_2B') || str_contains($web,'ASSAM_HR_PHASE2A') || str_contains($api,'ASSAM_HR_PHASE2A')) hrStop('NO-GO: routes unreadable / HR already installed');
function once(string $data,string $needle,string $replacement,string $name): string {
    if (substr_count($data,$needle)!==1) hrStop('NO-GO: route anchor mismatch: '.$name);
    return str_replace($needle,$replacement,$data);
}
$web=once($web,'use Illuminate\\Support\\Facades\\Route;',
    "use App\\Http\\Controllers\\Web\\HrManagerController;\nuse App\\Http\\Controllers\\Web\\StaffHrPolicyController;\nuse App\\Http\\Middleware\\EnsureHrManager;\nuse Illuminate\\Support\\Facades\\Route;",'web imports');
$api=once($api,'use Illuminate\\Support\\Facades\\Route;',
    "use App\\Http\\Controllers\\Api\\V1\\HrApiController;\nuse App\\Http\\Middleware\\EnsureHrManager;\nuse Illuminate\\Support\\Facades\\Route;",'api imports');
$legacyLogin="if (\$path === '/staff' || str_starts_with(\$path, '/staff/')) {";
$web=once($web,$legacyLogin,"if (\$path === '/staff' || str_starts_with(\$path, '/staff/') || \$path === '/erp/hr' || str_starts_with(\$path, '/erp/hr/')) {",'HR login intended');
$webAnchor="// Phase 7 — Staff Portal. Same identity database, separate permission boundary.";
$nativeRoutes=<<<'ROUTES'
// ASSAM_HR_NATIVE_2B — direct ERP HR UI, isolated from Admin and Legacy permissions.
Route::middleware(['auth', EnsureStaffUser::class, EnsureHrManager::class])
    ->prefix('erp/hr')->name('erp.hr.')->group(function () {
        Route::get('/', [HrManagerController::class, 'index'])->name('dashboard');
        Route::post('/policies', [HrManagerController::class, 'createPolicy'])->name('policies.create');
        Route::post('/policies/{policy}/publish', [HrManagerController::class, 'publishPolicy'])->whereNumber('policy')->name('policies.publish');
    });

ROUTES;
$web=once($web,$webAnchor,$nativeRoutes.$webAnchor,'native HR route position');
$staffAnchor="    Route::get('/', [StaffPortalController::class,'index'])->name('staff.portal');";
$staffPolicyRoutes=<<<'ROUTES'
    // ASSAM_HR_NATIVE_2B — ordinary staff can acknowledge only published HR policies.
    Route::get('/policies', [StaffHrPolicyController::class, 'index'])->name('staff.hr.policies');
    Route::post('/policies/{policy}/ack', [StaffHrPolicyController::class, 'acknowledge'])->whereNumber('policy')->name('staff.hr.policies.ack');
ROUTES;
$web=once($web,$staffAnchor,$staffAnchor."\n".$staffPolicyRoutes,'staff policy routes');
$apiAnchor="        Route::post('/auth/logout', [AuthController::class, 'logout']);";
$apiRoutes=<<<'ROUTES'
        // ASSAM_HR_NATIVE_2B — token authenticated read/ack for staff, HR manager operations.
        Route::get('/staff/hr-policies', [HrApiController::class, 'publishedPolicies']);
        Route::post('/staff/hr-policies/{policy}/ack', [HrApiController::class, 'acknowledgePolicy'])->whereNumber('policy');
        Route::middleware(EnsureHrManager::class)->prefix('hr')->group(function () {
            Route::get('/dashboard', [HrApiController::class, 'dashboard']);
            Route::get('/staff', [HrApiController::class, 'staffDirectory']);
            Route::get('/policies', [HrApiController::class, 'managePolicies']);
            Route::post('/policies', [HrApiController::class, 'createPolicy']);
            Route::post('/policies/{policy}/publish', [HrApiController::class, 'publishPolicy'])->whereNumber('policy');
        });
ROUTES;
$api=once($api,$apiAnchor,$apiAnchor."\n".$apiRoutes,'API HR routes');
$files['routes/web.php']=$web; $files['routes/api.php']=$api;
foreach ($files as $rel=>$content) {
    if (str_ends_with($rel,'.php')) { try { token_get_all($content,TOKEN_PARSE); } catch (Throwable $e) { hrStop('NO-GO: parser for '.$rel.': '.$e->getMessage()); } }
    if (!str_starts_with($rel,'routes/') && file_exists($app.'/'.$rel)) hrStop('NO-GO: target already exists: '.$rel);
}
echo "HR NATIVE 2B PRECHECK PASS: direct /erp/hr, 9 new files, 2 guarded route updates.\n";
if ($action==='check') hrStop('READ ONLY PASS; no files changed.',0);
$backup=$app.'/storage/app/hr_native_2b_backup_'.date('Ymd_His').'_'.getmypid();
if (!mkdir($backup,0700,true)) hrStop('NO-GO: protected backup directory failure');
foreach (['routes/web.php','routes/api.php'] as $rel) {
    if (!copy($app.'/'.$rel,$backup.'/'.str_replace('/','_',$rel))) hrStop('NO-GO: cannot back up '.$rel);
}
$created=[];$updated=[];
try {
    foreach ($files as $rel=>$content) {
        if (str_starts_with($rel,'routes/')) continue;
        $target=$app.'/'.$rel;
        if (!is_dir(dirname($target)) && !mkdir(dirname($target),0755,true)) throw new RuntimeException('mkdir failed: '.$rel);
        $f=fopen($target,'x');
        if (!$f) throw new RuntimeException('exclusive create failed '.$rel);
        $n=fwrite($f,$content); fclose($f); $created[]=$target;
        if ($n!==strlen($content)) throw new RuntimeException('short write '.$rel);
    }
    foreach (['routes/web.php','routes/api.php'] as $rel) {
        $target=$app.'/'.$rel; $hash=hash_file('sha256',$target);
        if ($hash!==hash_file('sha256',$backup.'/'.str_replace('/','_',$rel))) throw new RuntimeException('concurrent route change '.$rel);
        $temp=tempnam(dirname($target),'.hrn_');
        if (!$temp || file_put_contents($temp,$files[$rel])!==strlen($files[$rel])) throw new RuntimeException('cannot stage route '.$rel);
        chmod($temp, fileperms($target)&0777);
        if (!rename($temp,$target)) throw new RuntimeException('cannot replace route '.$rel);
        $updated[]=$rel;
    }
    foreach ($files as $rel=>$content) if (hash_file('sha256',$app.'/'.$rel)!==hash('sha256',$content)) throw new RuntimeException('hash failed '.$rel);
} catch (Throwable $e) {
    foreach ($updated as $rel) copy($backup.'/'.str_replace('/','_',$rel),$app.'/'.$rel);
    foreach ($created as $path) @unlink($path);
    hrStop('NO-GO: SOURCE ROLLBACK: '.$e->getMessage());
}
echo 'APPLIED HR NATIVE SOURCE ONLY. BACKUP='.$backup."\n";
echo 'MIGRATION NOT RUN. STAFF GRANT NOT ASSIGNED. APP APK NOT PUBLISHED.' . "\n";