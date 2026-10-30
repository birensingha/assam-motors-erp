# AM206 — Admin Inspection Master (STAGING, phased rollout)

**Status:** Package committed on main for review and STAGING installation. Not deployed to Hostinger by a GitHub push. Stage 1 is **configuration only**.

## User-approved workflow (corrected prototype)
- In Job Card, **before** assigning an Inspector: choose staff and either **Direct Send** (published Master) or **Customize & Send** (Job Card-specific point list).
- **After** assignment: show the existing Assigned Inspector/status panel, including Open My Assigned Inspection and Admin Manage Inspection Assignment. Do not duplicate this panel.
- Dynamic forms support 1–100 points, type Good/Fair/Poor, Yes/No, Available/Not Available, Fuel Level and Number Reading, custom required and defect answers.
- Inspection results with configured defects can be reviewed in existing AM189 Defect Management; selected findings may later go to the AM205 Optional Estimate/customer item-wise approval workflow.
- Existing 10/20/30 point historical inspections and AM189 audit trail must stay unchanged.

## Stage 1 — files now prepared
- New `Am206InspectionMaster` service, admin web controller, responsive editable view, tables for immutable master versions and future per-Job-Card checklist snapshots.
- Defaults copy names from the CURRENT canonical `VehicleInspectionChecklist::items()` rather than replacing existing points.
- Publishing the master **does not change** existing Staff App inspection forms or live Job Card assignment. All new routes are under the existing `EnsureErpAdmin` group.
- Existing Staff API requires exactly 30 Good/Fair/Poor; AM206 deliberately **does not** modify that validator until Stage 2 is fully compatible.

## Deployment safety
1. `python3 install.py check /path/to/assam-erp-staging` — validates existing routes and filename collisions, no writes.
2. `python3 install.py prepare /path/to/assam-erp-staging` — copies only new files, PHP-lints controller/service/migration.
3. Take a **fresh verified STAGING DB backup** before any migration.
4. Run `php artisan migrate --path=database/migrations/2026_10_30_113000_create_am206_inspection_master_versions.php --force` after review.
5. `AM206_STAGING_APPROVED=YES python3 install.py activate /path/to/assam-erp-staging` — adds Admin-only routes; verifies route security/Blade compile, backs up/restores routes on failure.
6. Access `/erp/configuration/inspection-master` as authorized Admin.

## Stage 2 — DO NOT claim completed
- Run `../AM207_INSPECTION_ASSIGNMENT_ANCHOR_AUDIT/audit.py` on STAGING to identify actual ManagerInspectionWorkController@assign and the Job Card state-dependent Blade panel.
- Implement a locked per-JC snapshot at assignment and an Admin-only Customize screen, before assignment.
- Ensure both Staff API and Staff Web validate and save **the same versioned checklist**; Android must fetch/render/submit that snapshot.
- Adapt AM189 to recognize **typed defect rules** without ever silently clearing a historical Fair/Poor finding.
- Test Direct Send, Customize, reassignment, offline draft resume, negative-point notes, actual ERP commit and QC PASS regression on disposable STAGING Job Cards before enabling dynamic send.

**Do not deploy AM180 Draft PR #109 unchanged:** it creates a second defect register and conflicts with active AM189 routes. AM179 PDF and AM181 Android sharing also need separate, verified integration.
