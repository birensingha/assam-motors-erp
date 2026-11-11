<?php
declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * No database mutations. SQL ROT timestamps are stored as naive IST wall time,
 * whereas Eloquent JSON serializes offset-qualified timestamps in UTC.
 * Read the raw SQL value, not a browser clock or a model's serialized ISO.
 */
final class RotLiveClock
{
    public static function snapshot(object $rot): array
    {
        $status = strtolower(trim((string)($rot->status ?? 'Assigned')));
        $saved = max(0, (int)($rot->total_seconds ?? 0));
        $std = max(0, (int)round((float)($rot->std_hours ?? 0) * 3600));
        $startRaw = self::raw($rot, 'start_time');
        $firstRaw = self::raw($rot, 'first_start_at');
        $now = CarbonImmutable::now('Asia/Kolkata');
        $runStart = self::parseLocal($startRaw);
        $first = self::parseLocal($firstRaw);
        $validRunning = $status === 'running' && $runStart !== null;
        $activeSeconds = $validRunning ? max(0, $now->getTimestamp() - $runStart->getTimestamp()) : 0;
        $elapsed = $saved + $activeSeconds;
        $remaining = max(0, $std - $elapsed);
        $overtime = $std > 0 ? max(0, $elapsed - $std) : 0;

        return [
            'rot_id' => (string)($rot->id ?? ''),
            'status' => $status,
            'running' => $validRunning,
            'time_warning' => $status === 'running' && !$validRunning,
            'elapsed_seconds' => $elapsed,
            'saved_seconds' => $saved,
            'standard_seconds' => $std,
            'remaining_seconds' => $remaining,
            'overtime_seconds' => $overtime,
            'progress_percent' => $std > 0 ? (int)round(100 * $elapsed / $std) : 0,
            'started_ist' => $first?->format('h:i A') ?? '—',
            'expected_end_ist' => $validRunning && $std > 0
                ? $now->addSeconds($remaining)->format('h:i A')
                : ($status === 'paused' ? 'PAUSED' : '—'),
            'server_epoch_ms' => $now->getTimestamp() * 1000,
        ];
    }

    private static function raw(object $rot, string $field): ?string
    {
        $value = method_exists($rot, 'getRawOriginal')
            ? $rot->getRawOriginal($field)
            : ($rot->{$field} ?? null);
        if ($value === null || $value === '') return null;
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:sP');
        }
        return trim((string)$value);
    }

    private static function parseLocal(?string $value): ?CarbonImmutable
    {
        if (!$value) return null;
        try {
            // Explicit UTC or offset timestamp: do not apply a second IST offset.
            return CarbonImmutable::parse($value, 'Asia/Kolkata')->setTimezone('Asia/Kolkata');
        } catch (\Throwable) {
            return null;
        }
    }
}
