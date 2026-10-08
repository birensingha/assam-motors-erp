# Phase 6 — ROT Performance / Technician Performance

This package defines the Admin-side ROT performance and detailed history reporting.

It builds on Phase 5's `rot_event_audit` event history.

## Admin screen

Filters:
- From Date
- To Date
- Technician / Staff
- ROT Status
- Job Card No.
- Vehicle Registration No.
- ROT Code / Description

Summary:
- Waiting / Assigned
- Running
- Paused
- Completed
- Standard Time
- Actual / Productive Time
- Efficiency %
- Over-standard ROT count

Detail:
- Technician
- Job Card
- Vehicle
- ROT
- Standard
- Actual
- Variance
- Efficiency
- First Start
- Last End
- Pause count / pause reason
- Current/final status

## Efficiency convention

For completed/comparable ROT:

```
efficiency_percent = standard_seconds / productive_seconds × 100
```

- 100% = completed exactly at standard.
- >100% = faster than standard.
- <100% = slower than standard.

Rows with missing standard time are excluded from efficiency denominator and marked N/A.

## Waiting / Assigned

Admin report should include ROT that has been assigned but not yet started.
These rows must not disappear merely because there is no START event yet.

See the API/report contracts and reference Admin screen in this folder.
