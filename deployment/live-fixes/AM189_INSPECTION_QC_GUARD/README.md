# AM189 — Actual STAGING Inspection Defect / QC PASS integration

**Source:** Hostinger 2026-10-29 read-only output, showing real controller methods:
- `app/Http/Controllers/Api/V1/StaffExtraController.php::saveInspection`: exactly 30 `good/fair/poor` results, canonical checklist, mandatory comments for Fair/Poor. Existing completion transaction preserved.
- `app/Http/Controllers/Web/StaffQcController.php::submit`: QC PASS/FAIL inside locked Job Card/QC transaction.
- `app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php::qcResult`: Admin QC PASS/FAIL and audit.
- `app/Http/Controllers/Api/V1/OwnerWorkshopController.php::qc`: Owner app is only permitted to assign QC `PENDING`, explicitly refuses direct PASS/FAIL.

## What AM189 DOES
1. Introduces separate `am189_inspection_defects` and append-only `am189_inspection_defect_audits` with zero existing-table migrations.
2. Supports initial `Fair` or `Poor` on the CURRENT 30-point form. Inspection completion remains permitted, with existing validation/assignment/remarks unchanged.
3. Before either actual Staff or Admin QC PASS is recorded, compare saved inspection results with registered defects and their status. An unregistered, OPEN, RECTIFIED or changed finding blocks PASS.
4. Add protected Admin page `/erp/job-cards/{job}/inspection-defects` for registering saved findings, recording actual repairer and reason, and performing re-inspection (PASS→VERIFIED, FAIL→OPEN).
5. Preserve original inspection answers, QC audit and Job Card lifecycle. Repair/verify updates write an independent audit trail. Safety repairs require a separate person from the repairer to verify.
6. Does **NOT** alter `OwnerWorkshopController@qc` because mobile Owner cannot submit PASS/FAIL.

## Important limits
- This is a **canonical legacy 30-point Good/Fair/Poor compatibility patch**, not the dynamic/typed master UI of Draft PR #109.
- It does not mark inspections complete, change any existing Job Card data, or retroactively credit-note/invoice a Job Card.
- Pre-existing Fair/Poor inspections must be registered and rectified/verified before a new QC PASS. Previous already completed QC is not automatically reversed.
- The optional Health PDF & valuation are in separate Draft PR #108 and need DOMPDF; neither is installed by AM189.
- Actual STAGING testing is required after manual installation. This README is not evidence of deployment or acceptance.

## Installation sequence (SSH, STAGING ONLY)
Run from `~/assam-motors-deploy` after `git pull --ff-only origin main`:

```sh
ROOT=~/domains/assammotors.com/assam-erp-staging
PATCH=deployment/live-fixes/AM189_INSPECTION_QC_GUARD/install.py
python3 -m py_compile "$PATCH"
python3 "$PATCH" check "$ROOT"
```

Only if this preflight says `AM189_CHECK_COMPLETE_NO_WRITES`:

```sh
python3 "$PATCH" prepare "$ROOT"
cd "$ROOT"
php artisan migrate --path=database/migrations/2026_10_29_161500_create_am189_inspection_defects.php --force
```

Take verified STAGING database backup **before** migrating. New tables only. Then activate with explicit approval:

```sh
cd ~/assam-motors-deploy
AM189_STAGING_APPROVED=YES python3 "$PATCH" activate "$ROOT"
```

The installer creates backups for original controllers and routes in `storage/app/am-backups/AM189_*`, validates PHP syntax before writing, verifies migration tables, and checks route authorization. If post-apply route verification fails, it restores the original files.

## Acceptance checklist
- `php artisan route:list --path=inspection-defects -v` shows Admin middleware.
- Anonymous/ordinary Staff cannot access Admin defect register.
- A Good-only completed inspection does **not** block QC PASS; existing workflow remains unchanged.
- A Fair/Poor completed inspection produces a QC PASS validation error, but does not block inspection submission or QC FAIL.
- Admin opens the correct Job Card `/erp/job-cards/{job}/inspection-defects`; registers findings, chooses actual repairing staff, enters repair detail. It remains blocked at RECTIFIED.
- An independently authorized Admin verifies actual repair. VERIFIED allows QC PASS for that unchanged finding. A failed recheck returns to OPEN.
- A changed original condition/remarks never silently inherits old verification. Audit rows preserve original findings and all transitions.
- Regression: Owner mobile QC assign (PENDING), Staff QC submit, Admin QC submit, Job Card and ROT, existing invoices still work.
- Do not test QC PASS on a real customer Job Card without approval; use a disposable STAGING test Job Card.

## Rollback
The activation script is intentionally not a database destructive rollback. If needed, restore the three original files from the printed `AM189_BACKUP` directory and run `php artisan route:clear`. Preserve both audit tables and records for investigation.
