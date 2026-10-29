#!/usr/bin/env bash
# AM198 READONLY inspect exact Job Card header action markup; no data writes.
set -Eeuo pipefail
cd "$1";test -f artisan
file=resources/views/job-cards/show.blade.php
echo "AM198_AUDIT_BEGIN"
echo "=== BUTTON LOCATIONS (no private record values) ==="
grep -nEi -m 48 'am75-quick-actions|am197-delete-open|Edit Job Card|Return to Estimate Review|Return to Estimate|edit-job|editJob|erp\.job-cards\.edit|estimate-review|return.*estimate|job-header|page-head' "$file" | cut -c1-250 || true
echo "=== HEADER NAV AND TITLE CONTEXT ==="
sed -n '370,560p' "$file" | cut -c1-245
echo "=== SECONDARY BUTTONS EXACT CONTEXT ==="
for key in 'Edit Job Card' 'Return to Estimate Review';do
 n="$(grep -n -F -m1 "$key" "$file" | cut -d: -f1 || true)"
 if [[ -n "$n" ]];then
  lo=$((n>32?n-32:1));hi=$((n+35))
  echo "___ $key at line $n ___"
  sed -n "${lo},${hi}p" "$file" | cut -c1-240
 fi
done
echo "=== ROUTES CHECK ==="
php artisan route:list --path=erp/job-cards --no-ansi 2>&1 | grep -Ei -m 20 'job-cards.*(edit|estimate|return)' | cut -c1-225 || true
echo "AM198_READONLY_COMPLETE"
