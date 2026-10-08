# Phase 1 — Customer Master Native Auth Fix

Problem observed from staging navigation:

`/legacy/workshop/customers.php` returns:

```json
{"error":"Admin login required"}
```

The staging ERP admin session and the standalone Legacy PHP admin session are separate auth boundaries. A logged-in ERP Admin should not be forced to create/reuse a second Legacy session, and the fix must **not** bypass authentication.

## Safe fix

1. Add a native authenticated Customer Master route under ERP/Laravel.
2. Change ERP navigation to use the native route.
3. Keep the legacy URL only as a compatibility redirect to the native route.
4. Let the native route's existing Admin middleware enforce login/authorization.
5. Do not copy Laravel cookies/tokens into a Legacy PHP session.
6. Do not accept a query-string `admin=1`, user ID, role, or unsigned handoff token.

Recommended canonical route:

`/erp/native/customers`

Old compatibility URL:

`/legacy/workshop/customers.php` → HTTP 302 → `/erp/native/customers`

## Customer Master functions

- Search by customer name / mobile / customer ID.
- Add Customer.
- Edit Customer.
- View linked vehicles / Job Cards where available.
- Prefer archive/disable over hard delete when historical Job Cards exist.

Fields already represented in the project UI prototype:
- Name
- Mobile
- WhatsApp
- Email
- Address

See the included route/controller/legacy redirect examples and acceptance checklist.

## Important

The live Laravel/PHP staging source is not stored on the current main GitHub branch. This PR therefore supplies the secure patch contract and integration examples; it does not claim that staging has already been deployed.
