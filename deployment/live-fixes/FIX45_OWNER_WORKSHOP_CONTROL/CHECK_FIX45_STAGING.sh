#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-/home/u956497103/domains/assammotors.com/assam-erp-staging}"
PKG_DIR="$(cd "$(dirname "$0")" && pwd)"
test -f "$APP_ROOT/artisan"
test -f "$APP_ROOT/routes/api.php"
test -f "$APP_ROOT/app/Models/ErpAuthUser.php"
test -f "$APP_ROOT/app/Services/RotWorkflowService.php"
test -f "$APP_ROOT/app/Http/Controllers/Api/V1/AdminMobileController.php"
php -l "$PKG_DIR/payload/OwnerWorkshopController.php" >/dev/null
php -l "$PKG_DIR/PATCH_ROUTES.php" >/dev/null
php -l "$PKG_DIR/MIGRATE_FIX45.php" >/dev/null
grep -q "admin/mobile/dashboard" "$APP_ROOT/routes/api.php"
grep -q "change-mechanic" "$APP_ROOT/routes/api.php"
grep -q "job_card_change_audits" "$APP_ROOT/app/Http/Controllers/Web/AdminJobCardPageController.php"
echo "FIX45 CHECK: PASS"
