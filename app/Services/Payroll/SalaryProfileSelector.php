<?php

namespace App\Services\Payroll;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Pick ONE effective-dated salary profile for a complete payroll period.
 * A mid-period salary revision must be split or explicitly reviewed; never
 * silently use the final salary rate for the whole month.
 *
 * This is a pure function; the caller must scope staff and authorize HR access.
 */
final class SalaryProfileSelector
{
    /**
     * @param array<int,array<string,mixed>> $profiles
     * @return array<string,mixed> Salary policy accepted by AttendancePayrollCalculator.
     */
    public function forPeriod(string $staffCode, string $from, string $to, array $profiles): array
    {
        $this->date($from);
        $this->date($to);
        if ($from > $to) {
            throw new InvalidArgumentException('Invalid salary period range.');
        }
        if (trim($staffCode) === '') {
            throw new InvalidArgumentException('Staff code is required.');
        }

        $overlapping = [];
        $covering = [];
        $starts = [];

        foreach ($profiles as $profile) {
            if ((string) ($profile['staff_code'] ?? '') !== $staffCode) {
                continue;
            }
            $start = (string) ($profile['effective_from'] ?? '');
            $end = $profile['effective_to'] ?? null;
            $this->date($start);
            if ($end !== null && $end !== '') {
                $end = (string) $end;
                $this->date($end);
                if ($end < $start) {
                    throw new InvalidArgumentException('Salary profile end is before start.');
                }
            } else {
                $end = null;
            }
            if (isset($starts[$start])) {
                throw new InvalidArgumentException('Duplicate effective-from salary profile for staff.');
            }
            $starts[$start] = true;

            if ($start <= $to && ($end === null || $end >= $from)) {
                $overlapping[] = $profile;
            }
            if ($start <= $from && ($end === null || $end >= $to)) {
                $covering[] = $profile;
            }
        }

        if (count($covering) !== 1 || count($overlapping) !== 1) {
            throw new InvalidArgumentException(
                'Salary Master missing, overlapping, or revised within the selected period. HR must review.'
            );
        }

        $profile = $covering[0];
        $basis = $profile['payable_days_basis'] ?? 'calendar';
        if (!in_array($basis, ['calendar','fixed_30','working_days'], true)) {
            throw new InvalidArgumentException('Invalid salary divisor policy.');
        }
        $workingDays = $profile['scheduled_work_days'] ?? null;
        if ($basis === 'working_days' && (!is_numeric($workingDays) || (int) $workingDays < 1 || (int) $workingDays > 31)) {
            throw new InvalidArgumentException('Working-days divisor requires scheduled work days.');
        }

        $amountFields = ['monthly_salary','overtime_hourly_rate','monthly_allowances'];
        foreach ($amountFields as $field) {
            $value = $profile[$field] ?? 0;
            if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < 0) {
                throw new InvalidArgumentException('Invalid Salary Master amount: '.$field);
            }
        }

        return [
            'salary_profile_effective_from' => (string) $profile['effective_from'],
            'salary_profile_effective_to' => $profile['effective_to'] ?? null,
            'monthly_salary' => (float) $profile['monthly_salary'],
            'payable_days_basis' => $basis,
            'scheduled_work_days' => $basis === 'working_days' ? (int) $workingDays : null,
            'overtime_hourly_rate' => (float) ($profile['overtime_hourly_rate'] ?? 0),
            'paid_weekly_off' => (bool) ($profile['paid_weekly_off'] ?? false),
            'paid_holiday' => (bool) ($profile['paid_holiday'] ?? false),
            'allowances' => (float) ($profile['monthly_allowances'] ?? 0),
        ];
    }

    private function date(string $value): void
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Invalid salary effective date: '.$value);
        }
    }
}
