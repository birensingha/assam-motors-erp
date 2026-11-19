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
