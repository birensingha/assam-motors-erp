# AM245/AM246 — Assam Motors Admin Feedback and WhatsApp

Implementation branch for **staging integration review**, not a deployed change.

## Critical integration boundaries
- Actual deployed Hostinger Laravel source is not stored in this repository's main branch. Accordingly these files are staged in `modules/am245-am246/`, NOT automatically inserted into app paths.
- Review controller/route/schema with the deployed ERP version before integrating.
- Register Laravel Gate `manage-customer-engagement` for **verified Owner/Admin only**, never just a client-provided role.
- Integrate ERP's **canonical customer ledger balance** via `AmEngagementLedger`; do not infer the balance by summing arbitrary invoices.
- Include `routes.php` within existing admin route group rather than blindly duplicating `erp` prefix. Adjust prefix to project routes.
- Copy migration to deployed app and run after tested backup. Add CUSTOMER > Reviews & Feedback and WhatsApp Messaging menu links to existing admin sidebar.
- PHP backend stores replies as **drafts**, never claims Google publication.
- WhatsApp wa.me is **manual-send** only. Log status explicitly says opened, NOT sent. Requires operator-confirmed consent; no automatic API.
- Customer identifiers, consent, phone numbers, and ledger source must be mapped from actual deployed models before enabling.
- Test staff/HR forbidden, admin allowed; CSRF, empty state, negative/zero balances, duplicate complaints, stock/financial regressions.

### Meta API phase
Automatic messaging requires business credentials, opt-in, approved templates and webhook delivery status. No credentials or business data should be committed to Git.

## Admin page separation (user-approved)
1. CUSTOMER > Reviews & Feedback: `am.customer-engagement.index`. Only feedback, ratings, reply drafts, complaints. **No outstanding figures, receipts, payment messages, or messaging log in this page.**
2. CUSTOMER > Customer Outstanding & WhatsApp: `am.customer-messaging.index`. Financial messaging form and activity log live here, behind both `manage-customer-engagement` and `view-customer-ledger` gates. Implement canonical outstanding register using real ERP finance models, not placeholder balances.
3. Message POST route: `am.customer-messaging.message`. Never post to the feedback route.
4. These routes and templates have not been mounted to live staging: merge and test only with deployed Laravel source.

## Next integration increment — searchable Customer Master
- The **Customer Outstanding & WhatsApp** page now has a searchable customer picker; direct numeric ID entry was removed from its visible form.
- GET `am.customer-messaging.customers` returns only customer ID/name, requires both Admin/customer engagement and customer ledger authorization, is rate limited and marked no-store.
- `AmEngagementLedger::searchCustomers($query, $limit)` must be bound to the deployed ERP's real scoped Customer Master queries. This is **not** a generic full-text database query or an automatic live connection.
- All ledger, customer and mobile values stay on the server during message construction. A typed browser customer name is not trusted to establish customer identity.
- Confirm zero, credit, invoice, credit-note, advance and on-account balances against the existing ledger; verify no staff, HR, unauthenticated or cross-company lookup; verify no WhatsApp delivery acknowledgement is fabricated.
- Pending mandatory stages: implemented adapter binding, staging PHP/Blade validation, actual sidebar insertion, database migration, customer opt-in audit, end-to-end browser testing.

## AM245 Feedback Customer/Vehicle/Job Card linking (Nov 19)
- Feedback creation now uses a search box, not manually entered Customer ID, name, vehicle number or Job Card reference.
- Protected GET `am.customer-engagement.targets` returns only IDs, customer name, vehicle plate and job reference for valid records; rate limited at 30/minute.
- New read-only `App\\Contracts\\AmFeedbackCustomerSource` interface:
  - `search(string $query, int $limit = 15): array` finds customer by name/vehicle/JC in current ERP company.
  - `resolve(int $customerId, ?int $vehicleId, ?int $jobCardId): ?array` **must verify** that selected vehicle/JC belong to that customer and current company.
