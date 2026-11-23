# Attendance report integration contract (ERP staging)

## New code in this branch
- `app/Services/Attendance/AttendanceDateRangeReport.php` — inclusive From/To filtering, optional staff filter, staff-wise summary, missing-checkout review flag, escaped CSV export (UTF-8 BOM).
- `resources/views/admin/attendance/range-report.blade.php` — GET form with date pickers, all/staff filter, summary, daily table, CSV URL and print/PDF via browser.
- `tests/Unit/AttendanceDateRangeReportTest.php` — report/calculation guard cases.
- `app/Services/Payroll/AttendancePayrollCalculator.php` — **preview only**, with tests.

## Required wiring once real Laravel source is available
1. Review existing attendance table and Staff/Auth relations. Keep any existing Attendance module routes and roles intact.
2. Add admin/HR-only GET route for date report. The view assumes `request('from')`, `request('to')`, `request('staff_id')` and optional `export=csv` on the same URL. Use separate explicit authorization for salary access.
3. Validate ISO dates, `from <= to`, pagination/date span limits; default range is current month in **Asia/Kolkata**. Do NOT trust raw staff_id as an access grant.
4. Database query must scope by authenticated user's permitted staff IDs **before** loading records, filter dates inclusive, and order date/staff; normalize per staff/local day. If there are multiple raw punches, aggregate before sending to report service. Do not cast timestamp to date in UTC.
5. Map each daily row to:
   ```php
   [
     'staff_id' => 'AM9472',
     'staff_name' => 'Kapil',
     'date' => '2026-11-02',
     'status' => 'present',
     'check_in' => '09:00',
     'check_out' => '18:00',
     'work_minutes' => 480,
     'late_minutes' => 0,
     'overtime_minutes' => 0,
   ]
   ```
   These are **illustrative**, not real records.
6. Call:
   ```php
   $report = app(\App\Services\Attendance\AttendanceDateRangeReport::class)
     ->build($normalizedRows, $from, $to, $selectedStaffId);
   if (request('export') === 'csv') {
       $csv = app(\App\Services\Attendance\AttendanceDateRangeReport::class)->csv($report);
       return response($csv, 200, [
           'Content-Type' => 'text/csv; charset=UTF-8',
           'Content-Disposition' => 'attachment; filename="assam-attendance-report.csv"',
       ]);
   }
   return view('admin.attendance.range-report', [
       'report' => $report,
       'staffOptions' => $authorizedStaffOptions,
   ]);
   ```
7. Verify CSV and HTML from same filtered data set; for large ranges use **server query pagination and/or stream CSV**. This in-memory service is intended for normalized bounded rows, not direct raw table scans.
8. Before payroll approval: classify missing/partial days, leaves, paid holidays and scheduled off days; do not treat missing rows as absences and do not auto-post salary.

## Incomplete until real source arrives
Existing staging Laravel `composer.json`, `artisan`, `routes/web.php`, controllers, migrations, and authentication/ledger code are not in this repository snapshot. The view and services are not linked to staging routes or live data. **Do not merge/deploy as a finished feature.**

## Safety
No attendance editing, no financial posting, no credentials in GitHub. Audit changes to attendance and payroll approval/void transactions in the actual ERP.
