#!/usr/bin/env bash
# Read-only bounded render map. Inspect where P55 is rendered and the current
# Assignment card structure before any changes. No php artisan/DB/cache writes.
set -u
ROOT="$HOME/domains/assammotors.com/assam-erp-staging"
cd "$ROOT" || exit 1
echo 'ROT_RENDER_MAP_START'
echo '=== 1. P55 INCLUDE REFERENCES (ONLY JOB CARD BLADE FILES) ==='
grep -nEi -m 3 'rot-alteration|rot.alteration|p55Lines|p55InvoiceLocked|p55OperationalLocked' resources/views/job-cards/*.blade.php 2>/dev/null | cut -c 1-220 | head -65 || true
echo '=== 2. P55 VIEWS: STRUCTURE + VARS ==='
f=resources/views/job-cards/rot-alteration.blade.php
if [ -f "$f" ]; then sed -n '25,145p' "$f" | cut -c 1-220; fi
echo '=== 3. JOB CARD ASSIGNMENT CARDS: ANCHORS ==='
f=resources/views/job-cards/show.blade.php
if [ -f "$f" ]; then
  grep -nEi -m 28 'Assign ROT|rot-list|rot-card|am90-labour-id|labour.assign|assign-mechanic|@include|rot-alteration' "$f" | cut -c 1-220 || true
  ln=$(grep -n -m 1 -E 'class="rot-list"|class="rot-card|Assign ROT' "$f" | cut -d: -f1)
  if [ -n "$ln" ]; then a=$((ln > 20 ? ln - 20 : 1)); b=$((ln + 90)); echo "ASSIGNMENT_EXCERPT_LINES=$a-$b"; sed -n "${a},${b}p" "$f" | cut -c 1-220; fi
fi
echo '=== 4. GET ROUTES / AMOUNT EDITOR ==='
grep -nEi -m 22 "rot.alteration|amount.editor|amount-editor|job.cards.labour.alter|job.cards.labour.assign|rot.billing" routes/web.php | cut -c 1-220 || true
echo '=== 5. CONTROLLER SOURCE PATH + VALIDATION FIELDS ==='
f=app/Http/Controllers/Web/JobCardRotAlterationController.php
if [ -f "$f" ]; then
  grep -nEi -m 35 'function|validate|standard_hours|labour_rate|discount_percent|alteration_reason|admin_note|alteration_target|invoice|audit' "$f" | cut -c 1-220 || true
else echo 'P55_CONTROLLER_NOT_PRESENT_AT_EXPECTED_PATH'; fi
echo 'ROT_RENDER_MAP_COMPLETE_NO_CHANGES'
