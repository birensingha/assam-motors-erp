<?php

$root = rtrim((string)($argv[1] ?? ''), '/');
if ($root === '' || !is_file($root.'/routes/api.php')) {
    fwrite(STDERR, "Usage: php PATCH_ROUTES.php /path/to/laravel/app\n");
    exit(2);
}

$file=$root.'/routes/api.php';
$c=file_get_contents($file);
if($c===false) throw new RuntimeException('Cannot read routes/api.php');

function afterOnce(string $src,string $anchor,string $insert,string $id): string {
    if(str_contains($src,$id)) return $src;
    $n=substr_count($src,$anchor);
    if($n!==1) throw new RuntimeException($id.' anchor count='.$n);
    return str_replace($anchor,$anchor."\n".$insert,$src);
}

$c=afterOnce(
    $c,
    "use App\\Http\\Controllers\\Api\\V1\\AdminMobileController;",
    "use App\\Http\\Controllers\\Api\\V1\\OwnerWorkshopController;",
    'FIX45_OWNER_WORKSHOP_IMPORT'
);

$anchor="            Route::get('/admin/mobile/documents/job-card/{job}', [AdminMobileController::class, 'jobCardDocument'])->whereNumber('job');";
$routes=<<<'PHPROUTES'
            // FIX45_OWNER_WORKSHOP_CONTROL — full Company Owner workshop operations.
            Route::get('/admin/mobile/workshop-dashboard', [OwnerWorkshopController::class, 'dashboard']);
            Route::get('/admin/mobile/workshop-floor', [OwnerWorkshopController::class, 'floor']);
            Route::post('/admin/mobile/job-cards', [OwnerWorkshopController::class, 'createJob']);
            Route::get('/admin/mobile/job-cards/{job}/control', [OwnerWorkshopController::class, 'jobControl'])->whereNumber('job');
            Route::put('/admin/mobile/job-cards/{job}', [OwnerWorkshopController::class, 'editJob'])->whereNumber('job');
            Route::post('/admin/mobile/job-cards/{job}/assign-technician', [OwnerWorkshopController::class, 'assignTechnician'])->whereNumber('job');
            Route::post('/admin/mobile/job-cards/{job}/priority', [OwnerWorkshopController::class, 'priority'])->whereNumber('job');
            Route::post('/admin/mobile/job-cards/{job}/transition', [OwnerWorkshopController::class, 'transition'])->whereNumber('job');
            Route::post('/admin/mobile/job-cards/{job}/inspection/assign', [OwnerWorkshopController::class, 'assignInspection'])->whereNumber('job');
            Route::post('/admin/mobile/job-cards/{job}/diagnosis', [OwnerWorkshopController::class, 'diagnosis'])->whereNumber('job');
            Route::post('/admin/mobile/job-cards/{job}/parts/request', [OwnerWorkshopController::class, 'requestPart'])->whereNumber('job');
            Route::post('/admin/mobile/job-cards/{job}/parts/{part}/status', [OwnerWorkshopController::class, 'partStatus'])->whereNumber('job')->whereNumber('part');
            Route::post('/admin/mobile/parts-requests/{requestId}/status', [OwnerWorkshopController::class, 'partsRequestStatus'])->whereNumber('requestId');
            Route::get('/admin/mobile/osl', [OwnerWorkshopController::class, 'osl']);
            Route::get('/admin/mobile/job-cards/{job}/osl', [OwnerWorkshopController::class, 'osl'])->whereNumber('job');
            Route::post('/admin/mobile/osl/{osl}/status', [OwnerWorkshopController::class, 'oslStatus'])->whereNumber('osl');
            Route::get('/admin/mobile/staff/{staff}/attendance', [OwnerWorkshopController::class, 'staffAttendance'])->whereNumber('staff');
            Route::post('/admin/mobile/job-cards/{job}/qc', [OwnerWorkshopController::class, 'qc'])->whereNumber('job');
            Route::post('/admin/mobile/job-cards/{job}/road-test', [OwnerWorkshopController::class, 'roadTest'])->whereNumber('job');
            Route::post('/admin/mobile/job-cards/{job}/key-custody', [OwnerWorkshopController::class, 'keyCustody'])->whereNumber('job');
            Route::post('/admin/mobile/job-cards/{job}/rework', [OwnerWorkshopController::class, 'rework'])->whereNumber('job');
            Route::get('/admin/mobile/job-cards/{job}/history', [OwnerWorkshopController::class, 'history'])->whereNumber('job');
            Route::post('/admin/mobile/attendance/{attendance}/correct', [OwnerWorkshopController::class, 'correctAttendance'])->whereNumber('attendance');
            Route::get('/admin/mobile/alerts', [OwnerWorkshopController::class, 'alerts']);
            Route::get('/admin/mobile/audit', [OwnerWorkshopController::class, 'auditIndex']);
PHPROUTES;

$c=afterOnce($c,$anchor,$routes,'FIX45_OWNER_WORKSHOP_CONTROL');


// Incremental-safe route additions for already-installed FIX45 staging.
function ensureRouteAfter(string $src,string $anchor,string $route,string $id): string {
    if(str_contains($src,$route)) return $src;
    $n=substr_count($src,$anchor);
    if($n!==1) throw new RuntimeException($id.' anchor count='.$n);
    return str_replace($anchor,$anchor."\n".$route,$src);
}

$c=ensureRouteAfter(
    $c,
    "            Route::get('/admin/mobile/workshop-floor', [OwnerWorkshopController::class, 'floor']);",
    "            Route::get('/admin/mobile/workshop-dashboard', [OwnerWorkshopController::class, 'dashboard']);",
    'FIX45_WORKSHOP_DASHBOARD_ROUTE'
);
$c=ensureRouteAfter(
    $c,
    "            Route::get('/admin/mobile/job-cards/{job}/osl', [OwnerWorkshopController::class, 'osl'])->whereNumber('job');",
    "            Route::post('/admin/mobile/osl/{osl}/status', [OwnerWorkshopController::class, 'oslStatus'])->whereNumber('osl');",
    'FIX45_OSL_STATUS_ROUTE'
);
$c=ensureRouteAfter(
    $c,
    "            Route::post('/admin/mobile/osl/{osl}/status', [OwnerWorkshopController::class, 'oslStatus'])->whereNumber('osl');",
    "            Route::get('/admin/mobile/staff/{staff}/attendance', [OwnerWorkshopController::class, 'staffAttendance'])->whereNumber('staff');",
    'FIX45_STAFF_ATTENDANCE_ROUTE'
);

if(file_put_contents($file,$c)===false) throw new RuntimeException('Cannot write routes/api.php');
echo "FIX45 routes: PASS\n";
