# Native Master Pages — Vendor, Part, Labour, Vehicle & Labour/Part Mapping

Goal: move Workshop master-data administration onto ERP-native authenticated routes instead of making Admin users jump into separate `/legacy/` pages/sessions.

## Canonical native routes

Recommended route family:

- `/erp/native/vendors`
- `/erp/native/parts`
- `/erp/native/labours`
- `/erp/native/vehicles`
- `/erp/native/labour-part-mapping`

Existing route names may be retained if the live ERP already has equivalent native pages.

## Authentication

All master routes must use the existing ERP Admin middleware/permission system.

Do not:
- copy Laravel cookies into Legacy PHP sessions;
- add query-string admin bypasses;
- expose CRUD endpoints without permission checks;
- make Legacy the primary navigation target.

## Existing Vehicle prototype alignment

The project already contains a Vehicle prototype with these fields:
- Vehicle Number
- Customer
- Brand
- Model
- Variant
- Fuel Type
- Manufacturing Year
- KM Reading
- Chassis Number
- Engine Number

The native Vehicle contract in this package keeps those fields aligned.

See:
- `NATIVE_MASTER_ROUTES.md`
- `MASTER_FIELDS_AND_RULES.md`
- `AdminNativeMasterController.php.example`
- `labour_part_mapping_reference.html`
- `ACCEPTANCE_CHECKLIST.md`

The live Laravel/PHP source is still not present in this repository, so these are integration contracts/reference implementations rather than a claim of staging deployment.
