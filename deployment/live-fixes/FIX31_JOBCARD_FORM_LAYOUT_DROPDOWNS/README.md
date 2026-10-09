# FIX31 — Job Card Form Layout + Proper Dropdowns

User requirement:
- Job Card data-entry fields should not pile vertically on desktop.
- Use a clean left/right two-column layout.
- Long notes/complaint fields remain full-width.
- Dropdowns must look and behave like proper selectable lists.
- Service Type must be a real dropdown, not a free-text box.

Scope:
- resources/views/job-cards/form.blade.php

Service Type options:
- General Service
- Periodic Service
- Running Repair
- Major Repair
- Diagnosis
- Electrical Repair
- AC Service
- Body & Paint
- Accident Repair
- Inspection
- Warranty / Goodwill
- Other

Existing legacy/custom service_type values are preserved when editing/reloading.

No DB migration. No Job Card save/business logic change.
