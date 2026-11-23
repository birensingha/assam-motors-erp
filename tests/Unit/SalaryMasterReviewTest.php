<?php

namespace Tests\Unit;

use App\Services\Payroll\SalaryProfileSelector;
use App\Services\Payroll\PayrollReadinessGuard;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SalaryMasterReviewTest extends TestCase
{
    public function test_current_salary_master_profile_maps_into_payroll_policy(): void
    {
        $policy = (new SalaryProfileSelector())->forPeriod('AM9472', '2026-11-01', '2026-11-30', [
            ['staff_code' => 'AM9472', 'effective_from' => '2026-10-01', 'effective_to' => null,
             'monthly_salary' => '30000.00', 'payable_days_basis' => 'fixed_30',
             'overtime_hourly_rate' => '100.00', 'paid_weekly_off' => true,
             'paid_holiday' => true, 'monthly_allowances' => '500.00'],
        ]);
        self::assertSame(30000.0, $policy['monthly_salary']);
        self::assertSame('fixed_30', $policy['payable_days_basis']);
        self::assertTrue($policy['paid_weekly_off']);
        self::assertSame(500.0, $policy['allowances']);
    }

    public function test_mid_month_raise_requires_manual_review(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new SalaryProfileSelector())->forPeriod('AM9472', '2026-11-01', '2026-11-30', [
            ['staff_code'=>'AM9472','effective_from'=>'2026-01-01','effective_to'=>'2026-11-14','monthly_salary'=>28000],
            ['staff_code'=>'AM9472','effective_from'=>'2026-11-15','effective_to'=>null,'monthly_salary'=>30000],
        ]);
    }

    public function test_unverified_and_missing_checkout_block_payroll(): void
    {
        $result = (new PayrollReadinessGuard())->inspect(
            '2026-11-01', '2026-11-02', ['2026-11-01', '2026-11-02'],
            [
                ['date'=>'2026-11-01','status'=>'present','verified'=>true,
                 'check_in'=>'09:00','check_out'=>'18:00'],
                ['date'=>'2026-11-02','status'=>'present','verified'=>false,
                 'check_in'=>'09:05','overtime_minutes'=>30],
            ]
        );
        self::assertFalse($result['ready']);
        self::assertSame(2, $result['days_recorded']);
        self::assertStringContainsString('Missing checkout', implode(';', $result['issues']));
        self::assertStringContainsString('Overtime not approved', implode(';', $result['issues']));
    }

    public function test_missing_date_is_not_auto_absent(): void
    {
        $result = (new PayrollReadinessGuard())->inspect(
            '2026-11-01','2026-11-02', ['2026-11-01','2026-11-02'],
            [['date'=>'2026-11-01','status'=>'present','verified'=>true]]
        );
        self::assertFalse($result['ready']);
        self::assertStringContainsString('Missing attendance day classification', implode(';', $result['issues']));
    }

    public function test_review_succeeds_only_with_verified_daily_rows(): void
    {
        $result = (new PayrollReadinessGuard())->inspect(
            '2026-11-01','2026-11-02', ['2026-11-01','2026-11-02'],
            [
                ['date'=>'2026-11-01','status'=>'weekly_off','verified'=>true],
                ['date'=>'2026-11-02','status'=>'present','verified'=>true,
                 'check_in'=>'09:00','check_out'=>'18:00','overtime_minutes'=>60,
                 'overtime_approved'=>true],
            ]
        );
        self::assertTrue($result['ready']);
        self::assertSame([], $result['issues']);
    }

    public function test_incomplete_roster_blocks_approval(): void
    {
        $result = (new PayrollReadinessGuard())->inspect(
            '2026-11-01','2026-11-02',['2026-11-01'],
            [['date'=>'2026-11-01','status'=>'present','verified'=>true]]
        );
        self::assertFalse($result['ready']);
        self::assertStringContainsString('Missing approved roster dates', implode(';', $result['issues']));
    }
}
