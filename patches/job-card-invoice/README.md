# Job Card → Invoice Conversion

This package defines the systematic Workshop conversion from a completed Job Card to an Invoice.

## Required lifecycle

```
WIP / IN PROGRESS
  ↓
QC PENDING
  ↓
QC PASSED
  ↓
READY FOR DELIVERY
  ↓
JOB CARD CLOSED
  ↓
CONVERT TO INVOICE
  ↓
INVOICED
```

Invoice conversion is not allowed directly from WIP, QC Pending or Ready for Delivery.

## Core rules

- Job Card must be CLOSED.
- No active/running ROT may remain.
- Final Parts/Labour totals are recalculated server-side before conversion.
- One Job Card may create only one final Invoice.
- Conversion must be idempotent.
- Invoice number is generated server-side from the existing invoice sequence.
- Invoice stores an immutable snapshot of Job Card header, Parts, Labour, discounts, taxes and totals.
- After conversion, normal Job Card editing is locked.
- View Invoice and Print Invoice read from the Invoice snapshot, not from mutable Job Card lines.

See:
- `JOB_CARD_INVOICE_FLOW.md`
- `JobCardInvoiceService.php.example`
- `invoice_view_reference.html`
- `INVOICE_PRINT_RULES.md`
- `ACCEPTANCE_CHECKLIST.md`
