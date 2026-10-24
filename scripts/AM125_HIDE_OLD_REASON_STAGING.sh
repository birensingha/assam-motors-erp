#!/usr/bin/env bash
# AM125 — remove duplicated OLD native Alteration Reason select inside AM116.
# STAGING ONLY. This is a shell installer, not CSS to paste in Bash.
set -Eeuo pipefail
ROOT="$HOME/domains/assammotors.com/assam-erp-staging"
cd "$ROOT"
test -f artisan
URL="https://raw.githubusercontent.com/birensingha/assam-motors-erp/fix/am125-hide-duplicate-p55-reason-20261024/deployment/live-fixes/AM125_HIDE_OLD_REASON/deploy.py"
curl --connect-timeout 15 --max-time 60 -fsSL "$URL" -o /tmp/AM125_HIDE_OLD_REASON_DEPLOY.py
python3 -m py_compile /tmp/AM125_HIDE_OLD_REASON_DEPLOY.py
python3 /tmp/AM125_HIDE_OLD_REASON_DEPLOY.py check "$PWD"
python3 /tmp/AM125_HIDE_OLD_REASON_DEPLOY.py apply "$PWD"
echo "AM125_INSTALL_FINISHED_STAGING_ONLY"
echo "NEXT: Browser hard-refresh; Admin Full ROT Correction should have ONE Reason picker. Choosing an option should unlock normally. Do not save a customer ROT for testing."
