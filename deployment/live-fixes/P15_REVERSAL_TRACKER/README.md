# P15 — 5-point Job Card Reversal Progress

Staging-only source implementation. Verified against JC-2026-0025 and the live staging schema on 2026-10-14.

## Status semantics

- **COMPLETE (muted grey)**: backend evidence verifies the stage-specific action, without deleting historical records.
- **REVIEW REQUESTED (blue)**: audit request exists, but no actual reversal is implied.
- **PENDING (orange)**: action has neither been completed nor requested.
- A cancelled Job Card is **complete only for the Cancel/Archive stage**; estimate, purchase and vendor accounting obligations remain separate.
- Estimate request (including duplicates already stored) is not replayed from this UI.
- Point 5 is not complete until ROT, parts, estimate, cancellation and linked purchase/vendor/finance checks are safely resolved.

## Code changes

- `PATCH_P15.php` performs strict anchor-matched source edits to only the `AdminJobCardUnwindController.php` and `unwind.blade.php` files.
- Existing ROT void history, ledger, stock movements, invoice data and estimates are never modified by installation.
- Creates complete backups before installation; aborts on concurrent edits and restores edited files if source replacement fails.
- Compiles the candidate Blade template and parses PHP before any source writes. This avoids relying only on `php artisan view:cache`.
- After staging deployment, a real admin-authenticated GET of JC-2026-0025 was rendered and checked successfully.
- No finance/stock/estimate reversal transactions are implemented by this UI-only release.

## Verified release

- Staging job: GitHub Actions run 38509076837.
- `P15_COMPILED_BLADE_PHP_LINT=PASS`
- `P15_REAL_ADMIN_RENDER_PASS`
- `P15_DONE_STEPS=3 P15_PENDING_STEPS=2`
- `P15_ESTIMATE_REVIEW_REQUESTS=2`

## Safety / later work

- Future action: authorized Estimate cancellation/reversal and purchase/vendor liability reconciliation must be implemented and audited separately.
- Historic stock movements retained is not equivalent to outstanding stock; compare issued-versus-returned quantities.
- Avoid repeating already registered review requests; mutation endpoint has not been redesigned in this release.
- Merge only after additional regression review; the staging installation is separate from GitHub merge.
