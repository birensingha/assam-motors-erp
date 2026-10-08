# Likely Causes of "No Job Card/Vehicle Found"

The exact live source is unavailable, so these are checks to perform against staging rather than assumptions about the current implementation.

## 1. Exact-match only search

Bad pattern:

```sql
WHERE vehicle_reg_no = :q
```

This fails for formatting differences such as:
- `AS01AB1234`
- `AS 01 AB 1234`
- `AS-01-AB-1234`

Fix: normalized comparison.

## 2. INNER JOIN hides valid Job Card

Bad pattern:

```sql
FROM job_cards jc
JOIN vehicles v ON ...
```

If a legacy Job Card has no current vehicle FK, the JC disappears entirely.

Fix: start from Job Card and LEFT JOIN optional relations.

## 3. Wrong status filter

A query limited to only `OPEN` may hide:
- WIP
- IN PROGRESS
- QC PENDING
- QC PASSED
- READY FOR DELIVERY

Fix: use the Workshop's actual allocation-eligible status list.

## 4. Searching display text instead of authoritative fields

The UI may show `JC-123 · AS01AB1234` but search only one field.

Fix: server endpoint searches both Job Card No. and Vehicle Registration.

## 5. Race condition / stale AJAX response

Typing quickly can let an older request arrive after a newer request and replace correct results with "No Found".

Fix: request sequence/AbortController and ignore stale responses.

## 6. Per-row state collision

In a multi-line purchase form, all rows may reuse one result container or one global selected ID.

Fix: every row owns:
- its input,
- result menu,
- selected part ID,
- selected Job Card ID.

## 7. Part search only checks code

If SQL searches only `part_code`, typing `oil filter` returns nothing.

Fix: search code + name + description + OEM/alternate reference.
