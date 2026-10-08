# Remaining Phase-1 Native Admin Pages

This package defines native ERP Admin routes for:

- Booking & Orders
- Users
- Products
- Staff
- Job Applications
- Staff Location
- Service Reminder

The purpose is to remove primary navigation dependency on separate Legacy PHP admin sessions.

## Canonical route family

Recommended:

- `/erp/native/bookings-orders`
- `/erp/native/users`
- `/erp/native/products`
- `/erp/native/staff`
- `/erp/native/job-applications`
- `/erp/native/staff-location`
- `/erp/native/service-reminders`

Existing equivalent native routes may be reused.

## Security

All routes use the ERP's existing Admin authentication/permission middleware.

Never:
- share/copy authentication cookies to Legacy PHP;
- use unsigned query-string admin flags;
- let Users/Staff role changes bypass permission checks;
- expose Staff Location to unauthorised users.

## Module intent

### Users
ERP login/account administration.

### Staff
Workshop/company employee master and operational Staff-App identity.

These are related but not the same master.

### Staff Location
Operational/read-only latest-location/reporting view. It should not become a hidden way to modify attendance or Staff identity.

See:
- `NATIVE_ROUTE_MAP.md`
- `MODULE_CONTRACTS.md`
- `AdminNativeOperationsController.php.example`
- `service_reminder_reference.html`
- `ACCEPTANCE_CHECKLIST.md`

Live Laravel/PHP production source is not present in this repository, so this is an integration contract/reference package.
