# Phase 10 — FCM Push Primary + Polling Fallback

This phase adds the server side of Staff push notifications.

## Behaviour

- FCM data push is the primary instant delivery path.
- Existing Android reminder polling remains enabled as fallback/reconciliation.
- Staff device tokens are registered only after authenticated Staff login.
- One Staff account may have multiple active devices.
- A rotated FCM token replaces the previous token for the same installation.
- Invalid/unregistered FCM tokens are disabled after send failures.
- No Firebase service-account private key is stored in Git.
- Server credentials must be supplied through `GOOGLE_APPLICATION_CREDENTIALS` or the deployment secret manager.

## Android payload

Send **data-only** messages so the Assam Motors app controls whether a notification is shown:

```json
{
  "alert_id": "91",
  "push_id": "91:ESCALATED:3:2026-10-08T16:45:00Z",
  "title": "ROT Paused",
  "message": "Resume or complete the ROT.",
  "status": "ESCALATED",
  "postpone_count": "3",
  "open_view": "alerts"
}
```

Do not add an FCM `notification` block for Staff compliance pushes.

## Send timing

Call push only **after** the authoritative database transaction commits.

Typical send points:
- new actionable alert created;
- reminder becomes due;
- third postponement / Admin escalation;
- meaningful server-side alert update requiring Staff attention.

Resolution normally does not need a user-visible push; polling/app refresh will remove the resolved alert.

## Fallback

Android keeps:
- 15-minute periodic reminder poll;
- immediate poll on app start/resume;
- immediate poll after every FCM push;
- scheduled snooze wakeups.

See the device-table migration, authenticated token endpoint, and HTTP v1 sender example.
