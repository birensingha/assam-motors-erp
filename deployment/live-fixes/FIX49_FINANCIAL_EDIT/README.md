# ASSAM MOTORS — FIX49 Job Card + OSL + Vendor + Payment Voucher Edit Controls

**Delivery type:** guarded staging integration candidate, NOT yet installed on the live Hostinger staging application. It has been code-linted and mock route-patch-tested but has NOT completed server/schema, browser, payment or customer-ledger acceptance testing.

**Target**: `staging.assammotors.com/erp/` (NEW ERP). **Do not apply on OLD ERP or production.**

## 4 requested features

1. **Direct Job Card Amount Editing:** `GET /erp/workshop-tools/job-cards/{id}/billing-edit`. Appears as an **Edit Job Card Amount / GST** button on the Job Card Summary. Shows Parts above Labour/ROT, editable quantity/standard-hours pricing quantity, taxable unit rate, discount %, GST %, GST preview, final preview, mandatory reason. Save recalculates job header totals and writes `job_card_change_audits`. It does not change actual ROT session time, stock issue, or invoices. For Closed (not invoiced), Admin price correction is allowed. For linked issued invoices or Cancelled Job Cards, editing is blocked until audited Invoice/ledger revision is integrated.
2. **OSL Purchase Edit:** `GET /erp/workshop-tools/osl/register`. Search recent OSL purchase lines and edit supplier quantity, vendor rate, discount, GST with mandatory reason. Recalculates OSL purchase rows and known batch totals in one transaction; writes existing Job Card change audit (for JC-linked OSL). **Does not silently edit customer billable OSL rate, Job Card Labour/ROT, paid supplier invoices or customer ledger.** Closed/Invoiced Job Card, paid/posted Purchase and schema-unknown cases are blocked pending exact PurchaseService mapping. This is an important **partial implementation**, not unrestricted past OSL editing.
3. **Vendor Database:** `GET /erp/workshop-tools/vendor-database`. Customer-style searchable vendor cards for profile, GSTIN/phone, purchase aggregates and vendor payments; links to existing Vendor Master and Vendor Ledger. Financial overview labelled provisional; the authoritative outstanding remains Vendor Ledger.

4. **Payment Voucher Editing:** Payment Voucher Register link → historical voucher search → Edit. Date, Amount, Payment Mode, Cash/Bank Account, Payment Reference and Narration fields are editable for active vouchers, with mandatory reason. Original Vendor/Against allocation is immutable in this edit; moving a posted payment to another vendor/purchase requires audited reversal and reissue. `workshop_payment_edit_audits` preserves old/new values, user and reason. Voucher amount is validated against authoritative `ExpensePaymentService::payableSources` and corrected purchase-batch round-off. INSTALL IS BLOCKED unless the read-only accounting preflight confirms no parallel payment journal would be left stale. This patch does NOT automatically repair historic finance records.

## Install protection

- All new pages and POSTs require authenticated, active ERP Admin (`ErpAuthUser::isAdmin()`), in addition to insertion inside the existing protected ERP route group.
- POSTs have CSRF token, optimistic old-amount comparison, validation, locked transaction and audit.
- Existing customer ledger, invoices, inventory movements and technician ROT timers are NEVER updated by FIX48.
- All installer route/view transformations are guarded against missing/non-unique anchors.
- APPLY backs up `routes/web.php` and Job Card summary, rolls back modified files on failures.
- One additive migration creates append-only workshop_payment_edit_audits. No backfill, no automatic or silent historical bill changes.

## Source prerequisite

Package expects FIX46 lifecycle and P9 payment settlement already integrated; its route line and view include are used as strict anchors. It targets the staging Laravel 13 application tree. **Because the real current Hostinger application source and schema are not accessible in this chat, `CHECK.sh` MUST be run before any staging install.** A success on local mock cannot guarantee the live financial business rules.

## Safe operator sequence (staging only, do not run blindly)

```bash
bash INSPECT.sh /path/to/staging/laravel
bash CHECK.sh /path/to/staging/laravel
# Read the inspect output and confirm schema and payment/ledger rules first.
bash APPLY.sh /path/to/staging/laravel
bash VERIFY.sh /path/to/staging/laravel
```

**No terminal preference:** the existing GitHub Actions "Assam Motors Staging SSH Control" workflow accepts `check_fix` / `inspect_fix` / `apply_fix`, but only for a FIX directory already committed to the `assam-motors-erp` repository main branch. This downloadable ZIP is **not** automatically there. A trusted operator must first review and integrate it. It is *not* a live deployment.

## Must-pass acceptance tests before everyday use

- Log in Admin; verify page links, all card lists, search and load, no `403` / `500`.
- Add/edit Parts and Labour on an OPEN test JC. Check the per-line calculation and final totals.
- Confirm ROT actual time, stock issue quantities and staff assignments unchanged.
- Test Closed JC without invoice (audit and recalculation); issued/invoiced JC editing must block.
- Test an unposted OSL purchase, check batch header, vendor ledger source, linked Job Card/OSL labour and supplier payments.
- Paid/posted OSL and invoiced JC must block rather than silently change posted accounting.
- Vendor Master search, Vendor Ledger links, multiple vehicles/vendor contacts from test data, pagination.
- Payment Register displays historical vouchers and Edit links; tested on Cash/Bank and Against Reference.
- Edit ₹5000→₹4000 on an unpaid test voucher, confirm Vendor Ledger/outstanding updates by exactly ₹1000 and no duplicate postings.
- Edit voucher Date/Mode/Account/Narration then verify finance reports reflect the same posting.
- Attempt duplicate submit, stale edit, voided voucher and overpayment; all must be rejected safely.
- Vendor/Against source change is refused until authorised reversal/reissue.
- Run existing FIX46 read-only invoice/ledger audit before and after.
- Confirm both `Print Tax Invoice` and `Customer Cost Summary` still use one authoritative invoice.

## Known limitations and next integration

The **historical issued-invoice correction** and **posted/paid OSL financial revision** need the actual present-day `AdminPurchasePageController`, `PurchaseService`, `AdminJobCardPageController`, `VendorService`, `routes/web.php` and relevant schema. Without them, enabling direct invoice or vendor ledger rewrites would risk breaking already recorded payments, supplier bills, customer balances, or GST data. The patch deliberately refuses such writes.

**Status: CODE PREPARED — source checks run locally. NO live staging install or authenticated finance-browser QA has been performed.**

## FIX49 extra read-only protection

`PREFLIGHT_PAYMENTS.php` verifies Payment Voucher schema, VendorService's voucher-derived ledger, existing P9 source types, and scans for obvious materialized payment journal links. Any unknown posting dependency must stop deployment. After staged installation (with backup) run the newly created payment register and audit migration, and test posted-voucher ledger reconciliation on isolated test records. The installer will never mass-edit existing vouchers.
## 2026-10-16 staging validation note

FIX49 was installed on Hostinger staging in Actions run 38783968055. The read-only detail QA (38784216344) and enterprise UI deployment/browser QA (38785782719) succeeded. This does not prove financial POST or ledger reconciliation. The first-install CHECK.sh intentionally fails once installed and is not a post-install health check. Older detail QA title expectations are outdated after the premium UI rename. Financial Save and issued-invoice revisions remain unverified. The OSL batch record_status guard correction is prepared in the source branch but not deployed to staging.
