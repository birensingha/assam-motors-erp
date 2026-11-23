<?php

namespace App\Services\Payroll;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Pure calculation layer. Does not write salary vouchers or edit attendance.
 * Caller must normalize attendance to one row per employee per local date.
 */
final class AttendancePayrollCalculator
{
    /**
     * @param array $attendance Rows: date (Y-m-d), status (present|absent|half_day|paid_leave|unpaid_leave|weekly_off|holiday),
     *   check_in, check_out (optional), work_minutes, overtime_minutes (optional)
     * @param array $policy monthly_salary, payable_days_basis (calendar|fixed_30|working_days),
     *   scheduled_work_days (only when working_days), paid_weekly_off (bool), paid_holiday (bool),
     *   overtime_hourly_rate, allowances, deductions, salary_advance (nonnegative numeric)
     * @return array A read-only preview with line items and payroll totals.
     */
    public function calculate(string $from, string $to, array $attendance, array $policy): array
    {
        try {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $from);
            $end = CarbonImmutable::createFromFormat('!Y-m-d', $to);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('Invalid report dates. Use Y-m-d.', 0, $e);
        }
        if (!$start || !$end || $start->format('Y-m-d') !== $from || $end->format('Y-m-d') !== $to || $end->lessThan($start)) {
            throw new InvalidArgumentException('Invalid From/To date range.');
        }
        if ($start->format('Y-m') !== $end->format('Y-m')) {
            throw new InvalidArgumentException('Payroll preview must be generated for a single calendar month. Split multi-month reports.');
        }

        $salary = $this->amount($policy, 'monthly_salary');
        $basis = $policy['payable_days_basis'] ?? 'calendar';
        $daysInMonth = $start->daysInMonth;
        $divisor = match ($basis) {
            'calendar' => $daysInMonth,
            'fixed_30' => 30,
            'working_days' => (int) ($policy['scheduled_work_days'] ?? 0),
            default => throw new InvalidArgumentException('Unknown payable-days basis.'),
        };
        if ($divisor < 1 || $divisor > 31) {
            throw new InvalidArgumentException('Invalid scheduled work days.');
        }

        $seen = [];
        $rows = [];
        $counts = array_fill_keys(['present','absent','half_day','paid_leave','unpaid_leave','weekly_off','holiday'], 0);
        $paidDays = 0.0;
        $workMinutes = 0;
        $overtimeMinutes = 0;

        foreach ($attendance as $item) {
            $date = (string) ($item['date'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !CarbonImmutable::hasFormatWithModifiers($date, 'Y-m-d')) {
                throw new InvalidArgumentException('Attendance row has invalid date.');
            }
            if ($date < $from || $date > $to) {
                continue;
            }
            if (isset($seen[$date])) {
                throw new InvalidArgumentException('Duplicate attendance date: '.$date);
            }
            $seen[$date] = true;
            $status = (string) ($item['status'] ?? '');
            if (!array_key_exists($status, $counts)) {
                throw new InvalidArgumentException('Unrecognized attendance status for '.$date);
            }
            $counts[$status]++;
            $fraction = match ($status) {
                'present', 'paid_leave' => 1.0,
                'half_day' => 0.5,
                'weekly_off' => !empty($policy['paid_weekly_off']) ? 1.0 : 0.0,
                'holiday' => !empty($policy['paid_holiday']) ? 1.0 : 0.0,
                default => 0.0,
            };
            $paidDays += $fraction;
            $minutes = $this->minutes($item, 'work_minutes');
            $ot = $this->minutes($item, 'overtime_minutes');
            $workMinutes += $minutes;
            $overtimeMinutes += $ot;
            $rows[] = [
                'date' => $date,
                'status' => $status,
                'check_in' => $item['check_in'] ?? null,
                'check_out' => $item['check_out'] ?? null,
                'work_minutes' => $minutes,
                'overtime_minutes' => $ot,
                'paid_day_fraction' => $fraction,
            ];
        }
        usort($rows, static fn ($a, $b) => strcmp($a['date'], $b['date']));

        $dailyRate = $salary / $divisor;
        $earned = round($dailyRate * $paidDays, 2);
        $overtime = round(($overtimeMinutes / 60) * $this->amount($policy, 'overtime_hourly_rate'), 2);
        $allowances = $this->amount($policy, 'allowances');
        $deductions = $this->amount($policy, 'deductions');
        $advance = $this->amount($policy, 'salary_advance');

        return [
            'from' => $from, 'to' => $to, 'rows' => $rows, 'counts' => $counts,
            'total_days_recorded' => count($rows), 'paid_days' => $paidDays,
            'work_minutes' => $workMinutes, 'overtime_minutes' => $overtimeMinutes,
            'salary_basis' => $basis, 'salary_divisor' => $divisor,
            'monthly_salary' => $salary, 'daily_rate' => round($dailyRate, 4),
            'earned_basic' => $earned, 'overtime_pay' => $overtime,
            'allowances' => $allowances, 'deductions' => $deductions,
            'salary_advance' => $advance,
            'net_salary' => round($earned + $overtime + $allowances - $deductions - $advance, 2),
            'requires_review' => true, // approval is always required before posting
        ];
    }

    private function amount(array $values, string $key): float
    {
        $value = $values[$key] ?? 0;
        if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < 0) {
            throw new InvalidArgumentException('Invalid nonnegative amount: '.$key);
        }
        return (float) $value;
    }

    private function minutes(array $values, string $key): int
    {
        $value = $values[$key] ?? 0;
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 0 || (int) $value > 1440) {
            throw new InvalidArgumentException('Invalid minutes: '.$key);
        }
        return (int) $value;
    }
}
