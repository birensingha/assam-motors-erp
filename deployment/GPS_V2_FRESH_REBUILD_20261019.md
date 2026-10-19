# Assam Motors GPS V2: Fresh subsystem rebuild (proposal / not deployed)

**This is a design and audit gate, not a live code deploy.** No staff, GPS, attendance or billing records are changed by this branch.

## Current evidence (19 Oct 2026)
- AM7887 Firajul: v6.3.18, source `ANDROID_LOGIN_SESSION_60S`, consistent uploads at 18:21:46, 18:22:46, 18:23:46.
- AM8435 Ratna: v6.3.18, `ADMIN_HR_OPT_IN_GPS`, intermittent.
- AM5077 Biren: v6.3.19, `ADMIN_HR_OPT_IN_GPS`, last received 18:12:48 as of 18:19:10.
- AM9472 Kapil: v6.3.19, `ANDROID_LOGIN_SESSION_60S`, last received 18:06:54 as of 18:19:10.

**Do not accuse staff of abuse based on GPS gaps.** These are availability symptoms, not identity or misconduct evidence.

## Implement in phases
1. Run a **read-only** audit of all staff codes/duplicate IDs, login-to-staff links, inactive accounts with valid sessions, orphan GPS staff IDs, last GPS version/source, and device-sharing signals. Never output bearer tokens, full emails, device IDs or exact coordinates. Shared phones can be legitimate test devices.
2. Create a **versioned GPS V2 API**. Authenticate the bearer token on every call, resolve the immutable staff identity on the server, prohibit clients from choosing `staff_id` or elevated roles, and enforce user consent. Keep Check-In/ROT geofence authorization separate from GPS collection.
3. Use **one native Android foreground location service** for each signed-in, consented Staff/HR/Admin profile; keep the location notification visible. Target fresh uploads every 60s without pretending Android can guarantee the interval in Doze/force-stop situations.
4. Keep both **actual GPS fix timestamp** and **server-received timestamp**. Mark STALE/DELAYED honestly and never reset the age because the dashboard refreshes.
5. On 401/403 or timeouts, log a safe reason and distinguish expired authentication from a denied role/route or temporarily unavailable server. Offline outbox must be partitioned by authenticated staff+session.
6. Pilot Biren and Kapil while leaving Firajul's v6.3.18 working. Test login/logout, Home/minimize, screen lock, network recovery, 5m/30m/2h/8h. Sign release APK with existing release certificate and publish in-app only after QA.

## Historical GPS reset: explicit data-scope decision required
Option A: Retire/hide old GPS history in the new interface; preserve existing `staff_location_logs` for audit/retention.
Option B: Permanently purge GPS-only history after a protected backup and confirmation that payroll/attendance/security/legal records do not require it. Never truncate staff, ERP auth, attendance, ROT, job cards or invoices. Old clients must not immediately repopulate the retired GPS table.

No delete, staff-token revocation, forced logout or global update manifest change should execute before an approved cutover.
