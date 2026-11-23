<?php

namespace App\Services\Attendance;

use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

/**
 * Builds inclusive date-range reports from normalized daily attendance.
 *
 * Input rows must already be authorization-scoped and aggregated to
 * one row per staff_id/date by the calling repository layer. This class
 * does NOT fetch or mutate attendance data.
 */
final class AttendanceDateRangeReport
{
    private const STATUSES = [
        'present', 'absent', 'half_day', 'paid_leave', 'unpaid_leave',
        'weekly_off', 'holiday', 'missing_checkout',
    ];

    /**
     * @param array<int, array<string,mixed>> $attendance
     * @return array<string,mixed>
     */
    public function build(array $attendance, string $from, string $to, ?string $staffId = null): array
    {
        $this->validDate($from);
        $this->validDate($to);
        if ($from > $to) {
            throw new InvalidArgumentException('From Date must not exceed To Date.');
        }
        if ($staffId !== null && trim($staffId) === '') {
            throw new InvalidArgumentException('Staff filter must be non-empty.');
        }

        $rows = [];
        $byStaff = [];
        $seen = [];

        foreach ($attendance as $source) {
            $date = (string) ($source['date'] ?? '');
            $this->validDate($date);
            if ($date < $from || $date > $to) {
                continue;
            }

            $id = trim((string) ($source['staff_id'] ?? ''));
            if ($id === '') {
                throw new InvalidArgumentException('Attendance staff_id is required.');
            }
            if ($staffId !== null && $id !== $staffId) {
                continue;
            }
            $key = $id.'|'.$date;
            if (isset($seen[$key])) {
                throw new InvalidArgumentException('Duplicate staff attendance day: '.$key);
            }
            $seen[$key] = true;

            $status = strtolower(trim((string) ($source['status'] ?? '')));
            if (!in_array($status, self::STATUSES, true)) {
                throw new InvalidArgumentException('Unknown attendance status on '.$date.': '.$status);
            }

            $work = $this->minutes($source, 'work_minutes');
            $late = $this->minutes($source, 'late_minutes');
            $overtime = $this->minutes($source, 'overtime_minutes');
            $checkIn = $this->optionalText($source['check_in'] ?? null);
            $checkOut = $this->optionalText($source['check_out'] ?? null);
            $needsReview = $status === 'missing_checkout' || ($checkIn !== '' && $checkOut === '');

            $row = [
                'staff_id' => $id,
                'staff_name' => trim((string) ($source['staff_name'] ?? '')),
                'date' => $date,
                'status' => $status,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'work_minutes' => $work,
                'late_minutes' => $late,
                'overtime_minutes' => $overtime,
                'needs_review' => $needsReview,
            ];

            $rows[] = $row;
            if (!isset($byStaff[$id])) {
                $byStaff[$id] = [
                    'staff_id' => $id,
                    'staff_name' => $row['staff_name'],
                    'recorded_days' => 0,
                    'work_minutes' => 0,
                    'late_minutes' => 0,
                    'overtime_minutes' => 0,
                    'review_days' => 0,
                    'status_counts' => array_fill_keys(self::STATUSES, 0),
                ];
            }
            $byStaff[$id]['recorded_days']++;
            $byStaff[$id]['work_minutes'] += $work;
            $byStaff[$id]['late_minutes'] += $late;
            $byStaff[$id]['overtime_minutes'] += $overtime;
            $byStaff[$id]['review_days'] += $needsReview ? 1 : 0;
            $byStaff[$id]['status_counts'][$status]++;
        }

        usort($rows, static fn (array $a, array $b): int =>
            strcmp($a['date'], $b['date']) ?: strcmp($a['staff_id'], $b['staff_id']));
        uasort($byStaff, static fn (array $a, array $b): int =>
            strcmp($a['staff_name'], $b['staff_name']) ?: strcmp($a['staff_id'], $b['staff_id']));

        return [
            'from' => $from,
            'to' => $to,
            'staff_id' => $staffId,
            'rows' => $rows,
            'staff_summary' => array_values($byStaff),
            'total_rows' => count($rows),
            'payroll_ready' => count(array_filter($rows, static fn (array $row): bool => $row['needs_review'])) === 0,
        ];
    }

    /** RFC 4180-style CSV; neutralizes spreadsheet formula injection. */
    public function csv(array $report): string
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new RuntimeException('Cannot prepare CSV export.');
        }
        try {
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, [
                'Staff ID', 'Staff Name', 'Date', 'Status', 'Check In', 'Check Out',
                'Worked Minutes', 'Late Minutes', 'Overtime Minutes', 'Needs Review',
            ], ',', '"', '');
            foreach ($report['rows'] ?? [] as $row) {
                fputcsv($stream, [
                    $this->csvSafe((string) $row['staff_id']),
                    $this->csvSafe((string) $row['staff_name']),
                    $row['date'], $row['status'],
                    $this->csvSafe((string) $row['check_in']),
                    $this->csvSafe((string) $row['check_out']),
                    $row['work_minutes'], $row['late_minutes'], $row['overtime_minutes'],
                    $row['needs_review'] ? 'YES' : 'NO',
                ], ',', '"', '');
            }
            rewind($stream);
            return stream_get_contents($stream) ?: '';
        } finally {
            fclose($stream);
        }
    }

    private function validDate(string $date): void
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Invalid attendance/report date: '.$date);
        }
    }

    private function minutes(array $row, string $field): int
    {
        $value = $row[$field] ?? 0;
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 0 || (int) $value > 1440) {
            throw new InvalidArgumentException('Invalid '.$field.'.');
        }
        return (int) $value;
    }

    private function optionalText(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (!is_scalar($value)) {
            throw new InvalidArgumentException('Check-in/out must be text or null.');
        }
        return trim((string) $value);
    }

    private function csvSafe(string $value): string
    {
        if (preg_match('/^\s*[=+\-@]/u', $value) === 1) {
            return "'".$value;
        }
        return $value;
    }
}
