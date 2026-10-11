<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use App\Models\NotificationOutbox;
use App\Models\StaffReminderState;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StaffNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->staff($request);
        $staffId = (int)$user->staff_id;

        $rows = NotificationOutbox::query()
            ->where('staff_id', $staffId)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $reminders = StaffReminderState::query()
            ->where('staff_id', $staffId)
            ->whereIn('id', $rows->pluck('reminder_state_id')->filter()->unique()->values())
            ->get()->keyBy('id');

        $items = $rows->map(function (NotificationOutbox $row) use ($reminders) {
            $state = $row->reminder_state_id ? $reminders->get($row->reminder_state_id) : null;
            $resolved = (bool)($state?->resolved_at) || (bool)$row->action_resolved_at;
            $payload = is_array($row->payload_json) ? $row->payload_json : [];
            $broadcast = (bool)($payload['broadcast'] ?? false);
            return [
                'id' => $row->id,
                'reminder_state_id' => $row->reminder_state_id,
                'type' => $row->notification_type,
                'title' => $row->title,
                'body' => $row->body,
                'deep_link' => $row->deep_link ?: 'assammotors://staff/dashboard',
                'status' => $row->status,
                'sent_at' => $row->sent_at,
                'opened_at' => $row->opened_at,
                'resolved_at' => $state?->resolved_at ?: $row->action_resolved_at,
                'broadcast' => $broadcast,
                'require_ack' => (bool)($payload['require_ack'] ?? false),
                'action_required' => !$resolved && ($broadcast || !in_array($row->notification_type, ['TEST'], true)),
                'provider_message_id' => $row->provider_message_id,
            ];
        })->values();

        return response()->json([
            'ok' => true,
            'pending_action_count' => $items->where('action_required', true)->count(),
            'unopened_count' => $items->filter(fn($i) => !$i['opened_at'])->count(),
            'notifications' => $items,
        ]);
    }

    public function opened(Request $request, int $notification): JsonResponse
    {
        $user = $this->staff($request);
        $row = NotificationOutbox::query()
            ->whereKey($notification)
            ->where('staff_id', (int)$user->staff_id)
            ->firstOrFail();

        $now = CarbonImmutable::now('Asia/Kolkata');
        if (!$row->opened_at) $row->opened_at = $now;

        $payload = is_array($row->payload_json) ? $row->payload_json : [];
        if ((bool)($payload['broadcast'] ?? false) && !$row->action_resolved_at) {
            // Opening an Owner broadcast is its acknowledgement. This keeps the
            // alert visible until Staff actually taps/reads it.
            $row->action_resolved_at = $now;
        }
        $row->save();

        return response()->json([
            'ok' => true,
            'opened_at' => $row->opened_at,
            'action_resolved_at' => $row->action_resolved_at,
        ]);
    }

    private function staff(Request $request): ErpAuthUser
    {
        $user = $request->user();
        abort_unless($user instanceof ErpAuthUser && $user->active && $user->isStaff(), 403, 'Linked Staff login required.');
        return $user;
    }
}
