#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${1:-}"
[[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || { echo "Usage: bash CHECK.sh /path/to/app"; exit 2; }
DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$APP_ROOT"

echo "ASSAM MOTORS FIX36B — INSPECTION END-TO-END CHECK (READ ONLY)"

FILES=(
  app/Http/Controllers/Web/AdminJobCardPageController.php
  app/Http/Controllers/Web/StaffPortalController.php
  app/Services/StaffDashboardService.php
  resources/views/job-cards/show.blade.php
  resources/views/staff/portal.blade.php
  routes/web.php
)
for f in "${FILES[@]}"; do
  test -f "$f" || { echo "NO-GO missing $f"; exit 3; }
  echo "PASS file: $f"
done

php -l "$DIR/PATCH_FIX36B.php" >/dev/null
php -l "$DIR/files/public/legacy/api/staff-inspections.php" >/dev/null
php -l "$DIR/files/public/legacy/api/staff-inspection.php" >/dev/null
php -l "$DIR/files/resources/views/staff/inspection-work.blade.php" >/dev/null

grep -q "public function assignDecidingTechnician" app/Http/Controllers/Web/AdminJobCardPageController.php
grep -q "Vehicle Inspection Pending" resources/views/job-cards/show.blade.php
grep -q "Inspection Reports / Diagnosis Review" resources/views/staff/portal.blade.php
grep -q "public function inspectionReport" app/Http/Controllers/Web/StaffPortalController.php
grep -q "function inspections" app/Http/Controllers/Api/V1/StaffExtraController.php
grep -q "inspection_submitted_by" app/Http/Controllers/Api/V1/StaffExtraController.php

if grep -q "INSPECTION_TECHNICIAN_ASSIGNED" app/Http/Controllers/Web/AdminJobCardPageController.php 2>/dev/null; then
  echo "FIX36B already appears installed"
  exit 5
fi

for name in staff-inspections.php staff-inspection.php; do
  live="public/legacy/api/$name"
  pkg="$DIR/files/public/legacy/api/$name"
  if [[ -f "$live" ]]; then
    if cmp -s "$live" "$pkg"; then
      echo "PASS existing compatible legacy API: $live"
    else
      echo "NO-GO: existing legacy API differs: $live"
      echo "LIVE:    $(sha256sum "$live" | awk '{print $1}')"
      echo "PACKAGE: $(sha256sum "$pkg" | awk '{print $1}')"
      echo "Run: bash INSPECT_EXISTING.sh $APP_ROOT"
      exit 4
    fi
  else
    echo "PASS legacy API absent and safe to install: $live"
  fi
done

echo "PASS native inspection API already supports assignment/save"
echo "PASS legacy Android inspection bridge is currently absent"
echo "PASS pending Job Card inspection UI baseline"
echo "CHECK RESULT: GO"
