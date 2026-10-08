# Assam Motors — Create Job Card Exact Flow

## 1. Open Create Job Card

Recommended route:

`GET /erp/job-cards/create`

The screen must open as a full Workshop form, not a tiny modal.

## 2. Customer / Vehicle

Customer search:
- Customer Name
- Mobile
- Customer ID/Code

Vehicle search:
- Registration No.
- VIN/Chassis when available
- Customer-linked vehicles first

After Customer selection:
- show linked vehicles;
- allow Vehicle selection;
- show Customer + Vehicle summary above the Job Card body.

Do not trust only the browser relation. On Save, server verifies the Vehicle belongs to the selected Customer.

## 3. Header

Fields:

- Customer
- Vehicle
- Odometer / KM
- Service Advisor
- Customer Complaint
- Workshop Instruction / Notes
- Promised Delivery Date/Time
- Optional source/reference such as Estimate No. when used

Server-generated/read-only after Save:

- Job Card No.
- Created At
- Created By
- Initial Workflow Status

## 4. Parts — TOP SECTION

Columns:

| Part Code | Part Name | Qty | Rate | Discount | Tax | Amount | Source | Action |
|---|---|---:|---:|---:|---:|---:|---|---|

Actions:
- + Add Part
- Part Code / Name autocomplete
- multiple rows
- remove unsaved row

Source:
- MANUAL
- ESTIMATE
- PURCHASE/ALLOCATION where applicable

Estimate is not required.

## 5. Labour / ROT — BELOW PARTS

Columns:

| Labour Code | Labour Description | Technician | Std Hrs | Qty | Rate | Amount | ROT | Action |
|---|---|---|---:|---:|---:|---:|---|---|

Actions:
- + Add Labour
- Labour Master search
- assign Technician
- create/attach ROT
- remove unsaved row

Labour must be column-wise, never one raw paragraph.

## 6. Totals

Display:

- Parts Subtotal
- Labour Subtotal
- Discount
- Tax
- Job Card Total

Client preview is convenience only.

**Server calculation is final.**

## 7. Save buttons

Recommended:

- Save Job Card
- Save & Start Work
- Cancel

### Save Job Card

Creates Job Card with initial `OPEN` or supported `DRAFT` state.

### Save & Start Work

Creates the Job Card, then transitions through the normal workflow to `WIP / IN PROGRESS` only if server rules allow it.

Do not create a hidden/direct state jump that Edit workflow cannot reproduce.

## 8. Transaction

Recommended transaction:

1. validate Customer;
2. validate Vehicle;
3. verify Customer ↔ Vehicle ownership;
4. validate header;
5. allocate next Job Card No. using existing server sequence;
6. insert Job Card header;
7. validate/calculate Parts;
8. insert Parts;
9. validate/calculate Labour;
10. create Technician/ROT assignments where selected;
11. calculate server totals;
12. write `JOB_CARD_CREATE` audit;
13. write Part/Labour/Technician audit rows where appropriate;
14. commit.

Any failure:
- rollback the transaction;
- return validation/business error;
- never silently create partial child lines.

## 9. Workflow after Create

```
OPEN / DRAFT
   ↓
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

Create Job Card must enter this same workflow. It must not introduce a shortcut directly to Invoice.
