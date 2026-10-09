# Assam Motors — Staging Deployment Runbook

Release line: `release/workshop-stack-20261008`  
Android line: `release/v6.0.18-20261008`

This runbook is conservative. It separates **database preparation**, **ERP application wiring**, **Android/Firebase enablement**, and **verification** so that one failure does not force a full rollback.

> Live staging inventory/schema discovery was completed on 09-Oct-2026. Exact source paths/tables are recorded in `deployment/LIVE_STAGING_MAP_20261009.md`. Targeted source contents are still pending review. This runbook does not claim staging has been changed.

## 0. Change window and freeze

Before starting:

1. announce staging maintenance window;
2. stop parallel manual edits to the same staging source;
3. record current Git commit/deployed build;
4. record current Android production version;
5. confirm a rollback operator is available;
6. do not run production signing or Firebase enablement yet.

Record start time, operator, old/new commit, backup paths, migration results, smoke tests and any rollback decision.

## 1. Preflight — READ ONLY

From this repository:

```bash
bash deployment/preflight.sh
```

If the real staging application is available locally:

```bash
APP_ROOT=/path/to/staging/app bash deployment/preflight.sh
```

Optional read-only DB connectivity/table inspection:

```bash
APP_ROOT=/path/to/staging/app \
DB_HOST=127.0.0.1 \
DB_PORT=3306 \
DB_DATABASE=staging_db \
DB_USERNAME=staging_user \
DB_PASSWORD='***' \
CHECK_DB=1 \
bash deployment/preflight.sh
```

Do not continue if required release files are missing, the supplied Laravel root is wrong, requested DB connectivity fails, tracked secret-like files are detected, or a backup cannot be created.

## 2. Capture deployment baseline

From the real staging app:

```bash
cd /path/to/staging/app
git rev-parse HEAD
git status --short
php artisan about 2>/dev/null || true
php artisan route:list > /tmp/assam-motors-routes-before.txt
```

Do not deploy over unexpected uncommitted source changes.

## 3. Database backup

Example only—use the actual staging backup process:

```bash
STAMP="$(date +%Y%m%d_%H%M%S)"
mkdir -p /secure/backups/assam-motors

MYSQL_PWD="$DB_PASSWORD" mysqldump \
  --host="$DB_HOST" \
  --port="$DB_PORT" \
  --user="$DB_USERNAME" \
  --single-transaction \
  --routines \
  --triggers \
  --events \
  "$DB_DATABASE" \
  > "/secure/backups/assam-motors/staging_${STAMP}.sql"

test -s "/secure/backups/assam-motors/staging_${STAMP}.sql"
```

Schema-only snapshot:

```bash
MYSQL_PWD="$DB_PASSWORD" mysqldump \
  --host="$DB_HOST" \
  --port="$DB_PORT" \
  --user="$DB_USERNAME" \
  --no-data \
  "$DB_DATABASE" \
  > "/secure/backups/assam-motors/staging_schema_${STAMP}.sql"
```

Never store DB dumps under public web root or in Git.

## 4. Database preparation — LIVE-SCHEMA OVERRIDE

**Do not apply the five old reference SQL files by default.**

The 09-Oct-2026 staging schema inventory showed existing live equivalents:

| Reference patch | Live staging equivalent | Decision |
|---|---|---|
| Job Card edit audit | `job_card_change_audits` + existing migration | **DO NOT CREATE PARALLEL TABLE** |
| OSL purchase line items | `workshop_purchase_batches` + `workshop_purchases` | **DO NOT CREATE PARALLEL TABLE** |
| ROT event audit | `rot_session_events` + `rot_mechanic_segments` | **DO NOT CREATE PARALLEL TABLE** |
| Staff action alerts | `staff_reminder_state` + reminder settings/engine | **DO NOT CREATE PARALLEL ALERT SYSTEM** |
| Staff push devices | `staff_devices` + notification/Firebase service | **DO NOT CREATE PARALLEL DEVICE REGISTRY** |

The old SQL files remain reference artifacts only.

