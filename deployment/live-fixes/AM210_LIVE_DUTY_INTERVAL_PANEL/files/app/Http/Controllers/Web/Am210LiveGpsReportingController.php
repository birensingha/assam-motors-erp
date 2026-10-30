<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AM210: user-selected numeric GPS interval, seconds/minutes in the
 * EXISTING Live Duty Location panel. No attendance, location, ROT or
 * geofence values are modified. Active Admin session required.
 */
final class Am210LiveGpsReportingController extends Controller
{
    private const MIN_SECONDS = 30;
    private const MAX_SECONDS = 900;

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user instanceof ErpAuthUser && $user->active && $user->isAdmin(),
            403,
            'Active ERP Admin/Owner permission required.'
        );
    }

    private function currentSeconds(): int
    {
        abort_unless(
            Schema::hasTable('staff_location_settings') &&
            Schema::hasColumn('staff_location_settings', 'sample_minutes') &&
            Schema::hasColumn('staff_location_settings', 'sample_seconds'),
            503,
            'AM210 seconds migration is required before enabling this setting.'
        );
        $row = DB::table('staff_location_settings')->where('id', 1)
            ->first(['sample_minutes', 'sample_seconds']);
        abort_unless($row, 503, 'GPS Settings record is missing.');
        $seconds = $row->sample_seconds === null
            ? (int) $row->sample_minutes * 60
            : (int) $row->sample_seconds;
        abort_unless(
            $seconds >= self::MIN_SECONDS && $seconds <= self::MAX_SECONDS,
            503,
            'Existing interval is outside the supported safe range; review settings first.'
        );
        return $seconds;
    }

    public function show(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        return $this->response($this->currentSeconds());
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'interval_seconds' => ['required', 'integer', 'between:30,900'],
        ]);
        $seconds = (int) $data['interval_seconds'];

        // Ensure migration and settings record exist; never create/reset them.
        $this->currentSeconds();
        DB::transaction(function () use ($seconds): void {
            $row = DB::table('staff_location_settings')->where('id', 1)
                ->lockForUpdate()->first(['id', 'sample_minutes', 'sample_seconds']);
            abort_unless($row, 503, 'GPS Settings record is missing.');
            DB::table('staff_location_settings')->where('id', 1)->update([
                'sample_seconds' => $seconds,
                // Legacy source remains readable for clients using minutes.
                'sample_minutes' => (int) ceil($seconds / 60),
            ]);
        }, 3);
        return $this->response($seconds, true);
    }

    private function response(int $seconds, bool $saved = false): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'interval_seconds' => $seconds,
            'sample_minutes' => (int) ceil($seconds / 60),
            'min_seconds' => self::MIN_SECONDS,
            'max_seconds' => self::MAX_SECONDS,
            'source' => 'STAFF_LOCATION_SETTINGS',
            'message' => $saved ? 'Custom GPS reporting interval saved in ERP.' : null,
        ])->header('Cache-Control', 'private, no-store');
    }
}
