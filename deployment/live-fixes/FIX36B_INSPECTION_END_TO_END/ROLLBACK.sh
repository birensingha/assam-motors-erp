#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${1:-}"
BACKUP="${2:-}"
[[ -n "$APP_ROOT" && -d "$BACKUP" ]] || { echo "Usage: bash ROLLBACK.sh /path/to/app /path/to/backup"; exit 2; }

FILES=(
  app/Http/Controllers/Web/AdminJobCardPageController.php
  app/Http/Controllers/Web/StaffPortalController.php
  app/Services/StaffDashboardService.php
  resources/views/job-cards/show.blade.php
  resources/views/staff/portal.blade.php
  routes/web.php
)

for f in "${FILES[@]}"; do
  cp -p "$BACKUP/$f" "$APP_ROOT/$f"
done

OPTIONAL=(
  public/legacy/api/staff-inspections.php
  public/legacy/api/staff-inspection.php
  resources/views/staff/inspection-work.blade.php
)
for f in "${OPTIONAL[@]}"; do
  if [[ -f "$BACKUP/$f" ]]; then
    mkdir -p "$APP_ROOT/$(dirname "$f")"
    cp -p "$BACKUP/$f" "$APP_ROOT/$f"
  else
    rm -f "$APP_ROOT/$f"
  fi
done

cd "$APP_ROOT"
php artisan view:clear >/dev/null 2>&1 || true
php artisan route:clear >/dev/null 2>&1 || true
echo "FIX36B ROLLBACK COMPLETE"
