# AM217 — OSL Purchase → Vendor Payment → Ledger (STAGING read-only audit)

**Reason:** An OSL supplier purchase is not selectable as a payable against-reference in Payment Voucher; the supplier may be missing from the Vendor dropdown, and OSL Purchase View lacks a safe correction path. Prior AM137 adds links, **not** a finance posting fix. AM157 displays the linked vendor only.

**Affected example (supply privately during audit):** Purchase `OB-261030184631-517`, vendor SAHERA ENGINEERING WORKSHOP, reported due ₹400. Do not edit, pay or reverse that row during preflight.

## Audit

```bash
cd ~/assam-motors-deploy
git fetch origin
git show origin/fix/am217-osl-vendor-payment-audit-20261030:deployment/live-fixes/AM217_OSL_VENDOR_PAYMENT_AUDIT/audit.py > /tmp/am217-osl-audit.py
python3 -m py_compile /tmp/am217-osl-audit.py
ROOT="$HOME/domains/assammotors.com/assam-erp-staging"
python3 /tmp/am217-osl-audit.py "$ROOT" 'OB-261030184631-517' 'SAHERA ENGINEERING WORKSHOP' > "$HOME/am217-osl-audit-private.txt"
```

**Privacy:** The targeted audit file stays on the Hostinger user's account; do not publish ERP customer rows, credentials, vendor IDs, private data, or complete purchase records to public GitHub. This script emits only table column names, route/controller names, existence checks and capped counts. The GitHub Actions smoke runs **structure-only**, without the purchase identifier.

## Required implementation gates

1. Locate exact supplier Vendor Master identity, check vendor is eligible for payee picker. If the purchase supplier exists by name but lacks a valid vendor_id, **stop for an audited link-repair procedure**, never silently create a duplicate.
2. Verify whether OSL Purchase is represented as purchase header, batch, or linked line and what uniquely identifies a payable source; validate original outstanding and any prior allocations.
3. Payment Voucher vendor select must use the same authorized Vendor Master ID and permit selecting the exact unpaid OSL source, with due/remaining amount from server-side ledger.
4. Allow full or partial allocation without overpayment; lock payable reference and voucher transaction; prevent duplicate submission; make voucher and vendor ledger atomic and idempotent.
5. OSL Purchase Edit must preserve all existing purchase lines. Change quantity, rate, GST, discount and supplier invoice only via an authorized, audited flow. Posted/paid purchases require reversal or delta adjustment consistent with existing journal rules. No raw overwrites of posted financial history.
6. Check ₹400 initial due, partial payment, remaining due, edit-before-payment, edit-after-payment guard and vendor outstanding against voucher allocations, **only in controlled STAGING testing**.

## State

This PR is a **read-only preflight tool**, not a payment/edit implementation or a deployed finance fix. Do not claim the supplier dropdown, payables, edit screen or live ledger is corrected until source/schema audit and transactional tests pass.
