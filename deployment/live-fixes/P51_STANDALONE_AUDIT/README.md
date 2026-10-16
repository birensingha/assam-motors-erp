# P51 — Independent NEW ERP + NEW Admin, Legacy preserved

## Business requirement
The NEW system must not call OLD ERP/OLD Admin for daily workshop work. NEW ERP and NEW Admin require distinct route ownership/navigation; OLD ERP and OLD Admin remain independently accessible for history/recovery until a signed-off cutover. Do not overwrite their databases or rewrite their URLs.

## Verified staging audit (16 October 2026)
- 263 routes; 146 prefixed with `erp/`; **zero** separate `admin/` routes before P51.
- Authenticated protected staging ERP uses `web + auth + EnsureErpAdmin`, with an active `ErpAuthUser` admin and separate staff roles.
- 102 staging tables; native Customer, Vehicle, Parts, Vendor, Job Card, Estimate, Invoice, Stock, Payment and HR-related schemas already exist (record presence is **not migration completeness**).
- `public/legacy` exists within Laravel; six staging application source files still reference legacy paths/APIs.
- FIX40 explicitly confirms `legacy/workshop/customers.php?action=...` remains required by legacy Workshop JavaScript. Blind redirects or deletion break features.

## Phase 1 source-only
- NEW protected Admin Console at `/erp/admin`, distinct Blade layout `layouts.new-admin`, role-gated by existing ERP admin middleware and explicit active-admin check.
- NEW ERP at `/erp/dashboard` remains intact. A visible NEW Admin switch is added to its native sidebar.
- OLD legacy links removed **from the new sidebar only**, no changes to their actual files or databases.
- No financial writes, schema changes, user migration, authentication bypass, or legacy service deletion.

## Phase 2 (NOT implemented by Phase 1)
1. Inventory OLD PHP modules, endpoints, table schemas, record counts, primary/foreign keys, attachments, and status/role taxonomy. Identify production vs staging legacy copies.
2. Maintain `migration_source_map` with immutable OLD source ID → NEW entity ID, unique per source and entity; build idempotent dry-run import. Preserve old IDs in mapped columns rather than overwriting staging native IDs.
3. Migration order: Customer → Vehicle → Parts / Labour / ROT / Vendor Masters → Staff / Accounts / Roles → Historical JC / Inspection / Diagnosis / Estimates → Parts Issues / Inventory Movements / OSL / Purchases → Invoices / Credit Notes / Receipts / Vouchers → Ledger / Tax Documents → Images / Documents / Notifications.
4. Each module: source backup & hash → read-only mapping preview → isolated import sandbox → conflict review → FK/orphan/duplicate checks → signed reconciliation → incremental cutover.
5. Money: compare per-customer/vendor opening balances, outstanding receivables/payables, line-item GST inclusive/exclusive semantics, invoice totals, stock-on-hand/valuation. Preserve issued document numbers and audit trail; no double posting.
6. Replace all `legacy/` frontend URLs and `customers.php?action` APIs with authenticated Laravel endpoints, keeping compatibility response shapes until consumers are migrated.
7. New Admin/ERP access control: independent UI, explicit role permissions, central server-side authorization for every action, authenticated session transitions; HR and Staff permissions not inherited automatically.
8. Verified zero NEW→OLD calls under browser/API instrumentation before final operational switch. Only then freeze OLD writes and operate OLD as read-only historical archive; **do not delete OLD**.

## Stop conditions
No live data import or old shutdown until source ownership, backups, source-to-target mapping and financial reconciliation are demonstrably correct. Keep both systems available during migration. Avoid dual active posting of money/stock.

## Outcome evidence
Use source-only staging deployment and authenticated GET-only browser tests. A PASS of the Admin Console does not mean Legacy database/content migration has happened.
