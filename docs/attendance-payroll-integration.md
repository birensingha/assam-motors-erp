# Assam Motors — Attendance Reporting & Payroll (integration work)

## Current status
This branch contains a **calculation engine and unit tests**, not a deployed or fully wired ERP feature. The repository's `main` currently lacks the Laravel runtime (`composer.json`, `artisan`, `routes/web.php`) and the original attendance schema/controllers. These must be added or made accessible before any safe integration.

## Requested report
- Filter **From Date / To Date** (inclusive), optional staff search, attendance status.
- Daily columns: staff ID/name, attendance date, check-in/out, worked minutes, overtime minutes, late minutes, status.
- Summary per staff: present/absent/half-day/paid leave/unpaid leave/weekly off/holiday, paid days.
- Export CSV/XLSX/PDF; dates interpreted in Asia/Kolkata.
- Leave historical data untouched; flag missed checkout, missing dates and unverified attendance.

## Salary model
- Employee-level effective-dated salary profile: monthly basic, pay divisor policy (calendar/fixed 30/scheduled days), paid weekly off/holiday, overtime hourly rate, allowances/deductions.
- Preview using `App\Services\Payroll\AttendancePayrollCalculator`. One employee and one calendar month per call. For arbitrary report ranges, salary **preview is not a complete monthly payable salary** until full month records and scheduled days have been reconciled.
- **Attendance records are source data**. Normalize one row per staff per date before invoking the calculator; duplicate dates error instead of silently double-counting.
- Missing day is NOT automatically absent. Resolve missing check-outs, approved leave, rest days, holidays and joining/exiting dates before approval.
- Salary advance is liability/asset adjustment, never a second deduction when already deducted via another posting. Reimbursement of staff-paid workshop expenses is a separate creditor/payable (vendor debit to staff credit where applicable), **not salary**.
- Workflow: Preview → HR review → Admin approve → Salary Payable entry → Payment voucher against salary payable; prohibit duplicate period+employee posting; reversal/audit for any correction.
- Store approved payroll as immutable snapshot of rates/attendance versions with approver, approval time, voucher reference, and reversal trail.

## Pending integration checklist
1. Restore actual Laravel ERP source and schema into the repo (exclude `.env`, credentials, customer exports, production backups).
2. Review existing Staff/Attendance entities, statuses, business rules, role middleware and real attendance time fields.
3. Add report endpoint with validated date bounds, staff scoping, pagination, CSV/XLSX/PDF exports.
4. Add salary profiles, effective dates, approved leave, holiday and overtime policies; migrations with rollback path.
5. Add payroll preview, locks, approval audit, and existing ledger voucher integration.
6. Test HR/Admin permissions; no staff access to other employees' salaries.
7. Deploy to staging only after backup and migrations review, verify report totals against saved attendance, then approve rollout.

## Sample integration
```php
$preview = app(\App\Services\Payroll\AttendancePayrollCalculator::class)->calculate(
    '2026-11-01',
    '2026-11-30',
    [['date'=>'2026-11-02','status'=>'present','work_minutes'=>480]],
    ['monthly_salary'=>30000,'payable_days_basis'=>'fixed_30']
);
```

No payment or salary posting occurs from this calculator.
