<?php
namespace App\Services;

/** Fixed-salary cost allocation and indicative absence exposure; NOT payroll. */
final class HrSalaryImpactCalculator
{
    public static function calculate(float $salary, int $scheduledDays, float $targetHoursPerDay, ?float $recoveryRate, int $confirmedAbsenceDays, float $productiveHours): array
    {
        if ($salary < 0 || $scheduledDays < 1 || $scheduledDays > 31 || $targetHoursPerDay < 0 || $targetHoursPerDay > 18 || ($recoveryRate !== null && $recoveryRate < 0)) {
            throw new \InvalidArgumentException('Invalid HR configuration');
        }
        $confirmedAbsenceDays = max(0, min($confirmedAbsenceDays, $scheduledDays));
        $dailySalary = $salary / $scheduledDays;
        $potentialHours = $confirmedAbsenceDays * $targetHoursPerDay;
        return [
            'monthly_fixed_salary' => round($salary, 2),
            'scheduled_workdays' => $scheduledDays,
            'confirmed_absent_days' => $confirmedAbsenceDays,
            'allocated_salary_per_workday' => round($dailySalary, 2),
            'absence_salary_cost_allocation' => round($dailySalary * $confirmedAbsenceDays, 2),
            'estimated_missed_productive_hours' => round($potentialHours, 2),
            'estimated_labour_opportunity_value' => $recoveryRate === null ? null : round($potentialHours * $recoveryRate, 2),
            'actual_recorded_productive_hours' => round(max(0, $productiveHours), 2),
            'salary_cost_per_recorded_productive_hour' => $productiveHours > 0 ? round($salary / $productiveHours, 2) : null,
            'salary_deduction' => 0.0,
            'interpretation' => 'Indicators only; salary remains fixed. This is not an approved deduction or guaranteed financial loss.',
        ];
    }
}