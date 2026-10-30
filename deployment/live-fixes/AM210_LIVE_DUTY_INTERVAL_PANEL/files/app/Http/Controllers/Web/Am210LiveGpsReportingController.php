<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * AM210 - Admin GPS reporting interval in the existing Live Duty Location UI.
 * Save only staff_location_settings.sample_minutes. No location, attendance,
 * geofence, precision, payroll, or ROT record is touched.
 */
final class Am210LiveGpsReportingController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user instanceof ErpAuthUser && $user->active && $user->isAdmin(),
            403,
            'Active ERP Admin/Owner permission required.'
        );
    }

    private function currentMinutes(): int
    {
        abort_unless(
            Schema::hasTable('staff_location_settings') &&
            Schema::hasColumn('staff_location_settings', 'sample_minutes'),
            503,
            'Staff GPS Settings are unavailable.'
        );
        $value = DB::table('staff_location_settings')->where('id', 1)
            ->value('sample_minutes');
        abort_if($value === null, 503, 'GPS Settings record is missing.');
        return (int) $value;
    }

    public function show(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $minutes = $this->currentMinutes();
        return response()->json([
            'ok' => true,
            'sample_minutes' => $minutes,
            'interval_seconds' => $minutes * 60,
            'supported_options' => [1, 5],
            'source' => 'STAFF_LOCATION_SETTINGS',
        ])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'sample_minutes' => ['required', 'integer', Rule::in([1, 5])],
        ]);
        $minutes = (int) $data['sample_minutes'];
        // Fail closed if the historical Settings row does not exist. Do not
        // insert defaults or risk replacing existing geofence configuration.
        $this->currentMinutes();
        DB::transaction(function () use ($minutes): void {
            $row = DB::table('staff_location_settings')
                ->where('id', 1)->lockForUpdate()->first(['id', 'sample_minutes']);
            abort_unless($row, 503, 'GPS Settings record is missing.');
            DB::table('staff_location_settings')
                ->where('id', 1)->update(['sample_minutes' => $minutes]);
        }, 3);

        return response()->json([
            'ok' => true,
            'sample_minutes' => $minutes,
            'interval_seconds' => $minutes * 60,
            'message' => 'Global GPS reporting interval saved in STAGING ERP.',
        ])->header('Cache-Control', 'private, no-store');
    }
}
