# P21 — Manager Vehicle Inspection (STAGING)

## Scope

The Owner/Admin login linked to a Staff Master **Manager** can see and use a dedicated web ERP **Manager Inspection Work** page. Assignment to a Manager is independent of `job_cards.technician_id` (deciding technician).

- `/erp/inspection-manager` — Admin-protected inspection queue, clear assignee and submitted-by indicators.
- `/erp/inspection-manager/{job}` — manager assignment and 10-category vehicle inspection checklist.
- New additive table `job_card_inspection_assignments` tracks assigned staff/time and completed staff/time.
- Job Card details now show “Inspection Assigned To” and “Inspection Completed By”, plus a shortcut.
- Only active Manager Staff linked to an authenticated active Admin may complete that assigned inspection through the new ERP page. Non-admin-only Managers currently need a distinct access route; not included.
- Pending OPEN inspections only; completed work not silently overwritten, and no historic job rows updated by installer.

## Gate

Inspection completion updates `inspection_status=completed`, `inspection_submitted_by` and inspection items. It does **not** complete Diagnosis, Technician Decision, or unlock Estimate.

## Test evidence

GitHub Actions staging run **38536556861**:
- `P21_MANAGER_WORKSPACE_RENDER_PASS`
- `P21_ADMIN_MANAGER_ROLE_LINK_PASS`
- `P21_JOB_CARD_ASSIGNMENT_VISIBILITY_PASS`
- `P21_NO_INSPECTION_ASSIGNMENTS_OR_SUBMISSIONS_AUTOMATED`
- `P21_STAGING_MANAGER_INSPECTION_PASS`

Staging backup: `storage/app/p21_manager_inspection_backup_20261014_140859_2434457`.

End-user actual form submission is still pending. This branch is a draft PR dependency for P22 and P23.
