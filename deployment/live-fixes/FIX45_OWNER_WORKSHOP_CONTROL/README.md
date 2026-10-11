# FIX45 — Owner Workshop Control Centre

Staging-native Owner/Admin mobile operations. No legacy backend is introduced.

## Adds
- Workshop Floor staff/ROT workload view.
- Mobile Job Card create/edit with existing Job Card numbering and audit rules.
- Technician assignment and Job Card priority.
- Job Card lifecycle controls with QC / Ready-for-Delivery gates.
- Inspection technician assignment.
- Diagnosis complete/reopen.
- Parts requisition + Part issued/fitted/returned status control.
- OSL purchase visibility from existing `workshop_purchases`.
- QC Pass / Fail with rework routing.
- Road Test start/complete/cancel.
- Vehicle key custody event history.
- Comeback / Rework linkage.
- Customer + Vehicle service history.
- Attendance correction with mandatory reason.
- Staff pending/escalated alerts for Owner.
- Owner action audit log.
- Emergency lifecycle override only when explicit override + reason is submitted.
- Existing ROT admin endpoints remain authoritative for reassign/reopen/history.

## Existing data reused
- `job_cards`
- `job_card_change_audits`
- `job_card_inspection`
- `job_card_parts`
- `job_card_labour`
- `rot_sessions`
- `workshop_purchases`
- `attendance`
- `staff_reminder_state`

## Additive staging schema
- `job_cards.priority`
- `workshop_owner_audit_logs`
- `workshop_qc_checks`
- `workshop_road_tests`
- `workshop_key_custody`
- `workshop_reworks`
- `workshop_parts_requests`

No destructive migration is used.
