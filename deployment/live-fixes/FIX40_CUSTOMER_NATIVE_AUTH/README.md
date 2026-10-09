# FIX40 — Legacy Customer Auth → Native Customer Master

Problem:
`/legacy/workshop/customers.php` returns `{"error":"Admin login required"}` even when the user is logged into the Laravel ERP.

Security rule:
- do **not** bypass authentication;
- do **not** copy Laravel auth into a Legacy PHP session;
- do **not** use unsigned query-string admin flags/tokens.

Target:
- use the existing authenticated native Customer Master route if present;
- update ERP navigation to that native route;
- keep the legacy URL only as a compatibility redirect after confirming it is not used as an AJAX/API endpoint.

This package is read-only. It maps the exact current staging baseline before the guarded apply package is created.
