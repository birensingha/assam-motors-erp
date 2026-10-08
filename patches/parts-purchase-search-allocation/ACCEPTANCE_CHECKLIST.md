# Parts Purchase Search — Acceptance Checklist

## Part search

- [ ] Typing 1 character does not spam the API.
- [ ] Typing 2+ characters triggers search after ~250 ms.
- [ ] Exact Part Code appears first.
- [ ] Part Code prefix search works.
- [ ] Part Name prefix search works.
- [ ] Part Name contains search works.
- [ ] Description/OEM search works when those fields exist.
- [ ] Search is not case-sensitive.
- [ ] Up/Down + Enter works.
- [ ] Selecting a result fills the correct purchase line only.
- [ ] Adding 10+ lines does not mix one row's search result into another row.

## Job Card / Vehicle allocation

Test the same active Job Card with all of these inputs:

- [ ] exact Job Card No.
- [ ] Job Card No. without dash
- [ ] lowercase Job Card No.
- [ ] exact Vehicle Registration No.
- [ ] vehicle number with spaces
- [ ] vehicle number without spaces
- [ ] vehicle number with/without dash
- [ ] Customer name
- [ ] Customer mobile when available

## "No Found" regression

- [ ] Valid open Job Card returns even if vehicle relation is missing.
- [ ] Valid open Job Card returns even if customer relation is missing.
- [ ] LEFT JOIN is used for optional vehicle/customer relations.
- [ ] Closed/Invoiced match is shown as non-selectable context instead of falsely saying it never existed.
- [ ] Search does not silently filter out WIP/QC/Ready-for-Delivery records that are still valid for purchase allocation.
- [ ] Actual selectable status list matches live Workshop business rules.

## Save integrity

- [ ] Selected Job Card ID is saved server-side.
- [ ] Vehicle registration shown in UI is display context; allocation authority is Job Card ID/validated server reference.
- [ ] Server re-validates that the Job Card is still eligible at Save time.
- [ ] Client cannot allocate to an Invoiced/locked Job Card by editing HTML/JSON.
