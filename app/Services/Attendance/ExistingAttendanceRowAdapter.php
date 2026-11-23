<?php

namespace App\Services\Attendance;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Map existing STAGING `attendance` fields to the read-only report contract.
 * Existing values: id, staff_id, staff_code, staff_name, date, check_in,
 * check_out, worked_hours, status, late, notes, created_at.
 *
 * Never writes to old attendance, never infers verification from timestamps.
 */
final class ExistingAttendanceRowAdapter
{
    /** @param array<string,mixed> $source */
    public function map(array $source): array
    {
        $date = trim((string) ($source['date'] ?? ''));
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Invalid saved attendance date.');
        }

        $staff = trim((string) ($source['staff_code'] ?? ''));
        if ($staff === '') {
            $staff = trim((string) ($source['staff_id'] ?? ''));
        }
        if ($staff === '') {
            throw new InvalidArgumentException('Missing staff identity in attendance.');
        }

        $rawStatus = strtolower(trim((string) ($source['status'] ?? '')));
        $status = match ($rawStatus) {
            'present' => 'present',
            'absent' => 'absent',
            'off' => 'weekly_off',
            default => throw new InvalidArgumentException('Unknown attendance status: '.$rawStatus),
        };

        $checkIn = trim((string) ($source['check_in'] ?? ''));
        $checkOut = trim((string) ($source['check_out'] ?? ''));
        $missingCheckout = $status === 'present' && $checkIn !== '' && $checkOut === '';
        $rawLate = strtolower(trim((string) ($source['late'] ?? '')));

        return [
            'staff_id' => $staff,
            'staff_numeric_id' => $source['staff_id'] ?? null,
            'staff_name' => trim((string) ($source['staff_name'] ?? '')),
            'date' => $date,
            'status' => $status,
            'source_status' => $rawStatus,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'work_minutes' => $this->workMinutes($source['worked_hours'] ?? null),
            // Existing "late" may be a flag, not a duration. Do not guess minutes.
            'late_flag' => in_array($rawLate, ['1','yes','true','late'], true),
            'late_minutes' => 0,
            'overtime_minutes' => 0, // needs separate approved overtime source
            'missing_checkout' => $missingCheckout,
            'verified' => false, // HR must verify before payroll approval
        ];
    }

    private function workMinutes(mixed $raw): int
    {
        if ($raw === null || $raw === '') {
            return 0;
        }
        if (is_numeric($raw)) {
            $hours = (float) $raw;
            if (!is_finite($hours) || $hours < 0 || $hours > 24) {
                throw new InvalidArgumentException('Invalid saved worked_hours.');
            }
            return (int) round($hours * 60);
        }
        if (is_string($raw) && preg_match('/^(\d{1,2}):([0-5]\d)$/', $raw, $m)) {
            $minutes = ((int) $m[1]) * 60 + (int) $m[2];
            if ($minutes > 1440) {
                throw new InvalidArgumentException('Invalid HH:MM worked_hours.');
            }
            return $minutes;
        }
        throw new InvalidArgumentException('Unrecognized saved worked_hours format.');
    }
}
