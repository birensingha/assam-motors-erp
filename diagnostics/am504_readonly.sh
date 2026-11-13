#!/usr/bin/env bash
# AM504 — read-only SSH diagnostic for Assam Motors STAGING (Hostinger shared hosting)
# SAFE: No database modifications, cache clearing, restarts, writes, or credential retrieval.
set -u
export LC_ALL=C

printf '%s\n' "AM504_READONLY_START"
printf '\n[%s]\n' 'Time'
date -Is 2>&1 || date

printf '\n[%s]\n' 'ERP public HTTP probes (anonymous, no login tokens)'
for path in /erp/ /erp/attendance; do
    printf 'Path: %s  ' "$path"
    curl --connect-timeout 5 --max-time 15 --silent --show-error \
      --output /dev/null \
      --write-out 'HTTP=%{http_code} CONNECT=%{time_connect}s TTFB=%{time_starttransfer}s TOTAL=%{time_total}s\n' \
      "https://staging.assammotors.com${path}" 2>&1 || printf '%s\n' 'PROBE_FAILED'
done

printf '\n[%s]\n' 'Host resources (if permitted)'
uptime 2>&1 || true
free -m 2>&1 || true
df -h "$HOME" 2>&1 || true

printf '\n[%s]\n' 'PHP version'
php -v 2>&1 | head -n 2 || true

printf '\n[%s]\n' 'Laravel app candidates (paths only; no secret file reads)'
for root in "$HOME/domains/staging.assammotors.com" "$HOME/public_html" "$HOME/assammotors.com"; do
    if [ -d "$root" ]; then
        find "$root" -maxdepth 4 -type f -name artisan -print 2>/dev/null | head -n 8
    fi
done

printf '\n%s\n' "AM504_READONLY_END"
printf '%s\n' "NOTE: This script does not check authenticated Staff APIs or access private server logs."
printf '%s\n' "Do not share .env, DB passwords, session cookies, access tokens, full sensitive logs."
