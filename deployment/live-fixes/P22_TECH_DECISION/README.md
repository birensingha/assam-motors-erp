# P22 — Sequential Final Diagnosis then Technician Decision

## STAGING workflow

1. Manager Vehicle Inspection must be submitted and COMPLETED.
2. Admin/Workshop completes **Final Diagnosis** alone from Job Card; `diagnosis_status=completed`, `decision_status=pending`, `estimate_allowed=0`.
3. Assigned Deciding Technician (linked active Staff ID matching `job_cards.technician_id`) sees a task on `/staff`, opens `/staff/inspections/{job}/report` and separately submits Technician Decision.
4. Eligible repair/replacement/OSL decision marks Decision complete and allows initial Estimate Part/Labour selection. `FURTHER_DIAGNOSIS` and `NO_REPAIR` remain Estimate-locked.

Old combined `erp.job-cards.diagnosis.complete` POST handler now points to `AdminDiagnosisStageController@complete`, preventing silent decision completion. Staff Decision POST uses `StaffTechnicianDecisionController@save`, Staff middleware and server-side assignment checks.

Original `EstimateService::assertEligible` enforces completed Inspection, Diagnosis, Decision and estimate flag. Existing supplementary Estimate exception for released WIP stays intact. Pre-existing approved/released estimates and work records are not modified during installation.

## Staging verification

- Preflight 38537071297 PASS.
- Deployment Actions run **38537344205** PASS:
  - `P22_REAL_JOBCARD_SEQUENTIAL_RENDER_PASS`
  - `P22_STAFF_TECH_DECISION_FORM_RENDER_PASS`
  - `P22_ESTIMATE_GATE_RETAINED_PASS`
  - `P22_NO_DIAGNOSIS_DECISION_OR_ESTIMATE_RECORDS_MUTATED`
  - `P22_STAGING_SEQUENTIAL_WORKFLOW_DEPLOY_PASS`

Backup: `storage/app/p22_workflow_backup_20261014_141545_2698992`.

**Not yet tested:** genuine user-submitted Manager Inspection, Admin Final Diagnosis, Technician Decision and subsequent Estimate on a fresh disposable Job Card. No POST business actions were auto-submitted.
