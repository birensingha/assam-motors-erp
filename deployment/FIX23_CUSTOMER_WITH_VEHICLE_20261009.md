# FIX23 — Native Customer + Vehicle creation (09-Oct-2026)

Purpose: restore the useful Legacy workflow inside the native ERP Customer Master.

Scope:
- app/Http/Controllers/Web/AdminNativeModuleController.php
- resources/views/admin/native/customers.blade.php

Behavior:
- New Customer can be saved alone, or with 1–5 vehicles in the same form.
- Vehicle fields: Registration, Registration Date, Make, Model, Year, Variant, Fuel, Odometer, Chassis/VIN, Engine No.
- Customer + vehicles save inside one DB transaction.
- Duplicate registration/chassis/engine is blocked using the existing am_vehicle_master identity keys.
- Make/Model/Year/Variant/Fuel is validated against active am_vehicle_catalog_master rows.
- Existing am_customer_master and am_vehicle_master are used.
- No DB migration.

Package:
ASSAM_MOTORS_FIX23_CUSTOMER_WITH_VEHICLE.tar.gz
SHA-256:
44e2df81d9c88a135fa9a6dd17ff89b334901bb3247b930b90a688fc8bfc4163
