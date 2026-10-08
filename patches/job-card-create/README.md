# Job Card Create — Workshop Native Flow

This package defines the Create Job Card flow that must match the existing Job Card Edit contract.

## Screen order

1. Customer / Vehicle / Job Card header
2. Customer Complaint / Workshop Notes
3. **Parts — TOP**
4. **Labour / ROT — BELOW**
5. Totals / Save actions

Estimate is optional.

Admin can create a Job Card and directly add Parts and Labour without first creating an Estimate.

## Initial status

Recommended initial status:

- `OPEN` for a Job Card that is immediately active; or
- `DRAFT` if the live ERP already supports explicit draft saving.

Do not invent a third create-only status.

## Key rules

- Job Card number is generated server-side.
- Customer and Vehicle are selected from authoritative masters.
- Vehicle must belong to the selected Customer unless an authorised correction workflow exists.
- Parts and Labour line items are optional at initial save but supported directly on Create.
- Labour rows are column-wise.
- Technician/ROT assignment may be done on create.
- Totals are recalculated server-side.
- Create + Parts + Labour + ROT assignment must commit atomically where the live schema permits.
- A failed child line must not leave a half-created Job Card without explicit recovery rules.

See:
- `JOB_CARD_CREATE_FLOW.md`
- `JobCardController.create_store.php.example`
- `job_card_create_reference.html`
- `ACCEPTANCE_CHECKLIST.md`
