# ASSAM MOTORS — Single ROT Management Panel (Staging)

## Approved UI decision — 23 October 2026
Retire the **standalone Job Card ROT Alteration panel/page** shown in the user's second screenshot. Keep the **ROT Assignment cards inside the Job Card** (first screenshot) as the only normal user-facing ROT management surface. Do not delete ROT entries, audit events, estimates, worked time or billing records as part of retiring a view.

### Each Job Card ROT card must provide
- Searchable Assign / Change Mechanic, supporting pre-assignment of multiple PLANNED ROTs (existing checked-in / Running constraints still apply as configured).
- Admin-only audited edit of Std Hours, Rate, Discount, with accurate GST/billing recalculation; preserve approved Estimate records.
- Existing Start / Pause / Resume / Complete and auditable Start/End time correction; never overwrite ROT session history.
- Admin-only Delete (or controlled reversal request) with mandatory reason; never hard-delete started, worked, purchase-linked or invoiced ROTs.
- Expandable History showing relevant decisions and mechanic/time corrections.

### Retirement sequence (strict order)
1. Run `scripts/UNIFIED_ROT_PANEL_READONLY_PREFLIGHT.sh` against authenticated STAGING Laravel source. Save findings.
2. Inspect every legacy-page link and named route and the existing Job Card Edit/Assign forms. Repository is deployment patches, not complete live source: **do not guess route names or selectors**.
3. Add/verify each edit action and its backend guard to the Assignment card.
4. Test a planned estimate-linked example: mechanic assignment, edit Std Hours, Rate, Discount, audit reason, totals, history; test cancel eligibility and denied cases. Confirm refresh persistence.
5. **Only after step 4 passes:** remove legacy page links from sidebar, Job Card, Amount Editor and all navigable ERP views; turn any old GET route into a role-safe redirect to the same Job Card card (retain named route compatibility only if required). Avoid deleting the old view until clean QA/rollback is confirmed.
6. Verify no duplicate panel remains on desktop or mobile; verify invoice, closed JC, credit note, estimate and customer ledger remain unchanged. Back up modified view/routes/controllers, run `php -l`, `php artisan view:cache`, `php artisan route:list`, and browser check before reporting deployed.

### Safety
- This branch currently contains **read-only preflight only**. It does not deploy a UI change.
- Never unlink routes/pages on STAGING until replacement actions work.
- No work on OLD production ERP, no automatic data removal, no invoice rewrite.