- Backend discards browser-entered labels and saves verified canonical name/vehicle/JC from `resolve()` only; rating 1–5 and comment remain validated.
- Customer Feedback contains no outstanding, payments or WhatsApp message forms; those stay in a separate permission-protected page.
- Before deployment, bind this interface in the actual Laravel app's service provider to a concrete existing Customer/Vehicle/Job Card repository and insert proper route/sidebar links using the real deployed code.
- This standalone source-contract is **not yet bound** to the Hostinger Laravel models. Never enable route in live staging until this is done, or saving feedback will fail.
- Draft PR checks: PHP syntax, static feedback separation and client JS syntax. End-to-end Customer Master/JC relational and Admin permission tests are still required.

## Staging-only delivery with reversible, non-destructive installer (2026-11-19)

The helper `tools/stage_feedback.py` accepts the **actual Laravel root** and has three strictly separated operations:

- **Default / dry-run:** identifies the exact target paths and refuses to use an unrecognised Laravel directory; reads file hashes only. It never opens `.env`, reads customer data, runs Artisan, modifies routes or touches the DB.
- **`--stage`:** copies this module's missing PHP/Blade/route/migration files into the staging code tree, aborting on any different existing target. Creates an explicit SHA-256 manifest of newly created files in `storage/app/am245-stage-manifest.json`. **Does not activate** route includes, Gates, service bindings, sidebar, or DB migrations; thus it is NOT a completed deployment.
- **`--rollback`:** deletes only exact, unchanged files recorded as newly created by `--stage`, refusing rollback if any were edited; no DB rollback or existing application file deletion.

Safety tests cover untouched existing routes, collision refusal, preservation of identical pre-existing files and modified-file rollback refusal. Do not use this helper to claim live deployment.

**Activation gate:** Before the module can be enabled, a developer with access to the actual Hostinger Laravel source must (1) verify existing customer, vehicle, JC tables/relations/company scope, (2) bind both `AmFeedbackCustomerSource` and `AmEngagementLedger` to implemented scoped adapters, (3) register server-side verified Owner/Admin Gates for customer engagement and the stricter ledger Gate, (4) mount `routes/am245-am246.php` inside real route groups, (5) insert the guarded sidebar links, (6) take database backup then run and verify migration, (7) test authorized user flow and forbidden staff/HR access, (8) verify feedback save, complaint audit, rating and search, no outstanding in feedback, and (9) document tested rollback for any additional active changes. The `AmEngagementLedger` binding is for the separate financial messaging page only.

For Hostinger's Laravel root previously identified by the operator, first run the read-only preflight tool `tools/staging_preflight.py` and share the non-secret structural result; full deployed source is not present in GitHub. Never run database migrations or change `routes/web.php` based on guessed schema.

### Tested ZIP delivery
The `AM245 Customer Feedback Module Checks` GitHub Actions workflow packages `modules/am245-am246/` into the **`am245-feedback-staging-only`** artifact, subject to all checks passing. Download it from the GitHub Actions run associated with PR #183. This ZIP contains the module and read-only/staging tooling; **uploading it alone does not activate the feature**. Never run the installer `--stage` until the actual deployed Laravel source mapping and permissions have been reviewed. The user's Hostinger staging has not been changed by GitHub commits or Actions artifact creation.

## November 19: filtered feedback export and author audit
- The Feedback register now has `Export Filtered CSV` using the same customer, vehicle/JC, rating and complaint-status filters as the register; capped at 5,000 rows per request and rate-limited at 10 requests per minute.
- Each feedback records the authenticated actor's `created_by` ID in the migration's newly created feedback table (if that migration has **not yet** been run). If integrating into an ERP where the migration was previously applied, create and review a safe additive migration rather than rerunning a modified migration.
- CSV export includes date/customer/vehicle/JC/rating/feedback/complaint state/reply draft/actor ID but **no outstanding balance, payment information or WhatsApp details**. User-submitted string cells pass the formula-injection guard in `App\\Support\\AmFeedbackCsv`, covered by PHP unit tests.
- Activation still requires verified Laravel model adapters, Owner/Admin gate bindings, route/sidebar insertion, backup, migration and authenticated staging E2E tests. The tests/ZIP from GitHub Actions are **not** a deployed installation.

### Canonical ID and balance guardrails (2026-11-19)

