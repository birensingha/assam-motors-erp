# Customer Master — Acceptance Checklist

## Authentication

- [ ] Logged-in ERP Admin opens Customer Master without seeing `Admin login required`.
- [ ] Logged-out browser opening `/erp/native/customers` is sent to the normal ERP Admin login.
- [ ] Logged-out browser opening old `/legacy/workshop/customers.php` redirects to the native route and then normal login.
- [ ] No query string, cookie copy, unsigned token or hard-coded admin bypass is used.
- [ ] Non-Admin authenticated user receives the ERP's normal forbidden/permission behaviour.

## Customer screen

- [ ] Customer list loads.
- [ ] Search by Name works.
- [ ] Search by Mobile works.
- [ ] Search by Customer ID/code works.
- [ ] Add Customer works.
- [ ] Edit Customer works.
- [ ] Name is required.
- [ ] Mobile is required.
- [ ] Email validation is enforced if supplied.

## Compatibility

- [ ] Search repository/live source for references to `customers.php` before replacing it.
- [ ] Confirm it is not consumed as an AJAX/JSON endpoint.
- [ ] Update sidebar/menu/dashboard links to the native route.
- [ ] Existing Job Card customer lookup still reads the same customer master records.
- [ ] Existing vehicle/customer links remain intact.
