#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${1:-}"
[[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || { echo "Usage: bash APPLY.sh /path/to/app"; exit 2; }
DIR="$(cd "$(dirname "$0")" && pwd)"

bash "$DIR/CHECK.sh" "$APP_ROOT"

STAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP="$(dirname "$APP_ROOT")/ASSAM_MOTORS_FIX36B_BACKUP_$STAMP"
FILES=(
  app/Http/Controllers/Web/AdminJobCardPageController.php
  app/Http/Controllers/Web/StaffPortalController.php
  app/Services/StaffDashboardService.php
  resources/views/job-cards/show.blade.php
  resources/views/staff/portal.blade.php
  routes/web.php
)

for f in "${FILES[@]}"; do
  mkdir -p "$BACKUP/$(dirname "$f")"
  cp -p "$APP_ROOT/$f" "$BACKUP/$f"
done

echo "BACKUP: $BACKUP"

php "$DIR/PATCH_FIX36B.php" "$APP_ROOT"

mkdir -p "$APP_ROOT/public/legacy/api"
mkdir -p "$APP_ROOT/resources/views/staff"
cp -p "$DIR/files/public/legacy/api/staff-inspections.php" "$APP_ROOT/public/legacy/api/staff-inspections.php"
cp -p "$DIR/files/public/legacy/api/staff-inspection.php" "$APP_ROOT/public/legacy/api/staff-inspection.php"
cp -p "$DIR/files/resources/views/staff/inspection-work.blade.php" "$APP_ROOT/resources/views/staff/inspection-work.blade.php"

cd "$APP_ROOT"
for f in   app/Http/Controllers/Web/AdminJobCardPageController.php   app/Http/Controllers/Web/StaffPortalController.php   app/Services/StaffDashboardService.php   public/legacy/api/staff-inspections.php   public/legacy/api/staff-inspection.php   resources/views/job-cards/show.blade.php   resources/views/staff/portal.blade.php   resources/views/staff/inspection-work.blade.php   routes/web.php
do
  php -l "$f"
done

php artisan view:clear >/dev/null 2>&1 || true
php artisan route:clear >/dev/null 2>&1 || true

bash "$DIR/VERIFY.sh" "$APP_ROOT"

echo "FIX36B APPLY COMPLETE"
echo "Backup: $BACKUP"
echo "No database migration was run."
