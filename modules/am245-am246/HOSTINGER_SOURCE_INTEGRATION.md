# AM245 Hostinger source-matched Job Card integration

Reviewed the operator-provided selected Laravel source (26 November 2026). **This is a GitHub code change only; NOT deployed.** Never commit private ERP sources, credentials or customer records to this public repository.

## Confirmed paths and schema

- Laravel uses bootstrap/app.php and routes/web.php. The ERP Admin group runs behind auth:web plus EnsureErpAdmin and URL prefix erp.
- Customer: am_customer_master (id, name, phone). Vehicle: am_vehicle_master (id, customer_id, registration_no). Job Card: job_cards (id, job_no, customer_id, vehicle_id, status, customer_public_status).
- Master-linked vehicle must belong to customer; Job Card must match both IDs.
- Actual Job Card page: resources/views/job-cards/show.blade.php. The action bar is nav.am75-quick-actions beside the Job Card Summary link.
- Sidebar: resources/views/layouts/erp.blade.php near the Customer link.
- The existing ErpAuthUser::isAdmin() includes supervisors, but this module's Gate deliberately permits only active role=admin until owner permissions are confirmed.
- Finished states: completed with public status Quality Check Passed / Ready for Delivery, or closed/invoiced. Completed without QC pass is not eligible.

## GitHub code ready

The new Hostinger feedback customer adapter performs bounded master-linked searches and relational verification. The invitation adapter verifies Job Card status, canonical contact and independently recorded customer WhatsApp opt-in. The AM245 provider binds only feedback contracts and restricts its Gate; AM246 ledger stays separate. Config defaults fail closed.

## Exact Hostinger wiring after source/consent review

1. Use staging-only dry run and reviewed backup. Stage AM245 files, not the financial AM246 route or migration.
2. Verify bootstrap/providers.php (not present in the uploaded archive). Register App\Providers\Am245HostingerServiceProvider::class once, checking for any pre-existing manage-customer-engagement Gate.
3. From the existing routes/web.php load routes/am245-feedback.php and routes/am245-feedback-invitations.php once, **outside** the existing prefixed ERP group. The included files already specify erp prefixes. Never mount the combined am245-am246.php routes simultaneously.
4. In resources/views/job-cards/show.blade.php, inside nav.am75-quick-actions immediately following the existing Job Card Summary link, include admin.engagement.job-card-feedback-action, passing jobCardId=(int)$job->id. Guard with Laravel Route::has('am.feedback.invitations.open') so a not-yet-enabled route cannot cause a 500.
5. In resources/views/layouts/erp.blade.php, add a Customer Reviews & Feedback link to route am.customer-engagement.index under @can('manage-customer-engagement').
6. Verify APP_URL/HTTPS, web auth guard, migrations already run, DB backup and CSRF; apply only reviewed, unapplied AM245 migrations.
7. **Consent still needs verified integration.** The uploaded source proves the customer phone field, but not a verified WhatsApp opt-in timestamp or consent policy. Until an auditable real opt-in field is established, config verified_customer_opt_in_column remains null and contact is deliberately blocked. Do not treat the operator checkbox as independent customer consent.
8. Confirm the installation is single-company (otherwise add tenant scope filters) before enabling verified_single_company_scope. Default is false.
9. Authenticated STAGING test: admin allowed, staff/supervisor denied, wrong associations rejected, QC status enforced, genuine opted-in recipient, manual WhatsApp compose only (not delivered), secure HTTPS link, expiry, single-use survey submit, complaint handling, and rollback readiness.

Neither source ZIP upload nor GitHub CI PASS proves the system is installed. No live server edit, migration or merge has been made.
