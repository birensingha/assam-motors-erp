# FIX40 — Legacy Customer Direct Navigation → Native Customer Master

Staging inspection confirmed:

- native authenticated Customer Master exists at `GET /erp/customers`;
- legacy `customers.php` still serves many `?action=...` JSON requests used by Workshop JavaScript;
- therefore replacing the whole legacy file with a redirect would break Job Card / Customer workflows.

FIX40 adds only an early guard:

- direct `GET/HEAD /legacy/workshop/customers.php` with **no action** → HTTP 302 `/erp/customers`;
- `customers.php?action=...` continues into the existing legacy endpoint unchanged;
- Laravel remains responsible for authentication on `/erp/customers`;
- no auth bypass, token handoff, or DB migration.

Apply with:

```bash
bash APPLY.sh /home/u956497103/domains/assammotors.com/assam-erp-staging
```
