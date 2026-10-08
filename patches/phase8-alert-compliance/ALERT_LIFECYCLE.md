# Staff Alert Lifecycle

## Identity

The server creates a deterministic `identity_key` from:

```
staff_id | alert_type | target_type | target_key
```

The `target_key` must represent the actual business object/event.

Examples:

- `staff=12 | ROT_NOT_STARTED | ROT | session:8841`
- `staff=12 | ROT_PAUSED | ROT | session:8841`
- `staff=12 | CHECKOUT_PENDING | ATTENDANCE | 2026-10-08`
- `staff=12 | INSPECTION_PENDING | INSPECTION | job:JC-1042`

This prevents duplicate alerts from repeated polling/cron generation.

## States

```
ACTIVE
  ↓ staff postpones
SNOOZED (postpone_count 1)
  ↓
SNOOZED (postpone_count 2)
  ↓
SNOOZED (postpone_count 3)
  ↓
ESCALATED
  ↓ optional Admin handling
UNDER_REVIEW
  ↓ business action satisfied
RESOLVED
```

The app may display SNOOZED as pending with a future `next_reminder_at`.

## 3-reminder rule

- Maximum postponements: 3.
- Each accepted postponement is written server-side.
- After count reaches 3:
  - `status = ESCALATED`
  - `escalated_at_*` is populated
  - no further snooze is accepted
  - alert remains visible to Staff
  - alert appears in Admin escalation queue

## Persistence rule

Active/Snoozed/Escalated/Under Review rows are returned from Poll even after:
- Check-Out
- next Check-In
- Logout/Login
- app restart
- app reinstall
- another Android device login

## Resolution

Do not add a generic Staff "Dismiss" operation.

Resolve only after server-side verification of the business action.

Examples:
- CHECKOUT_PENDING → attendance row now has check_out.
- CHECKIN_PENDING → attendance row now has check_in.
- ROT_NOT_STARTED → ROT status is no longer Assigned/Waiting.
- ROT_PAUSED → ROT status is no longer Paused.
- INSPECTION_PENDING → inspection status is Completed/Approved.

Resolution records:
- resolved_at_local / UTC
- resolved_by
- resolution_note
- RESOLVED event row

## Poll response contract

Every active alert should include:

```json
{
  "id": 91,
  "identity_key": "...",
  "type": "ROT_PAUSED",
  "target_type": "ROT",
  "target_key": "session:8841",
  "title": "Paused ROT pending",
  "message": "Resume or complete the ROT.",
  "status": "SNOOZED",
  "postpone_count": 2,
  "max_postponements": 3,
  "next_reminder_at": "2026-10-08 22:20:00",
  "admin_escalated": 0,
  "escalated_at": null,
  "resolved_at": null
}
```

Android local reminder state is fallback only.
