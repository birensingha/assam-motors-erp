<?php

namespace App\Services\Payroll;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Conservative payroll completeness check. Nothing is approved or posted here.
 * Expected dates must come from an HR-approved calendar/roster and must cover
 * EVERY calendar date in the payroll preview period, including rest days.
 */
final class PayrollReadinessGuard
{
    /**
     * @param array<int,string> $expectedDates Complete calendar days in this period
     * @param array<int,array<string,mixed>> $attendance Daily rows for ONE staff member
     * @return array{ready:bool,issues:array<int,string>,days_expected:int,days_recorded:int}
     */
    public function inspect(string $from, string $to, array $expectedDates, array $attendance): array
    {
        $first = $this->validDate($from);
        $last = $this->validDate($to);
        if ($first > $last || $first->format('Y-m') !== $last->format('Y-m')) {
            throw new InvalidArgumentException('Readiness guard requires a valid single-month period.');
        }

        $issues = [];
        if ($first->format('j') !== '1' || $last->format('Y-m-d') !== $last->format('Y-m-t')) {
            $issues[] = 'Monthly payroll approval needs the full calendar month. For joiners/leavers, use a separate authorised prorated workflow.';
        }
        $expected = [];
        foreach ($expectedDates as $date) {
            $this->validDate($date);
            if ($date < $from || $date > $to || isset($expected[$date])) {
                throw new InvalidArgumentException('Roster has duplicate/out-of-period date: '.$date);
            }
            $expected[$date] = true;
        }

        $allCalendar = [];
        for ($day = $first; $day <= $last; $day = $day->modify('+1 day')) {
            $allCalendar[$day->format('Y-m-d')] = true;
        }
        $unrostered = array_diff_key($allCalendar, $expected);
        if ($unrostered) {
            $issues[] = 'Missing approved roster dates: '.implode(', ', array_keys($unrostered));
        }

        $seen = [];
        foreach ($attendance as $row) {
            $date = (string) ($row['date'] ?? '');
            $this->validDate($date);
            if ($date < $from || $date > $to) {
                throw new InvalidArgumentException('Attendance outside salary period: '.$date);
            }
            if (isset($seen[$date])) {
                throw new InvalidArgumentException('Duplicate staff attendance date: '.$date);
            }
            $seen[$date] = true;

            if (!isset($expected[$date])) {
                $issues[] = 'Attendance date not in approved roster: '.$date;
            }
            $status = (string) ($row['status'] ?? '');
            if (!in_array($status, ['present','absent','half_day','paid_leave','unpaid_leave','weekly_off','holiday'], true)) {
                $issues[] = 'Unresolved attendance status: '.$date;
            }
            if (($row['verified'] ?? false) !== true) {
                $issues[] = 'Attendance needs HR verification: '.$date;
            }
            if (in_array($status, ['present','half_day'], true)
                && empty($row['check_in'])) {
                $issues[] = 'Missing check-in: '.$date;
            }
            if (($row['missing_checkout'] ?? false) === true
                || (in_array($status, ['present','half_day'], true) && empty($row['check_out']))) {
                $issues[] = 'Missing checkout: '.$date;
            }

            $ot = $row['overtime_minutes'] ?? 0;
            if (filter_var($ot, FILTER_VALIDATE_INT) === false || (int) $ot < 0 || (int) $ot > 1440) {
                throw new InvalidArgumentException('Invalid overtime minutes: '.$date);
            }
            if ((int) $ot > 0 && ($row['overtime_approved'] ?? false) !== true) {
                $issues[] = 'Overtime not approved: '.$date;
            }
        }

        $missing = array_diff_key($expected, $seen);
        if ($missing) {
            $issues[] = 'Missing attendance day classification: '.implode(', ', array_keys($missing));
        }

        return [
            'ready' => $issues === [],
            'issues' => $issues,
            'days_expected' => count($allCalendar),
            'days_recorded' => count($seen),
        ];
    }

    private function validDate(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Invalid date: '.$value);
        }
        return $date;
    }
}
