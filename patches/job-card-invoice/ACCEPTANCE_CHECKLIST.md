# Job Card → Invoice Acceptance Checklist

## Gate

- [ ] WIP Job Card cannot convert.
- [ ] QC Pending cannot convert.
- [ ] QC Passed cannot convert directly.
- [ ] Ready for Delivery cannot convert directly.
- [ ] Only CLOSED Job Card can convert.
- [ ] Running/unresolved ROT blocks closure/conversion according to business rules.

## Conversion

- [ ] Server locks Job Card during conversion.
- [ ] Server recalculates all final totals.
- [ ] Invoice number generated server-side.
- [ ] Customer snapshot saved.
- [ ] Vehicle snapshot saved.
- [ ] Parts snapshot saved.
- [ ] Labour snapshot saved.
- [ ] Tax/totals snapshot saved.
- [ ] Job Card linked to Invoice.
- [ ] Job Card status becomes INVOICED.
- [ ] INVOICE_CONVERT audit written.
- [ ] Any failure rolls back entire conversion.

## Duplicate prevention

- [ ] Double-click Convert creates one Invoice only.
- [ ] Browser retry creates one Invoice only.
- [ ] Concurrent requests create one Invoice only.
- [ ] Retried request after success redirects to existing Invoice.
- [ ] Existing invoice relation is unique where live schema supports it.

## Post-conversion

- [ ] Job Card normal Edit blocked.
- [ ] Convert button hidden.
- [ ] Invoice No. visible on Job Card.
- [ ] View Invoice opens correct Invoice.
- [ ] Invoice values do not change when Part/Labour Master changes later.

## Print

- [ ] Print page has no sidebar/navigation.
- [ ] Parts print above Labour.
- [ ] Customer/Vehicle/Job Card/Invoice references print.
- [ ] Tax breakup and Grand Total print.
- [ ] A4 browser/PDF print is readable.
- [ ] Print uses Invoice snapshot, not live Job Card data.
