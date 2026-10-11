<?php
$root = $argv[1] ?? null;
if (!$root || !is_dir($root) || !is_file($root.'/routes/api.php')) {
    fwrite(STDERR, "Invalid Laravel root\n");
    exit(2);
}
$file = $root.'/routes/api.php';
$c = file_get_contents($file);
if ($c === false) { fwrite(STDERR, "Cannot read routes/api.php\n"); exit(2); }

function ensure_after(string &$c, string $anchor, string $insert): void {
    if (str_contains($c, trim($insert))) return;
    if (!str_contains($c, $anchor)) {
        fwrite(STDERR, "Missing route anchor: ".$anchor."\n");
        exit(3);
    }
    $c = str_replace($anchor, $anchor."\n".$insert, $c);
}

ensure_after(
    $c,
    "use App\\Http\\Controllers\\Api\\V1\\AdminStaffComplianceController;",
    "use App\\Http\\Controllers\\Api\\V1\\AdminMobileController;"
);
ensure_after(
    $c,
    "use App\\Http\\Controllers\\Api\\V1\\StaffNotificationController;",
    "use App\\Http\\Controllers\\Api\\V1\\StaffLocationController;"
);

ensure_after(
    $c,
    "        Route::post('/staff/notifications/{notification}/opened', [StaffNotificationController::class, 'opened'])->whereNumber('notification');",
    "        // FIX44 — native staging duty location upload.\n        Route::post('/staff/location', [StaffLocationController::class, 'store']);"
);

$adminAnchor = "            Route::get('/admin/staff-notification-compliance', [AdminStaffComplianceController::class, 'index']);";
$adminRoutes = <<<'PHPROUTES'
            // FIX44 — Company Owner/Admin mobile control centre.
            Route::get('/admin/mobile/dashboard', [AdminMobileController::class, 'dashboard']);
            Route::get('/admin/mobile/staff', [AdminMobileController::class, 'staff']);
            Route::get('/admin/mobile/staff-locations', [AdminMobileController::class, 'locations']);
            Route::get('/admin/mobile/job-cards', [AdminMobileController::class, 'jobCards']);
            Route::get('/admin/mobile/customers', [AdminMobileController::class, 'customers']);
            Route::post('/admin/mobile/broadcast', [AdminMobileController::class, 'broadcast']);
            Route::get('/admin/mobile/documents/job-card/{job}', [AdminMobileController::class, 'jobCardDocument'])->whereNumber('job');
PHPROUTES;
ensure_after($c, $adminAnchor, $adminRoutes);

if (file_put_contents($file, $c) === false) {
    fwrite(STDERR, "Cannot write routes/api.php\n");
    exit(4);
}
echo "FIX44 route patch: PASS\n";
