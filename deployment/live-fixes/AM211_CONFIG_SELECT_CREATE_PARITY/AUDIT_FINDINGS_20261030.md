# AM211 actual STAGING read-only audit findings (user output, 30 October 2026)

## Audit outcome
`AM211_COMPLETE_READ_ONLY_NO_FILES_OR_DB_DATA_MODIFIED`; successfully inspected live source and table column names. **No corrective UI patch has been applied.**

## Actual OLD ERP features absent or not parity-tested in native NEW ERP

| Page | OLD ERP source | Native NEW ERP source | Deficiency |
|---|---|---|---|
| Parts Master | `public_html/workshop/parts-master.html`: category `select#pfCategory`, modal Add/Edit `partForm`, CSV/XLSX upload, sample CSV, Refresh, client-side search | `resources/views/admin/native/parts.blade.php`: 17-line Laravel form, `name=category` input, Add New Part details, server GET search, inline edit | Restore category/subcategory dropdown choices/search and CSV/template/refresh parity while retaining native PHP POST; DO NOT call old API |
| ROT Master | `public_html/workshop/labour-master.html`: `select#lfCategory`, Add/Edit modal, upload, sample CSV, Refresh, client-side search | `resources/views/admin/native/labour.blade.php`: 36-line Laravel Create/Edit, plain category input, search/inline edit, SAC field | Restore category dropdown and full native feature parity; AM212 adds separate internal technical work instructions |
| Vehicle Catalogue | `vehicle_makes(id,name,active)`, `vehicle_models(id,make_id,name,active)`, `vehicle_variants(id,model_id,year_from,year_to,name,fuel_type,transmission,active)`; plus `am_vehicle_catalog_master` | `resources/views/admin/native/vehicles.blade.php`: `vmMake`, `vmModel`, variant typed fields; native `saveVehicle` updates `am_vehicle_catalog_master` | Provide searchable Make → Model → Variant lookup from native tables, allow new entry, preserve model-year/variant IDs and normalized/canonical entity rules |
| Labour-Part mapping | OLD `mapping-upload.html` + `mapping-crud.php` | NEW `mappings.blade.php` form currently takes free-text `part_code` and `rot_code` | Add server-validated searchable master selection; ensure no duplicate pair |

## Confirmed staging table names
- `parts_master`: part_code, part_name, category, sub_category, vehicle_make, vehicle_model, purchase_price, selling_price, hsn_code, gst_percent, unit, minimum_stock, active, stock_qty.
- `labour_master`: id, rot_code, sac_code, rot_description, category, standard_hours, labour_rate, gst_percent, active.
- `am_vehicle_catalog_master`: catalogue make/model/year/variant/fuel/transmission fields.
- `vehicle_makes`, `vehicle_models`, `vehicle_variants`: linked normalized taxonomy.

## Critical protection to address before master deletion
Actual native `deletePart()` performs direct `parts_master` row deletion; existing `deleteLabour()` deletes associated `part_rot_mapping` and master row. **These operations require a separate relational-link, purchase/JC/invoice/stock protection patch.** User-approved rule: no direct deletion of billed purchase item; use audited Purchase Return and appropriate customer credit/stock workflow, not rewriting financial history. Distinguish deleting a *master* vs deleting a *purchase line*.

## AM212 development discovery
`createLabour()` explicitly inserts `['rot_description'=>$data['rot_description'],...]` into `labour_master`; `updateLabour()` instead calls `DB::table('labour_master')->where('id',$labour)->update($data)`. The AM212 installer therefore must add the validated optional `work_procedure` key to Create's insert array **only**, while Update uses the existing validated `$data` unchanged. Fixed by AM212 GitHub commit `cdfbc440`.

## Acceptance
1. OLD and NEW have same available category choices; type-to-find works and new master entry can be created without legacy redirect.
2. Vehicle select/filter Make/Model/Variant based on current catalogue; edit reopens the same entity; typed new variant persists without duplicate.
3. Existing database IDs, mappings, Job Card and invoice snapshots stay stable.
4. Master delete with linked stock, purchase, billing, JC or ROT usage must not silently remove records.
5. User verifies actual browser Create/Edit for all three masters; do not claim full parity on route migration alone.
