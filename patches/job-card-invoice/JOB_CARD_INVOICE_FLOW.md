# Job Card → Invoice Exact Flow

## 1. Pre-conversion status gates

Convert button should appear only when:
- Job Card status = CLOSED;
- Job Card is not already invoiced;
- no running/paused-required ROT remains unresolved;
- final server totals are available.

If not eligible, show the blocking reason instead of a dead button.

## 2. Final validation

Before conversion, server locks the Job Card row and re-checks:

- current status is CLOSED;
- invoice_id / invoice_no is still NULL;
- customer still exists or required customer snapshot fields are available;
- vehicle snapshot fields are available;
- all active ROTs satisfy closure rules;
- Parts rows are valid;
- Labour rows are valid;
- tax configuration is valid;
- final totals recalculate successfully.

Browser totals are ignored.

## 3. Final server calculation

Recommended totals:

```
parts_taxable
parts_tax
parts_total

labour_taxable
labour_tax
labour_total

subtotal
discount_total
tax_total
round_off
grand_total
```

Use the live ERP's actual tax model.

## 4. Immutable Invoice snapshot

The Invoice must preserve, at conversion time:

### Header
- Invoice No.
- Invoice Date/Time
- Job Card ID / No.
- Customer ID + Customer Name/Mobile/Address snapshot
- Vehicle ID + Registration/VIN snapshot
- Odometer
- Service Advisor
- complaint/reference if required
- totals/tax summary
- created/converted by

### Parts snapshot
- Part ID
- Part Code
- Part Name/Description
- Qty
- Rate
- Discount
- Tax
- Amount

### Labour snapshot
- Labour ID
- Labour Code
- Labour Description
- Qty
- Rate
- Discount/Tax when applicable
- Amount

Technician/ROT performance may remain Job Card history and does not need to be customer-facing invoice detail unless business requires it.

## 5. Atomic conversion transaction

1. begin DB transaction;
2. SELECT Job Card FOR UPDATE;
3. reject if already invoiced;
4. validate CLOSED state;
5. recalculate Job Card totals;
6. allocate Invoice No. using existing locked sequence;
7. create Invoice header;
8. copy Parts snapshot;
9. copy Labour snapshot;
10. store tax/total snapshot;
11. link Invoice ↔ Job Card;
12. set Job Card status = INVOICED;
13. write INVOICE_CONVERT audit;
14. commit.

If any step fails:
- rollback everything;
- Job Card remains CLOSED;
- no partial Invoice remains.

## 6. Duplicate prevention

Protect at multiple layers:

- transaction row lock on Job Card;
- server check for existing invoice_id/invoice_no;
- unique database relation/index where live schema supports it;
- POST button disabled after submit for user convenience;
- if duplicate request arrives after successful conversion, return the existing Invoice instead of creating a second one.

## 7. Routes

Suggested behaviour:

- POST `/erp/job-cards/{id}/convert-invoice`
- GET `/erp/invoices/{invoice}`
- GET `/erp/invoices/{invoice}/print`

Existing route names may be retained.

## 8. After conversion

Job Card:
- status shows INVOICED;
- Edit becomes View;
- Parts/Labour mutation blocked;
- Convert button hidden;
- Invoice No. displayed and linked.

Invoice:
- View Invoice
- Print Invoice
- existing payment/accounting actions where applicable
