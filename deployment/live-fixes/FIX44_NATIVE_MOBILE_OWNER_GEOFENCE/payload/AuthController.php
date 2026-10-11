<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use App\Services\GeoFenceService;
use App\Services\MandatoryNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class AuthController extends Controller
{
    public function login(Request $request, MandatoryNotificationService $notifications, GeoFenceService $geoFence): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:180'],
            'password' => ['required', 'string', 'max:200'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'is_mocked' => ['nullable', 'boolean'],
            'provider' => ['nullable', 'string', 'max:40'],
            'location_time_ms' => ['nullable', 'integer'],
        ]);

        $user = ErpAuthUser::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($data['email'])])
            ->where('active', 1)
            ->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid email or password.'], 422);
        }

        $loginGeo = ['required' => false, 'verified' => false, 'admin_exempt' => $user->isAdmin()];

        // AM_NATIVE_STAFF_LOGIN_GEOFENCE_FIX44
        // Company Owner/Admin may work remotely. Every normal Staff login is workshop-bound.
        if (!$user->isAdmin()) {
            if (!$user->isStaff()) {
                return response()->json(['message' => 'This account is not linked to an active Staff identity.'], 403);
            }

            foreach (['latitude','longitude','accuracy_m','is_mocked','location_time_ms'] as $field) {
                if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
                    throw ValidationException::withMessages([
                        'location' => 'Fresh workshop GPS location is required for Staff login.',
                    ]);
                }
            }

            $this->assertFreshLocation((int)$data['location_time_ms']);
            $loginGeo = $geoFence->verify(
                (float)$data['latitude'],
                (float)$data['longitude'],
                (float)$data['accuracy_m'],
                (bool)$data['is_mocked']
            );
        }

        $ability = $user->isAdmin() ? 'erp-admin' : 'staff-work';
        $token = $user->createToken($data['device_name'] ?? 'assam-motors', [$ability])->plainTextToken;

        if ($user->isStaff() && !$user->isAdmin()) {
            try {
                DB::table('staff_login_events')->insert([
                    'staff_id' => $user->staff_id,
                    'login_at' => now('Asia/Kolkata'),
                    'source' => 'APP_NATIVE_GEOFENCE',
                    'user_agent' => substr((string)$request->userAgent(), 0, 255),
                    'ip_address' => substr((string)$request->ip(), 0, 64),
                ]);
            } catch (\Throwable $e) {}
            $notifications->resolveByTarget((int)$user->staff_id, 'LOGIN_MISSED', now('Asia/Kolkata')->toDateString());
        }

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'staff_id' => $user->staff_id,
                'is_admin' => $user->isAdmin(),
                'is_staff' => $user->isStaff(),
                'mobile_mode' => $user->isAdmin() ? 'OWNER_ADMIN' : 'STAFF',
            ],
            'location' => [
                'required' => (bool)($loginGeo['required'] ?? false),
                'verified' => (bool)($loginGeo['verified'] ?? false),
                'distance_m' => $loginGeo['distance_m'] ?? null,
                'inside_geofence' => $loginGeo['inside'] ?? null,
                'admin_exempt' => $user->isAdmin(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();
        return response()->json(['ok' => true]);
    }

    private function assertFreshLocation(int $locationTimeMs): void
    {
        $nowMs = (int)round(microtime(true) * 1000);
        $age = abs($nowMs - $locationTimeMs);
        if ($locationTimeMs <= 0 || $age > 180000) {
            throw ValidationException::withMessages([
                'location' => 'Fresh GPS fix required. Please enable Location/GPS and try again.',
            ]);
        }
    }
}
