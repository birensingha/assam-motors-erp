# Search API Contract

## Part autocomplete

Recommended endpoint:

`GET /erp/api/purchase/parts/search?q=<text>`

Minimum query length: **2 characters**.

Example response:

```json
{
  "items": [
    {
      "id": 51,
      "code": "OF-1001",
      "name": "Oil Filter",
      "description": "Engine oil filter",
      "oem_no": "16510-...",
      "unit": "PCS",
      "last_rate": 325.00
    }
  ]
}
```

### Server search order

Rank matches roughly as:

1. exact Part Code
2. Part Code prefix
3. Part Name prefix
4. Part Name contains
5. Description / OEM contains

Use parameterized SQL.

Do not make Part Name search depend on an exact code match.

---

## Job Card / Vehicle allocation search

Recommended endpoint:

`GET /erp/api/purchase/job-card-search?q=<text>`

Example response:

```json
{
  "items": [
    {
      "job_card_id": 1042,
      "job_card_no": "JC-1042",
      "status": "WIP",
      "selectable": true,
      "vehicle_id": 212,
      "vehicle_reg_no": "AS01AB1234",
      "customer_name": "Customer Name",
      "label": "JC-1042 · AS01AB1234 · Customer Name"
    }
  ]
}
```

### Normalization

For search comparison, normalize:

```
AS 01 AB-1234  -> AS01AB1234
jc-1042        -> JC1042
```

At minimum remove:
- spaces
- hyphens
- dots
- slashes

and compare case-insensitively.

### Status policy

Selectable statuses should come from the ERP's actual editable Workshop status list.

Typical examples:
- OPEN
- DRAFT
- WIP
- IN PROGRESS
- QC PENDING
- QC PASSED
- READY FOR DELIVERY

Closed/Invoiced records may be returned with `selectable=false` for diagnostics/context, but must not be allocated unless business rules explicitly allow it.

### Critical query rule

Start from Job Card:

```
Job Card
  LEFT JOIN Vehicle
  LEFT JOIN Customer
```

Do **not** use an INNER JOIN to Vehicle as a prerequisite for finding the Job Card.

Otherwise a valid Job Card with missing/legacy vehicle linkage can incorrectly become "No Job Card/Vehicle Found".
