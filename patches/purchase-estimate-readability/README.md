# Purchase + Estimate Readability / In-Page Zoom

User requirement:

- Purchase and Estimate screens are currently too small to read comfortably.
- Do **not** solve this by opening a separate oversized popup/window.
- Present the same page in a larger, more readable in-page layout.

## Target pages

- Parts Purchase
- OSL Purchase
- Estimate Create/Edit
- Estimate Preview
- Similar Workshop financial/line-item forms where the same compact layout is reused

## Default presentation

Desktop target:
- content width up to ~1650–1700px;
- title 24–28px;
- labels 12–14px;
- inputs/selects minimum ~44–48px high;
- table body text ~13–15px;
- row height large enough for comfortable scanning;
- totals visually separated and sticky where practical;
- horizontal scroll for large line-item tables rather than shrinking text.

## In-page size controls

Optional controls:

- 100%
- 115% — recommended/default comfortable view
- 125%

The controls change CSS sizing variables on the same page.

They must **not**:
- call `window.open()`;
- force browser full-screen;
- open a second popup;
- change print/PDF scale.

## Print

Print layout remains normal A4/PDF sizing.

The readability scale is screen-only and is reset/ignored under `@media print`.

See:
- `READABILITY_RULES.md`
- `workshop_readability.css.example`
- `workshop_readability.js.example`
- `purchase_readability_reference.html`
- `estimate_readability_reference.html`
- `ACCEPTANCE_CHECKLIST.md`

The actual live Purchase/Estimate templates are not present in this repository, so this package is a reusable integration layer/reference rather than a claim of staging deployment.
