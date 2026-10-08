# Purchase / Estimate Readability Rules

## 1. Same page, not separate window

Primary rule:

> Increase readability inside the existing ERP page.

Avoid:
- modal-only primary forms;
- narrow 900px desktop forms;
- tiny embedded preview iframe;
- `window.open()` for normal Purchase/Estimate viewing;
- browser zoom instructions as the main solution.

## 2. Comfortable default

Recommended desktop baseline:

| Element | Target |
|---|---:|
| Main content max width | 1650–1700px |
| Page horizontal padding | 18–24px |
| Page title | 24–28px |
| Section heading | 17–20px |
| Field label | 12–14px |
| Input/select text | 15–17px |
| Input/select height | 44–48px |
| Table header | 12–14px |
| Table body | 13–15px |
| Line input height | 40–44px |
| Primary button | 44px+ |

## 3. Large line-item tables

For Purchase/Estimate:
- keep columns useful;
- do not make all columns tiny just to fit viewport;
- set a reasonable `min-width`;
- allow horizontal scrolling;
- keep header sticky;
- keep important first columns visually stable if feasible;
- use numeric alignment for Qty/Rate/Tax/Amount.

## 4. Totals

Totals should be easy to locate:
- Subtotal
- Discount
- Tax
- Round Off where used
- Grand Total

On wide desktop, totals can remain visible/sticky near the lower/right area.

## 5. Estimate Preview

Preview should:
- use the full available main content width;
- render document centered;
- default to a visually larger on-screen reading size;
- keep Print/PDF size independent;
- avoid a tiny 700–800px iframe when more width exists.

## 6. In-page size preference

Use CSS variables rather than page/browser zoom.

Suggested screen scale:
- 1.00
- 1.15
- 1.25

Persist per browser/device with `localStorage` if appropriate.

Do not store this as accounting/business data.

## 7. Mobile/tablet

On small screens:
- retain readable font sizes;
- allow horizontal table scroll;
- stack header fields;
- totals use full width;
- primary Save action remains easy to reach.

Do not shrink the full desktop table to unreadable 8–10px text.

## 8. Print isolation

Under `@media print`:
- set scale to 1;
- hide screen-only size controls;
- remove sticky positioning;
- use existing A4 print rules.
