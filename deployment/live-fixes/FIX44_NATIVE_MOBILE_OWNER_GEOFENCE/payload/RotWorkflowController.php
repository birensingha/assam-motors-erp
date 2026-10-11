<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use App\Services\GeoFenceService;
use App\Services\RotWorkflowService;
use App\Services\MandatoryNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class RotWorkflowController extends Controller
{
    public function start(Request $request, string $session, RotWorkflowService $service, MandatoryNotificationService $notifications, GeoFenceService $geoFence): JsonResponse
    {
        [$user, $restrictedStaffId] = $this->actor($request, false);
        if ($restrictedStaffId) {
            $notifications->assertCompliant($restrictedStaffId);
            $this->assertStaffGeoFence($request, $geoFence);
        }
        $rot = $service->start($session, $user->role, $user->staff_id, $restrictedStaffId);
        if ($restrictedStaffId) $notifications->resolveByTarget($restrictedStaffId, 'ROT_NOT_STARTED', $session);
        return response()->json(['ok' => true, 'rot' => $rot]);
    }

    public function pause(Request $request, string $session, RotWorkflowService $service): JsonResponse
    {
        [$user, $restrictedStaffId] = $this->actor($request, false);
        $data = $request->validate([
            'pause_reason' => ['required', 'string', 'max:120'],
            'pause_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $rot = $service->pause($session, $data['pause_reason'], $data['pause_note'] ?? null, $user->role, $user->staff_id, $restrictedStaffId);
        return response()->json(['ok' => true, 'rot' => $rot]);
    }

    public function resume(Request $request, string $session, RotWorkflowService $service, MandatoryNotificationService $notifications, GeoFenceService $geoFence): JsonResponse
    {
        [$user, $restrictedStaffId] = $this->actor($request, false);
        if ($restrictedStaffId) {
            $notifications->assertCompliant($restrictedStaffId);
            $this->assertStaffGeoFence($request, $geoFence);
        }
        $rot = $service->resume($session, $user->role, $user->staff_id, $restrictedStaffId);
        if ($restrictedStaffId) $notifications->resolveByTarget($restrictedStaffId, 'ROT_PAUSED_TOO_LONG', $session);
        return response()->json(['ok' => true, 'rot' => $rot]);
    }

    public function complete(Request $request, string $session, RotWorkflowService $service, MandatoryNotificationService $notifications): JsonResponse
    {
        [$user, $restrictedStaffId] = $this->actor($request, false);
        $rot = $service->complete($session, $user->role, $user->staff_id, $restrictedStaffId);
        if ($restrictedStaffId) {
            $notifications->resolveByTarget($restrictedStaffId, 'ROT_NOT_STARTED', $session);
            $notifications->resolveByTarget($restrictedStaffId, 'ROT_PAUSED_TOO_LONG', $session);
        }
        return response()->json(['ok' => true, 'rot' => $rot]);
    }

    public function reopen(Request $request, string $session, RotWorkflowService $service): JsonResponse
    {
        $user = $this->admin($request);
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'note' => ['required', 'string', 'max:2000'],
        ]);
        return response()->json([
            'ok' => true,
            'rot' => $service->reopenResetEndTime($session, $data['reason'], $data['note'], $user->email ?: $user->name),
        ]);
    }

    public function changeMechanic(Request $request, string $session, RotWorkflowService $service): JsonResponse
    {
        $user = $this->admin($request);
        $data = $request->validate([
            'staff_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
            'note' => ['required', 'string', 'max:2000'],
        ]);
        return response()->json([
            'ok' => true,
            'rot' => $service->changeMechanic($session, (int)$data['staff_id'], $data['reason'], $data['note'], $user->email ?: $user->name),
        ]);
    }

    public function history(Request $request, string $session, RotWorkflowService $service): JsonResponse
    {
        $this->admin($request);
        return response()->json(['ok' => true] + $service->history($session));
    }

    private function assertStaffGeoFence(Request $request, GeoFenceService $geoFence): void
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['required', 'numeric', 'min:0', 'max:10000'],
            'is_mocked' => ['required', 'boolean'],
            'location_time_ms' => ['required', 'integer'],
        ]);

        $nowMs = (int)round(microtime(true) * 1000);
        if ((int)$data['location_time_ms'] <= 0 || abs($nowMs - (int)$data['location_time_ms']) > 180000) {
            throw ValidationException::withMessages(['location' => 'Fresh GPS fix required to Start/Resume ROT.']);
        }

        $geoFence->verify(
            (float)$data['latitude'],
            (float)$data['longitude'],
            (float)$data['accuracy_m'],
            (bool)$data['is_mocked']
        );
    }

    private function actor(Request $request, bool $adminOnly): array
    {
        $user = $request->user();
        if (!$user instanceof ErpAuthUser || !$user->active) {
            throw ValidationException::withMessages(['auth' => 'Valid ERP login required.']);
        }
        if ($adminOnly && !$user->isAdmin()) {
            abort(403, 'Admin/Supervisor permission required.');
        }
        if ($user->isAdmin()) {
            return [$user, null];
        }
        if (!$user->isStaff()) {
            abort(403, 'Staff identity is not linked.');
        }
        return [$user, (int)$user->staff_id];
    }

    private function admin(Request $request): ErpAuthUser
    {
        [$user] = $this->actor($request, true);
        return $user;
    }
}
