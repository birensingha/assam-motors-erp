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
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);
        $reason = trim((string) $validated['reason']);

        $result = DB::transaction(function () use ($job, $actor, $reason): array {
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
            if ($customerId < 1) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Job Card has no linked Customer Master ID. Link must be reviewed first.',
                ]);
            }

            // AM171 showed only 16 of 32 JCs have a numeric ID in this master.
            // Numeric ID alone is NOT enough to prove identity across two
            // separate master tables. Verify a second identifier.
            $master = DB::table('am_erp_customers')->where('id', $customerId)->first();
            if (!$master) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Customer ID is not in active ERP Customer Master. Review linkage first.',
                ]);
            }

            $cardPhone = preg_replace('/\D+/', '', (string) ($card->customer_phone ?? ''));
            $masterPhone = preg_replace('/\D+/', '', (string) ($master->phone ?? ''));
            $cardEmail = strtolower(trim((string) ($card->customer_email ?? '')));
            $masterEmail = strtolower(trim((string) ($master->email ?? '')));

            // Prefer the 10-digit verified Indian mobile number. If phones
            // conflict, an email coincidence cannot override that conflict.
            $cardMobile = strlen($cardPhone) >= 10 ? substr($cardPhone, -10) : '';
            $masterMobile = strlen($masterPhone) >= 10 ? substr($masterPhone, -10) : '';
            $phoneAvailable = $cardMobile !== '' && $masterMobile !== '';
            $phoneMatches = $phoneAvailable && hash_equals($cardMobile, $masterMobile);
            $emailMatches = $cardEmail !== '' && $masterEmail !== '' &&
                hash_equals($cardEmail, $masterEmail);

            if (($phoneAvailable && !$phoneMatches) ||
                (!$phoneMatches && !$emailMatches)) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Customer identity cannot be verified by phone/email. No name was changed. Review Customer Link.',
                ]);
            }

            $oldName = trim((string) ($card->customer_name ?? ''));
            $newName = trim((string) ($master->name ?? ''));
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
                'action' => 'AM172_CUSTOMER_NAME_SYNC',
                'before_json' => json_encode([
                    'customer_id' => $customerId,
                    'customer_name' => $oldName,
                ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE),
                'after_json' => json_encode([
                    'customer_id' => $customerId,
                    'customer_name' => $newName,
                    'verification' => $phoneMatches ? 'PHONE_MATCH' : 'EMAIL_MATCH',
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
                ? 'Customer name synced from verified ERP Master. Audit saved.'
                : 'Job Card already has the current Customer Master name.'
        );
    }
}
