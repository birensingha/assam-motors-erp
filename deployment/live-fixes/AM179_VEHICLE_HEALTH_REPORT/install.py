#!/usr/bin/env python3
"""AM179: conservative STAGING-only deploy helper.
Never edits Job Card inspection submissions, QC, production or customer records.
Usage: python3 install.py check|apply /path/to/assam-erp-staging
"""
import os
import sys
import shutil
import tempfile
from pathlib import Path
from datetime import datetime

if len(sys.argv) != 3 or sys.argv[1] not in ("check", "apply"):
    raise SystemExit("Usage: python3 install.py check|apply STAGING_LARAVEL_ROOT")
mode = sys.argv[1]
root = Path(sys.argv[2]).expanduser().resolve()
src = Path(__file__).resolve().parent / "files"
if not (root / "artisan").is_file() or not (root / "routes/web.php").is_file():
    raise SystemExit("AM179_STOP_NOT_A_LARAVEL_PROJECT")
if "staging" not in str(root).lower():
    raise SystemExit("AM179_STOP_NOT_STAGING_PATH")

files = [
    "app/Services/VehicleHealthReportService.php",
    "app/Http/Controllers/Web/VehicleHealthReportController.php",
    "app/Http/Controllers/Api/V1/VehicleHealthMobileController.php",
    "resources/views/vehicle-health/report.blade.php",
    "resources/views/vehicle-health/pdf.blade.php",
    "database/migrations/2026_10_28_174000_create_am_vehicle_health_valuations.php",
]
for rel in files:
    if not (src / rel).is_file():
        raise SystemExit("AM179_STOP_PACKAGE_FILE_MISSING " + rel)

route_path = root / "routes/web.php"
api_route_path = root / "routes/api.php"
if not api_route_path.is_file():
    raise SystemExit("AM179_STOP_NATIVE_API_ROUTE_FILE_MISSING")
routes = route_path.read_text(encoding="utf8")
api_routes = api_route_path.read_text(encoding="utf8")
api_anchor = "Route::post('/staff/location', [StaffLocationController::class, 'store']);"
api_marker = "AM181_NATIVE_VEHICLE_HEALTH_PDF"
api_line = ("    // " + api_marker + ": inside existing authenticated staff API group.\n"
            "    Route::get('/staff/inspections/{job}/vehicle-health-pdf', "
            "[\\\\App\\\\Http\\\\Controllers\\\\Api\\\\V1\\\\VehicleHealthMobileController::class, 'pdf'])"
            "->whereNumber('job');")
api_installed = api_marker in api_routes
if not api_installed and api_routes.count(api_anchor) != 1:
    raise SystemExit("AM179_STOP_NATIVE_AUTH_ROUTE_ANCHOR_NOT_FOUND")
new_api_routes = api_routes if api_installed else api_routes.replace(api_anchor,api_anchor+"\n"+api_line,1)
admin_anchor = "Route::get('/job-cards/{job}/inspection-report', [AdminJobCardPageController::class,'inspectionReport'])->whereNumber('job')->name('erp.job-cards.inspection-report');"
staff_anchor = "Route::get('/inspections/{job}/report', [StaffPortalController::class,'inspectionReport'])->whereNumber('job')->name('staff.inspection-report');"
admin_routes = """
    // AM179 OPTIONAL_VEHICLE_HEALTH_REPORT: inherit existing ERP admin auth middleware.
    Route::get('/job-cards/{job}/vehicle-health-report', [\\App\\Http\\Controllers\\Web\\VehicleHealthReportController::class,'adminView'])->whereNumber('job')->name('erp.job-cards.vehicle-health');
    Route::get('/job-cards/{job}/vehicle-health-report.pdf', [\\App\\Http\\Controllers\\Web\\VehicleHealthReportController::class,'adminPdf'])->whereNumber('job')->name('erp.job-cards.vehicle-health-pdf');
    Route::post('/job-cards/{job}/vehicle-health-report/valuation', [\\App\\Http\\Controllers\\Web\\VehicleHealthReportController::class,'saveValuation'])->whereNumber('job')->name('erp.job-cards.vehicle-health-valuation');
"""
staff_routes = """
    // AM179 STAFF_VEHICLE_HEALTH_REPORT: inherits existing staff portal middleware.
    Route::get('/inspections/{job}/vehicle-health-report', [\\App\\Http\\Controllers\\Web\\VehicleHealthReportController::class,'staffView'])->whereNumber('job')->name('staff.vehicle-health');
    Route::get('/inspections/{job}/vehicle-health-report.pdf', [\\App\\Http\\Controllers\\Web\\VehicleHealthReportController::class,'staffPdf'])->whereNumber('job')->name('staff.vehicle-health-pdf');
"""
marker = "AM179 OPTIONAL_VEHICLE_HEALTH_REPORT"
installed = marker in routes
if installed != ("AM179 STAFF_VEHICLE_HEALTH_REPORT" in routes):
    raise SystemExit("AM179_STOP_PARTIAL_ROUTE_INSTALL_REVIEW_REQUIRED")
