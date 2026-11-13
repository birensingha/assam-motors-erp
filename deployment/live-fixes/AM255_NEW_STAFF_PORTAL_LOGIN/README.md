# AM255 — New Staff Portal / Android login and Check-In repair

## Verified STAGING incident — 13 November 2026
A newly created active Staff Master AM5848 (Staff ID 9) had no account in erp_auth_users, which Staff Portal and native Android Staff App both require to log in. Staff Master holds a unique email and a supported bcrypt password hash, without any matching/linked ERP login account.

## Fix
1. After a fresh complete private STAGING SQL backup, repair_am5848.php creates ONLY the missing staff-role ERP authentication row linked to Staff ID 9. It copies the original hashed password without exposing, changing or resetting it. No HR/Admin role or attendance record is created or changed.
2. StaffLoginBootstrapService is an additional guarded fallback in Staff Portal and App login controllers. It activates only when ERP account is genuinely missing, the Staff Master is active and uniquely identified by email, and the password submitted by staff validates against the existing Staff Master bcrypt/Argon hash. Existing ERP accounts, inactive users, conflicting email/staff links and Admin identities are never overwritten or resurrected.
3. A small protected AttendanceService change makes an already-open today Check-In idempotent: a retry returns the existing row unchanged, preventing duplicate Check-In or a misleading error. It still rejects a second check-in after Check-Out, missing prior-day Check-Out, stale/fake/out-of-range GPS and failing mandatory notifications. No attendance data is modified during installation.

## Server-side verification
Auto-deploy GitHub Actions: https://github.com/birensingha/assam-motors-erp/actions/runs/43421665510
Passed: full STAGING DB backup export, AM5848 account linked user ID 10, Staff Portal/API source PHP lint and auth route verification, AttendanceService syntax and repeat Check-In guard. The password remains as originally entered when creating Staff Master. No passwords, login tokens or GPS coordinates are printed.

## Read-only attendance findings
As of 13 November 2026 AM5848 already had one Present / Checked In attendance for today. No prior-day missing Check-Out for the active staff inspected. The attendance table has an existing unique (staff_id,date) composite index. Recent Laravel log sample showed no lock-wait timeout or deadlock; intermittent 504 errors remain unconfirmed and should not be claimed fixed.

## Actual acceptance pending
Log into https://staging.assammotors.com/staff/login with registered staff email and original password. Use fresh, precise and permitted workshop GPS in the native Staff App. A Staff Portal login does not automatically authenticate the native App; both accounts share verified ERP credentials but use separate sessions/tokens. Actual user-session login and device Check-In have not been exercised by deployment. Do not send passwords to ChatGPT.
