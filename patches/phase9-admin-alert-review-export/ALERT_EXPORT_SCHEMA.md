# Alert Compliance Excel Export Schema

Workbook filename:
`staff-alert-compliance-YYYY-MM-DD_HHMM.xlsx`

Use the same Admin filters currently applied on screen.

## Sheet 1 — Staff Summary

Columns:

| Column | Meaning |
|---|---|
| Staff Code | Staff identifier |
| Staff Name | Staff display name |
| Active | Current ACTIVE count |
| Snoozed | Current SNOOZED count |
| Escalated | Current ESCALATED count |
| Under Review | Current UNDER_REVIEW count |
| Resolved in Period | Alerts resolved inside selected date range |
| Total Postponements | Sum of postpone_count |
| Oldest Pending Since | Earliest unresolved first-triggered time |
| Oldest Pending Age | Human-readable duration |
| Avg Resolution Minutes | Average trigger → resolution duration for resolved alerts |
| Max Resolution Minutes | Longest trigger → resolution duration |

## Sheet 2 — Alert Detail

Columns:

| Column | Meaning |
|---|---|
| Alert ID | Server alert ID |
| Staff Code | Staff code |
| Staff Name | Staff name |
| Alert Type | e.g. ROT_PAUSED |
| Status | ACTIVE / SNOOZED / ESCALATED / UNDER_REVIEW / RESOLVED |
| Target Type | ROT / ATTENDANCE / INSPECTION |
| Target Key | Stable business target |
| Job Card | Job Card No. |
| Vehicle | Registration No. |
| ROT Session | ROT session ID |
| Title | Alert title |
| Message | Alert message |
| First Triggered | Asia/Kolkata |
| Postpone Count | 0–3 |
| Next Reminder | Asia/Kolkata |
| Escalated At | Asia/Kolkata |
| Admin Reviewer | Admin name/code |
| Admin Note | Latest note |
| Resolved At | Asia/Kolkata |
| Resolved By | System/Admin |
| Resolution Note | Resolution reason |
| Current Age Minutes | Age for unresolved rows |
| Resolution Minutes | Trigger → resolution for resolved rows |

## Formatting

- Freeze top row.
- Autofilter all columns.
- Bold header.
- Date/time format: `dd-mmm-yyyy hh:mm`.
- Numeric columns remain numeric.
- Status cells may be conditionally formatted by the implementation.
- One alert = one detail row; do not merge cells.

## Data rules

- Export is generated server-side.
- Admin identity comes from authenticated ERP session.
- Filters use parameterized queries.
- Staff Summary must be calculated from the same filtered Alert Detail dataset.
- Unresolved alerts remain in export regardless of Check-Out/Login state.
