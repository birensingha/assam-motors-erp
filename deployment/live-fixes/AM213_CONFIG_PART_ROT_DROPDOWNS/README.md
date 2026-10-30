# AM213 — Parts & ROT native Master old dropdown choice restoration

**Status:** GitHub patch prepared for STAGING preflight. Neither deployed nor browser-confirmed yet.

## User's observed issue
AM184/AM185 moved Configuration tiles to native NEW ERP, but native Parts/ROT category fields became plain text inputs; OLD ERP had category dropdown lists and searchable working master screens. AM211 audit identified the actual legacy HTML paths, NEW ERP form names and database tables.

## AM213 scope
- For Parts Master, use OLD ERP `parts-master.html` `select#pfCategory` options as the source of category suggestions.
- For ROT Master, use OLD ERP `labour-master.html` `select#lfCategory` options as the source of category suggestions.
- Also show suggestions from every distinct saved value in the **corresponding native NEW ERP master table**, not only visible paginated records.
- If the existing NEW ERP input fields exist in both Create and Edit, enable searchable datalist for Parts `category`, `sub_category`, `unit`, `vehicle_make`, `vehicle_model`; for Labour `category`. The OLD list for a secondary field is imported only when a matching OLD `select` actually exists.
- User may type an existing value to filter and select, **or enter a new value**. Existing Admin Create/Edit POST handlers, validation and database persistence remain unchanged.
- Do not touch `public_html/workshop/`, legacy APIs, master data, stock, Job Cards, invoices, purchase history, services or Android.
- Gracefully **STOP** if native input controls, OLD category options, view structure or form actions differ from expected staging source.

## Installation: safe source-only STAGING
```bash
cd ~/assam-motors-deploy
git pull --ff-only origin main
ROOT="$HOME/domains/assammotors.com/assam-erp-staging"
PATCH="deployment/live-fixes/AM213_CONFIG_PART_ROT_DROPDOWNS/install.py"
python3 -m py_compile "$PATCH" &&
python3 "$PATCH" check "$ROOT"
```

**Do not apply if check fails.** Check compiles candidate Blade through live Laravel and PHP lints the compiled result, with no database writes or native view changes.

After a passing check:
```bash
python3 "$PATCH" apply "$ROOT"
```

Rollback source views only if they have not subsequently been edited:
```bash
python3 "$PATCH" rollback "$ROOT"
(cd "$ROOT" && php artisan view:clear)
```

## Browser acceptance
1. Login as Admin on STAGING NEW ERP; open Configuration → Parts Master.
2. In New Part, Category accepts keyboard typing. Typing `Bra` offers Brakes from OLD ERP. Selecting fills Category; creating a new Part uses the existing master Save.
3. Edit the same Part; current Category is preserved and suggestions appear.
4. Open ROT Master. New ROT and Edit both suggest the OLD category values (Engine, Brakes, Suspension, etc.) and all existing saved categories. Existing SAC, GST, hours, rate and AM212 Work Procedure remain unchanged.
5. A new typed Category is not silently inserted into a different master table: it is saved as the selected Part/ROT category field by the standard native Create/Edit handler.
6. Search and New Create workflows continue to use native ERP routes; no redirects to OLD ERP.
7. No broken pages/validation/HTTP 500. Verify on desktop and mobile-responsive browser.

## Remaining configuration parity (separate work)
AM213 is **not the complete OLD ERP master feature migration**. OLD CSV/XLSX upload, downloadable CSV template, native auto-refresh, linked Vehicle Make → Model → Variant, and the other dependent picker workflows still require their own guarded implementation and testing. Protect master deletes when referenced by stock/purchase/Job Card/Invoice; do not enable unsafe hard delete.
