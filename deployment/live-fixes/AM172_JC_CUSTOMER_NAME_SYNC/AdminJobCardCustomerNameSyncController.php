<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * AM172 - explicitly sync a verified Customer Master name into ONE not-yet-
 * invoiced Job Card. Receipt/invoice/customer master data is never modified.
 */
final class AdminJobCardCustomerNameSyncController extends Controller
{
    public function post(Request $request, int $job): RedirectResponse
    {
        $actor = $request->user();
        abort_unless(
            $actor instanceof ErpAuthUser && $actor->active && $actor->isAdmin(),
            403,
            'Admin access required.'
        );

        $validated = $request->validate([
            'mode' => ['required', 'in:sync,manual'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'new_name' => ['nullable', 'required_if:mode,manual', 'string', 'min:2', 'max:200'],
        ]);
        $mode = (string) $validated['mode'];
        $reason = trim((string) $validated['reason']);
        $manualName = trim((string) ($validated['new_name'] ?? ''));

        $result = DB::transaction(function () use ($job, $actor, $mode, $manualName, $reason): array {
            $card = DB::table('job_cards')->where('id', $job)->lockForUpdate()->first();
            if (!$card) {
                abort(404);
            }

            $status = strtolower(trim((string) ($card->status ?? '')));
            if (in_array($status, ['invoiced', 'cancelled', 'canceled'], true) ||
                !empty($card->invoice_id)) {
                throw ValidationException::withMessages([
                    'customer_name' => 'Invoiced/cancelled Job Card is locked. Use audited invoice correction.',
                ]);
            }

            // Do not rewrite a Job Card with ANY invoice history, including
            // credited/reversed invoices. That history is an immutable snapshot.
            $hasInvoice = DB::table('invoices')
                ->where('job_card_id', $job)
                ->orWhere('active_job_card_id', $job)
                ->orWhere('original_job_card_id', $job)
                ->exists();
            if ($hasInvoice) {
                throw ValidationException::withMessages([
                    'customer_name' => 'Invoice history exists. Customer name correction requires financial review.',
                ]);
            }

            $customerId = (int) ($card->customer_id ?? 0);
            $verification = 'ADMIN_MANUAL_REVIEW';
            $newName = $manualName;

            if ($mode === 'sync') {
                if ($customerId < 1) {
                    throw ValidationException::withMessages([
                        'customer_id' => 'No linked Customer Master ID; use manual name correction after reviewing identity.',
                    ]);
                }

                // AM171: numeric IDs can collide across two different customer
                // tables. Verify a second identifier before syncing.
                $master = DB::table('am_erp_customers')->where('id', $customerId)->first();
                if (!$master) {
                    throw ValidationException::withMessages([
                        'customer_id' => 'Customer ID is not in ERP Master. Use reviewed manual name correction.',
                    ]);
                }

                $cardPhone = preg_replace('/\D+/', '', (string) ($card->customer_phone ?? ''));
                $masterPhone = preg_replace('/\D+/', '', (string) ($master->phone ?? ''));
                $cardEmail = strtolower(trim((string) ($card->customer_email ?? '')));
                $masterEmail = strtolower(trim((string) ($master->email ?? '')));

                $cardMobile = strlen($cardPhone) >= 10 ? substr($cardPhone, -10) : '';
                $masterMobile = strlen($masterPhone) >= 10 ? substr($masterPhone, -10) : '';
                $phoneAvailable = $cardMobile !== '' && $masterMobile !== '';
                $phoneMatches = $phoneAvailable && hash_equals($cardMobile, $masterMobile);
                $emailMatches = $cardEmail !== '' && $masterEmail !== '' &&
                    hash_equals($cardEmail, $masterEmail);

                if (($phoneAvailable && !$phoneMatches) ||
                    (!$phoneMatches && !$emailMatches)) {
                    throw ValidationException::withMessages([
                        'customer_id' => 'Phone/email verification failed. No automatic sync performed; manually review identity.',
                    ]);
                }
                $verification = $phoneMatches ? 'PHONE_MATCH' : 'EMAIL_MATCH';
                $newName = trim((string) ($master->name ?? ''));
            }

            $oldName = trim((string) ($card->customer_name ?? ''));
            if ($newName === '') {
                throw ValidationException::withMessages([
                    'customer_name' => 'Customer Master name is empty. No changes made.',
                ]);
            }
            if ($oldName === $newName) {
                return ['changed' => false, 'name' => $newName];
            }

            DB::table('job_cards')->where('id', $job)->update([
                'customer_name' => $newName,
                'updated_at' => now('Asia/Kolkata'),
            ]);
            DB::table('job_card_change_audits')->insert([
                'job_card_id' => $job,
                'entity_type' => 'CUSTOMER',
                'entity_id' => $customerId,
                'action' => $mode === 'sync' ? 'AM172_CUSTOMER_NAME_SYNC' : 'AM172_CUSTOMER_NAME_MANUAL',
                'before_json' => json_encode([
                    'customer_id' => $customerId,
                    'customer_name' => $oldName,
                ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE),
                'after_json' => json_encode([
                    'customer_id' => $customerId,
                    'customer_name' => $newName,
                    'verification' => $verification,
                ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE),
                'reason' => $reason,
                'actor_id' => (int) $actor->id,
                'actor_name' => (string) ($actor->name ?? 'Admin'),
                'created_at' => now('Asia/Kolkata'),
                'updated_at' => now('Asia/Kolkata'),
            ]);

            // Transaction fails closed if audit insertion fails.
            return ['changed' => true, 'name' => $newName];
        }, 3);

        return redirect()->route('erp.job-cards.show', $job)->with('success',
            $result['changed']
                ? ($mode === 'sync' ? 'Customer name synced from verified ERP Master. Audit saved.' : 'Job Card customer name corrected manually. Audit saved.')
                : 'Job Card already has this customer name.'
        );
    }
}
