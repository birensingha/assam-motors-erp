# Admin Alert Compliance Workflow

## Queue

Recommended native route:

`/erp/native/staff-alerts`

Default ordering:
1. ESCALATED
2. UNDER_REVIEW
3. ACTIVE
4. SNOOZED
5. oldest first within the same priority

## Filters

- From Date
- To Date
- Staff
- Status
- Alert Type
- Job Card
- Vehicle
- Free-text search

## Detail panel

Show:
- Alert ID / identity
- Staff name / code
- Alert type
- Target
- Job Card / Vehicle / ROT
- First triggered
- Current status
- Reminder/Postpone count
- Escalated at
- Current Admin reviewer
- Admin notes
- Resolution
- Full event timeline

## Mark Under Review

Allowed from:
- ESCALATED
- ACTIVE
- SNOOZED

Required:
- authenticated Admin
- Admin note

Effect:
- status = UNDER_REVIEW
- admin_reviewed_by = Admin user
- reviewed timestamp saved
- note saved
- ADMIN_UNDER_REVIEW event written

It does **not** set resolved_at.

## Add Admin Note

Allowed for any unresolved alert.

Effect:
- update latest admin_note for quick display
- append ADMIN_NOTE event in event history

Do not overwrite history when a new note is added.

## Resolution

Normal resolution is business-driven.

Before resolving, server verifies the alert target:

- CHECKIN_PENDING → attendance has check_in
- CHECKOUT_PENDING → attendance has check_out
- ROT_NOT_STARTED → ROT is no longer Assigned/Waiting
- ROT_PAUSED → ROT is no longer Paused
- INSPECTION_PENDING → inspection is complete/approved

If verification passes:
- status = RESOLVED
- resolved timestamp
- resolved_by
- resolution note
- RESOLVED event

### Admin override

If the business requires an exceptional manual close, use a separate permission such as:
`staff_alerts.force_resolve`

Require:
- explicit privileged Admin permission
- mandatory reason
- ADMIN_FORCE_RESOLVE event
- actor ID
- old/new status
- full audit

Never treat a normal "Under Review" action as resolution.

## Staff visibility

The Staff app continues to receive:
- ACTIVE
- SNOOZED
- ESCALATED
- UNDER_REVIEW

Only RESOLVED disappears from active Staff alerts.
