#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"
[[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || { echo "Usage: bash CHECK.sh /path/to/app"; exit 2; }
DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$APP_ROOT"

echo "ASSAM MOTORS FIX38 — JOB CARD EDIT CHECK (READ ONLY)"
CTRL=app/Http/Controllers/Web/AdminJobCardPageController.php
FORM=resources/views/job-cards/form.blade.php
EDIT=resources/views/job-cards/edit.blade.php
INDEX=resources/views/job-cards/index.blade.php
SHOW=resources/views/job-cards/show.blade.php
ROUTES=routes/web.php

for f in "$CTRL" "$FORM" "$EDIT" "$INDEX" "$SHOW" "$ROUTES"; do
  [[ -f "$f" ]] || { echo "NO-GO: missing $f"; exit 3; }
  echo "PASS file: $f"
done

php -l "$DIR/PATCH_FIX38.php" >/dev/null || { echo "NO-GO: patcher syntax"; exit 3; }
echo "PASS patcher syntax"

php artisan route:list 2>&1 | grep -qE 'GET\|HEAD[[:space:]]+erp/job-cards/\{job\}/edit' || { echo "NO-GO: edit route missing"; exit 4; }
php artisan route:list 2>&1 | grep -qE 'PUT[[:space:]]+erp/job-cards/\{job\}' || { echo "NO-GO: update route missing"; exit 4; }
echo "PASS edit/update routes"

grep -q "Modification Reason" "$EDIT" || { echo "NO-GO: existing edit reason missing"; exit 4; }
grep -q "@method('PUT')" "$EDIT" || { echo "NO-GO: existing edit PUT missing"; exit 4; }
grep -q "erp.job-cards.edit" "$INDEX" || { echo "NO-GO: list Edit button missing"; exit 4; }
grep -q "erp.job-cards.edit" "$SHOW" || { echo "NO-GO: summary Edit button missing"; exit 4; }
grep -q "job_card_change_audits" "$CTRL" || { echo "NO-GO: audit backend marker missing"; exit 4; }
grep -q "Locked Job Card" "$CTRL" || { echo "NO-GO: locked-card guard marker missing"; exit 4; }
echo "PASS existing edit process + audit/lock baseline"

if grep -q "FIX38_EDIT_FORM_CONSISTENCY" "$FORM"; then
  echo "FIX38 already installed"
  exit 5
fi

echo "== DRY-RUN AGAINST CURRENT LIVE SHARED FORM =="
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
mkdir -p "$TMP/resources/views/job-cards"
cp -p "$FORM" "$TMP/resources/views/job-cards/form.blade.php"
touch "$TMP/artisan"
php "$DIR/PATCH_FIX38.php" "$TMP"

for m in FIX38_EDIT_FORM_CONSISTENCY "@method('PUT')" "name=\"reason\"" "Modification Reason"; do
  grep -q "$m" "$TMP/resources/views/job-cards/form.blade.php" || { echo "NO-GO: dry-run missing $m"; exit 6; }
done

echo "PASS dry-run shared edit form"
echo "CHECK RESULT: GO"
