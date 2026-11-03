# AM287 — Android Native Inspection READ API (STAGING ONLY)

Status: **Source prepared on a feature branch, NOT deployed or activated.** This package does not write to Hostinger and does not change routes/api.php.

## Source-grounded dependencies

- AM189 has the authoritative InspectionQcGuard::findings() and audited defect register OPEN -> RECTIFIED -> VERIFIED (failed re-inspection returns OPEN). Its web controller owns repair and independent verification. Mobile UI must not mark VERIFIED itself.
- AM206 Stage 1 provides Am206InspectionMaster::active() and master types condition, yes_no, availability, fuel, reading. It explicitly does NOT change Staff's exactly-30 Good/Fair/Poor POST or enable typed per-Job-Card submission.

## Two strictly read-only methods

1. GET /api/v1/admin/mobile/inspection-master: Published AM206 master with dynamic_submit_enabled=false. Fails 503/409 if service, table or published version is unavailable.
2. GET /api/v1/admin/mobile/job-cards/{job}/inspection-defects: AM189 findings, unresolved_count, statuses and audit-related details for the exact Job Card. Always qc_pass_authorized=false because GET does not authorize transaction.

Both enforce active Laravel ErpAuthUser Owner/Admin; no POST writes, permission bypass, new migrations, second defect register, or customer financial mutations.

## Route integration — review snippet only, NOT performed

After staging preflight and confirmed AM189/AM206 installation, add the following inside the EXISTING authenticated /api/v1/admin/mobile route group, preserving its original middleware:

    Route::get('inspection-master',
      [\App\Http\Controllers\Api\V1\Am287InspectionReadController::class, 'master']);
    Route::get('job-cards/{job}/inspection-defects',
      [\App\Http\Controllers\Api\V1\Am287InspectionReadController::class, 'defects'])
      ->whereNumber('job');

The above is not an installed route. Verify actual Hostinger routes/api.php and route:list authentication before activation. No public anonymous route allowed.

## Acceptance before marking integrated

- php -l the controller; verify route:list -v and exact API auth middleware.
- Anonymous and ordinary Staff token must be 401/403, never defect data.
- Missing AM189/AM206 migration returns 503/409, not fake points.
- Authorized Admin GET must show correct Job Card, Fair/Poor, OPEN/RECTIFIED/VERIFIED, changed/duplicate/orphaned flags and unresolved count.
- Compare the AM189 Web register and read-only API for a disposable STAGING Job Card. Ensure GET causes no audit/database changes.
- The Android inspector keeps the legacy 30-point Good/Fair/Poor submission until AM206 Stage 2 introduces versioned per-JC snapshot and server-side validation.
- Even when unresolved_count=0, do not assume QC PASS is authorized: the correct Staff/Admin QC transaction must run InspectionQcGuard::assertQcReady() under lock.
- Verify actual Android Admin login 401/403, device drafts and server response before original-signing any APK.

## Important source-version discrepancy

FIX45_OWNER_WORKSHOP_CONTROL/payload/OwnerWorkshopController.php in this repository accepted PASS/FAIL in an earlier source revision. AM189 describes a later Hostinger source limiting mobile Owner QC to PENDING. Audit actual staging controller before permitting native PASS/FAIL. This package assumes PENDING-only and exposes no QC PASS mutation.

## Deployment

No installer, no auto-route modifications, no production APK. Review and explicit staging approval required before activation, with fresh backups and tested rollback.
