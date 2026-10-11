<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use App\Models\StaffDevice;
use App\Services\AttendanceService;
use App\Services\MandatoryNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class AttendanceController extends Controller
{
    public function today(Request $request, AttendanceService $service, MandatoryNotificationService $notifications): JsonResponse
    {
        $user = $this->staff($request);
        return response()->json([
            'attendance' => $service->today((int)$user->staff_id),
            'notification_compliance' => $notifications->notificationCompliance((int)$user->staff_id),
        ]);
    }

    public function checkIn(Request $request, AttendanceService $service, MandatoryNotificationService $notifications): JsonResponse
    {
        $user = $this->staff($request);
        $this->assertNotificationReady((int)$user->staff_id, $notifications);
        $data = $this->location($request);
        $this->assertFreshLocation((int)$data['location_time_ms']);
        $attendance = $service->checkIn((int)$user->staff_id, $data);
        $notifications->resolveByTarget((int)$user->staff_id, 'CHECKIN_MISSED', now('Asia/Kolkata')->toDateString());
        return response()->json(['ok' => true, 'attendance' => $attendance]);
    }

    public function checkOut(Request $request, AttendanceService $service, MandatoryNotificationService $notifications): JsonResponse
    {
        $user = $this->staff($request);
        $data = $this->location($request);
        $this->assertFreshLocation((int)$data['location_time_ms']);
        $attendance = $service->checkOut((int)$user->staff_id, $data);
        $notifications->resolveByTarget((int)$user->staff_id, 'CHECKOUT_PENDING', now('Asia/Kolkata')->toDateString());
        return response()->json(['ok' => true, 'attendance' => $attendance]);
    }

    private function assertNotificationReady(int $staffId, MandatoryNotificationService $notifications): void
    {
        $status = $notifications->notificationCompliance($staffId);
        if ($status['compliant'] ?? false) return;

        // v6.1+ supports native polling fallback when Firebase client config is
        // not yet installed. Notification permission must still be ON.
        $pollingCapable = StaffDevice::query()
            ->where('staff_id', $staffId)
            ->where('active', 1)
            ->where('notification_permission', 1)
            ->where('app_version', 'like', '6.1%')
            ->exists();

        if (!$pollingCapable) $notifications->assertCompliant($staffId);
    }

    private function location(Request $request): array
    {
        return $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['required', 'numeric', 'min:0', 'max:10000'],
            'is_mocked' => ['required', 'boolean'],
            'provider' => ['nullable', 'string', 'max:40'],
            'device_id' => ['nullable', 'string', 'max:160'],
            'app_version' => ['nullable', 'string', 'max:40'],
            'location_time_ms' => ['required', 'integer'],
            'device_time_ms' => ['nullable', 'integer'],
        ]);
    }

    private function assertFreshLocation(int $locationTimeMs): void
    {
        $nowMs = (int)round(microtime(true) * 1000);
        if ($locationTimeMs <= 0 || abs($nowMs - $locationTimeMs) > 180000) {
            throw ValidationException::withMessages([
                'location' => 'Fresh GPS fix required for Attendance.',
            ]);
        }
    }

    private function staff(Request $request): ErpAuthUser
    {
        $user = $request->user();
        abort_unless($user instanceof ErpAuthUser && $user->active && $user->isStaff() && !$user->isAdmin(), 403, 'Linked Staff login required.');
        return $user;
    }
}
