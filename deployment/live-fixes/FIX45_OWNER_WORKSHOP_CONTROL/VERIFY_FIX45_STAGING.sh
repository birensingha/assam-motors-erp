#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-/home/u956497103/domains/assammotors.com/assam-erp-staging}"
php -l "$APP_ROOT/app/Http/Controllers/Api/V1/OwnerWorkshopController.php" >/dev/null
php -l "$APP_ROOT/routes/api.php" >/dev/null
grep -q "FIX45_OWNER_WORKSHOP_CONTROL" "$APP_ROOT/routes/api.php"
grep -q "public function floor" "$APP_ROOT/app/Http/Controllers/Api/V1/OwnerWorkshopController.php"
grep -q "public function transition" "$APP_ROOT/app/Http/Controllers/Api/V1/OwnerWorkshopController.php"
grep -q "public function qc" "$APP_ROOT/app/Http/Controllers/Api/V1/OwnerWorkshopController.php"
grep -q "public function roadTest" "$APP_ROOT/app/Http/Controllers/Api/V1/OwnerWorkshopController.php"
grep -q "public function correctAttendance" "$APP_ROOT/app/Http/Controllers/Api/V1/OwnerWorkshopController.php"

cd "$APP_ROOT"
php artisan route:list --path=api/v1/admin/mobile | grep -q "workshop-dashboard"
php artisan route:list --path=api/v1/admin/mobile | grep -q "workshop-floor"
php artisan route:list --path=api/v1/admin/mobile | grep -q "rot/{session}/action"
php artisan route:list --path=api/v1/admin/mobile | grep -q "rot/{session}/reassign"
php artisan route:list --path=api/v1/admin/mobile | grep -q "rot/{session}/reopen"
php artisan route:list --path=api/v1/admin/mobile | grep -q "job-cards/{job}/osl/request"
php artisan route:list --path=api/v1/admin/mobile | grep -q "osl-requests/{requestId}/status"
php artisan route:list --path=api/v1/admin/mobile | grep -q "job-cards/{job}/control"
php artisan route:list --path=api/v1/admin/mobile | grep -q "job-cards/{job}/qc"
php artisan route:list --path=api/v1/admin/mobile | grep -q "staff/{staff}/attendance"
php artisan route:list --path=api/v1/admin/mobile | grep -q "osl/{osl}/status"
php artisan route:list --path=api/v1/admin/mobile | grep -q "attendance/{attendance}/correct"
grep -q "JOB_PART_ISSUE" "$APP_ROOT/app/Http/Controllers/Api/V1/OwnerWorkshopController.php"
grep -q "JOB_PART_RETURN" "$APP_ROOT/app/Http/Controllers/Api/V1/OwnerWorkshopController.php"
grep -q "ROT_ADMIN_REASSIGN" "$APP_ROOT/app/Http/Controllers/Api/V1/OwnerWorkshopController.php"
grep -q "OSL_REQUEST" "$APP_ROOT/app/Http/Controllers/Api/V1/OwnerWorkshopController.php"
grep -q "last_service" "$APP_ROOT/app/Http/Controllers/Api/V1/OwnerWorkshopController.php"

php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$tables=['workshop_owner_audit_logs','workshop_qc_checks','workshop_road_tests','workshop_key_custody','workshop_reworks','workshop_parts_requests','workshop_osl_requests'];
foreach($tables as $t){
  if(!Illuminate\Support\Facades\Schema::hasTable($t)){ fwrite(STDERR,"Missing $t\n"); exit(20); }
}
if(!Illuminate\Support\Facades\Schema::hasColumn('job_cards','priority')){ fwrite(STDERR,"Missing job_cards.priority\n"); exit(21); }
echo "FIX45 schema verification: PASS\n";
PHP

echo "FIX45 VERIFY: PASS"
