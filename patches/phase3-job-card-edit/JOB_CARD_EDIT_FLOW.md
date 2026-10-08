# Assam Motors — Job Card Edit Flow

## 1. Job Card list

Every row must expose:
- View
- **Edit**
- Print / Preview
- Workflow status

Edit opens the same Job Card ID; it must never create a duplicate Job Card.

## 2. Header

Editable while Job Card is not invoiced:
- Customer
- Vehicle
- Odometer
- Service advisor
- Customer complaint
- Workshop instruction / notes
- Promised delivery date/time

Read-only:
- Job Card No.
- Created at
- Created by
- Invoice No. once converted

## 3. Parts — TOP SECTION

Table columns:
| Part Code | Part Name | Qty | Rate | Discount | Tax | Amount | Source | Action |
|---|---|---:|---:|---:|---:|---:|---|---|

Actions:
- + Add Part
- Search Part Master by code/name
- Add multiple rows
- Delete unsaved row
- Remove saved row with audit

Source values:
- ESTIMATE
- MANUAL
- PURCHASE/ALLOCATION

**Estimate is optional.** Admin can add Parts directly to the Job Card.

## 4. Labour / ROT — BELOW PARTS

Labour must be column-wise, not a paragraph/raw row dump.

| Labour Code | Labour Description | Technician | Std Hrs | Qty | Rate | Amount | ROT Status | Action |
|---|---|---|---:|---:|---:|---:|---|---|

Actions:
- + Add Labour
- Search Labour Master
- Assign / change technician
- Create/attach ROT
- Remove line before closure, with audit

**Estimate is optional.** Labour may be added directly.

## 5. Save behaviour

Buttons:
- Save Changes
- Save & Continue
- Cancel
- Preview Job Card

Update must run in one DB transaction:
1. lock current Job Card row;
2. validate workflow status;
3. update header;
4. upsert Parts;
5. upsert Labour;
6. recalculate totals server-side;
7. write audit records;
8. commit.

Server calculation is authoritative.

## 6. Workflow

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

### QC PENDING → QC PASSED
Requires:
- QC staff/user
- QC date/time
- checklist/result
- optional note

### QC PASSED → READY FOR DELIVERY
Requires:
- all active ROT completed or deliberately closed by authorised Admin;
- no unresolved mandatory workshop action.

### READY FOR DELIVERY → JOB CARD CLOSED
Requires:
- final Parts/Labour totals calculated;
- no running ROT;
- closure confirmation.

### JOB CARD CLOSED → CONVERT TO INVOICE
This is **systematic, not direct from WIP**.
Invoice conversion copies the final server-calculated Job Card Parts/Labour snapshot.

## 7. Locking

Once INVOICED:
- normal Edit button becomes View;
- Parts/Labour/header changes are blocked;
- invoice conversion cannot run twice.

Closed but not invoiced:
- only authorised Admin may Reopen;
- mandatory reopen reason;
- audit event required.

## 8. API contract

Suggested native endpoints/routes:

- GET `/erp/job-cards/{id}/edit`
- PUT/PATCH `/erp/job-cards/{id}`
- POST `/erp/job-cards/{id}/parts`
- DELETE `/erp/job-cards/{id}/parts/{lineId}`
- POST `/erp/job-cards/{id}/labour`
- DELETE `/erp/job-cards/{id}/labour/{lineId}`
- POST `/erp/job-cards/{id}/workflow`
- POST `/erp/job-cards/{id}/reopen`
- POST `/erp/job-cards/{id}/convert-invoice`

Existing route names may be retained; these are behavioural contracts, not a forced framework rename.

## 9. Audit events

At minimum:
- JOB_CARD_EDIT
- PART_ADD
- PART_UPDATE
- PART_REMOVE
- LABOUR_ADD
- LABOUR_UPDATE
- LABOUR_REMOVE
- TECHNICIAN_ASSIGN
- STATUS_CHANGE
- QC_PASS
- READY_FOR_DELIVERY
- JOB_CARD_CLOSE
- JOB_CARD_REOPEN
- INVOICE_CONVERT
