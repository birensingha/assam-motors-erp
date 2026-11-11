#!/usr/bin/env bash
# Bounded inspection for one-panel ROT migration. READ ONLY. Never prints .env.
set -u
cd "$HOME/domains/assammotors.com/assam-erp-staging" || exit 1
echo '=== A: ROT ALTERATION ROUTES (literal file excerpts) ==='
sed -n '136,155p' routes/web.php | cut -c 1-240
echo '=== B: SHOW VIEW – button/link/action anchors ==='
grep -nEi -m 42 'rot.alteration|rot-alteration|rot.assign|Assign ROT|mechanic|am90-edit|data-am90|standard.hours|rate|discount|labour.delete-admin' resources/views/job-cards/show.blade.php | cut -c 1-200 || true
echo '=== C: ALTERATION VIEW – forms and headings ==='
grep -nEi -m 30 '<form|action=|route\(|<h[1-6]|Lock|Alter|Hours|Rate|Discount|Save Job Card' resources/views/job-cards/rot-alteration.blade.php | cut -c 1-200 || true
echo '=== D: ERP LAYOUT – navigation anchors ==='
grep -nEi -m 25 'rot.alteration|rot-alteration|ROT Alteration|job-card|sidebar|include' resources/views/layouts/erp.blade.php | cut -c 1-200 || true
echo '=== E: MAIN JOB CARD AND ROT VIEW – legacy links ==='
for f in resources/views/job-cards/index.blade.php resources/views/job-cards/show.blade.php resources/views/job-cards/rot-billing-control.blade.php resources/views/job-cards/rot-time-editor.blade.php; do
  test -f "$f" || continue
  grep -nEi -m 12 'rot.alteration|rot-alteration|ROT Alteration|route.*alter' "$f" | sed "s#^#$f:#" | cut -c 1-200 || true
done
echo 'ROT_DETAIL_INSPECTION_COMPLETE_NO_CHANGES'
