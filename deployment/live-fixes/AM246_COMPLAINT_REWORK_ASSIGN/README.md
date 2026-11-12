# AM246 — Assign Complaint Rework Mechanic (Staging)

## Supported workflow
1. Admin opens **Complaint #1 → Assign / View Rework Mechanic**. The original Completed ROT, staff and timer remain unchanged.
2. Admin selects a **different active mechanic** and enters actual corrective-work instructions. Saving creates a single, audited `am_complaint_rework_links` assignment and private notifications to the original/receiving staff.
3. Both mechanics see the appropriate item in their **authenticated web Staff Portal**. Receiving mechanic sees assignment even before a ROT has been linked; original mechanic sees who is handling the rework.
4. To record time and productivity without inventing a second ROT billing workflow, Admin creates a **separate new, not-started ASSIGNED ROT** with existing native Job Card controls, with this receiving mechanic and the same Job Card.
5. Admin selects and links the new ROT from the complaint rework page. Server validates exact ROT string ID, same JC and receiving mechanic, original Completed ROT preservation and absence of prior complaint link. All assignment/link events are audited.
6. Receiving mechanic uses **existing native Check-In, ROT Start/Pause/Resume/Complete**, which already records assigned staff and actual hours; no automatic payroll incentive or job invoice charge is made by AM246.
7. Admin Work Complaint Register now includes **Rework Assignment** (name and linked ROT when available). `ASSIGNED` or `ROT_LINKED` never misleadingly says **Rectified**. QC verification and final rectified status require subsequent integration.

## Critical difference from old AM241 draft
Old AM241 used unsignedBigInteger for ROT IDs. In this ERP actual ROT session IDs can be strings (e.g. `ROT261103163521SVOX8`). AM246 uses **varchar(191)** for both original and receiving ROT session IDs, and validates actual SQL column types in preflight/apply. Do **not** apply the obsolete AM241 migration/service.

## Deployment gates (Hostinger STAGING)
```bash
ROOT="$HOME/domains/assammotors.com/assam-erp-staging"
SCRIPT="/path/to/downloaded/AM246_COMPLAINT_REWORK_ASSIGN/install.py"
python3 "$SCRIPT" check "$ROOT"      # read-only
python3 "$SCRIPT" prepare "$ROOT"    # install additive migration file; no DB changes
php /path/to/downloaded/AM246_COMPLAINT_REWORK_ASSIGN/staging_db_backup.php "$ROOT"  # full private staging MariaDB dump with basic schema coverage validation
(cd "$ROOT" && php artisan migrate --path=database/migrations/2026_11_10_173000_create_complaint_rework_assignments.php --force) # explicit DB schema change
python3 "$SCRIPT" apply "$ROOT"      # source backup, PHP lint, Blade compile, view cache, route smoke, rollback on failure
```
Do not proceed after any STOP or failed backup. No existing complaint, original ROT, finance or staff entry changes during installer. If backup source has changed after apply, rollback refuses to overwrite newer changes. Database migrations are additive, with a preservation-only down().

## Hostinger PHP `proc_open()` disabled — safe shell dump fallback

On this hosting account the original `staging_db_backup.php` stops with
`AM246_BACKUP_STOP_PROC_OPEN_DISABLED`. **This means the backup, migration and install have NOT happened.**

The corrected shell-based backup is now included; it executes `mariadb-dump` or
`mysqldump` from the user's SSH shell rather than using PHP `proc_open`.
It gets STAGING DB configuration from Laravel via a PHP helper that stores
credentials only in a private temporary `0600` MySQL defaults file, does not
print the password, validates that the Laravel app URL is STAGING, verifies
every base table has a `CREATE TABLE` statement in the SQL export, and stores a
private SQL backup with a SHA256 manifest under
`storage/app/am-backups/AM246_REWORK_SQL_SNAPSHOT`.

After fetching the latest AM246 branch and extracting its package:

```bash
bash "$PACKAGE/staging_db_backup_shell.sh" "$ROOT"
```

Only after `AM246_FRESH_STAGING_DB_BACKUP_EXPORT_VERIFIED`:

```bash
(cd "$ROOT" && php artisan migrate --path=database/migrations/2026_11_10_173000_create_complaint_rework_assignments.php --force)
python3 "$PACKAGE/install.py" apply "$ROOT"
```

If backup reports `STOP_AM246_SHELL_BACKUP_NO_MARIADB_DUMP_BINARY`, use Hostinger hPanel's MariaDB database backup/export facility and verify the artifact first. Do not run the migration without a verified export. A verified export is not the same as a tested restoration.

## Browser acceptance
- Admin GET `/erp/rot-work-complaints/1/rework` shows case and original ROT.
- Admin selects a different actual employee only when ready; POST must show assigned receiver, no auto ROT billing, staff portal receiving inbox new assignment, original staff portal new receiver.
- Admin creates a legitimate new native ROT on JC #11 (check no unintended customer billing), returns and links it. Original ROT must stay Completed, with original timestamps/assignment unchanged.
- Admin register shows receiving mechanic, rework state, linked new ROT; never calls it completed before native ROT completion / QC.
- Retry duplicate assignment or wrong staff/wrong job/original ROT link must fail without partial rows.
- Staff cannot read other employees' complaints through URL parameters.
- Admin / Staff browser run needed after installation; installing alone cannot confirm user-level end-to-end behavior.

## Excluded
- This does not create a new ROT session automatically, assign wages, calculate a new payroll incentive, perform QC PASS, or produce a new Android Kotlin APK.
- Assignment completion/rectification is **not** inferred from the linked ROT alone; future QC verification must be recorded explicitly.

## Status
Code prepared in GitHub. Not installed or browser verified until user supplies Hostinger command results.
