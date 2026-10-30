<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AM210: minimal, authenticated, read-only staff GPS interval endpoint.
 * Route MUST inherit the existing POST /api/v1/staff/location authentication.
 * Does NOT expose geofence, workshop coordinates, or staff locations.
 */
final class GpsReportingIntervalController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user(), 401, 'Authenticated Staff session required.');

        $minutes = 1;
        if (Schema::hasTable('staff_location_settings')
            && Schema::hasColumn('staff_location_settings', 'sample_minutes')) {
            $value = DB::table('staff_location_settings')
                ->where('id', 1)->value('sample_minutes');
            if (is_numeric($value)) {
                $candidate = (int) $value;
                if ($candidate >= 1 && $candidate <= 15) $minutes = $candidate;
            }
        }
        return response()->json([
            'ok' => true,
            'interval_seconds' => $minutes * 60,
            'sample_minutes' => $minutes,
            'source' => 'STAFF_LOCATION_SETTINGS',
        ])->header('Cache-Control','private, no-store');
    }
}
