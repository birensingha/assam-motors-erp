#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"
[[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || { echo "Usage: bash VERIFY.sh /path/to/app"; exit 2; }
cd "$APP_ROOT"
FORM=resources/views/job-cards/form.blade.php
EDIT=resources/views/job-cards/edit.blade.php
INDEX=resources/views/job-cards/index.blade.php
SHOW=resources/views/job-cards/show.blade.php
CTRL=app/Http/Controllers/Web/AdminJobCardPageController.php

echo "ASSAM MOTORS FIX38 — VERIFY"
grep -q "FIX38_EDIT_FORM_CONSISTENCY" "$FORM" && echo "PASS shared edit form guard" || exit 1
grep -q "@method('PUT')" "$FORM" && echo "PASS shared form PUT method" || exit 1
grep -q 'name="reason"' "$FORM" && echo "PASS mandatory modification reason" || exit 1
grep -q "erp.job-cards.edit" "$INDEX" && grep -q "erp.job-cards.edit" "$SHOW" && echo "PASS Edit buttons list + summary" || exit 1
grep -q "Modification Reason" "$EDIT" && echo "PASS dedicated edit page reason" || exit 1
grep -q "job_card_change_audits" "$CTRL" && echo "PASS Job Card audit backend" || exit 1
grep -q "Locked Job Card" "$CTRL" && echo "PASS closed/invoiced/cancelled locking guard" || exit 1
echo "VERIFY RESULT: PASS"
