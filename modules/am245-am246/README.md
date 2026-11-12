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
