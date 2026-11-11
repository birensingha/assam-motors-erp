# AM213 — OSL Purchase Edit + Vendor Payment linkage (STAGING QA)

## Incident reported by owner, 30 Oct 2026

- OSL Purchase **OB-261030184631-517**
- Vendor **SAHERA ENGINEERING WORKSHOP (DAKHINGAON)**
- Supplier Invoice **SEW/30102026/001**
- Job Card **JC-2026-0011**
- OSL Item **BUSHING R&R**
- Supplier total **₹400.00** (from owner screenshot)
- Current Purchase View has **Back** and **Print View**, but no Edit Purchase.
- Vendor not visible in Payment Voucher dropdown; payment cannot be entered.

**No actual STAGING vendor IDs, payable amount after prior payments, or ledger
allocations have been verified. Do not manually insert voucher or alter Vendor
Master / purchase records based on the screenshot.**

## Confirmed earlier repository capabilities

- Existing route \`erp.fix48.osl-edit\`: edits supplier OSL purchase line.
- Existing route \`erp.payments.create\`: Payment Voucher form.
- Existing route \`erp.vendors.ledger\`: vendor ledger.
- Existing earlier AM137 OSL Labour editor links to payment create using
  \`source=OSL_JOB&id=<workshop_purchases.id>\`. **The current Payment service
  must verify source-kind handling; a UI link alone cannot prove POST support.**
- Existing purchase schema supports \`workshop_purchases\`,
  \`workshop_purchase_batches\`, and \`workshop_vendors\` with potential
  \`vendor_id\` and \`supplier_name\` distinctions.
- Previous FIX20B corrected MariaDB \`ONLY_FULL_GROUP_BY\` in
  \`ExpensePaymentService::payableSources()\` for Parts Purchases, using
  \`COALESCE(NULLIF(p.purchase_batch_no,''),p.purchase_no)\`.
  We must audit OSL source selection separately, not change SQL mode.

## Deliverables in QA branch

1. \`audit.py\`: **read-only STAGING diagnostic** identifying:
   - Actual Purchase View/controller/routes, supplier edit and voucher links
   - Exact purchase rows/batch and their canonical vendor_id
   - Vendor Master match and possible mismatch
   - Payable-source and voucher schema
   - Existing payment vouchers for purchase references, to guard against
     duplicate payouts
   - Actual Purchase View Blade anchor and variables for safe UI placement.
2. \`osl-purchase-actions.blade.php\`: **guarded reusable template**, not yet
   installed: Edit OSL Purchase, Vendor Ledger and Make Payment (Against
   Reference). Payment link is blocked if canonical vendor linkage is
   missing/mismatched; Edit remains available to correct it. Multi-line OSL
   bills are not silently treated as a single payable source.

## Read-only audit command: DO NOT copy repo branches over live STAGING

The code is deliberately in a GitHub feature branch until source audit
passes. From Hostinger SSH:

\`\`\`bash
cd ~/assam-motors-deploy &&
git fetch origin feature/am213-osl-purchase-payment-edit-qa &&
git show FETCH_HEAD:deployment/live-fixes/AM213_OSL_PAYMENT_EDIT/audit.py > /tmp/am213-osl-audit.py &&
python3 -m py_compile /tmp/am213-osl-audit.py &&
python3 /tmp/am213-osl-audit.py "$HOME/domains/assammotors.com/assam-erp-staging" \
    --purchase-no OB-261030184631-517
\`\`\`

This command checks source/DB rows and route metadata, and does **not**
perform financial, code or table writes. Please review output before sharing
if any other private customer/vendor details appeared unexpectedly.

## Next implementation once audit is available

- **Supplier identity:** Use \`workshop_vendors.id\` as canonical Vendor ID;
  never substitute supplier_name text for a missing ID or guess vendor by
  approximate name. If one exact Vendor Master matches, prepare an audited
  correction with uniqueness, status, existing-payment and reference locks.
- **Payment Voucher:** Extend the existing \`ExpensePaymentService\` payable
  source selection so eligible *unpaid OSL Purchase* appears once as a valid
  Against Reference option. Filter by canonical Vendor ID and active status.
  Compute outstanding from existing ledger/allocations, not from purchase
  total alone. Guard duplicate submit, overpayment, and vendor/source
  mismatch; transaction and audit in current service.
- **Purchase View:** Put Edit Purchase / Make Payment / Vendor Ledger beside
  existing Print View or in the same compact actions row; retain existing
  purchase edit authorization and blocking rules. Include multi-line batch
  safety and preserve prior purchase_no, supplier invoice and JC links.
- **Edit correction:** Existing OSL editor must preserve all lines in same
  purchase, re-evaluate GST inclusive/exclusive, recalculations, reason and
  immutable ledger history. Already-paid bills require audited reversal or
  adjustment, never silent amount overwrite.
- **QA:** Unpaid ₹400 bill, partially paid, fully paid, same vendor with
  multiple bills, other vendor, deleted/void purchase, multi-line batch,
  supplier mismatch, and payment rollback. Verify supplier ledger balance
  before/after. Never post a live ₹400 test voucher.

## Status

**Source audit + guarded UI partial committed to feature branch. Not deployed.
No PAYMENT or purchase code has been changed on STAGING.**
