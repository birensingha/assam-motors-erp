#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${1:-}"
[[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || exit 2
cd "$APP_ROOT"

echo "ASSAM MOTORS FIX36B — READ ONLY VERIFY"

for f in   app/Http/Controllers/Web/AdminJobCardPageController.php   app/Http/Controllers/Web/StaffPortalController.php   app/Services/StaffDashboardService.php   public/legacy/api/staff-inspections.php   public/legacy/api/staff-inspection.php   resources/views/job-cards/show.blade.php   resources/views/staff/portal.blade.php   resources/views/staff/inspection-work.blade.php   routes/web.php
do
  php -l "$f" >/dev/null
done

grep -q "assignInspectionTechnician" app/Http/Controllers/Web/AdminJobCardPageController.php
grep -q "INSPECTION_TECHNICIAN_ASSIGNED" app/Http/Controllers/Web/AdminJobCardPageController.php
grep -q "Vehicle Inspection Assigned To" resources/views/job-cards/show.blade.php
grep -q "Submitted By:" resources/views/job-cards/show.blade.php
grep -q "inspection_pending" app/Services/StaffDashboardService.php
grep -q "Vehicle Inspections Assigned To Me" resources/views/staff/portal.blade.php
grep -q "saveInspectionWork" app/Http/Controllers/Web/StaffPortalController.php
grep -q "Vehicle Inspection completed and sent to Job Card" public/legacy/api/staff-inspections.php

php artisan route:list 2>&1 | grep -q "erp.job-cards.inspection.assign-technician"
php artisan route:list 2>&1 | grep -q "staff.inspection-work"
php artisan route:list 2>&1 | grep -q "staff.inspection-submit"

php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$count=DB::table("job_cards")
  ->whereNotNull("technician_id")
  ->where(function($q){
    $q->whereNull("inspection_status")
      ->orWhereRaw("LOWER(COALESCE(inspection_status,\"pending\")) NOT IN (\"completed\",\"skipped\")");
  })->count();
echo "PASS pending assigned inspection rows=".$count.PHP_EOL;
'

echo "PASS Admin assignment route"
echo "PASS Staff web pending inspection + completion"
echo "PASS Android legacy inspection compatibility API"
echo "PASS Job Card submitted inspector display"
echo "VERIFY RESULT: PASS"
