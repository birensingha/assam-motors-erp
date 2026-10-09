#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"
[[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || { echo "Usage: bash VERIFY.sh /path/to/app"; exit 2; }
cd "$APP_ROOT"
LEG=public/legacy/workshop/customers.php

echo "ASSAM MOTORS FIX40 — VERIFY"
php -l "$LEG" >/dev/null && echo "PASS legacy customers.php syntax" || exit 1
grep -q "FIX40_DIRECT_BROWSER_REDIRECT" "$LEG" && echo "PASS direct browser redirect guard" || { echo "FAIL redirect guard"; exit 1; }
grep -q "Location: /erp/customers" "$LEG" && echo "PASS redirect target /erp/customers" || { echo "FAIL redirect target"; exit 1; }
grep -q "\$_GET\['action'\]" "$LEG" && echo "PASS legacy action API preserved" || { echo "FAIL action guard"; exit 1; }
php artisan route:list 2>&1 | grep -qE 'GET\|HEAD[[:space:]]+erp/customers[[:space:]]' && echo "PASS native Customer Master route" || { echo "FAIL native route"; exit 1; }
echo "VERIFY RESULT: PASS"
