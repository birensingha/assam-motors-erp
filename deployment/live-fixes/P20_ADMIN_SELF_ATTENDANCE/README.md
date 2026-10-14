# P20 — Admin My Attendance (STAGING ERP)

## Root cause (read-only audit)

- The referenced active Admin auth account is correctly linked to an active Staff record with `attendance_enabled=1`.
- Existing Admin `/erp/attendance` displayed the staff matrix and Admin attendance corrections, but had no *self Check-In/Check-Out* actions.
- The Staff Android attendance API intentionally rejects Admin logins; do not change ordinary staff geofence enforcement.
- On 2026-10-14 (IST), linked staff had one Present record, a Check-In, and no Check-Out. Hence showing another Check-In would risk a duplicate.

## Fix

Top of **STAGING ERP → Attendance** now contains an Owner/Admin **My Attendance** panel. It uses the authenticated `ErpAuthUser->staff_id` only, never a submitted staff ID, and shows status, today's timestamps and worked hours. The offered action depends on the exact today's database row: Check-In (none), Check-Out (checked in), Complete (checked out), or Review Required (duplicate/invalid).

New Owner/Admin-only POST endpoints are in the existing admin-authenticated ERP route group:
- `erp.attendance.self.checkin`
- `erp.attendance.self.checkout`

The dedicated controller uses Asia/Kolkata server time, locks the linked staff and attendance rows, refuses overwrites and duplicates, blocks Check-Out while ROT is Running, and appends transactions to `hr_audit_logs`. It records no fake GPS; Owner/Admin web self attendance is a distinct remote-geofence-exempt workflow. Regular Staff App attendance policy, geofence verification, login tokens and ROT services remain unchanged.

## Staging deploy and verification

- Preflight: Actions run `38534745210` passed.
- Deployment: Actions run `38534934913` passed.
- `P20_REAL_ADMIN_PAGE_RENDER_PASS`
- `P20_TODAY_CHECKOUT_BUTTON_VISIBLE_PASS`
- `P20_SELF_POST_ROUTES_PROTECTED_PASS`
- `P20_NO_CHECKIN_CHECKOUT_OR_HR_RECORDS_MUTATED`
- `P20_STAGING_ADMIN_SELF_ATTENDANCE_DEPLOY_PASS`
- Original source backup under `storage/app/p20_admin_self_attendance_backup_20261014_135520_1744647`

**No Check-In or Check-Out was automatically performed** during deployment. UI alone has been tested in an authenticated GET; a real user-initiated POST is required for end-to-end business acceptance.

## Scope boundary

This fix is for the **STAGING Web ERP Admin login**, not an updated Android APK. The Admin Android app mode also needs a separately approved interface/API workflow if Check-In/Out must be shown there.
