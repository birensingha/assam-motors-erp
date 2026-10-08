# Phase 3 — Job Card Edit

This patch defines the safe edit workflow for Assam Motors Workshop Job Cards.

## Required UI order

1. Job Card / Customer / Vehicle header
2. Customer complaint / workshop notes
3. **Parts** — first
4. **Labour / ROT** — second
5. Workflow / QC / Delivery status
6. Audit history

Parts and Labour can be added directly inside the Job Card even when no Estimate exists.

## Edit rules

- Job Card number is immutable.
- Draft / Open / WIP Job Cards are editable.
- Parts and Labour rows may be added, changed or removed before closure.
- A Labour line may be mapped to ROT and technician assignment.
- QC must be completed before Ready for Delivery.
- Ready for Delivery must be completed before Job Card Close.
- Invoice conversion is available only after Job Card Close.
- Invoiced Job Cards are locked.
- Reopening a Closed/Invoiced Job Card requires Admin privilege + mandatory reason + audit row.

See `JOB_CARD_EDIT_FLOW.md` for the screen and API contract.
Run `001_job_card_edit_audit.sql` before enabling production edits.
