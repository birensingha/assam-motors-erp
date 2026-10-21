<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * FIX61B, STAGING only. A single admin command coordinates the existing
 * *unpaid* Full Credit Note and CLOSED -> WIP transition atomically.
 * Payment-linked invoice handling is intentionally not attempted here:
 * FIX57 supports only one fully paid allocation and is not adequate for
 * split or partially paid receipts.
 *
 * No custom tax or ledger formula is introduced. Existing strict financial
 * controls and original invoice audit/snapshot are retained.
 */
final class AdminJobCardReturnController extends Controller
{
    public function store(Request $request, int $job): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof ErpAuthUser && $actor->active && $actor->isAdmin(), 403);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'confirm_return' => ['required', 'accepted'],
        ]);
        $reason = trim((string) $data['reason']);
        if (mb_strlen($reason) < 10) {
            throw ValidationException::withMessages(['reason' => 'Enter a meaningful correction reason.']);
        }

        $result = DB::transaction(function () use ($request, $job, $reason): array {
            // Lock same financial objects in existing posting order, avoiding
            // any changes to existing Invoice, Receipt or Tax calculation paths.
            $card = DB::table('job_cards')->where('id', $job)->lockForUpdate()->first();
            if (!$card) abort(404);
            if (strtolower(trim((string) $card->status)) !== 'invoiced' || empty($card->invoice_id)) {
                throw ValidationException::withMessages([
                    'status' => 'Return to JC requires an invoiced Job Card with its original invoice linked.',
                ]);
            }
            $invoiceId = (int) $card->invoice_id;
            $invoice = DB::table('invoices')->where('id', $invoiceId)->lockForUpdate()->first();
            if (!$invoice || (int) $invoice->job_card_id !== $job ||
                (int) $invoice->customer_id !== (int) $card->customer_id ||
                strtolower(trim((string) $invoice->status)) !== 'issued') {
                throw ValidationException::withMessages([
                    'invoice' => 'Linked invoice is not ISSUED and unpaid. Finance review required; no changes posted.',
                ]);
            }
            if (abs((float) $invoice->paid_amount) > 0.009 ||
                abs((float) $invoice->paid_total) > 0.009 ||
                abs((float) $invoice->balance_due - (float) $invoice->grand_total) > 0.02 ||
                DB::table('customer_payments')->where('invoice_id', $invoiceId)->exists() ||
                DB::table('customer_payment_allocations')->where('invoice_id', $invoiceId)->exists() ||
                DB::table('customer_advance_transfers')->where('original_invoice_id', $invoiceId)->exists()) {
                throw ValidationException::withMessages([
                    'finance' => 'Payment-linked, adjusted or partial-paid invoice: automatic reversal NOT enabled yet. Keep original receipt; complete Finance allocation reconciliation first.',
                ]);
            }
            if (DB::table('invoice_credit_notes')->where('invoice_id', $invoiceId)->exists()) {
                throw ValidationException::withMessages([
                    'invoice' => 'An invoice Credit Note already exists. Duplicate reversal blocked.',
                ]);
            }

            // Both handlers use DB::transaction internally. Laravel nested
            // transactions share this outer transaction; if reopening fails,
            // the CN and all ledger changes roll back together.
            $request->merge([
                'reason' => $reason,
                'credit_date' => now('Asia/Kolkata')->toDateString(),
                'confirmed_finance' => '1',
            ]);
            app(AdminInvoiceCreditNoteController::class)->post($request, $job, $invoiceId);

            $afterCredit = DB::table('job_cards')->where('id', $job)->first();
            $afterInvoice = DB::table('invoices')->where('id', $invoiceId)->first();
            if (!$afterCredit || strtolower((string) $afterCredit->status) !== 'closed' ||
                !empty($afterCredit->invoice_id) ||
                !$afterInvoice || strtolower((string) $afterInvoice->status) !== 'reversed') {
                throw ValidationException::withMessages([
                    'finance' => 'Reversal state was not verified. The full transaction was cancelled.',
                ]);
            }

            app(AdminJobCardLifecyclePageController::class)->reopenClosed($request, $job);

            $final = DB::table('job_cards')->where('id', $job)->first();
            if (!$final || !in_array(strtolower((string) $final->status),
                    ['wip', 'in-progress', 'in progress', 'work in progress'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'JC did not reach WIP. All financial changes were rolled back.',
                ]);
            }
            $cn = DB::table('invoice_credit_notes')
                ->where('invoice_id', $invoiceId)->first();
            if (!$cn || strtolower((string) $cn->status) !== 'posted') {
                throw ValidationException::withMessages([
                    'finance' => 'Credit Note was not posted. Return to JC cancelled.',
                ]);
            }
            return ['number' => (string) $cn->credit_note_no, 'invoice' => (string) $invoice->invoice_no];
        }, 3);

        return redirect()->route('erp.job-cards.show', $job)->with('success',
            'Return to JC complete: original '.$result['invoice'].
            ' fully credited by '.$result['number'].
            '. Job Card is now WIP and editable. Original invoice and audit are preserved.');
    }
}
