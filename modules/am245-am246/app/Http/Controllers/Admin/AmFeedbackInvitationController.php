<?php
namespace App\Http\Controllers\Admin;

use App\Contracts\AmFeedbackInvitationSource;
use App\Http\Controllers\Controller;
use App\Support\AmFeedbackInvitationToken;
use App\Support\AmJobCardFeedbackInvite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AmFeedbackInvitationController extends Controller
{
    /**
     * AM245 only: operator initiates a WhatsApp *compose* redirect.
     * Not an auto-send and not proof of customer receipt or delivery.
     */
    public function openWhatsApp(Request $request, AmFeedbackInvitationSource $source)
    {
        $v = $request->validate([
            'job_card_id' => ['required', 'integer', 'min:1'],
            'consent' => ['accepted'],
        ]);
        $jobCardId = (int)$v['job_card_id'];
        // The deployed adapter must query the real ERP with tenant/company scope.
        $c = $source->forJobCard($jobCardId);
        abort_unless(is_array($c), 404, 'Job Card not found');
        foreach (['customer_id', 'vehicle_id', 'job_card_id'] as $key) {
            abort_unless(isset($c[$key]) && is_int($c[$key]) && $c[$key] > 0,
                422, 'Verified Job Card identifiers required');
        }
        abort_unless($c['job_card_id'] === $jobCardId, 422, 'Job Card mismatch');
        abort_unless(($c['service_finished'] ?? null) === true,
            409, 'Complete the Job Card before requesting feedback');
        abort_unless(($c['messaging_permitted'] ?? null) === true,
            403, 'Verified WhatsApp permission required');

        $name = trim((string)($c['customer_name'] ?? ''));
        $vehicle = trim((string)($c['vehicle'] ?? ''));
        $ref = trim((string)($c['job_card_ref'] ?? ''));
        // Never store the phone number or trust a browser-supplied destination.
        $phoneRaw = trim((string)($c['phone'] ?? ''));
        abort_unless($phoneRaw !== '' && preg_match('/\A[+0-9() .-]+\z/D', $phoneRaw),
            422, 'Valid customer WhatsApp number required');
        $phone = preg_replace('/\D+/', '', $phoneRaw);
        if (strlen($phone) === 10 && preg_match('/\A[6-9]/', $phone)) {
            $phone = '91'.$phone;
        }
        abort_unless(preg_match('/\A91[6-9][0-9]{9}\z/D', $phone),
            422, 'Valid Indian WhatsApp number required');

        $issued = AmFeedbackInvitationToken::issue();
        $feedbackUrl = route('am.feedback.public.show', ['token' => $issued['token']]);
        abort_unless(str_starts_with($feedbackUrl, 'https://'), 503,
            'HTTPS public feedback route required');
        $message = AmJobCardFeedbackInvite::compose($name, $ref, $vehicle, $feedbackUrl);
        $now = now();
        DB::transaction(function () use ($c, $jobCardId, $name, $vehicle, $ref, $issued, $request, $now) {
            $existing = DB::table('am_feedback_invitations')
                ->where('job_card_id', $jobCardId)->lockForUpdate()->first();
            abort_if($existing && $existing->responded_at !== null,
                409, 'Feedback already submitted for this Job Card');
            $values = [
                'customer_id' => $c['customer_id'],
                'vehicle_id' => $c['vehicle_id'],
                'customer_name_snapshot' => $name,
                'vehicle_snapshot' => $vehicle,
                'job_card_ref_snapshot' => $ref,
                'token_hash' => $issued['hash'],
                'expires_at' => $now->copy()->addDays(7),
                'compose_redirected_at' => $now,
                'requested_by' => (int)$request->user()->id,
                'updated_at' => $now,
            ];
            if ($existing) {
                // Rotating the token invalidates any previously opened links.
                DB::table('am_feedback_invitations')->where('id', $existing->id)
                    ->update($values + ['request_count' => $existing->request_count + 1]);
            } else {
                DB::table('am_feedback_invitations')->insert($values + [
                    'job_card_id' => $jobCardId,
                    'request_count' => 1,
                    'created_at' => $now,
                ]);
            }
        });

        $redirect = redirect()->away('https://wa.me/'.$phone.'?text='.rawurlencode($message));
        $redirect->headers->set('Cache-Control', 'private, no-store');
        $redirect->headers->set('Referrer-Policy', 'no-referrer');
        return $redirect;
    }

    private function pendingInvitation(string $token, bool $lock = false)
    {
        if (!preg_match('/\A[a-f0-9]{64}\z/D', $token)) {
            abort(404);
        }
        $query = DB::table('am_feedback_invitations')
            ->where('token_hash', AmFeedbackInvitationToken::hash($token))
            ->whereNull('responded_at')
            ->where('expires_at', '>', now());
        $invitation = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($invitation, 404, 'Feedback invitation unavailable');
        return $invitation;
    }

    /** Public token page: no customer or vehicle information is rendered. */
    public function publicForm(string $token)
    {
        $this->pendingInvitation($token);
        return $this->publicPage($token, false);
    }

    public function publicSubmit(Request $request, string $token)
    {
        $v = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'max:3000'],
        ]);
        DB::transaction(function () use ($token, $v) {
            // Locks consumption and prevents duplicate feedback under parallel POSTs.
            $row = $this->pendingInvitation($token, true);
            $now = now();
            $feedbackId = DB::table('am_customer_feedback')->insertGetId([
                'customer_id' => $row->customer_id,
                'vehicle_id' => $row->vehicle_id,
                'job_card_id' => $row->job_card_id,
                'customer_name' => $row->customer_name_snapshot,
                'vehicle' => $row->vehicle_snapshot,
                'job_card_ref' => $row->job_card_ref_snapshot,
                'rating' => (int)$v['rating'],
                'comment' => $v['comment'],
                'source' => 'whatsapp_invite',
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('am_feedback_invitations')->where('id', $row->id)
                ->update(['feedback_id' => $feedbackId,
                          'responded_at' => $now, 'updated_at' => $now]);
        });
        return $this->publicPage('', true);
    }

    private function publicPage(string $token, bool $submitted)
    {
        return response()->view('admin.engagement.public-feedback',
            ['token' => $token, 'submitted' => $submitted], 200, [
                'Cache-Control' => 'private, no-store',
                'Referrer-Policy' => 'no-referrer',
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
                'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'",
            ]);
    }
}
