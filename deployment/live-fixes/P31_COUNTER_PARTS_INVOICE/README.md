# P31 — Direct Parts Sale Invoice (without opening Job Card)

## Request
Parts counter sales should be directly invoiced without a workshop Job Card. This module is separate from the canonical Work Complete → QC → Ready for Delivery → Close → Invoice job-card workflow.

## STAGING routes
- `GET /erp/counter-sales` — Counter Parts Invoice Register
- `GET /erp/counter-sales/create` — Purchase-style 360px multiline Counter Parts Invoice form
- `POST /erp/counter-sales` — create and post invoice from existing Customer Master & Parts Master, admin only
- `GET /erp/invoices/{invoice}` and `/erp/invoices/{invoice}/print` — existing document routes delegate to direct invoice-specific immutable line views where a direct_invoice_requests link exists
- Purchase Register includes a shortcut for Direct Parts Sale Invoice

## Database contracts
`invoices.job_card_id` is already nullable. The additive migration adds:
- `direct_invoice_requests` for deduplication via unique UUID request key and link to existing Invoice ID
- `direct_invoice_lines` immutable parts snapshots (qty, master ID, rate, discount, GST, stock before/after)

## Transaction requirements
A single DB transaction must lock the request idempotency row and each Parts Master stock row; reject untracked/insufficient inventory; server-recalculate discounts, GST and round-off; insert an invoice with `job_card_id=NULL`, write one line snapshot and inventory out-movement for every sold item, decrement stock and insert one linked Customer Ledger debit. No automatic money receipt is posted; collect payment through existing payment/receipt module against that invoice. The initial implementation rejects duplicate parts in the same submission and rejects zero-priced sales.

## Deployment safety
- No automatic financial transaction POST is issued during automated staging acceptance.
- First deployment run **38554172810** failed real form GET due to a non-existent `status` column in canonical `am_customer_master`; original app source was automatically rolled back, without invoice/stock/ledger writes.
- A read-only schema debug run **38554325073** identified the mismatch. The customer selector query was corrected and the guarded installer made idempotent for the identical already-present additive migration.
- Preflight **38554415350** PASS.
- Corrected staging deployment **38554492761** SUCCESS:
  - `P31_COUNTER_SALE_CREATE_AND_REGISTER_GET_RENDER_PASS`
  - `P31_INVOICE_SHOW_AND_PRINT_RENDER_PASS`
  - `P31_ADMIN_PROTECTED_ROUTES_AND_NULL_JC_PASS`
  - `P31_NO_FINANCIAL_STOCK_INVOICE_OR_LEDGER_POST_EXECUTED`
  - `P31_STAGING_COUNTER_PARTS_INVOICE_DEPLOY_PASS`
- Verified staging source backup: `storage/app/p31_counter_sale_backup_20261014_163906_2746920`.

## Outstanding acceptance and compliance
- **Real invoice POST has not yet been tested**. On STAGING, use a controlled test customer and item, carefully inspect post results for 1 invoice, 1 customer ledger debit, stock decrease and 1 immutable line snapshot; do not submit a customer-facing live invoice before acceptance.
- Payment/receipt allocation from the existing module should be separately verified.
- Legal invoice/T&C/registered address/GSTIN/tax compliance (including interstate IGST vs intrastate CGST/SGST) need to be finalized for actual production usage. The source uses Parts Master GST% totals and has not implemented all jurisdiction-specific invoicing.
- Cancellation, sales returns and credit notes require an audited stock/ledger reversal workflow; **never hard-delete posted invoices or inventory movement records**.
- Do not merge blindly into the old `main` branch: staging deploy is via guarded incremental patches.
