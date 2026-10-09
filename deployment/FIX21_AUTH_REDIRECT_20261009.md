# FIX21 — Auth redirect 500 root cause (09-Oct-2026)

Latest live Laravel errors for all three reported pages are the same:

`Route [login] not defined.`

Affected browser pages:
- `/erp/payments/create`
- `/erp/customers`
- `/erp/job-cards/create`

The protected routes use Laravel `auth` middleware. The application defines:
- `erp.login` at `/erp/login`
- `staff.login` at `/staff/login`

but does not define the generic route name `login` expected by Laravel's guest redirect path.

FIX21 changes only `routes/web.php`:
- add public `/login` route named `login`;
- inspect the saved intended URL;
- route Staff intended URLs to `staff.login`;
- route ERP/default intended URLs to `erp.login`.

No DB/schema/controller/view/service change.

Important correction:
FIX20C backend verification was not sufficient to mark browser pages PASS. UI PASS now requires actual HTTP/browser verification.
