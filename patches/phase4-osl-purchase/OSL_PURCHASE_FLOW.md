# OSL Purchase — Exact Behaviour

## Header

Reuse the same header controls/validation already used by Parts Purchase wherever possible:

- Vendor
- Vendor Invoice / Bill No.
- Invoice Date
- Purchase Date
- GST / Tax mode
- Reference / GRN if applicable
- Remarks

Header should remain visible while line items grow.

## Multi-line OSL grid

Each row:

| # | OSL Code | OSL Description | Job Card / Vehicle | Qty | Rate | Disc % | Tax % | Tax Amt | Line Total | Action |
|---|---|---|---|---:|---:|---:|---:|---:|---:|---|

### Search

Typing code or OSL description must show matching OSL Master records.

Search should match:
- OSL code
- OSL name / description
- common text in description

Keyboard:
- Arrow keys move results
- Enter selects
- Tab moves to next cell

### Job Card / Vehicle allocation

Every line can be:
- GENERAL / STOCK / UNALLOCATED, or
- allocated to one Job Card / Vehicle.

Search must accept:
- Job Card No.
- Vehicle Registration No.
- Customer name when available.

Selected result should show both:
`JC-XXXX · AS01AB1234`

No silent "No Job Card/Vehicle Found" when a valid open Job Card exists.

## Multiple lines

Buttons:
- + Add Line
- Duplicate Line
- Remove Line

Adding one line must not submit the form.

At least 10–20 lines should remain usable on laptop/mobile widths.

## Calculation

For each row:

```
gross = qty × rate
discount = gross × disc% / 100
taxable = gross - discount
tax = taxable × tax% / 100
line_total = taxable + tax
```

Document:

```
subtotal = sum(taxable)
tax_total = sum(tax)
grand_total = subtotal + tax_total
```

Client preview is convenience only.
**Server calculation is final.**

## Save transaction

1. validate vendor/header;
2. validate every non-empty row;
3. lock/create purchase header;
4. insert/update all line rows;
5. recalculate totals on server;
6. write allocation refs;
7. write audit record;
8. commit.

If any line fails, rollback the whole document.

## Edit

Existing OSL Purchase must reopen with every saved line.
Editing one row must not overwrite other lines.

## Delete

- Unsaved line: remove locally.
- Saved line: soft-delete or audited removal depending on existing ERP pattern.

## UI

Follow Parts Purchase:
- same page width;
- larger readable controls;
- sticky header/total area if useful;
- blue header/action;
- green save;
- amber allocation/search highlights;
- red delete/error.

Avoid tiny modal-sized forms.
