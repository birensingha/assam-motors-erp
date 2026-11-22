# AM245 Job Card feedback request / AM246 WhatsApp launch integration

## Implemented GitHub-only AM245 invitation increment (22 Nov 2026)

- `AmFeedbackInvitationSource` is an unbound interface: an authenticated ERP adapter must verify tenant-scoped Job Card, Customer, Vehicle, finished status, consent and phone. No financial ledger dependency.
- `AmFeedbackInvitationController` checks the verified adapter, operator confirmation and permitted recipient, creates a seven-day hashed-token invitation, and opens WhatsApp compose for a manual send. This is NOT an automatic or confirmed delivery.
- `2026_11_22_000000_create_am245_feedback_invitations.php` keeps a unique row per Job Card, hash-only opaque token, actor, expiry, request count, and optional response ID; re-request rotates the token, while a completed response cannot be reopened.
- `feedback-invitation-routes.php` defines an Admin-gated invitation POST and separate public CSRF-protected, throttled token GET/POST. It must be mounted separately after route-prefix verification; do not double-mount the existing feedback routes.
- `public-feedback.blade.php` offers the public 1-5 rating/comment form without financials or identifying snapshots. The public POST consumes the token in a locked transaction and links the feedback to canonical snapshots, once.
- `job-card-feedback-action.blade.php` is a guarded reusable button partial. It is NOT inserted into the currently deployed ERP Job Card because its real Blade action source is missing.
- The staging-only installer now includes AM245 invitation files in feedback scope, but does not bind the adapter, activate routes, change the existing ERP, run migrations or deploy.

### SSH preflight handoff (no application changes)

The prior command `python3 staging_preflight.py` failed from the SSH home directory because the script was not present there. The preflight now accepts `--root` and can be run from a separate, temporary directory without placing scripts in the Laravel application.

First locate the Laravel `artisan` file using a read-only search limited to the staging domain folder. Download the reviewed `tools/staging_preflight.py` from the PR branch into a temporary operator-controlled location, then run `python3 /path/to/staging_preflight.py --root /actual/laravel/root`. Do not run the script without first verifying the downloaded file matches the reviewed PR; avoid untrusted URLs. Do not share `.env`, database contents, or credentials. The output is structural metadata only and cannot by itself verify deployment.

### Required before VERIFIED STAGING

1. Obtain the non-secret structure preflight report from the actual Hostinger Laravel root; check actual Customer/Vehicle/Job Card models, service status, consent, Admin Gates and Job Card Blade actions.
2. Implement and bind the invitation source adapter, mount optional routes once, and include the guarded partial in actual Job Card page after checking route namespaces and company scope.
3. Review database state and backup before approved migration; test role denial, wrong company, incomplete Job Card, consent/invalid phone rejection, HTTPS, expiry, token rotation and one-time feedback save.
4. Keep AM246 finance behind its distinct ledger permission gate, and verify authentic STAGING browser flows before reporting deployment.

GitHub CI PASS shows source checks only, never installation or delivery.

---

Status: **DRAFT CODE ONLY; NOT DEPLOYED.** The sample Job Card discussed by the operator is an acceptance scenario, not a fixture. Never commit its customer identity or phone.

## Job Card action
- Add **Request Feedback on WhatsApp** to Job Card Summary and Ready for Delivery (admin only), after checking the deployed ERP's actual Job Card action layout.
- Resolve the Job Card, vehicle, customer and phone **server-side**, with company scope and verified relationships; do not trust browser-supplied labels or recipient numbers.
- Require a completed/ready-for-delivery state (use actual ERP status enum), operator permission, valid consent/legitimate messaging permission and verified destination.
- Use `AmFeedbackInvitationToken::issue()` to create a 256-bit random opaque token and SHA-256 hash. Store only the hash (never the raw token) with an expiry and verified Job Card association. Validate with `AmFeedbackInvitationToken::matches()` after loading the record, and separately enforce expiry, single-use and rate limits in the future controller. Generate an unpredictable single-use or expiring opaque invitation token server-side. Store only a token hash and Job Card association. Never put customer ID, plate, or phone in the URL query. A token redemption endpoint must rate-limit, validate expiry and prevent duplicate submissions. Do not use the authenticated Admin feedback POST as a public endpoint.
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
