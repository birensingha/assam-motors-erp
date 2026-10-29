# AM130 Admin Missed Check-Out Register — STAGING ERP

## User-approved workflow
After AM129 blocks next-day Staff App Check-In when an earlier date's Check-Out is missing, the Owner/Admin needs an easy, audited recovery path in **NEW ERP → Attendance**, not a separate extra system.

**Register:** independent of selected staff/month filters, show up to 100 earliest unresolved attendance rows, with staff name/code, date, Check-In, missing status, actual Check-Out time and mandatory **reason / evidence**. Refresh to handle any beyond 100.

**Correction:** use existing **Admin-only** `erp.attendance.update` endpoint. Resolve only a previous date with a real recorded Check-In and missing Check-Out. Require server-validated actual Check-Out HH:mm and a 12–1000 character reason, with actual time later than Check-In and elapsed max 18 hours (same attendance day). Overnight duty must go to authorised HR correction. Transaction locks attendance row, updates Checkout/worked hours, and inserts `hr_audit_logs` entry (`ATTENDANCE_MISSED_CHECKOUT_RESOLVED`, before/after/actor/reason); either both commit or neither.

**Bypass prevention:** Existing ordinary Admin Edit and Admin Mark Attendance upsert cannot silently fill a historical missing Checkout outside the audited form. Staff API and Staff Web Check-Out remain today-only (from AM129); employee cannot backdate. Existing audited HR operations correction continues to work.

## Boundaries and safeguards
- Never fake 11:59 PM departure, never auto-correct 17 historical missing records, and no database UPDATE during installation or QA.
- Reuse existing audit table; **no new database table or migration**.
- GitHub workflow uses existing **STAGING** SSH secrets only. Backup controller+view, guarded source anchors, syntax preflight, PHP lint, Blade cache, render a form with an existing pending row, verify required audit guards, login smoke. On any failure restore previous files, remove partial, clear/recompile view cache.
- Existing Admin Attendance chart/matrix filters and other routes are left intact. This phase does not update Android App APK, does not auto-pause ROT, and does not deploy to production.
- User needs no Hostinger command. Because only Admin may see past staff attendance, never expose names of affected staff to public.
- *QA boundaries*: Read-only rendering and source checks are not an actual authenticated browser click/save test; no historical attendance data should be edited just to test the feature.

## Staging target
https://staging.assammotors.com/erp/attendance

## Post-deploy acceptance
1. Open NEW ERP as Admin, Attendance. Missed Check-Out Register above daily/matrix report.
2. Rows shown independent of month filter, with staff/date and time.
3. Admin enters verified Check-Out + reason; audit recorded and next-day Check-In released when all older dates are resolved.
4. Running ROT handling and Android popup are separate follow-up work; staff cannot manually correct history.
