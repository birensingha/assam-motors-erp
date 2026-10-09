# FIX36 — Staff Inspection Assignment Sync

Symptom:
- Admin selects a staff member (example: Firajul) for Job Card inspection.
- Staff Android app shows no assigned inspection.

Known Android behavior:
- Primary endpoint: /staff-inspections.php
- Fallback endpoint: /staff-inspection.php?action=list
- Fallback is used only when primary returns HTTP 404.
- Therefore a primary endpoint returning HTTP 200 with an empty row set can mask a valid assignment exposed by the legacy endpoint.

This first step is READ-ONLY. It maps:
- Job Card inspection assignee field(s)
- Staff identity/id/code/email mapping
- both inspection endpoints
- their query filters and response shapes
- current assigned rows for the named staff member

No source/DB changes.