The source adapter `AmFeedbackCustomerSource::resolve()` must now return **customer_id, vehicle_id, job_card_id** and canonical labels, and validate the requested customer/vehicle/job-card association against the real deployed ERP and company scope. The feedback POST checks every non-null submitted ID against the adapter's returned canonical IDs before inserting feedback. A stale or faulty adapter that returns only labels can no longer silently attach feedback to a different vehicle or Job Card. This is a deliberate interface requirement; update the actual deployed adapter before activation.

The AM246 message preview rejects non-finite or out-of-range numeric outstanding balances, rather than generating misleading WhatsApp messages or failing on DB decimal overflow. AM246 still only opens WhatsApp; no automatic message delivery is claimed.

These are code-level guardrails, **not a replacement for end-to-end tests with the real Hostinger source, database, staff permissions and Customer/Vehicle/JC mappings**.

## Feedback-first rollout — AM245 decoupled from AM246 (2026-11-19)

To avoid delaying Customer Feedback on the canonical financial-ledger integration, AM245 has an isolated activation package:

- `feedback-only-routes.php` declares ONLY six routes for the AM245 Feedback admin page, lookup, feedback save, draft reply, complaint actions, and CSV export. These are all guarded by authenticated `can:manage-customer-engagement` and rate-limited where relevant.
- `resources/views/admin/engagement/feedback-only-sidebar.blade.php` adds only Reviews & Feedback, not WhatsApp or financial messaging.
- `2026_11_12_010000_create_am_customer_engagement.php` now creates ONLY `am_customer_feedback`, `am_customer_complaints` and `am_customer_complaint_history`, not the financial message log.
- `2026_11_19_000000_create_am246_customer_message_log.php` is the SEPARATE AM246 migration, to be run only after finance/WhatsApp authorization and ledger adapter are verified.
- The safety installer now defaults to `--scope feedback`; `--scope all` is an explicit opt-in for staging both modules. Neither mode registers routes/gates, runs migrations, changes existing ERP source, or claims live deployment. For AM245 feedback-only, the installer's file map intentionally excludes the ledger contract, messaging view/route and AM246 migration.
- Both routes files have overlapping AM245 route names: **do not mount the combined routes file and the feedback-only routes file together.** Choose exactly one after checking the real deployed ERP's routes and prefix.
- If the old combined migration was **already run** anywhere, do not run a modified historical migration; use reviewed additive migrations and a measured schema diff. No confirmed Hostinger application of these migrations is available in the current session.

**The remaining AM245 blocker is integration with the actual deployed Hostinger Laravel ERP**: install the correct concrete `AmFeedbackCustomerSource` adapter, register the server-side Owner/Admin Gate, safely include only the AM245 routes and guarded sidebar link, back up database before applying feedback-only migration, then perform authenticated end-to-end regression tests. Keep AM246 disabled until it passes separate ledger/payment consent/permission testing. A GitHub Actions artifact proves build/test, not installation.

### Canonical relational ID persistence (2026-11-19)
AM245 feedback now stores `vehicle_id` and `job_card_id` (nullable indexed integers) alongside `customer_id` and human-readable snapshots. The controller writes only the IDs already checked against the deployed `AmFeedbackCustomerSource::resolve()` adapter. This prevents feedback history from depending solely on mutable vehicle plates and job reference labels. The static QA suite asserts both schema columns and controller writes.

**Migration safety:** This modifies the as-yet-unverified initial AM245 migration. If it has already been executed on any environment, do NOT rerun or edit the historical migration there; instead create a reviewed additive migration for the two columns and backfill only after validating real ERP relationships. This is NOT evidence of staging installation.

### Exact nullable association verification (2026-11-19)

The AM245 feedback save endpoint now rejects an adapter result that returns a vehicle or Job Card ID when the user did not select that association. Both nullable IDs must match exactly, including null, rather than merely checking provided non-null IDs. This prevents accidentally recording an unselected vehicle/Job Card through an over-eager or stale adapter. Static regression assertions cover both nullable and non-null ID comparisons. This is code-level validation only; real Hostinger model mapping and authenticated integration tests remain mandatory.
