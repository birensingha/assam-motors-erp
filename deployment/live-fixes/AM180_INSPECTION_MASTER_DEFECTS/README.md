# AM180 — Inspection Master + audited defect state machine (STAGING)

## Current status
**Code staged on GitHub branch only. NOT deployed to the running ERP.**
Built as a self-contained addition because the repository holds deployment patches, not the actual live Laravel source. All live integration has to be reviewed against STAGING source first.

## What this package actually implements
- Versioned Inspection Master with the original 30 listed checks as defaults; Admin can add/remove items and publish 1–100 checklist points. Old versions and per-Job-Card snapshots are preserved.
- Per-point types: condition Good/Fair/Poor; Yes/No; Available/Not Available; Fuel E/quarter/half/three-quarter/F; numeric Reading. This removes inappropriate Good/Fair/Poor selections on fuel and odometer.
- Per-point required/optional, defect-triggering answers (e.g. CHECK ENGINE LIGHT = YES means fault; horn functional = NO means fault), safety flag and 1–10 health weight.
- Validated server-side submission helper: rejects missing required points, unauthorized values, duplicate/unknown point IDs and defects without observation. This helper must be *called by the live submit endpoint*; it does not yet change that endpoint.
- Audited defect states **OPEN → RECTIFIED → VERIFIED**. Failed re-inspection returns to OPEN; safety defects cannot be verified by the same actor who rectified them.
- Reusable server-side QC PASS blocker `InspectionDefectService::assertQcReady($jobId)`, rejects unresolved defects including RECTIFIED (not yet verified).
- Protected Admin master UI, checklist JSON endpoint for assigned Staff Portal users and protected defect transitions.

## Important: what is NOT yet connected
- Actual Android 6.3.20 app still uses its fixed 30-point list and has a known unverified/non-working completion submission. App needs authenticated master API, UI and final submit integration.
- Existing Laravel `StaffExtraController::saveInspection`, owner mobile `manager-inspections/{job}/complete`, legacy Staff Portal submit, any legacy PHP endpoint all must call the SAME validation/snapshot/defect recording logic. Different endpoints are in use; **none are patched by AM180**.
- The server-side QC PASS button/backend must call `assertQcReady($jobId)` within its existing Job Card lock/transaction. **Not patched by AM180**; do not claim QC blocking is in force yet.
- Workflow should allow successful inspection submit when defects exist (Option A), but keep OPEN defects pending until repair and re-inspection.
- AM179 optional branded Vehicle Health PDF is a separate Draft PR #108. AM180 adds no report PDF.
- No customer vehicle valuation or price calculation changes.

## Install plan for maintainers
1. Back up STAGING files and database. Review exact live controllers, routes, status columns, and duplicate legacy endpoints.
2. Run `python3 install.py check /full/path/to/assam-erp-staging` (read-only); stop on changes to anchors or existing files.
3. Review the PHP sources, DB migration and auth/role mapping against actual STAGING user model; check Composer/PHP versions and `php -l`.
4. Only after review run `AM180_ALLOW_STAGING=YES python3 install.py apply /full/path/to/assam-erp-staging`.
5. Separately apply the reviewed migration with a database backup.
6. Review `php artisan route:list -v` to verify Admin and Staff routes inherit the correct middleware and the controller's role checks match actual session accounts.
7. TEST before exposing in navigation; never run on production until tests pass.

## Acceptance tests
- Admin publishes 30-point default as version 1. Admin publishes 20-point version 2; existing version-1 job snapshots remain 30; new jobs use version 2.
- Non-admin cannot publish; anonymous and unassigned staff cannot load another job's snapshot/defect list.
- Fuel choices E/¼/½/¾/F; odometer numeric reading; CEL YES raises defect but CEL NO does not; horn NO defect, horn YES normal.
- Required blank answer returns 422 with exact point name; defect answer without observation returns 422 and preserves draft.
- Valid inspection with defect completes successfully once *actual submit API is wired*; defect recorded OPEN.
- OPEN blocks QC PASS; after rectification status RECTIFIED still blocks; verified PASS unblocks. Failed verification reopens and blocks again.
- Actor/timestamps/original defects never overwritten when rectified, and audit rows are append-only.
- In-flight inspection and old completed reports are not retroactively recalculated under newly published checklist.
- Existing job-card, ROT, finance, invoices and GPS keep functioning.

## Server-side integration contract (not yet applied)
Inside a single existing inspection completion transaction:
```php
$snapshot = app(\App\Services\InspectionMasterService::class)->jobSnapshot($jobId);
$clean = app(\App\Services\InspectionMasterService::class)
    ->validateSubmission($snapshot['points'], $requestItems);
// Save the authoritative inspection results in the existing Job Card schema.
// Use a separate typed answer storage/snapshot where old status column only accepts Good/Fair/Poor.
// Do not mark Completed unless the transaction succeeds.
app(\App\Services\InspectionDefectService::class)->recordFindings($jobId, $clean, $staffCode);
// existing status Completed update inside SAME transaction
```
Before any server-side QC PASS transition:
```php
app(\App\Services\InspectionDefectService::class)->assertQcReady($jobId);
```
This contract intentionally stops short of injecting code into unknown live production-critical functions.
