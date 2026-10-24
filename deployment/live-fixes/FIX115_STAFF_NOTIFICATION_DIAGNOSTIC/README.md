# FIX115: Staff Notification Compliance — Read-only Staging Audit

## Scope

Read-only diagnosis of missing FCM tokens and missing `Last Sent` values on
`/erp/staff-notifications`. This must **not** change attendance, ROT actions,
GPS tracking, staff sessions, Firebase credentials or database rows.

## Run

Run on the **Hostinger staging terminal** using a local copy of the script:

```bash
bash deployment/live-fixes/FIX115_STAFF_NOTIFICATION_DIAGNOSTIC/inspect.sh
```

The script uses the normal staging Laravel bootstrap and only executes SELECT
and schema-introspection queries. It does not print FCM tokens or credentials.
If run outside the Git checkout, supply the staging root as the first argument.

## What to verify after the report

1. Confirm `staff_devices` vs `staff_push_devices` ownership: the NEW ERP
   DeviceController should register tokens used by the FCM outbox sender.
2. Investigate staff with missing tokens without treating `FCM Health: OK` as
   delivery proof. For Admin/Owner, check whether `staff/device` supports their role.
3. Determine whether `notification_outbox` gets `sent_at` and provider IDs,
   and whether scheduler/queue workers are actually dispatching entries.
4. Cross-check Android `native_device_last_registration_http` after deploying
   the coordinated Staff App change. Non-2xx values need server diagnosis.
5. Run an actual-device test: create a pending Staff action, send FCM, verify
   receipt, confirm polling fallback, then perform the action and confirm
   server-side resolution.
6. Check reminder identity/deduplication and three-postponement escalation against
   the **live** server engine before changing timings or deleting existing alerts.

## Caveat

The ERP Git repository contains patch/reference packages, not the complete
live Laravel source. This diagnostic does not assert that an alert has reached
a phone. An FCM server acceptance is not equivalent to display or action.
