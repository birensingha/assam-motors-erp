<?php
/**
 * Dependency-free smoke test for the Attendance Report, Salary Master and approval guard.
 * Run: php tests/smoke/attendance_payroll.php
 * Does not connect to the database, alter payroll, or require vendor/autoload.php.
 */
declare(strict_types=1);

require_once __DIR__.'/../../app/Services/Attendance/AttendanceDateRangeReport.php';
require_once __DIR__.'/../../app/Services/Payroll/SalaryProfileSelector.php';
require_once __DIR__.'/../../app/Services/Payroll/PayrollReadinessGuard.php';
require_once __DIR__.'/../../app/Services/Payroll/ExistingHrSalaryProfileAdapter.php';

use App\Services\Attendance\AttendanceDateRangeReport;
use App\Services\Payroll\SalaryProfileSelector;
use App\Services\Payroll\PayrollReadinessGuard;
use App\Services\Payroll\ExistingHrSalaryProfileAdapter;

function check(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException('FAILED: '.$label);
    }
    echo "PASS: {$label}\n";
}

$report = (new AttendanceDateRangeReport())->build([
    ['staff_id'=>'AM9472','staff_name'=>'Sample Staff','date'=>'2026-11-01','status'=>'present','work_minutes'=>480],
    ['staff_id'=>'AM9472','staff_name'=>'Sample Staff','date'=>'2026-11-02','status'=>'missing_checkout','check_in'=>'09:00'],
    ['staff_id'=>'AM7887','staff_name'=>'Other Staff','date'=>'2026-11-01','status'=>'absent'],
], '2026-11-01','2026-11-02','AM9472');

check($report['total_rows'] === 2, 'inclusive From-To filter, staff-only rows');
check($report['staff_summary'][0]['review_days'] === 1, 'missing checkout reported');
check($report['payroll_ready'] === false, 'missing checkout not payroll-ready');
check(str_contains((new AttendanceDateRangeReport())->csv($report), 'Staff ID'), 'CSV export available');

$policy = (new SalaryProfileSelector())->forPeriod('AM9472','2026-11-01','2026-11-30', [
    ['staff_code'=>'AM9472','effective_from'=>'2026-10-01','monthly_salary'=>30000,
        'payable_days_basis'=>'calendar','overtime_hourly_rate'=>100],
]);
check($policy['monthly_salary'] === 30000.0, 'salary master rate selection');
$existing = (new ExistingHrSalaryProfileAdapter())->policyForMonth('4','2026-11', [
    ['staff_id'=>4,'report_month'=>'2026-11-01','monthly_salary'=>'27000.00',
     'scheduled_workdays'=>26,'estimated_recovery_rate_per_hour'=>780],
]);
check($existing['monthly_salary'] === 27000.0, 'existing HR monthly salary reused');
check($existing['overtime_hourly_rate'] === 0.0, 'recovery rate not treated as overtime wage');
check($existing['requires_hr_policy_confirmation'] === true, 'HR payroll policy review required');


$fullDates = [];
$fullRecords = [];
foreach (range(1, 30) as $n) {
    $date = sprintf('2026-11-%02d', $n);
    $fullDates[] = $date;
    $fullRecords[] = ['date'=>$date,'status'=>'weekly_off','verified'=>true];
}
$fullRecords[1] = [
    'date'=>'2026-11-02', 'status'=>'present', 'verified'=>true,
    'check_in'=>'09:00', 'check_out'=>'18:00',
    'overtime_minutes'=>60, 'overtime_approved'=>true,
];
$guard = new PayrollReadinessGuard();
check($guard->inspect('2026-11-01','2026-11-30',$fullDates,$fullRecords)['ready'] === true,
    'fully verified month may proceed to separate HR/Admin approval');
$fullRecords[1]['check_out'] = '';
check($guard->inspect('2026-11-01','2026-11-30',$fullDates,$fullRecords)['ready'] === false,
    'missing checkout blocks payroll');
check($guard->inspect('2026-11-01','2026-11-02',array_slice($fullDates,0,2),array_slice($fullRecords,0,2))['ready'] === false,
    'partial month blocked');

echo "Attendance/payroll smoke checks complete. No payroll was posted.\n";
