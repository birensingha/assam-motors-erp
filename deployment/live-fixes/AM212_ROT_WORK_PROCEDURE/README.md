# AM212 — ROT Master Work Procedure / Technical Instructions

## Requested by workshop Admin
ROT description is short billing/dispatch text. A separate optional 5-line expandable textarea stores detailed workshop work procedure under the *same* `labour_master` ROT record, editable whenever the master is edited.

Example (Admin-entered, vehicle-specific):
- ROT: `ROT-CUS-0211`
- Description: `Rear Torsion Beam Axle Assy – R&R`
- Work Procedure / Technical Instructions:
  1. Remove both rear wheels
  2. Remove both rear brake drums
  3. Disconnect handbrake cable
  4. Disconnect brake hose/pipe brackets as required
  5. Disconnect ABS wheel-speed sensor wiring/brackets, if equipped
  6. Remove rear shock absorber lower bolts
  7. Safely unload/remove rear coil springs
  8. Support beam with suitable jack
  9. Remove both beam-to-body mounting bolts
  10. Lower complete rear torsion beam
  11. Refit in reverse sequence following the vehicle's service procedures and fastener specifications
  12. Bleed brakes if hydraulic lines were opened
  13. Verify brake/handbrake adjustment and road-test

This is an **illustrative procedure provided by the user**, not a universal vehicle workshop manual. Model/variant-specific procedures, torques and safety precautions must be confirmed separately. Do not automatically assign this to other vehicles or overwrite other ROT masters.

## Technical scope
- Add nullable `labour_master.work_procedure` text column. No seed, no existing master changes.
- Add separate multiline textarea after ROT description in **Create** and **Edit** forms of native `resources/views/admin/native/labour.blade.php`.
- Include optional `work_procedure` input in existing Admin master Create/Update validation and same DB insert/update, max 12,000 characters.
- Escape output through Blade; store plain text with line breaks.
- **Internal-only**; not in customer estimate/invoice descriptions or customer prints.
- Do not modify stock, ROT pricing, GST, JC history, payroll, sessions, assignments or Staff App.
- No OLD ERP redirects. Current Admin route and middleware unchanged.

## Safe staging sequence

### Step 1 — preflight only (no writes)
```bash
cd ~/assam-motors-deploy
git pull --ff-only origin main
ROOT="$HOME/domains/assammotors.com/assam-erp-staging"
python3 -m py_compile deployment/live-fixes/AM212_ROT_WORK_PROCEDURE/install.py
python3 deployment/live-fixes/AM212_ROT_WORK_PROCEDURE/install.py check "$ROOT"
```
If check fails at a guard, **STOP** and provide the exact output. Do not run migration or apply.

### Step 2 — after preflight PASS
```bash
python3 deployment/live-fixes/AM212_ROT_WORK_PROCEDURE/install.py prepare "$ROOT"
```
Confirm a **fresh, restorable STAGING database backup** via Hostinger before changing the schema. Once confirmed, run this specific additive migration:
```bash
(cd "$ROOT" && php artisan migrate --path=database/migrations/2026_10_30_190000_add_work_procedure_to_labour_master.php --force)
```

### Step 3 — guarded UI/backend source install
```bash
python3 deployment/live-fixes/AM212_ROT_WORK_PROCEDURE/install.py apply "$ROOT"
```
Installer backs up Controller and view, checks live DB column and Master sources, lints new PHP and compiled Blade, recompiles views; source rollback on failure.

### Step 4 — acceptance
1. Open `/erp/labour` as Admin. Create a disposable test ROT with a multiline procedure; see confirmation.
2. Reopen same master, verify all lines persisted, edit and save a change, refresh.
3. Verify original short description, SAC/GST, rate, standard hours unchanged by procedure edit.
4. Confirm existing Job Cards and customer invoice prints are unchanged.
5. Check permissions and no errors. Do not mark COMPLETE until successful.

Rollback source only (column intentionally stays to preserve recorded technical notes):
```bash
python3 deployment/live-fixes/AM212_ROT_WORK_PROCEDURE/install.py rollback "$ROOT"
(cd "$ROOT" && php artisan view:clear)
```

## AM211 dependency
The user's shell output was `AM211 AUDIT COMPLETE` because its detailed output was redirected to `~/am211-config-parity-audit.txt`. That report is **not included** in the pasted console transcript. It is required to fully reproduce legacy Parts/ROT/Vehicle search + select + new-create parity. AM212 adds the ROT technical procedure field but **does not** complete AM211 parity.
