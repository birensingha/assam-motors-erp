<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\ErpAuthUser;
use App\Models\RotSession;
use App\Models\StaffLocationLog;
use App\Models\StaffLocationSetting;
use App\Services\GeoFenceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StaffLocationController extends Controller
{
    public function store(Request $request, GeoFenceService $geoFence): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof ErpAuthUser && $user->active && $user->isStaff() && !$user->isAdmin(), 403, 'Linked Staff login required.');

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['required', 'numeric', 'min:0', 'max:10000'],
            'is_mocked' => ['required', 'boolean'],
            'provider' => ['nullable', 'string', 'max:40'],
            'device_id' => ['nullable', 'string', 'max:160'],
            'app_version' => ['nullable', 'string', 'max:40'],
            'location_time_ms' => ['required', 'integer'],
            'source' => ['nullable', 'string', 'max:40'],
        ]);

        if ((bool)$data['is_mocked']) {
            return response()->json(['message' => 'Mock/Fake location is not allowed.', 'reason' => 'MOCK_LOCATION'], 422);
        }

        $settings = StaffLocationSetting::query()->whereKey(1)->first();
        if (!$settings || !$settings->tracking_enabled) {
            return response()->json(['message' => 'Duty location tracking is disabled.', 'reason' => 'TRACKING_DISABLED'], 409);
        }

        $staffId = (int)$user->staff_id;
        $attendance = Attendance::query()
            ->where('staff_id', $staffId)
            ->whereDate('date', CarbonImmutable::now('Asia/Kolkata')->toDateString())
            ->whereNotNull('check_in')->whereNull('check_out')
            ->orderByDesc('id')->first();

        if (!$attendance) {
            return response()->json(['message' => 'No active duty attendance.', 'reason' => 'NO_DUTY_ATTENDANCE'], 409);
        }

        $recordedAt = CarbonImmutable::createFromTimestampMs((int)$data['location_time_ms'], 'Asia/Kolkata');
        if ($recordedAt->lt(CarbonImmutable::parse($attendance->check_in, 'Asia/Kolkata')->subMinutes(5))) {
            return response()->json(['message' => 'Location sample is outside duty window.', 'reason' => 'OUTSIDE_DUTY_WINDOW'], 409);
        }

        $distance = null;
        $inside = null;
        if ($settings->latitude !== null && $settings->longitude !== null) {
            $distance = $geoFence->distanceMetres(
                (float)$data['latitude'],
                (float)$data['longitude'],
                (float)$settings->latitude,
                (float)$settings->longitude
            );
            $inside = $distance <= max(20, (int)$settings->radius_m);
        }

        $maxAccuracy = max(10, (int)$settings->max_accuracy_m);
        $quality = (float)$data['accuracy_m'] <= $maxAccuracy ? 'OK' : 'WEAK_ACCURACY';

        $activeRotId = RotSession::query()
            ->where('staff_id', $staffId)
            ->whereIn('status', ['Running','Paused','Assigned'])
            ->orderByRaw("FIELD(status,'Running','Paused','Assigned')")
            ->value('id');

        StaffLocationLog::query()->create([
            'staff_id' => $staffId,
            'attendance_id' => $attendance->id,
            'active_rot_id' => $activeRotId,
            'recorded_at' => $recordedAt,
            'received_at' => CarbonImmutable::now('Asia/Kolkata'),
            'latitude' => (float)$data['latitude'],
            'longitude' => (float)$data['longitude'],
            'accuracy_m' => (float)$data['accuracy_m'],
            'provider' => substr((string)($data['provider'] ?? ''), 0, 40) ?: null,
            'distance_m' => $distance !== null ? round($distance, 1) : null,
            'inside_geofence' => $inside,
            'quality' => $quality,
            'device_id' => substr((string)($data['device_id'] ?? ''), 0, 160) ?: null,
            'app_version' => substr((string)($data['app_version'] ?? ''), 0, 40) ?: null,
            'source' => substr((string)($data['source'] ?? 'ANDROID_FOREGROUND'), 0, 40),
            'device_time_ms' => (int)$data['location_time_ms'],
        ]);

        return response()->json([
            'ok' => true,
            'distance_m' => $distance !== null ? round($distance, 1) : null,
            'inside_geofence' => $inside,
            'quality' => $quality,
            'sampleMinutes' => max(5, min(60, (int)($settings->sample_minutes ?? 10))),
        ]);
    }
}
