#!/usr/bin/env bash
# ASSAM MOTORS AM120: inspect REAL staging routes + full admin ROT correction contracts.
# Read-only, small fixed files only, no application bootstrap/database/cache changes.
set -u
ROOT="${1:-$HOME/domains/assammotors.com/assam-erp-staging}"
cd "$ROOT" || exit 1
test -f artisan || { echo "STOP_NOT_STAGING_ROOT"; exit 1; }
echo "AM120_COMPLETE_ROT_CORRECTION_AUDIT_START"
echo "=== 1: ALL RELEVANT ROUTES (bounded file lines only) ==="
if test -d routes; then
  for f in routes/*.php; do
    test -f "$f" || continue
    grep -nEi -m 24 "amount.editor|amount-editor|rot.alteration|labour.alter|rot.reopen|rot.history|reset.end.time|change.mechanic|reopenResetEndTime|labour.assign|rot.time|reversal" "$f" 2>/dev/null |
      sed "s#^#$f:#" | cut -c 1-250 || true
  done
fi
echo "=== 2: JOB CARD MAIN HEADER / ACTION LINKS ==="
f=resources/views/job-cards/show.blade.php
if test -f "$f"; then
  grep -nEi -m 38 "Amount Editor|amount.editor|amount-editor|Job Card Amount|rot-alteration|rot-time-editor|rot.reopen|reopen|reset|rot.history|labour.billing|labour.alter" "$f" |
    cut -c 1-250 || true
fi
echo "=== 3: P55 EXACT BLADE FORM / FIELD CONTRACT ==="
f=resources/views/job-cards/rot-alteration.blade.php
if test -f "$f"; then
  sed -n '48,145p' "$f" | cut -c 1-250
  echo "P55_FORM_ASSIGNMENT_REFERENCES:"
  grep -nEi -m 28 'p55Rows|p55Session|source_estimate|mechanic|job.card|form|data-p55|@forelse|@foreach' "$f" |
    cut -c 1-250 || true
fi
echo "=== 4: P55 CONTROLLER VALIDATION + PERSISTENCE, LIMITED ==="
f=app/Http/Controllers/Web/JobCardRotAlterationController.php
if test -f "$f"; then
  sed -n '35,140p' "$f" | cut -c 1-250
  echo "P55_PERSISTENCE_GUARD_LINES:"
  grep -nEi -m 30 'status|invoice|mechanic|rot.start|rot.end|oldHours|oldRate|discount|audit|lockForUpdate|reopen|reset' "$f" |
    cut -c 1-250 || true
fi
echo "=== 5: MAIN JC RENDERED ROT CARD DOM (NO CUSTOMER DATA) ==="
f=resources/views/job-cards/show.blade.php
if test -f "$f"; then
  grep -nEi -m 35 'rot-card|rot-actions|Assign ROT|am-v647-time-editor|rot.change-mechanic|rot.reopen|rot.history|am90-labour|am118' "$f" |
    cut -c 1-250 || true
fi
echo "=== 6: AMOUNT EDITOR PARENT INCLUDES (ONLY LIKELY VIEWS) ==="
for f in resources/views/job-cards/*.blade.php; do
  grep -nEi -m 3 "@include\('job-cards.rot-alteration|@includeIf\('job-cards.rot-alteration|Job Card Amount Editor" "$f" 2>/dev/null |
    sed "s#^#$f:#" | cut -c 1-240 || true
done
echo "AM120_COMPLETE_ROT_CORRECTION_AUDIT_COMPLETE_NO_WRITES"
