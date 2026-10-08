# FCM + Polling Integration Rules

## 1. Push is acceleration, not source of truth

The alert database remains authoritative.

FCM only tells the Android app:
"Something changed; show this user-visible alert and refresh now."

The Android service immediately schedules the existing reminder poll.

## 2. Data-only messages

Use a data-only FCM message for Staff compliance alerts.

Reason:
- app can reject pushes after explicit logout;
- app controls notification ID/channel;
- app can deduplicate push vs polling;
- app opens the exact Alerts view;
- server state is still refreshed immediately.

## 3. Priority

Use Android HIGH priority only for actionable, user-visible Staff alerts.

Do not use high priority for silent background analytics/sync.

## 4. Token lifecycle

- Android receives/refreshes the FCM token.
- Token is stored locally.
- After authenticated Staff login, app POSTs it to `staff-device-token.php`.
- Token registration is bound to server-authenticated Staff ID.
- Same installation + rotated token disables the older token.
- UNREGISTERED response disables stale token server-side.

## 5. Security

Never commit:
- Firebase service-account JSON
- private keys
- OAuth access tokens
- production FCM registration-token dumps

Use:
- `GOOGLE_APPLICATION_CREDENTIALS`
- deployment secret store
- `FIREBASE_PROJECT_ID`

FCM registration tokens should be treated as secrets and should not be written to application logs.

## 6. Delivery failure

An FCM failure must not fail:
- Check-In/Check-Out
- ROT action
- alert creation
- escalation
- Admin review

The business transaction commits first.
Push send happens afterward.
Polling recovers delivery.

## 7. Duplicate control

Android records:
- last push ID per alert
- last push timestamp per alert

Normal polling suppresses a notification for the same alert for two minutes after FCM display.
Forced snooze wakeups are not suppressed.

## 8. Deployment order

1. Apply device/delivery tables.
2. Deploy authenticated token-registration endpoint.
3. Configure Firebase project + Android app package `com.assammotors.staff`.
4. Configure server credentials securely.
5. Deploy FCM sender.
6. Build Android v6.0.18 with Firebase config values.
7. Login Staff app once to register token.
8. Send a test alert.
9. Verify push arrives instantly.
10. Disable network/push temporarily and verify polling fallback still surfaces the alert.
