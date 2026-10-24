# AM128 — P55 Full ROT Correction: show assigned mechanic (STAGING only)

## Confirmed staging evidence
Read-only GitHub Actions AM127 inspection checked ROT-DNT-001:
- `job_card_labour` labour line ID 79: `mechanic_id=3`, `mechanic_name=FIRAJUL HOQUE`.
- Linked completed `rot_sessions.staff_id=3`, `staff_code=AM7887`.
- P55 Blade `<select name="mechanic_id">` renders `Not Assigned`, then iterates `($p55Staff??collect())`.
- AM123 integration in Main Job Card passed `p55Rows` but did **not** provide `p55Staff`; so P55 had only the default empty option regardless of existing assigned mechanic ID. The backend assignment is not lost.

## Patch
Inject `p55Staff` and `p55Sessions` into AM123's existing P55 include:
- `p55Staff`: staff with active status **plus any already-assigned labour mechanic IDs**, ensuring historical Completed ROTs retain their selected name even if the staff member is no longer active. Inactive staff will still be rejected for new reassignment by the existing server service.
- `p55Sessions`: sessions for the current Job Card, so P55 Completed status/timing reads from the actual linked session rather than fallback values.
- No alterations to job_card_labour rows, rot_sessions, work timings, route/controller validation, GST, invoices or credit notes.

## GitHub staging deployment
Merging the PR adds the workflow `.github/workflows/deploy-am128-p55-mechanic-staging.yml`, which automatically triggers on push to main for this deployment path and uses already configured **STAGING SSH** only. The GitHub Action checks Python/PHP syntax, does remote read-only anchor checks, backups the AM123 view, applies atomic view edit, compiles Blade, checks compiled PHP, and verifies ROT-DNT-001 selected staff is present in active/assigned options. Automatic rollback if compilation fails. Login page smoke check follows.

## Acceptance / remaining
- Main JC > ROT-DNT-001 > Admin Full ROT Correction > Mechanic should preselect **FIRAJUL HOQUE · AM7887** instead of Not Assigned, and dropdown should contain searchable/allowed staff.
- No changes to the existing labour line/session records.
- The P55 mechanic select after a new selection still uses existing audited reason + unlock/alter and backend validation; actual save requires separate disposable JC browser QA.
- This patch does not fix the separate ROT Live productive clock recalculation issue or Qty support.
