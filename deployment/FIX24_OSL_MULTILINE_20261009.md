# FIX24 — OSL Purchase Multi-Line Format (09-Oct-2026)

Goal: make OSL Purchase behave like Parts Purchase for supplier-bill entry.

Live baseline reviewed:
- `AdminPurchasePageController::storeOsl()` was single-line.
- `PurchaseService::saveOsl()` was single-line.
- `purchases/osl-create.blade.php` was single-line.
- Existing schema already supports normalized purchase rows and purchase batches.

FIX24:
- shared Vendor / Purchase Date / Supplier Invoice header;
- multi-line OSL entry (up to 50);
- per-line Job Card / Vehicle;
- OSL Master or manual description;
- Qty / Vendor Rate / Discount / Purchase GST;
- Customer Rate / Customer GST;
- line totals + overall bill totals;
- Add/Remove Line;
- `saveOslBatch()` transaction wrapper reusing existing `saveOsl()` business rules;
- `purchase_batch_no` + `purchase_line_no` stored on OSL rows;
- `workshop_purchase_batches` OSL header created from actual line totals;
- no DB migration.

Package SHA-256:
`6ada0efeef21579f3d2157929acaedd6d086fbc9644fe45dcd4b443b46e48e1a`

Validation:
- controller PHP syntax PASS
- service PHP syntax PASS
- embedded JS syntax PASS after Blade placeholder substitution
- CHECK/APPLY/VERIFY/ROLLBACK synthetic smoke PASS
