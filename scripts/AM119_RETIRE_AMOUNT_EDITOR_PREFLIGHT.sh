#!/usr/bin/env bash
# AM119 / ASSAM MOTORS — read-only staging map for retiring duplicate Job Card Amount Editor.
# NO application writes, database queries, artisan boot, cache operations, or broad source scans.
set -u
cd "$HOME/domains/assammotors.com/assam-erp-staging" || exit 1
test -f artisan || exit 1
echo 'AM119_DUPLICATE_AMOUNT_EDITOR_AUDIT_START'
echo '=== A. GET/POST ROUTE DECLARATIONS ==='
grep -nEi -m 45 'amount.editor|amount-editor|rot.alteration|rot-alteration|billing.control|labour.alter|labour.billing|job.cards.show|rot.billing' routes/web.php | cut -c 1-220 || true
echo '=== B. AMOUNT EDITOR VIEW FILENAMES ==='
find resources/views -maxdepth 5 -type f \( -iname '*amount*' -o -iname '*alteration*' -o -iname '*editor*' \) -print 2>/dev/null | head -45 || true
echo '=== C. EXISTING MAIN JOB CARD CONTROL REFERENCES ==='
f=resources/views/job-cards/show.blade.php
if test -f "$f"; then
  grep -nEi -m 32 'amount.editor|amount-editor|rot.alteration|rot-alteration|job.card.amount|am90-edit|std hrs locked|standard_hours|labour.delete-admin|labour.assign' "$f" | cut -c 1-220 || true
fi
echo '=== D. EXISTING ERP SIDEBAR LINKS ==='
for f in resources/views/layouts/erp.blade.php resources/views/layouts/erp-shell-fix50.blade.php resources/views/layouts/admin.blade.php; do
  if test -f "$f"; then
    echo "FILE=$f"
    grep -nEi -m 18 'amount.editor|amount-editor|Amount Editor|rot.alteration|Job Card|rot.billing' "$f" | cut -c 1-220 || true
  fi
done
echo '=== E. OLD EDITOR FILE HEADER (LIMITED) ==='
for f in resources/views/job-cards/rot-alteration.blade.php resources/views/job-cards/rot-billing-control.blade.php; do
  if test -f "$f"; then echo "FILE=$f"; grep -nEi -m 12 'p55-rot-edit|Job Card Amount Editor|std hours|labour_rate|discount_percent|data-p55-form' "$f" | cut -c 1-220 || true; fi
done
echo 'AM119_AUDIT_COMPLETE_NO_CHANGES'
