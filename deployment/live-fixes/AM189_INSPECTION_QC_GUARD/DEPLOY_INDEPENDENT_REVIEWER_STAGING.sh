#!/usr/bin/env bash
set -euo pipefail
ROOT="$HOME/domains/assammotors.com/assam-erp-staging"
REPO="$HOME/assam-motors-deploy"
PREFIX="deployment/live-fixes/AM189_INSPECTION_QC_GUARD/files"
test -f "$ROOT/artisan" && test -d "$REPO/.git" || { echo "STOP: staging root or repo missing"; exit 1; }
cd "$REPO"
git rev-parse --verify "FETCH_HEAD^{commit}" >/dev/null
BACKUP="$ROOT/storage/app/am-backups/AM_QC_REVIEWER_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP"
paths=( app/Services/InspectionQcGuard.php app/Http/Controllers/Web/InspectionQcDefectController.php resources/views/inspection-defects/index.blade.php database/migrations/2026_11_05_150000_add_independent_qc_reviewer_staff.php )
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
for rel in "${paths[@]}"; do mkdir -p "$TMP/$(dirname "$rel")"; git show "FETCH_HEAD:$PREFIX/$rel" > "$TMP/$rel"; done
php -l "$TMP/app/Services/InspectionQcGuard.php"
php -l "$TMP/app/Http/Controllers/Web/InspectionQcDefectController.php"
php -l "$TMP/database/migrations/2026_11_05_150000_add_independent_qc_reviewer_staff.php"
grep -q "function verify(" "$ROOT/app/Services/InspectionQcGuard.php"
grep -q "function verify(" "$ROOT/app/Http/Controllers/Web/InspectionQcDefectController.php"
grep -q "Independent Re-inspection" "$ROOT/resources/views/inspection-defects/index.blade.php"
echo "AM_QC_PREFLIGHT_PASS"
for rel in "${paths[@]}"; do if [ -e "$ROOT/$rel" ]; then mkdir -p "$BACKUP/$(dirname "$rel")"; cp -p "$ROOT/$rel" "$BACKUP/$rel"; fi; done
echo "BACKUP: $BACKUP"
for rel in "${paths[@]}"; do mkdir -p "$ROOT/$(dirname "$rel")"; cp "$TMP/$rel" "$ROOT/$rel"; done
cd "$ROOT"
if ! php artisan migrate --path=database/migrations/2026_11_05_150000_add_independent_qc_reviewer_staff.php --force; then
  for rel in "${paths[@]}"; do if [ -f "$BACKUP/$rel" ]; then cp -p "$BACKUP/$rel" "$ROOT/$rel"; else rm -f "$ROOT/$rel"; fi; done
  echo "MIGRATION_FAILED_CODE_RESTORED"; exit 1
fi
php artisan optimize:clear
php -l app/Services/InspectionQcGuard.php
php -l app/Http/Controllers/Web/InspectionQcDefectController.php
echo "AM_QC_REVIEWER_STAGING_CODE_INSTALLED"
echo "No QC status automatically changed; independent verification required"
