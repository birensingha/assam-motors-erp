# Phase 8 — Staff Alerts / Reminder Compliance

This patch defines the server-authoritative alert system for the Assam Motors Staff App.

## Goals

- One stable server alert identity per **Staff + Alert Type + Business Target**.
- Alert state survives Android logout, Check-Out, app reinstall and later Check-In.
- Maximum 3 postponements/reminders.
- After the third postponement, the alert enters **Admin Escalation**.
- Staff cannot permanently dismiss an unresolved business alert.
- Alert is resolved only when the required ERP business action is satisfied.
- Full event/audit history is retained.
- Admin can mark an escalated alert Under Review and add notes without resolving it.

## Important behaviour

Check-Out / Login / App close must **never delete active alerts**.

Examples:
- Assigned ROT not started → stays active until that ROT is started/completed/cancelled by authorised business logic.
- Paused ROT → stays active until Resume/Complete/authorised closure.
- Missed Check-Out → stays active until attendance is corrected/completed.
- Inspection pending → stays active until the inspection is completed.

See:
- `001_staff_action_alerts.sql`
- `staff_alert_identity.php.example`
- `staff_reminders_api.php.example`
- `ALERT_LIFECYCLE.md`

The live staging reminder PHP source is not present in this repository, so this is an isolated deployment contract rather than a claim that staging is already updated.
