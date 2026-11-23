<?php

namespace App\Services\Payroll;

use InvalidArgumentException;

/**
 * Read-only bridge from live HR salary impact rows into a payroll preview.
 *
 * The existing hr_salary_impact_profiles contains one monthly salary per
 * employee/month, not effective-dated HR contract history. Treat this as
 * a PREVIEW source, never final payroll authority without HR approval.
 *
 * Estimated recovery per hour is a workshop profitability metric;
 * it must NOT be mistaken for overtime wages.
 */
final class ExistingHrSalaryProfileAdapter
{
    /**
     * @param array<int,array<string,mixed>> $profiles Authorized, normalized HR rows
     * @return array<string,mixed> Input policy for AttendancePayrollCalculator
     */
    public function policyForMonth(string $staffId, string $yearMonth, array $profiles): array
    {
        if (trim($staffId) === '' || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $yearMonth)) {
            throw new InvalidArgumentException('Expected staff ID and YYYY-MM salary month.');
        }

        $matches = [];
        foreach ($profiles as $profile) {
            $id = trim((string) ($profile['staff_id'] ?? ''));
            $rawMonth = trim((string) ($profile['report_month'] ?? ''));
            if ($id !== $staffId || !preg_match('/^\d{4}-(0[1-9]|1[0-2])(?:-01)?$/', $rawMonth)
                || substr($rawMonth, 0, 7) !== $yearMonth) {
                continue;
            }
            $matches[] = $profile;
        }
        if (count($matches) !== 1) {
            throw new InvalidArgumentException('HR monthly salary profile missing/duplicated; requires reconciliation.');
        }
        $item = $matches[0];
        $salary = $item['monthly_salary'] ?? null;
        $days = $item['scheduled_workdays'] ?? null;
        if (!is_numeric($salary) || !is_finite((float) $salary) || (float) $salary < 0
            || filter_var($days, FILTER_VALIDATE_INT) === false || (int) $days < 1 || (int) $days > 31) {
            throw new InvalidArgumentException('HR salary or scheduled workdays invalid.');
        }

        return [
            'source_table' => 'hr_salary_impact_profiles',
            'staff_id' => $staffId,
            'salary_month' => $yearMonth,
            'monthly_salary' => (float) $salary,
            // Existing scheduled_workdays is used only when HR confirms this
            // represents PAY divisor, not solely productivity target.
            'payable_days_basis' => 'working_days',
            'scheduled_work_days' => (int) $days,
            'overtime_hourly_rate' => 0.0,
            'paid_weekly_off' => false,
            'paid_holiday' => false,
            'allowances' => 0.0,
            'deductions' => 0.0,
            'salary_advance' => 0.0,
            'requires_hr_policy_confirmation' => true,
        ];
    }
}