Before any new migration:
1. review `deployment/LIVE_STAGING_MAP_20261009.md`;
2. inspect targeted live source;
3. extend the existing live schema only when a concrete missing column/table is proven;
4. create a new Laravel migration matching the real app conventions;
5. backup staging first.

No reference SQL migration should be executed merely because it exists under `patches/`.

## 5. Application wiring order

### Group A — Admin/native navigation

Wire Customer, Vendor, Part, Labour, Vehicle, Labour/Part Mapping, Booking & Orders, Users, Products, Staff, Job Applications, Staff Location and Service Reminder.

Verify ERP Admin login, permissions, no Legacy login loop, and safe legacy redirects.

**Checkpoint A:** if auth/navigation is unstable, stop and revert this group.

### Group B — Payment Voucher

1. reproduce `/erp/payments/create` 500;
2. capture exact staging exception;
3. implement the targeted fix;
4. run `php artisan optimize:clear`;
5. verify GET returns 200;
6. enable POST only after accounting schema/posting is mapped.

Do not expose public `APP_DEBUG=true`.

### Group C — Job Card lifecycle

Wire Create, Edit, Parts above Labour, direct Parts/Labour, ROT assignment, QC, Ready, Close, Invoice conversion, View and Print.

Verify:

```
CREATE
→ WIP
→ QC PENDING
→ QC PASSED
→ READY FOR DELIVERY
→ CLOSED
→ CONVERT TO INVOICE
→ INVOICED
```

A duplicate conversion must return the same Invoice, not create a second one.

### Group D — Purchase / Estimate

Wire Parts Purchase autocomplete, JC/Vehicle allocation, OSL multi-line entry and same-page readability.

Verify Part Code/Name search, vehicle-format normalization, 10+ independent rows and server-authoritative totals.

### Group E — ROT

Wire event audit, pause reason/note and Performance.

Test Start → Pause with note → Resume → Complete and inspect audit timestamps/durations.

### Group F — Staff Alerts / Compliance

Verify:

```
ACTIVE
→ SNOOZED 1
→ SNOOZED 2
→ SNOOZED 3
→ ESCALATED
→ UNDER_REVIEW
→ RESOLVED
```

Check-Out/Login must not erase unresolved server alerts.

## 6. Composer/dependency gate

Before Excel export:

```bash
composer show phpoffice/phpspreadsheet
```

Before FCM HTTP v1:

```bash
composer show google/auth
```

Use the application's normal Composer process; do not manually copy vendor folders.

## 7. Firebase / FCM server enablement

Enable only after normal alert polling works.

Requirements:
- `FIREBASE_PROJECT_ID`;
- secure readable service-account credentials;
- outbound HTTPS to FCM;
- `google/auth`;
- device-token endpoint.

Test token registration, FCM SENT audit, push receipt, immediate reconciliation, then deliberately test polling fallback.

## 8. Staff app update feed

Publish update metadata only after the signed APK exists, is uploaded to the trusted Assam Motors HTTPS endpoint and its SHA-256 is re-verified.

## 9. Android production release gate

Before signing v6.0.18:
- alert backend live;
- device-token endpoint live;
- FCM sender live;
- Android Firebase values configured;
- push test successful;
- polling fallback successful.

Use the **existing** production signing key/workflow. Do not run one-time key generation again.

Expected bundle:
- signed APK;
- release notes;
- licence;
- `SHA256SUMS.txt`;
- `latest.json`.

## 10. Physical device upgrade test

Start with one internal Staff device. Verify in-place upgrade, login/session, Attendance, ROT actions, pause note, alerts, FCM, polling fallback, notification routing, update check, reboot/package-replaced reminder scheduling and duty location policy.

## 11. Post-deploy observation

Watch Laravel/PHP errors, DB deadlocks/duplicate keys, invoice conversion failures, Part/JC search failures, ROT action errors, stale alerts, FCM UNREGISTERED responses and update-feed errors.

FCM delivery failure must not roll back committed business actions; polling remains fallback.

## 12. Rollback

Use `deployment/ROLLBACK_MATRIX.md`.

General rule: **rollback code first; preserve additive audit/data tables unless their presence itself causes a verified problem.**
