#!/usr/bin/env bash
# AM122 - SAFE READ-ONLY live Laravel inventory/estimate/JC integration audit.
# No database writes; no artisan commands; no source modifications.
set -euo pipefail
ROOT="${1:-$HOME/domains/assammotors.com/assam-erp-staging}"
cd "$ROOT"
test -f artisan || { echo "AM122_ERROR=NOT_LARAVEL_ROOT"; exit 2; }
echo 'AM122_STAGING_READONLY_INSPECT_V1'
printf 'ROOT=%s\n' "$PWD"

show() {
 local f="$1" from="$2" to="$3"
 echo "=== SOURCE $f lines $from-$to ==="
 if [ -f "$f" ]; then sed -n "${from},${to}p" "$f"; else echo 'FILE_MISSING'; fi
}
echo '=== REQUIRED FILES ==='
for f in resources/views/estimates/create.blade.php public/js/estimate-entry.js resources/views/job-cards/show.blade.php app/Http/Controllers/Web/AdminEstimatePageController.php app/Models/PartMaster.php routes/web.php;do
 if [ -f "$f" ];then printf 'PRESENT %s\n' "$f";else printf 'MISSING %s\n' "$f";fi
done

echo '=== PART CREATE ROUTES ==='
if [ -f routes/web.php ];then
 grep -nEi 'part.*master|master.*part|inventory|job-cards.*parts|estimates.*(create|store)' routes/web.php | head -n 90 || true
fi
echo '=== PART CONTROLLER FILES ==='
find app/Http/Controllers -maxdepth 4 -type f \( -iname '*part*' -o -iname '*inventory*' \) -print 2>/dev/null | head -n 35
echo '=== ESTIMATE RELEVANT ANCHORS ==='
if [ -f resources/views/estimates/create.blade.php ];then
 grep -nEi 'part_master_id|part.?search|new.?part|quick.?create|create.?part|add.?part|estimate-entry\.js' resources/views/estimates/create.blade.php | head -n 75 || true
fi
echo '=== ESTIMATE JS ANCHORS ==='
if [ -f public/js/estimate-entry.js ];then
 grep -nEi 'part|fetch\(|ajax|submit|change|option' public/js/estimate-entry.js | head -n 90 || true
fi
echo '=== JOB CARD PART ANCHORS ==='
if [ -f resources/views/job-cards/show.blade.php ];then
 grep -nEi 'part_master_id|new.?part|quick.?create|search.?part|add.?part|part.?select' resources/views/job-cards/show.blade.php | head -n 105 || true
fi
show app/Models/PartMaster.php 1 140
show app/Http/Controllers/Web/AdminEstimatePageController.php 1 115
echo 'AM122_READ_ONLY_INSPECT_COMPLETE_NO_CHANGES'
