# AM238 — Parts Purchase JC Enter-key bug fix

**STAGING deployment verified on 4 November 2026**.

## Reported bug
After selecting `JC-2026-0011 — AS01HC0595 — SUMEN ROY`, Enter changed the hidden JC selection to an unrelated first item (`JC-20261031-00046 — AS01FK2840 — NEW OM SAI MOTORS`) and did not open the next purchase line.

## Verified cause
The AM234 `wireJobFilter` keyboard handler called `show()` on Enter when the list was hidden. For an already-selected JC, `show()` deliberately used an empty query to display all Job Cards and set the active index to zero. The Enter handler then called `choose(visible[0])`, overwriting the legitimate JC id. The existing `wireEnterFlow` listened on hidden `.job-select` instead of the visible `.job-filter`, so it did not advance.

## Scoped fix
On Enter:
- If the visible JC field already displays the selected item and hidden `job_card_id` is present, close suggestions and invoke the existing `finishRow(row)`.
- `finishRow` validates a selected Part and focuses the next Part Search, creating a new blank purchase line when required.
- If the JC is not yet selected, Enter keeps choosing the highlighted suggestion.
- No changes to the database, stock, vendor accounting, backend purchase-save rules, GST, layout, or parts picker.

The guarded script patches **both** the Laravel source JS and actual served `public_html/staging/js/purchase-entry.js`, changes script version for cache busting, and backs up all 3 affected files. On failing browser QA, all three are restored.

## Browser QA
[Successful GitHub Actions run #41820965761](https://github.com/birensingha/assam-motors-erp/actions/runs/41820965761)

- Select AM-0835 Part, JOB allocation, SUMEN ROY JC ID **11**, press Enter -> first row remains ID 11, second blank row opens and Part Search has focus.
- Second line selects JC-20261031-00046 via keyboard Enter, next Enter opens third row, both original JC IDs remain unchanged.
- Actual served JS marker verified over HTTP and original Part Search preserved.
- No purchase saved, no stock/financial entries posted.

Related: strict multi-JC purchase duplication rules and mandatory JC billing allocation reconciliation remain separate backend tasks, not implemented here.
