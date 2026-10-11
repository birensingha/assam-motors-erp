<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use App\Models\NotificationOutbox;
use App\Models\StaffDevice;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AdminMobileController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $this->admin($request);
        $today = CarbonImmutable::now('Asia/Kolkata')->toDateString();

        $staffTotal = Schema::hasTable('staff')
            ? DB::table('staff')->whereRaw("LOWER(COALESCE(status,'active'))='active'")->count()
            : 0;
        $present = Schema::hasTable('attendance')
            ? DB::table('attendance')->whereDate('date', $today)->whereNotNull('check_in')->distinct('staff_id')->count('staff_id')
            : 0;
        $busy = Schema::hasTable('rot_sessions')
            ? DB::table('rot_sessions')->where('status', 'Running')->distinct('staff_id')->count('staff_id')
            : 0;

        $rot = Schema::hasTable('rot_sessions')
            ? DB::table('rot_sessions')->selectRaw('status, COUNT(*) total')->groupBy('status')->pluck('total', 'status')
            : collect();
        $jobs = Schema::hasTable('job_cards')
            ? DB::table('job_cards')->selectRaw("COALESCE(status,'OPEN') status, COUNT(*) total")->groupBy('status')->pluck('total', 'status')
            : collect();

        $pending = Schema::hasTable('staff_reminder_state')
            ? DB::table('staff_reminder_state')->whereNull('resolved_at')->count()
            : 0;
        $escalated = Schema::hasTable('staff_reminder_state')
            ? DB::table('staff_reminder_state')->whereNull('resolved_at')->whereNotNull('escalated_at')->count()
            : 0;

        return response()->json([
            'ok' => true,
            'mode' => 'OWNER_ADMIN',
            'today' => $today,
            'staff' => [
                'total_active' => (int)$staffTotal,
                'present' => (int)$present,
                'busy' => (int)$busy,
                'idle' => max(0, (int)$present - (int)$busy),
                'not_checked_in' => max(0, (int)$staffTotal - (int)$present),
            ],
            'rot' => [
                'assigned' => (int)($rot['Assigned'] ?? 0),
                'running' => (int)($rot['Running'] ?? 0),
                'paused' => (int)($rot['Paused'] ?? 0),
                'completed' => (int)($rot['Completed'] ?? 0),
            ],
            'job_cards' => $jobs,
            'alerts' => [
                'pending' => (int)$pending,
                'escalated' => (int)$escalated,
            ],
        ]);
    }

    public function staff(Request $request): JsonResponse
    {
        $this->admin($request);
        if (!Schema::hasTable('staff')) return response()->json(['ok' => true, 'staff' => []]);

        $today = CarbonImmutable::now('Asia/Kolkata')->toDateString();
        $staff = DB::table('staff')
            ->whereRaw("LOWER(COALESCE(status,'active'))='active'")
            ->orderBy('name')->limit(500)->get();

        $attendance = Schema::hasTable('attendance')
            ? DB::table('attendance')->whereDate('date', $today)->orderByDesc('id')->get()->unique('staff_id')->keyBy('staff_id')
            : collect();

        $rots = Schema::hasTable('rot_sessions')
            ? DB::table('rot_sessions')->whereIn('status', ['Running','Paused','Assigned'])
                ->orderByRaw("FIELD(status,'Running','Paused','Assigned')")
                ->orderByDesc('updated_at')->get()->unique('staff_id')->keyBy('staff_id')
            : collect();

        $locations = Schema::hasTable('staff_location_logs')
            ? DB::table('staff_location_logs')->orderByDesc('recorded_at')->orderByDesc('id')->limit(3000)->get()->unique('staff_id')->keyBy('staff_id')
            : collect();

        $rows = $staff->map(function ($s) use ($attendance, $rots, $locations) {
            $a = $attendance->get($s->id);
            $r = $rots->get($s->id);
            $l = $locations->get($s->id);
            return [
                'id' => (int)$s->id,
                'staff_code' => $s->staff_code ?? null,
                'name' => $s->name ?? 'Staff',
                'phone' => $s->phone ?? null,
                'email' => $s->email ?? null,
                'role' => $s->role ?? 'Staff',
                'department' => $s->department ?? null,
                'attendance' => $a ? [
                    'check_in' => $a->check_in ?? null,
                    'check_out' => $a->check_out ?? null,
                    'status' => $a->status ?? null,
                ] : null,
                'active_rot' => $r ? [
                    'id' => $r->id,
                    'job_card_id' => $r->job_card_id ?? null,
                    'job_card_no' => $r->job_card_no ?? null,
                    'rot_code' => $r->rot_code ?? null,
                    'rot_desc' => $r->rot_desc ?? null,
                    'status' => $r->status ?? null,
                    'updated_at' => $r->updated_at ?? null,
                ] : null,
                'location' => $l ? [
                    'latitude' => (float)$l->latitude,
                    'longitude' => (float)$l->longitude,
                    'accuracy_m' => $l->accuracy_m !== null ? (float)$l->accuracy_m : null,
                    'distance_m' => $l->distance_m !== null ? (float)$l->distance_m : null,
                    'inside_geofence' => (bool)($l->inside_geofence ?? false),
                    'recorded_at' => $l->recorded_at ?? null,
                    'quality' => $l->quality ?? null,
                ] : null,
            ];
        })->values();

        return response()->json(['ok' => true, 'staff' => $rows]);
    }

    public function locations(Request $request): JsonResponse
    {
        $this->admin($request);
        if (!Schema::hasTable('staff_location_logs')) return response()->json(['ok' => true, 'locations' => []]);

        $q = DB::table('staff_location_logs as l')
            ->leftJoin('staff as s', 's.id', '=', 'l.staff_id')
            ->select('l.*', 's.staff_code', 's.name as staff_name', 's.department')
            ->orderByDesc('l.recorded_at')->orderByDesc('l.id');

        if ($request->filled('staff_id')) $q->where('l.staff_id', (int)$request->query('staff_id'));

        $latestOnly = $request->boolean('latest', true);
        $rows = $q->limit($latestOnly ? 3000 : 500)->get();
        if ($latestOnly) $rows = $rows->unique('staff_id')->values();

        return response()->json(['ok' => true, 'locations' => $rows]);
    }

    public function jobCards(Request $request): JsonResponse
    {
        $this->admin($request);
        if (!Schema::hasTable('job_cards')) return response()->json(['ok' => true, 'job_cards' => []]);

        $q = DB::table('job_cards as j')
            ->leftJoin('staff as s', 's.id', '=', 'j.technician_id')
            ->select('j.*', 's.name as technician_name', 's.staff_code as technician_code')
            ->orderByDesc('j.id');

        $search = trim((string)$request->query('q', ''));
        if ($search !== '') {
            $like = "%{$search}%";
            $q->where(function ($w) use ($like) {
                $w->where('j.job_no', 'like', $like)
                  ->orWhere('j.customer_name', 'like', $like)
                  ->orWhere('j.customer_phone', 'like', $like)
                  ->orWhere('j.vehicle_reg_no', 'like', $like);
            });
        }

        return response()->json(['ok' => true, 'job_cards' => $q->limit(250)->get()]);
    }

    public function customers(Request $request): JsonResponse
    {
        $this->admin($request);
        $table = Schema::hasTable('am_customer_master') ? 'am_customer_master' : (Schema::hasTable('customer_master') ? 'customer_master' : null);
        if (!$table) return response()->json(['ok' => true, 'customers' => []]);

        $q = DB::table($table)->orderByDesc('id');
        $hasEmail = Schema::hasColumn($table, 'email');
        $search = trim((string)$request->query('q', ''));
        if ($search !== '') {
            $like = "%{$search}%";
            $q->where(function ($w) use ($like, $hasEmail) {
                $w->where('name', 'like', $like)->orWhere('phone', 'like', $like);
                if ($hasEmail) $w->orWhere('email', 'like', $like);
            });
        }
        return response()->json(['ok' => true, 'customers' => $q->limit(200)->get()]);
    }

    public function broadcast(Request $request): JsonResponse
    {
        $user = $this->admin($request);
        $data = $request->validate([
            'scope' => ['required', 'in:one,selected,all'],
            'staff_id' => ['nullable', 'integer', 'min:1'],
            'staff_ids' => ['nullable', 'array', 'max:200'],
            'staff_ids.*' => ['integer', 'min:1'],
            'title' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:500'],
            'type' => ['nullable', 'string', 'max:60'],
            'require_ack' => ['nullable', 'boolean'],
        ]);

        if ($data['scope'] === 'one') {
            $ids = [(int)($data['staff_id'] ?? 0)];
        } elseif ($data['scope'] === 'selected') {
            $ids = array_values(array_unique(array_map('intval', $data['staff_ids'] ?? [])));
        } else {
            $ids = Schema::hasTable('staff')
                ? DB::table('staff')->whereRaw("LOWER(COALESCE(status,'active'))='active'")->pluck('id')->map(fn($x)=>(int)$x)->all()
                : [];
        }
        $ids = array_values(array_filter($ids, fn($x) => $x > 0));
        if (!$ids) return response()->json(['message' => 'No staff selected.'], 422);

        $now = CarbonImmutable::now('Asia/Kolkata');
        $queued = 0;
        $pushDevices = 0;
        foreach ($ids as $staffId) {
            $devices = StaffDevice::query()
                ->where('staff_id', $staffId)
                ->where('active', 1)
                ->where('notification_permission', 1)
                ->whereNull('fcm_invalidated_at')
                ->whereNotNull('fcm_token')
                ->get();

            // FCM-capable devices get one row each. If FCM is not configured on
            // the Android client yet, keep one device-less row so native polling
            // still delivers the Owner/Admin broadcast inside Staff App.
            $targets = $devices->isNotEmpty() ? $devices : collect([null]);
            foreach ($targets as $device) {
                NotificationOutbox::query()->create([
                    'staff_id' => $staffId,
                    'staff_device_id' => $device?->id,
                    'reminder_state_id' => null,
                    'notification_type' => $data['type'] ?? 'ADMIN_BROADCAST',
                    'title' => $data['title'],
                    'body' => $data['message'],
                    'deep_link' => 'assammotors://staff/alerts',
                    'payload_json' => [
                        'broadcast' => true,
                        'require_ack' => (bool)($data['require_ack'] ?? false),
                        'sent_by_admin_user_id' => $user->id,
                        'polling_fallback' => $device === null,
                    ],
                    'status' => $device ? 'PENDING' : 'POLLING',
                    'available_at' => $now,
                ]);
                $queued++;
                if ($device) $pushDevices++;
            }
        }

        return response()->json([
            'ok' => true,
            'selected_staff' => count($ids),
            'devices_found' => $pushDevices,
            'queued' => $queued,
            'polling_fallback' => $pushDevices === 0,
            'message' => 'Broadcast queued for Staff App delivery.',
        ]);
    }

    public function jobCardDocument(Request $request, int $job): JsonResponse
    {
        $this->admin($request);
        abort_unless(Schema::hasTable('job_cards'), 404);
        $header = DB::table('job_cards')->where('id', $job)->first();
        abort_unless($header, 404, 'Job Card not found.');

        $parts = Schema::hasTable('job_card_parts')
            ? DB::table('job_card_parts')->where('job_card_id', $job)->orderBy('id')->get()
            : collect();
        $labour = Schema::hasTable('job_card_labour')
            ? DB::table('job_card_labour')->where('job_card_id', $job)->orderBy('id')->get()
            : collect();
        $estimate = Schema::hasTable('job_estimates')
            ? DB::table('job_estimates')->where('job_card_id', $job)->orderByDesc('id')->first()
            : null;
        $invoice = Schema::hasTable('invoices')
            ? DB::table('invoices')->where('job_card_id', $job)->orderByDesc('id')->first()
            : null;

        return response()->json([
            'ok' => true,
            'document' => [
                'type' => 'JOB_CARD',
                'title' => 'Job Card '.($header->job_no ?? ('#'.$job)),
                'header' => $header,
                'parts' => $parts,
                'labour' => $labour,
                'estimate' => $estimate,
                'invoice' => $invoice,
            ],
        ]);
    }

    private function admin(Request $request): ErpAuthUser
    {
        $user = $request->user();
        abort_unless($user instanceof ErpAuthUser && $user->active && $user->isAdmin(), 403, 'Owner/Admin permission required.');
        return $user;
    }
}
