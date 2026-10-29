#!/usr/bin/env bash
# AM197 read-only STAGING source inspection.
set -Eeuo pipefail
cd "$1";test -f artisan
file=resources/views/job-cards/show.blade.php
echo AM197_AUDIT_START
echo "FILE_LINES=$(wc -l < "$file")"
echo '=== AM193 AND AM195 MARKERS / INLINE QUICK ACTION ==='
grep -nE -m 55 'AM193_UNUSED_JOB_CARD_DELETE_ACTION|AM195_SINGLE_JC_DELETE_SECTION|Delete Empty Job Card|Permanently Delete Empty Job Card|am75-quick-actions|FIX75_JC_THREE_ACTION_BAR|Job Card Summary|Correct Customer Name|AM194_COMPACT_CUSTOMER_CORRECTION|Estimate/ Reverse|Credit Note|delete-unused' "$file" | cut -c1-240 || true
echo '=== TOP SECTION CONTEXT (ONLY FIRST 180 LINES) ==='
sed -n '1,180p' "$file" | cut -c1-245
echo '=== OLD LEGACY DELETION AREA ==='
n="$(grep -n -m1 'Delete Empty Job Card' "$file" | cut -d: -f1 || true)"
if [[ -n "$n" ]];then lo=$((n>22?n-22:1)); hi=$((n+63));sed -n "${lo},${hi}p" "$file" | cut -c1-240;fi
echo '=== EXISTING ROUTE & BACKEND ==='
php artisan route:list --path=erp/job-cards --no-ansi 2>&1 | grep -Ei -m 8 'delete-unused|show' | cut -c1-220 || true
echo AM197_READONLY_AUDIT_COMPLETE