if not installed:
    if routes.count(admin_anchor) != 1 or routes.count(staff_anchor) != 1:
        raise SystemExit("AM179_STOP_EXACT_AUTH_ROUTE_ANCHORS_NOT_FOUND")
    new_routes = routes.replace(admin_anchor, admin_anchor + "\n" + admin_routes, 1).replace(
        staff_anchor, staff_anchor + "\n" + staff_routes, 1
    )
else:
    new_routes = routes

for rel in files:
    target = root / rel
    if target.exists() and target.read_bytes() != (src / rel).read_bytes():
        raise SystemExit("AM179_STOP_EXISTING_DIFFERENT_FILE " + rel)

# The HTML page can render without Dompdf; PDF buttons must not be enabled until
# Dompdf is installed. Block deployment to avoid claiming PDF functionality.
pdf_available = (root / "vendor/dompdf/dompdf").is_dir()
print("AM179_STAGING_PACKAGE_CHECK")
print("AM179_ROUTE_ANCHORS_OK", installed or (routes.count(admin_anchor) == 1 and routes.count(staff_anchor) == 1))
print("AM179_EXISTING_INSTALL", installed)
print("AM179_PDF_ENGINE", "FOUND" if pdf_available else "MISSING_INSTALL_DOMPDF_FIRST")
print("AM179_PACKAGE_FILES", len(files))
print("AM181_NATIVE_SHARE_ROUTE", "already installed" if api_installed else "inherited authenticated staff route")
if mode == "check":
    print("AM179_DRY_RUN_COMPLETE_NO_WRITES")
    sys.exit(0)
if os.environ.get("AM179_ALLOW_STAGING") != "YES":
    raise SystemExit("AM179_STOP_SET_AM179_ALLOW_STAGING_YES_FOR_STAGING_ONLY")
if not pdf_available:
    raise SystemExit("AM179_STOP_PDF_ENGINE_MISSING")
backup = root / "storage/app/am-backups" / ("AM179_" + datetime.now().strftime("%Y%m%d_%H%M%S"))
backup.mkdir(parents=True, exist_ok=False)
shutil.copy2(route_path, backup / "web.php.before-am179")
shutil.copy2(api_route_path, backup / "api.php.before-am179")
for rel in files:
    target = root / rel
    if not target.exists():
        target.parent.mkdir(parents=True, exist_ok=True)
        fd, tmp = tempfile.mkstemp(dir=target.parent, prefix=".am179_")
        try:
            with os.fdopen(fd, "wb") as handle:
                handle.write((src / rel).read_bytes())
            os.replace(tmp, target)
        finally:
            if os.path.exists(tmp): os.unlink(tmp)
if not installed:
    fd, tmp = tempfile.mkstemp(dir=route_path.parent, prefix=".am179_routes_")
    try:
        with os.fdopen(fd, "w", encoding="utf8") as handle:
            handle.write(new_routes)
        shutil.copymode(backup / "web.php.before-am179", tmp)
        os.replace(tmp, route_path)
    finally:
        if os.path.exists(tmp): os.unlink(tmp)
if not api_installed:
    fd, tmp = tempfile.mkstemp(dir=api_route_path.parent,prefix=".am181_api_")
    try:
        with os.fdopen(fd, "w", encoding="utf8") as handle:
            handle.write(new_api_routes)
        shutil.copymode(backup / "api.php.before-am179", tmp)
        os.replace(tmp, api_route_path)
    finally:
        if os.path.exists(tmp): os.unlink(tmp)
print("AM179_FILES_AND_ROUTES_STAGED", len(files))
print("AM179_BACKUP", backup)
print("NEXT_MANUAL_STEP: php artisan migrate --path=database/migrations/2026_10_28_174000_create_am_vehicle_health_valuations.php --force")
print("NEXT_MANUAL_STEP: php artisan route:clear && php artisan view:clear")
print("NO_DATABASE_MIGRATIONS_EXECUTED_BY_INSTALLER")
