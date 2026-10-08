# Master Fields & Business Rules

Exact database column names must be mapped to the live schema.

## Vendor Master

Recommended fields:
- Vendor Code
- Vendor Name
- Contact Person
- Mobile
- WhatsApp
- Email
- Address
- GSTIN
- State/State Code if GST logic requires it
- Active/Inactive

Rules:
- Vendor Code unique if the current ERP uses a code.
- GSTIN validated only when supplied.
- Prefer deactivate/archive over hard delete when Purchase history exists.

## Part Master

Recommended fields:
- Part Code
- Part Name
- Description
- OEM / Alternate No.
- Unit
- HSN
- Tax %
- Purchase Rate / Last Purchase Rate according to existing accounting rules
- Sale/Issue Rate where applicable
- Minimum/Reorder level where inventory uses it
- Active/Inactive

Rules:
- Part Code must be unique.
- Do not hard-delete a Part referenced by Job Card/Purchase/Invoice history.
- This Part Master is the authoritative source for Parts Purchase autocomplete and Job Card Part search.

## Labour Master

Recommended fields:
- Labour Code
- Labour Description
- Standard Hours / ROT Standard Time
- Rate
- Tax %
- Category
- Active/Inactive

Rules:
- Labour Code unique.
- Standard time must be >= 0.
- Rate must be >= 0.
- Historical Invoice/Job Card rows keep snapshots and must not change when master rate changes.

## Vehicle Master

Aligned with existing prototype:
- Vehicle Registration Number
- Customer
- Brand
- Model
- Variant
- Fuel Type
- Manufacturing Year
- KM Reading
- Chassis/VIN
- Engine Number

Rules:
- Registration number normalized for search.
- Registration number unique where business policy requires.
- Customer relation required.
- KM cannot be negative.
- Vehicle referenced by Job Cards should not be hard-deleted.

## Labour / Part Mapping

Purpose:
associate Parts normally required/recommended for a Labour/ROT operation.

Columns:
- Labour
- Part
- Default Qty
- Required/Optional
- Active/Inactive
- Note

Rules:
- same active Labour + Part pair should not be duplicated;
- mapping must reference active master records at create time;
- deleting a mapping does not delete Part/Labour masters;
- historical Job Cards remain unchanged;
- mapping may prefill a Job Card but final Parts addition still follows Job Card validation and pricing rules.
