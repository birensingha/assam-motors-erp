# AM240 hotfix — string ROT session ID 500 error

## Confirmed staging exception
2026-11-10 13:26:37 staging.ERROR: RotWorkComplaintController::create(): Argument #3 ($rot) must be of type int, string given. Laravel routes provide the session identifier as a URL string. Original completed ROT may use an alphanumeric key.

## Source changes
- Change three signatures only in RotWorkComplaintController: ownedRot(int $job,string $rot), create(Request $request,int $job,string $rot), store(Request $request,int $job,string $rot).
- Add a forward-only migration to align `am_rot_work_complaints.rot_session_id` with string-typed `rot_sessions.id` if needed. Conversion is rejected when complaint table has existing rows to avoid silent coercion.
- Fix the original AM240 initial migration to use a VARCHAR(191) session ID for fresh installs. Previously applied AM240 migrations are not rerun.
- No new or changed actions on original ROT, no Job Card status changes or invoice mutations.

## Safe deployment
Fetch branch fix/am240-rot-string-id-500-20261110. Extract deployment/live-fixes/AM240_ROT_STRING_ID_FIX via git archive into a temporary folder; run `python3 staging_hotfix.py check $HOME/domains/assammotors.com/assam-erp-staging` before `apply`.

Output of check: AM240_ROT_ID_SOURCE_TYPE, AM240_COMPLAINT_ID_STORAGE_TYPE, AM240_EXISTING_COMPLAINT_ROW_COUNT, AM240_STRING_ROT_PREFLIGHT_PASS.
Output of apply: AM240_STRING_ROT_SCHEMA_COMPAT_PASS, AM240_ROT_STRING_ID_FIX_INSTALLED.

## Verification required
- Open JC-2026-0011, click Report Work Complaint on Completed ROT: must render form without 500.
- Confirm JC number, original ROT session, and mechanic are the expected record.
- Only after verifying the form, write the REAL complaint description and submit. Ensure complaint and audit row exist with exact original session ID.
- Monthly report: Reported=1, Confirmed=0 for an unresolved complaint; only supervisor review can mark as confirmed.
- Original completed ROT status, elapsed time, billing and invoice remain unchanged.
- HTTP 500 source-level error is fixed, but end-to-end browser and database POST testing is still pending until the installer is run on the user's staging environment.

## Recovery
`python3 staging_hotfix.py rollback STAGING_ROOT` restores original controller from guarded backup. It does NOT drop databases, audit records, or revert a safe forward-only widening of complaint ID storage. Do not rerun old AM240 installer.

## AM241 follow-up
AM241 service and related migration still expect integer session IDs and have NOT been deployed. They must be reconciled with the actual ROT PK schema before dual-mechanic notifications are installed.
