# FIX36B — Vehicle Inspection End-to-End

Root cause confirmed from live source + FIX36 output:

- Job Card stores assigned staff in `job_cards.technician_id`.
- Native Laravel `StaffExtraController` already lists/saves inspections for that staff ID.
- Android Staff App uses legacy URLs `/legacy/api/staff-inspections.php` and `/legacy/api/staff-inspection.php`.
- Those legacy compatibility files were absent.
- Staff Web Portal only showed completed Inspection Reports for Diagnosis Review; it had no pending Inspection work queue.
- Job Card pending Inspection block did not show/assign the Inspection Technician.

FIX36B adds:

1. Admin Job Card — visible **Vehicle Inspection Assigned To** + assign/change action.
2. Staff Android — legacy compatibility API for list/detail/submit.
3. Staff Web Portal — pending inspections + full checklist submit.
4. Job Card — **Submitted By + Submitted At + Inspection Report** after completion.
5. Inspection submit keeps Diagnosis pending and Estimate locked until the normal Diagnosis/Decision workflow is completed.
6. Existing `inspection_submitted_by` preserves the actual inspector even if a different Deciding Technician is assigned later.

No DB migration. Existing `job_cards` + `job_card_inspection` are reused.

Android matching UI is on Staff App PR #6 branch and builds via GitHub Actions.
