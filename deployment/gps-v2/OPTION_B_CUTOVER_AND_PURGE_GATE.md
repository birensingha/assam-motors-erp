# GPS V2 Option B — confirmed by owner on 19 Oct 2026

**Chosen:** Permanently remove old GPS location history for every staff ID, then commission a genuinely new GPS V2 subsystem.

## Non-negotiable sequence
1. Run read-only `deployment/gps-v2/audit_staff_gps_readonly.php` and `deployment/gps-v2/gps_option_b_preflight.php`; resolve identity duplicates, shared-account signals, missing linked staff and foreign keys.
2. Verify applicable records-retention, attendance disputes and legal audit obligations. Treat apparent GPS stops as technical symptoms, not misconduct evidence.
3. Build, test and stage GPS V2 alongside old service. Maintain exactly one location publisher per consented account. Keep geofence work-authorization independent.
4. Take a **private, access-restricted, restorable, verified backup** of the old GPS-only history, plus necessary metadata, **outside public_html**. Record row counts, checksum, backup location, retention/purge date. Protect any encrypted backup keys separately.
5. During a controlled cutover, prevent old app versions and old API writers from refilling the retired history table. Only the new GPS V2 endpoint accepts post-cutover samples; don't disable staff access or attendance.
6. After confirming zero new writers, verifying backup and FK dependencies, purge **GPS-only location sample history** in a transaction/batched deletes. The exact table list comes from schema preflight; do NOT delete the `staff`, ERP authentication, `staff_location_settings` workshop/geofence settings, attendance, ROT, payroll, JC or invoice tables.
7. Verify zero old-history rows, new V2 started from fresh epoch, and all active staff mapped correctly. Where retention permits, subsequently erase the temporary old-GPS backup according to the agreed retention/destruction date; until then it remains securely restricted.
8. Roll out the signed Android version only after actual-phone tests (Biren and Kapil pilot; avoid breaking Firajul) and old signing-certificate match. Never assume Android can guarantee 60-second updates when power management stops foreground work.

## Status
Owner selected **Option B**. Authorization does **not** bypass the explicitly requested backup/retention prerequisites. Nothing has been deleted or reset. The preflight is SELECT/metadata-only.

## Unexpected or suspicious accounts
Flag duplicate staff codes, unlinked accounts, impossible linked IDs, inactive staff with active ERP logins, repeated shared-device logins, and inconsistent GPS source tokens. Report these as review flags; do not assert intent without supporting evidence.
