# AM288 — Locked versioned inspection assignment (STAGING SOURCE ONLY)

**Status: Draft code, NOT installed on Hostinger; feature flag OFF.** These files are kept separate from AM206 Stage 1, the 30-point Staff submission and AM189 defect register.

## Files
- Am288InspectionAssignment service: source copy of AM206 published master or custom 1–100 ordered points, with server-side validation, exact Job Card Open/pending checks, row locks, immutable ASSIGNED form and append-only audit.
- Am288InspectionAssignmentController: Admin-only authenticated preview/configure/assign API proposal.
- New append-only assignment audit migration (never drops history).

## Proposed Admin flow
1. Open Job Card and GET /api/v1/admin/mobile/job-cards/{job}/inspection-form.
2. Direct Send = STANDARD published AM206 master; Customize & Send = CUSTOM selected points. Validate the 1–100 chosen definition using AM206.
3. POST /api/v1/admin/mobile/job-cards/{job}/inspection-form/configure with mode, custom points if selected, and mandatory reason.
4. POST /api/v1/admin/mobile/job-cards/{job}/inspection-form/assign with staff_id and mandatory reason.
5. GET again to verify ASSIGNED state, exact Job Card and staff ID. Do not retry a POST blindly after network loss.

The configured form is an immutable per-Job Card snapshot after assignment. Inspector assignment must **not** overwrite the assigned mechanic.

## Critical feature gate
The controller explicitly rejects configure and assign (503) unless the server-side feature config features.am288_typed_submission_ready is TRUE. It must remain FALSE until the typed Staff submit endpoint, saved result schema, AM189 compatible defect mapping, independent safety verification and server QC-PASS transaction guards are implemented and tested.

AM206 Stage 1 only manages published master versions. The current Staff GET/POST still expects the original exactly 30 Good/Fair/Poor points; custom 20-point answers CANNOT be submitted safely through that endpoint.

## Deployment prohibition
No installation script, no route registration, no activated feature flag. Do NOT merge/deploy for customer use. Staging-specific route auth middleware and a verified database backup are required before any migration. Preserve original 30-point inspection drafts and audit records.

## Acceptance before activation
Verify anonymous/staff tokens cannot call Admin configure/assign; reject invoiced, closed, non-pending and previously answered Job Cards; reject duplicate and invalid point definitions; reject second assignment and reconfiguration after LOCK; verify audit transaction rollback on failure; verify new typed Staff results and AM189 historical Fair/Poor guard; check re-inspection independent verification; test on disposable staging Job Cards and spare phone, then separately sign an original-certificate QA APK.

References: AM189 existing defect register and AM206 Stage 1 master (both require actual staging deployment audit). The implementation is not equivalent to finished AM288 until those tests pass.
