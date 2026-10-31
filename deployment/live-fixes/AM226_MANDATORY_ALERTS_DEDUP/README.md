# AM226 — Mandatory Notifications Lifecycle Correction (STAGING)

## Problem reported
Web Staff Portal displayed 29 pending alerts, with historical `LOGIN_MISSED`, `CHECKIN_MISSED`, `CHECKOUT_PENDING`, `NOTIFICATION_DISABLED` records. AM225 grouped them and repaired action links, but did not fix generator/resolve state.

## Read-only root cause (31 October 2026)
- Database `staff_reminder_state` already has **unique key** `staff_id,reminder_type,target_key`; aggregate audit found **zero duplicate identities**.
- Historical reminders are distinct daily business target keys. Do not mass-delete or falsely resolve.
- `StaffReminderEngine::syncStaff()` generated `NOTIFICATION_DISABLED` using a **daily** key, so one ongoing permission issue was recorded as a new active condition each day.
- Legacy `staff_login_events` **exists**; login evidence can be checked against the same day. Missed historical logins must not be assumed completed.
- Today's Check-Out resolves target today, but old Admin-corrected Check-Outs needed reconciliation against their original attendance dates.

Aggregate audit counts across all staff BEFORE AM226 (not an individual's 29):
| Type | Total | Unresolved |
|---|---:|---:|
| CHECKIN_MISSED | 97 | 45 |
| CHECKOUT_PENDING | 59 | 27 |
| LOGIN_MISSED | 99 | 68 |
| NOTIFICATION_DISABLED | 32 | 32 |
| ROT_NOT_STARTED | 32 | 2 |
| ROT_PAUSED_TOO_LONG | 17 | 0 |

## Scoped fix
1. New `NOTIFICATION_DISABLED` reminder uses stable target key `permission` while compliance remains false; no new date-specific alerts.
2. When actual notification compliance becomes true, resolve outstanding `NOTIFICATION_DISABLED` reminders, including older dated states; update linked notification outbox `action_resolved_at`.
3. Existing `LOGIN_MISSED`, `CHECKIN_MISSED` and `CHECKOUT_PENDING` are resolved **only** if the same-date login event / staff attendance check-in or checkout exists. This includes historical attendance corrected by Admin, but unresolved genuinely missed actions stay unresolved.
4. Internal `resolve()` and ROT `resolveTypeExcept()` use `MandatoryNotificationService::resolveByTarget()` to keep outbox resolution consistent with state changes.
5. Dashboard shows one actionable permission issue instead of counting all historical dated instances as separate active permission tasks, while the staff portal still retains full `reminders` history with the original individual records and groups.
6. No master/finance/ROT timer/attendance data modification by installer. No standalone Android rollout or changes to notification-permission blocking, next-day Check-In guard, or Check-Out/Admin recovery.

## Deployment and QA
- Workflow: `.github/workflows/deploy-am226-mandatory-alerts.yml`
- Guarded installer: `install.py check|apply|rollback STAGING_ROOT`
- Source file backups, full private STAGING MariaDB export+checksum first, Laravel Blade compilation and PHP syntax lint before source apply.
- Read-only post verify: `php post_verify.php STAGING_ROOT`, prints only aggregate counts / no staff identifiers or actual records.
- The scheduled `assam:staff-reminders` job will reconcile satisfied historical conditions; **do not manually force background notifications** for QA.
- Authenticated staff browser testing is a separate acceptance step.

## Browser acceptance
- Staff portal: summary `active actions` and `records` separately; identical permission issue counted once.
- Open Work targets from AM225 (Attendance, ROT, Permission) navigate correctly.
- Following actual enablement of notifications, Staff Portal should not retain `NOTIFICATION_DISABLED` as active after scheduled sync.
- Following verified Admin correction of a missed historical Check-Out, scheduler resolves the matching `CHECKOUT_PENDING`, while any older unrelated missed days remain open.
- Previous-day missing Check-Out still blocks next-day Check-In until Admin corrects it (AM129/AM130); no bypass created.
- No unresolved alert is resolved merely for age, logout, or clearing notifications.

## Remaining
Staff Portal browser login/action click verification; Android permission/close behavior and APK integration last per user priority. AM227 attendance guard end-to-end cross-surface QA next.
