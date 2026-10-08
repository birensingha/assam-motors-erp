# Create Job Card — Acceptance Checklist

## Customer / Vehicle

- [ ] Customer search by Name works.
- [ ] Customer search by Mobile works.
- [ ] Customer ID/Code search works.
- [ ] Linked Vehicle list updates after Customer selection.
- [ ] Vehicle Registration search works.
- [ ] Server rejects Vehicle that does not belong to selected Customer unless authorised correction workflow exists.

## Header

- [ ] Job Card No. generated server-side.
- [ ] Job Card No. cannot be edited by browser.
- [ ] Customer Complaint required.
- [ ] Odometer cannot be negative.
- [ ] Promised delivery accepts date/time.
- [ ] Created By comes from authenticated Admin/User.

## Parts

- [ ] Parts section is above Labour.
- [ ] Direct Part add works without Estimate.
- [ ] Part Code/Name autocomplete works.
- [ ] 10+ Part rows do not mix selected IDs.
- [ ] Qty > 0 enforced.
- [ ] Rate/discount/tax recalculated server-side.
- [ ] Browser amount manipulation does not change final server amount.

## Labour / ROT

- [ ] Labour section is below Parts.
- [ ] Labour is column-wise.
- [ ] Direct Labour add works without Estimate.
- [ ] Technician can be assigned per Labour row.
- [ ] Standard Hours comes from Labour/ROT Master where available.
- [ ] ROT assignment is created only for valid Technician/Labour relation.
- [ ] Multiple Labour rows save independently.

## Save / Transaction

- [ ] Save creates one Job Card only.
- [ ] Double-click/double-submit cannot create duplicate Job Cards.
- [ ] Child line failure rolls back the create transaction.
- [ ] Initial status is OPEN or supported DRAFT.
- [ ] Save & Start Work uses normal workflow transition to WIP.
- [ ] JOB_CARD_CREATE audit is written.
- [ ] Created Job Card opens in Edit using the same Parts/Labour layout.

## Workflow

- [ ] Create cannot jump directly to Invoice.
- [ ] QC → Ready → Close → Invoice sequence remains unchanged.
- [ ] Invoiced Job Card is locked.
