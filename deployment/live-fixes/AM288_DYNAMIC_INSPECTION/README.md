# AM288 - Dynamic Inspection (STAGING code, not deployed)

**NOT LIVE:** No routes, DB migrations, or staff APK are deployed by this PR.

## Source
- Api/V1/Am288DynamicInspectionController.php: Admin assigns STANDARD published Master or CUSTOM selected published keys; locks point snapshot and assigned staff per Job Card, enables exact, audited typed Staff GET/POST.
- Middleware/Am288BlockLegacyInspection.php: MUST be attached to existing legacy fixed-30 Staff POST route and hold Job Card row lock; 409 if a dynamic form is already assigned. Does not affect legacy GET.
- Services/InspectionQcGuard.php: candidate replacement for the authoritative AM189 guard, extending findings to typed answers and preserving repair/re-inspection history. Must three-way merge against exact live controller revision, NEVER blind overwrite.
- database/migrations/2026_11_03_174000_create_am288_dynamic_inspection_answers.php: immutable typed answers and append-only event audit; migration down() leaves history intact.

## Proposed routes - NOT INSTALLED

Only inside existing authenticated mobile /api/v1 middleware group:

    POST admin/mobile/job-cards/{job}/inspection-form/assign -> Am288DynamicInspectionController@assign
    GET  staff/inspections/{job}/dynamic -> Am288DynamicInspectionController@show
    POST staff/inspections/{job}/dynamic -> Am288DynamicInspectionController@submit

On the same deployment add Am288BlockLegacyInspection to POST staff/inspections/{job} (legacy), with correct route parameter and existing bearer auth. Lock the job_cards row through the old submission.

## Contract
Admin assign: {"staff_id":123,"mode":"CUSTOM","point_keys":["p01","p03"],"reason":"Select critical reception checks"}. STANDARD has all published points, CUSTOM selects a unique published subset (types and defect answers immutable). Locked snapshot and Staff assignment are separate from JC mechanic. Only open/inspection-pending JC, no previous results/defects.

GET response: {"ok":true,"contract":"AM288_DYNAMIC_V1","job":{"id":42,"status":"open","inspection_status":"pending"},"form":{"state":"ASSIGNED","form_hash":"64 hex","points":[...]}}. Only Admin or assigned Staff may read.

POST: {"form_hash":"64 hex","items":[{"point_key":"p01","value":"yes","remarks":""}]}.
All exact ordered keys, optional/required and condition/yes_no/availability/fuel/reading types checked, mandatory defect remarks. Job Card and form row locks, no duplicate writes, stale form check, append-only audit; AM189 defect OPEN rows created atomically. Android must reconcile by GET after network uncertainty; no POST auto retry.

## Safety & deployment blockers
- Existing AM189 OPEN -> RECTIFIED -> VERIFIED and independent safety re-inspection remain authoritative. Old and typed defects must both be checked by AM189 assertQcReady in the actual Staff/Admin QC PASS transaction. Native Owner UI permits only QC PENDING assignment.
- Verify real Hostinger routes/api.php and StaffExtraController, AM206 migrations, AM189 guard/controller versions. No duplicate defect register, no permission bypass.
- PHP lint all files and inspect php artisan route:list -v middleware. Test 401/403, wrong Staff, stale hash, duplicate/delayed POST, legacy/dynamic race on disposable STAGING Job Cards.
- Test Standard and CUSTOM 20-point forms, typing choices, 500-char note bounds, independent repair & reinspection, QC PASS blocked on unresolved/missing/edited answers.
- Downstream estimate/diagnosis/health reports may assume legacy job_card_inspection records. Dynamic typed answers use a new table; these modules must be integrated before production rollout.
- Fresh verified staging DB+code backups, explicit approval, rollback and on-phone QA required. Existing v6.3.20 with unsynced inspection draft must remain untouched.

This source package intentionally has NO automatic installer or production release.
