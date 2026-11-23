<?php

namespace Tests\Unit;

use App\Services\Payroll\ExistingHrSalaryProfileAdapter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ExistingHrSalaryProfileAdapterTest extends TestCase
{
    public function test_existing_salary_impact_row_is_preview_only(): void
    {
        $p = (new ExistingHrSalaryProfileAdapter())->policyForMonth('4', '2026-11', [
            ['staff_id' => 4, 'report_month' => '2026-11-01',
             'monthly_salary' => '27000.00', 'scheduled_workdays' => 26,
             'estimated_recovery_rate_per_hour' => 780],
        ]);

        self::assertSame(27000.0, $p['monthly_salary']);
        self::assertSame(26, $p['scheduled_work_days']);
        self::assertSame(0.0, $p['overtime_hourly_rate']);
        self::assertTrue($p['requires_hr_policy_confirmation']);
    }

    public function test_missing_profile_fails_closed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ExistingHrSalaryProfileAdapter())->policyForMonth('4', '2026-11', []);
    }

    public function test_duplicate_monthly_profile_fails_closed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ExistingHrSalaryProfileAdapter())->policyForMonth('4', '2026-11', [
            ['staff_id'=>4, 'report_month'=>'2026-11', 'monthly_salary'=>27000, 'scheduled_workdays'=>26],
            ['staff_id'=>4, 'report_month'=>'2026-11-01', 'monthly_salary'=>28000, 'scheduled_workdays'=>26],
        ]);
    }
}
