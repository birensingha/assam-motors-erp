# FIX29 — Job Card Estimate / Supplementary Estimate Shortcut

Problem:
- The Estimate create/store route already exists.
- The Estimate create page already knows when to behave as a supplementary estimate after a prior estimate was converted to the Job Card.
- The Job Card detail page does not expose a clear action.

Fix:
- Add a visible `Estimate / Supplementary Estimate` button at the top of every Job Card detail page.
- Button uses the existing `erp.estimates.create` route.
- No DB migration.
- No Estimate calculation or approval logic change.
