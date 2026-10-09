# FIX30 — Supplementary Estimate / WIP Diagnosis Deadlock

Problem:
- Supplementary Estimate required Diagnosis COMPLETED.
- Diagnosis completion is intentionally blocked once Job Card is Work In Progress.
- Result: impossible loop for additional work discovered during WIP.

Correct business rule:
- INITIAL / REVISED Estimate still requires completed Inspection + Diagnosis + Technician Decision + estimate unlock.
- SUPPLEMENTARY Estimate is allowed when a previous approved Estimate has already been SENT TO JOB CARD.
- WIP Diagnosis is not reopened or rewritten.
- WIP Job Card status is preserved while a Supplementary draft is prepared.

Files:
- app/Services/EstimateService.php
- app/Http/Controllers/Web/AdminEstimatePageController.php
- resources/views/job-cards/show.blade.php

No DB migration.
