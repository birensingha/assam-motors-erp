# P23 — Inspection Manager Assignment API / Old Mobile Diagnosis Guard

## Purpose

Legacy Staff Inspection API previously inferred inspector from `technician_id`, conflicting with separate Deciding Technician identity. The old Owner mobile API `diagnosis action=COMPLETE` simultaneously marked both Diagnosis and Technician Decision and unlocked Estimate, bypassing P22.

## Changes (STAGING API source)

- Staff Inspector API list/detail/save is scoped to explicit `job_card_inspection_assignments.assigned_staff_id` and linked active Staff Manager. Completed rows are protected from rewrite.
- Owner/Admin mobile `assignInspection` assigns an active Manager separately and never modifies Job Card `technician_id`.
- Legacy combined Owner/Admin mobile Diagnosis `action=COMPLETE` now refuses with clear message to use sequential Final Diagnosis on Web ERP and Staff Technician Decision via Staff Portal.
- No pre-existing inspection, parts, labour, Estimate, salary, inventory, invoice or ledger rows were modified by deployment.
- The Android APK/UI itself was **not** rebuilt. API compatibility and user-facing Admin-Mode controls require separate Android QA.

## Verified

- Dry-run Actions run 38537644735 PASS.
- Staging deployment Actions run **38537761326** PASS:
  - `P23_MANAGER_ASSIGNED_INSPECTION_LIST_PASS`
  - `P23_MOBILE_COMBINED_DIAGNOSIS_LOCK_PASS`
  - `P23_API_PHP_SYNTAX_PASS`
  - `P23_NO_ADMIN_OR_STAFF_INSPECTION_POST_EXECUTED`
  - `P23_STAGING_API_GUARDS_DEPLOY_PASS`

Backups: `storage/app/p23_inspection_api_backup_20261014_084855_2915518`.

Actual user role/form POST acceptance remains to be tested on a fresh staging card.
