# AM245 Job Card feedback request / AM246 WhatsApp launch integration

Status: **DRAFT CODE ONLY; NOT DEPLOYED.** The sample Job Card discussed by the operator is an acceptance scenario, not a fixture. Never commit its customer identity or phone.

## Job Card action
- Add **Request Feedback on WhatsApp** to Job Card Summary and Ready for Delivery (admin only), after checking the deployed ERP's actual Job Card action layout.
- Resolve the Job Card, vehicle, customer and phone **server-side**, with company scope and verified relationships; do not trust browser-supplied labels or recipient numbers.
- Require a completed/ready-for-delivery state (use actual ERP status enum), operator permission, valid consent/legitimate messaging permission and verified destination.
- Generate an unpredictable single-use or expiring opaque invitation token server-side. Store only a token hash and Job Card association. Never put customer ID, plate, or phone in the URL query. A token redemption endpoint must rate-limit, validate expiry and prevent duplicate submissions. Do not use the authenticated Admin feedback POST as a public endpoint.
- Use `App\Support\AmJobCardFeedbackInvite::compose` only with verified canonical context and the server-created HTTPS invitation URL. This helper **does not generate tokens, persist requests, contact WhatsApp, or send messages**.
- Open a wa.me compose link with operator confirmation; label the audit state **WhatsApp opened, not sent**. Do not claim delivery. For automatic sending, require a separate approved WhatsApp Business API integration and opt-in.
- Track requested/opened/received/reminder due with idempotency, operator and timestamps, without exposing the destination or token to broad staff roles.

## Permission boundary
- AM245 feedback request creation and invitation management: authenticated verified Owner/Admin `manage-customer-engagement`.
- AM246 financial messaging remains behind **both** `manage-customer-engagement` and `view-customer-ledger`. Do not place financial balances or messaging logs in AM245.
- A feedback-only service invitation must not require financial ledger permission or call `AmEngagementLedger`.

## Staging activation acceptance
1. Obtain non-secret read-only `tools/staging_preflight.py` report from the real Hostinger Laravel root; verify models, status enums, Gate definitions, route groups and sidebar placement.
2. Bind scoped Customer/Vehicle/Job Card adapter; implement token storage, public feedback controller, CSRF/rate limiting and safe recipient resolution against real source.
3. Add guarded Job Card buttons, invitation audit and a public rating/comment page. Keep public feedback submission separate from admin actions.
4. Verify authorized Admin can open compose for an eligible Job Card; staff/HR and cross-company access denied; invalid number/consent/status rejected; no fake send receipt.
5. Verify feedback submission is associated with the correct Job Card exactly once, and customer data is never leaked through tokens, logs or URL parameters.
6. Run regression tests, review migration and backup, and perform authenticated staging browser QA before claiming deployment.

The example vehicle/job-card scenario is for manual acceptance only and must not be stored in automated test fixtures.
