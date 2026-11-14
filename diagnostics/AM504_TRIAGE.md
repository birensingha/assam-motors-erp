# AM504 — STAGING Attendance + Android Staff 504 incident (2026-11-13)

**Incident:** Admin `/erp/attendance` returned nginx 504; Android Staff login and check-in also reported timing out. GitHub issue #187.

## Responsibility
- **GitHub (assistant):** diagnosis script, safe code audit or fix in a branch/PR once real Laravel source is available, review attendance/login/DB timeout handling, regression-test plan.
- **Hostinger server (owner):** execute SSH commands or hPanel steps that require server credentials, inspect redacted nginx/PHP/Laravel/MariaDB logs, apply server configuration/restart/rollback only after evidence and backup.
- **Do not merge experimental AM245/AM246 during outage.**

## Read-only SSH diagnostic
The included `am504_readonly.sh` only performs anonymous HTTP probes, CPU/memory/disk info, PHP version and lists plausible Laravel `artisan` paths. No environment files or database data are read, no server changes are made.

On Hostinger SSH, either read the script in GitHub and run it from a known location, or run the following directly (no download required):

```bash
date -Is
for path in /erp/ /erp/attendance; do
  curl -sS -o /dev/null --connect-timeout 5 --max-time 15 \
    -w "HTTP=%{http_code} TTFB=%{time_starttransfer}s TOTAL=%{time_total}s\n" \
    "https://staging.assammotors.com${path}"
done
```

## Root cause triage (Hostinger user / operator)
1. In Hostinger hPanel inspect **Resource Usage / CPU / RAM / I/O**, PHP error logs and error log timestamps around first 504.
2. Identify whether `/erp/` and `/erp/attendance` differ. If both fail, suspect PHP workers, Laravel bootstrap or DB. If only attendance fails, inspect that route's DB query/locks.
3. From actual Laravel root, view **redacted, limited** recent lines in `storage/logs/laravel.log` or date-specific Laravel logs; do not paste .env, user PII, tokens, session IDs, credentials or full production dumps.
4. Where access permits: inspect nginx upstream timeout, PHP-FPM busy workers, MariaDB long transactions/slow queries/locks. Avoid kill/restart/caching changes until evidence.
5. Check that staff login uses a reachable API and check-in is idempotent: a 504 may occur after a write; avoid repeated check-ins until verifying DB results.
6. Recovery acceptance: successful Admin attendance GET and operations; Staff Android login; exactly **one** check-in persisted; correct app/ERP status; repeat tests for two staff and multiple concurrent requests.
7. Record all corrective actions in issue #187 with redacted evidence. Request authorized Hostinger support if host service resource controls or logs are inaccessible.

## Non-goals
Not a fix, not a deployment, no automated monitor. Never classify a 504 as exclusively authentication or Attendance UI until upstream checks confirm it.
