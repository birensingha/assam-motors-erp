# Phase 1 — Native ERP Navigation Cleanup

The first ERP work block removes staging navigation links that still jump into `/legacy/` pages.

The prepared patch is conservative:
- reuses Laravel-native pages already present in `AdminNativeModuleController`;
- creates stable `/erp/native/*` aliases only when the native view/method exists;
- never hides a missing native page by sending the user back to Legacy;
- backs up changed files before modification;
- produces an audit report of mapped and unresolved modules.

Initial targets: Booking & Orders, Users, Products, Staff, Job Applications, Staff Location, Service Reminder, Customer, Vendor Master, Part Master, Labour Master, Vehicle Master and Labour/Part Mapping.


## Status

**SUPERSEDED AS AN UMBRELLA PATCH — retained for history only.**

The original navigation-cleanup intent has now been split into the more specific prepared packages:

- `patches/phase1-customer-native-auth/`
- `patches/native-master-pages/`
- `patches/phase1-native-operations/`

Do not deploy this folder as a separate fourth implementation layer. Use the specific packages above plus the current Integration Map.
