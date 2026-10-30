<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only, protected Staff GPS interval API. Route must inherit the same
 * authentication and active-staff checks as POST /api/v1/staff/location.
 * Does NOT expose GPS positions or workshop coordinates.
 */
final class GpsReportingIntervalController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user(), 401, 'Authenticated Staff session required.');
        $seconds = 60;
        if (Schema::hasTable('staff_location_settings')
            && Schema::hasColumn('staff_location_settings', 'sample_minutes')) {
            $hasSeconds = Schema::hasColumn('staff_location_settings', 'sample_seconds');
            $columns = $hasSeconds
                ? ['sample_minutes', 'sample_seconds']
                : ['sample_minutes'];
            $row = DB::table('staff_location_settings')
                ->where('id', 1)->first($columns);
            if ($row) {
                $exact = $hasSeconds && $row->sample_seconds !== null
                    ? (int) $row->sample_seconds
                    : (int) $row->sample_minutes * 60;
                if ($exact >= 30 && $exact <= 900) $seconds = $exact;
            }
        }
        return response()->json([
            'ok' => true,
            'interval_seconds' => $seconds,
            'sample_minutes' => (int) ceil($seconds / 60),
            'source' => 'STAFF_LOCATION_SETTINGS',
        ])->header('Cache-Control', 'private, no-store');
    }
}
