<?php

namespace Tests\Unit;

use App\Services\Payroll\AttendancePayrollCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AttendancePayrollCalculatorTest extends TestCase
{
    public function test_salary_with_half_day_leave_overtime_and_advance(): void
    {
        $result = (new AttendancePayrollCalculator())->calculate(
            '2026-11-01', '2026-11-30',
            [
                ['date'=>'2026-11-02','status'=>'present','work_minutes'=>480,'overtime_minutes'=>120],
                ['date'=>'2026-11-03','status'=>'half_day','work_minutes'=>240],
                ['date'=>'2026-11-04','status'=>'paid_leave'],
                ['date'=>'2026-11-05','status'=>'absent'],
            ],
            ['monthly_salary'=>30000,'payable_days_basis'=>'fixed_30','overtime_hourly_rate'=>100,'allowances'=>500,'deductions'=>100,'salary_advance'=>1000]
        );
        self::assertSame(2.5, $result['paid_days']);
        self::assertSame(2500.0, $result['earned_basic']);
        self::assertSame(200.0, $result['overtime_pay']);
        self::assertSame(2100.0, $result['net_salary']);
        self::assertTrue($result['requires_review']);
    }

    public function test_duplicate_dates_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new AttendancePayrollCalculator())->calculate('2026-11-01','2026-11-30', [
            ['date'=>'2026-11-02','status'=>'present'],
            ['date'=>'2026-11-02','status'=>'present'],
        ], ['monthly_salary'=>20000]);
    }

    public function test_multimonth_payroll_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new AttendancePayrollCalculator())->calculate('2026-11-01','2026-12-31',[],['monthly_salary'=>20000]);
    }
}
