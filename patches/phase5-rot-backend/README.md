# Phase 5 — ROT Backend Audit & Asia/Kolkata Time

This patch prepares the ERP backend contract for the Staff Android ROT workflow.

## What it adds

- authoritative ROT event/audit history for **START / PAUSE / RESUME / COMPLETE**;
- both Asia/Kolkata local timestamp and UTC timestamp for every event;
- Android pause reason + note persistence;
- request-key based idempotency so a network retry does not create duplicate audit rows;
- indexes for staff, ROT session, job card and date-wise performance/history queries.

## Android payload

The Staff app sends the existing fields unchanged:

```json
{"id":"<rot-session-id>","action":"pause"}
```

For Pause it now also sends optional metadata:

```json
{
  "id":"<rot-session-id>",
  "action":"pause",
  "pause_reason":"Parts Waiting",
  "pause_note":"Waiting for oil filter",
  "device_recorded_at_ms":1791470000000,
  "action_source":"ANDROID_STAFF_APP"
}
```

Existing servers may ignore the extra fields, so the request remains backward compatible.

## Apply order

1. Run `001_create_rot_event_audit.sql`.
2. Copy/adapt `rot_audit_helper.php.example` into the current PHP API layer.
3. In `staff-rot-v3.php`, call the audit helper **only after the requested ROT state change succeeds**.
4. Pass the authenticated Staff ID, current Job Card ID/No and current ROT session status into the audit helper.
5. For Pause, pass `pause_reason` and `pause_note` from the decoded JSON request.
6. Verify Start → Pause → Resume → Complete produces four ordered audit rows.
7. Verify retrying the same Android Pause request does not duplicate the row when the same device timestamp is supplied.

## Important

The GitHub ERP repository currently stores patch packages/backlog rather than the live staging PHP source. Therefore this patch does not pretend that the staging endpoint itself has already been modified. It is deliberately isolated so it can be applied to the live server source once that source is made available in the repository/deployment workspace.
