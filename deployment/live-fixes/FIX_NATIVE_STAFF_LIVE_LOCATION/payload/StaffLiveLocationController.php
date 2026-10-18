<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Native, authenticated, READ-ONLY Staff Duty Location dashboard.
 *
 * Server can refresh each second without inventing GPS samples.
 * Phone's actual recorded_at and received_at are sent separately.
 * Source: the same app-uploaded staff_location_logs used by staging ERP.
 * No attendance/ROT/location changes are performed by this controller.
 */
final class StaffLiveLocationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeOwner($request);
        $settings = DB::table('staff_location_settings')->where('id', 1)->first();
        return view('admin.native.staff-live-location', [
            'mapSettings' => [
                'latitude' => $settings?->latitude !== null ? (float)$settings->latitude : null,
                'longitude' => $settings?->longitude !== null ? (float)$settings->longitude : null,
                'radius_m' => (int)($settings?->radius_m ?? 100),
                'reporting_accuracy_m' => (int)($settings?->max_accuracy_m ?? 100),
                'checkin_accuracy_m' => (int)($settings?->checkin_max_accuracy_m ?? 30),
            ],
        ]);
    }

    public function feed(Request $request): JsonResponse
    {
        $this->authorizeOwner($request);
        $validated = $request->validate([
            'after' => ['nullable', 'integer', 'min:0'],
            'meta' => ['nullable', 'boolean'],
        ]);
        $after = max(0, (int)($validated['after'] ?? 0));
        $meta = $after === 0 || !empty($validated['meta']);
        $staff = collect();
        if ($meta) {
            $staff = DB::table('staff')
                ->whereRaw("LOWER(status) = 'active'")
                ->orderBy('name')
                ->get(['id','staff_code','name','role','department']);
        }

        $rows = collect();
        $cursor = $after;
        if ($after === 0) {
            // A late upload of an old offline fix must never replace the newest
            // physically recorded position on initial page load.
            $freshest = DB::table('staff_location_logs')
                ->selectRaw('staff_id, MAX(recorded_at) as latest_recorded_at')
                ->groupBy('staff_id');
            $latestRows = DB::table('staff_location_logs as l')
                ->joinSub($freshest, 'freshest', function ($join) {
                    $join->on('l.staff_id', '=', 'freshest.staff_id')
                        ->on('l.recorded_at', '=', 'freshest.latest_recorded_at');
                })
                ->orderByDesc('l.id')->limit(500)
                ->get(['l.id','l.staff_id','l.recorded_at','l.received_at',
                    'l.latitude','l.longitude','l.accuracy_m','l.provider',
                    'l.distance_m','l.inside_geofence','l.quality','l.app_version',
                    'l.speed_mps','l.active_rot_id']);
            $seen = [];
            foreach ($latestRows as $row) {
                if (isset($seen[(int)$row->staff_id])) continue;
                $seen[(int)$row->staff_id] = true;
                $rows->push($row);
            }
            $cursor = (int)(DB::table('staff_location_logs')->max('id') ?? 0);
        } else {
            // Low-cost primary-key incremental poll. No repeated full-table scan
            // on the 1-second update loop; never skip rows across a 200-item page.
            $rows = DB::table('staff_location_logs')->where('id', '>', $after)
                ->orderBy('id')->limit(200)
                ->get(['id','staff_id','recorded_at','received_at',
                    'latitude','longitude','accuracy_m','provider',
                    'distance_m','inside_geofence','quality','app_version',
                    'speed_mps','active_rot_id']);
            if ($rows->isNotEmpty()) $cursor = (int)$rows->last()->id;
        }

        $attendance = [];
        $rotnow = [];
        if ($meta) {
            $day = CarbonImmutable::now('Asia/Kolkata')->toDateString();
            foreach (DB::table('attendance')->where('date', $day)
                ->whereNotNull('check_in')->orderByDesc('id')
                ->get(['staff_id','check_in','check_out']) as $a) {
                $id = (int)$a->staff_id;
                if (!array_key_exists($id, $attendance)) {
                    $attendance[$id] = ['checked_in' => $a->check_out === null,
                        'check_in' => $this->time($a->check_in), 'check_out' => $this->time($a->check_out)];
                }
            }
            foreach (DB::table('rot_sessions')
                ->whereIn('status',['Assigned','Running','Paused'])
                ->orderByRaw("FIELD(status,'Running','Paused','Assigned')")
                ->limit(300)->get(['staff_id','id','rot_code','status','job_card_no']) as $r) {
                $id = (int)$r->staff_id;
                if ($id && !array_key_exists($id,$rotnow)) $rotnow[$id] = [
                    'rot_id' => (string)$r->id,
                    'rot_code' => (string)$r->rot_code,
                    'status' => (string)$r->status,
                    'job_card_no' => (string)$r->job_card_no
                ];
            }
        }

        $settings = DB::table('staff_location_settings')->where('id', 1)->first();
        $payload = [
            'ok' => true,
            'server_time' => CarbonImmutable::now('Asia/Kolkata')->toIso8601String(),
            'cursor' => $cursor,
            'initial' => $after === 0,
            'settings' => [
                'radius_m' => (int)($settings?->radius_m ?? 100),
                'reporting_accuracy_m' => (int)($settings?->max_accuracy_m ?? 100),
                'checkin_accuracy_m' => (int)($settings?->checkin_max_accuracy_m ?? 30),
                'latitude' => $settings?->latitude === null ? null : (float)$settings->latitude,
                'longitude' => $settings?->longitude === null ? null : (float)$settings->longitude,
            ],
            'points' => $rows->map(fn($r) => $this->location($r))->values()->all(),
            'staff' => $meta ? $staff->map(fn($s) => [
                'id' => (int)$s->id, 'code' => (string)$s->staff_code,
                'name' => (string)$s->name, 'role' => (string)$s->role,
                'department' => (string)$s->department,
            ])->all() : null,
            'attendance' => $meta ? $attendance : null,
            'rot' => $meta ? $rotnow : null,
        ];
        return response()->json($payload)->header('Cache-Control','private, no-store, max-age=0')
            ->header('X-Content-Type-Options','nosniff');
    }

    public function history(Request $request): JsonResponse
    {
        $this->authorizeOwner($request);
        $data = $request->validate([
            'staff_id' => ['required','integer','min:1'],
            'date' => ['required','date_format:Y-m-d'],
        ]);
        $id = (int)$data['staff_id'];
        $active = DB::table('staff')->where('id',$id)->whereRaw("LOWER(status)='active'")
            ->exists();
        abort_unless($active, 404);
        $date = CarbonImmutable::parse($data['date'],'Asia/Kolkata');
        abort_if($date->isFuture() || $date->lessThan(CarbonImmutable::now('Asia/Kolkata')->subDays(31)),422,
            'Location history is limited to the past 31 days.');
        $from = $date->startOfDay()->format('Y-m-d H:i:s');
        $to = $date->endOfDay()->format('Y-m-d H:i:s');
        $points = DB::table('staff_location_logs')->where('staff_id',$id)
            ->whereBetween('recorded_at',[$from,$to])
            ->orderByDesc('recorded_at')->orderByDesc('id')->limit(300)
            ->get(['id','staff_id','recorded_at','received_at','latitude','longitude',
                'accuracy_m','provider','distance_m','inside_geofence','quality',
                'app_version','speed_mps','active_rot_id'])
            ->reverse()->values()->map(fn($r)=>$this->location($r))->all();
        return response()->json(['ok'=>true,'date'=>$data['date'],'staff_id'=>$id,'points'=>$points,
            'limited'=>count($points)>=300])
            ->header('Cache-Control','private, no-store, max-age=0');
    }

    private function authorizeOwner(Request $request): void
    {
        $user = $request->user();
        abort_unless($user instanceof ErpAuthUser && $user->active && $user->isAdmin(), 403,
            'Active ERP Administrator permission required for staff GPS monitoring.');
    }

    private function location(object $r): array
    {
        return [
            'id'=>(int)$r->id,'staff_id'=>(int)$r->staff_id,
            'recorded_at'=>$this->time($r->recorded_at),
            'received_at'=>$this->time($r->received_at),
            'lat'=>$r->latitude===null?null:(float)$r->latitude,
            'lng'=>$r->longitude===null?null:(float)$r->longitude,
            'accuracy_m'=>$r->accuracy_m===null?null:round((float)$r->accuracy_m,1),
            'distance_m'=>$r->distance_m===null?null:round((float)$r->distance_m,1),
            'inside_geofence'=>$r->inside_geofence===null?null:(bool)$r->inside_geofence,
            'quality'=>(string)$r->quality,
            'provider'=>(string)$r->provider,
            'app_version'=>(string)$r->app_version,
            'speed_kmh'=>$r->speed_mps===null?null:round(max(0,(float)$r->speed_mps)*3.6,1),
            'active_rot_id'=>$r->active_rot_id===null?null:(string)$r->active_rot_id,
        ];
    }

    private function time(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        return CarbonImmutable::parse((string)$value, 'Asia/Kolkata')->toIso8601String();
    }
}
