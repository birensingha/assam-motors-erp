# Assam Motors — HR Fixed Salary & Absence Impact (Phase 4)

Staging-only integration for existing HR Manager account. No employee account, password, payroll slip, payables, salary deduction, customer ledger or invoice data is modified.

## Salary cost allocation versus production opportunity

For each configured staff + month, define:
- `monthly_salary`: actual fixed monthly pay (rupees; editable only by authorized HR Manager)
- `scheduled_workdays`: actual planned duty days this month, excluding approved weekly offs/public holidays
- `target_productive_hours_per_day`: realistic productive-hours target
- `estimated_recovery_rate_per_hour` (optional): estimated *realizable* labour revenue per productive hour

Only explicit distinct `attendance.status=absent` dates are counted. Pending/blank time records, approved `leave` and `off` are not counted as absent. The report does not determine whether absence is paid or unpaid.

Formulas:
- Allocated salary cost for absences = monthly_salary / scheduled_workdays × confirmed_absent_days.
- Estimated missed productive hours = confirmed_absent_days × target_productive_hours_per_day.
- Indicative lost labour opportunity = missed productive hours × configured estimated_recovery_rate_per_hour; blank if rate not configured.
- Actual ROT productivity and efficiency use the existing HR Operations ROT segment report. Shared ROTs must not double-credit standard hours.

These are **management indicators, not payroll deductions or realized-loss accounting**. Wage deductions, paid/unpaid leave, overtime, allowances and statutory contributions require distinct approved payroll rules.

## Security and audit
- Routes inherit existing native ERP HR Manager middleware and recheck `HrAccess::permitted` in both Web and API controllers.
- Employee salary configuration is month-specific; HR profile writes require an explanation and create an audit entry.
- Staff self-service routes and Android normal staff API do not include salary data.
- No defaults for actual salary, scheduled working days, hour targets or hourly recovery rate.

## Change and deployment
- Additive source files, two HR-only routes and one HR operations navigation link.
- Guarded installer checks current live source anchors and refuses duplicate installs.
- Staging backup for existing source; workflow preflights table metadata, installs, validates routes, runs additive migration, and guest security smoke.
- Test with month + employee from authorized HR account before rollout to staff app.