# AM244 — Complaint Decision Guard and Readable Audit Timeline

## Observed defect on STAGING
- Admin Complaint #1 for JC #11, original Completed ROT assigned to Kapil Rajbongshi (AM9472).
- User screenshot shows repeated `INVESTIGATION REVIEW` events, including `WORKMANSHIP_CONFIRMED` followed by `UNDER_REVIEW` with no separately recorded correction reason.
- Original AM242 result view displayed raw `new_json` audit payloads; current status can be misleading when a legacy form reverts a previously confirmed decision.
- AM243 empty-month register fix was installed successfully and **must not be rolled back**.

## Changes
1. Replace only the existing Admin `review()` method, preserving routes and all other controller actions.
2. Prevent duplicate no-op audit entries (same status, outcome, findings and recorded response).
3. Use `expected_audit_id` to reject a stale form submitted after another correction.
4. Require a 15+ character correction reason whenever the case is or **ever was** reviewed, including when a previously confirmed case was reverted under older behavior.
5. Require explicit confirmation checkbox before changing a **currently saved final outcome** to any other outcome, including Under Review.
6. Save every actual decision correction as `DECISION_CORRECTED` with previous and new JSON, reason and actor; preserve all older events.
7. Replace the Admin Result view with a current-decision summary and human-readable audit timeline. Show older actions including ambiguous legacy reversals, without raw JSON output.
8. Label employee response as **Admin-recorded**, not verified staff self-submission. Do not infer actual mechanic admission or apply automatic salary penalty.

## Safety
- No migration, no existing case edits, no correction of current outcome without an explicit authorized future action.
- No original Completed ROT, JC, QC, invoicing, payment, staff app, or existing audit row modifications.
- Guarded `check|apply|rollback` installer for STAGING only with source-anchored replacement, PHP lint, actual Laravel Blade compile, view cache and synthetic render smoke. Read-only `check` before apply.
- Existing later changes to controller or report cause installation STOP rather than overwrite.
- Automatic restore of original files if apply-time smoke test fails; rollback refuses if installed file hashes changed.

## Expected acceptance
- Open `/erp/rot-work-complaints/1` in Admin login: current result clearly shows Under Review or Confirmed based on the current persisted row, with historical timeline reflecting previous decisions.
- Existing `REPORTED` and all previous `INVESTIGATION_REVIEW` events stay visible with meaningful labels and times; no technical JSON dumps.
- Clicking Save without changes creates **no extra audit row**.
- After an existing final decision, an attempted change without reason must show validation error; attempting to change a current final outcome without checkbox must also show validation error.
- A case with historical confirmed outcome but currently Under Review still requires a reason for any new decision.
- After authorized change, Admin, monthly performance and the related staff portal should reflect the **new effective finding**; no automatic rework mechanic allocation.
- Verified status requires actual browser tests after Hostinger deployment.

## Deployment status
As long as only a GitHub PR exists, nothing is installed on STAGING.
