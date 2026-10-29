# AM129 — Next-day Check-In blocked by unresolved previous Check-Out

User-confirmed policy, server-side only, Staging first:

- If any earlier attendance date for a staff member has a `check_in` but `check_out = NULL`, **Staff App and staff web portal Check-In fail with HTTP 422**, before GPS validation. The message includes the oldest unresolved attendance date and: `CHECK-IN BLOCKED … Contact Admin … Staff cannot back-date Check-Out`.
- Previous-day Check-Out may **only** be corrected via an existing authorised Admin or HR correction workflow. Staff Check-Out continues to target today's attendance date only. No self-backdating and no automatic 23:59 fake hours. Staff login/GPS tracking remains unchanged, while ROT Start/Resume still needs valid Check-In.
- All older unresolved attendance rows count, not just yesterday. A 2026-10-29 read-only audit found 17 open historical records belonging to 4 staff in Staging. They will be immediately blocked until management resolves ALL unresolved dates.
- Admin Web Attendance update route exists (`erp.attendance.update`), HR API correction route exists (`api/v1/hr/operations/attendance`) with mandatory audited reason. This patch does not change privileges.
- Fix lives in shared `app/Services/AttendanceService.php` so existing Android Staff App versions receive the same server response. Rendering a **dedicated polished Contact Admin modal** may require a later Android UI update; a backend 422 message cannot force an installed app to render a new screen.

Safeguards: only one source file patched with strict anchors; backup-first GitHub Actions STAGING-only SSH workflow; php -l, negative Check-In test with nested DB transaction always rolled back, positive read-only checks that historical Check-Out remains missing and staff cannot submit previous date, stage login smoke, automatic source rollback on failure. No staging attendance mutation, no migration, no production ERP changes. Existing historical attendance notes and financial records remain as-is.

API error format: Laravel's standard ValidationException 422 `errors.attendance[]`, includes date, reason and Contact Admin. The native app must show the API error body; if existing versions mask 422 as generic text, an Android app UI update will be needed.
