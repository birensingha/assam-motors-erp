<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * AM250: auditable reversal of a duplicate Money Receipt.
 * Never deletes a posted document or erases its original ledger credit.
 */
final class AdminMoneyReceiptVoidController extends Controller
{
    private function stop(string $message): never
    {
        throw ValidationException::withMessages(['reason' => $message]);
    }

    private function cents(mixed $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    public function void(Request $request, int $receipt): RedirectResponse
    {
        $actor = $request->user();
        abort_unless(
            $actor instanceof ErpAuthUser &&
            $actor->active && $actor->isAdmin() &&
            in_array(strtolower(trim((string) $actor->role)), ['admin', 'owner'], true),
            403, 'Owner/Admin permission required to void a Money Receipt.'
        );
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:8', 'max:300'],
        ]);
        $reason = trim($data['reason']);
        if ($reason === '') $this->stop('Enter a cancellation reason.');

        foreach (['am_customer_master', 'customer_payments',
                  'customer_payment_allocations', 'customer_ledger', 'invoices'] as $table) {
            abort_unless(Schema::hasTable($table), 503, 'Receipt accounting schema is unavailable.');
        }

        DB::transaction(function () use ($receipt, $reason, $actor): void {
            // Match Money Receipt creation lock order: customer first, then receipt.
            $lookup = DB::table('customer_payments')->where('id', $receipt)->first();
            abort_unless($lookup, 404, 'Money Receipt not found.');
            $customer = DB::table('am_customer_master')->where('id', (int) $lookup->customer_id)
                ->lockForUpdate()->first();
            if (!$customer) $this->stop('Customer Master is missing. Reconcile before voiding.');
            $p = DB::table('customer_payments')->where('id', $receipt)->lockForUpdate()->first();
            if (!$p || (int) $p->customer_id !== (int) $customer->id)
                $this->stop('Receipt customer changed. Retry after reconciliation.');
            if (strtolower((string) $p->payment_status) !== 'posted' || $p->reversed_at !== null)
                $this->stop('This receipt has already been reversed or is not posted.');
            if (!str_starts_with((string) $p->receipt_no, 'RCPT-'))
                $this->stop('Only native RCPT Money Receipts can be voided by this control.');

            // A credit-note transfer can spend/transfer a receipt; it cannot be
            // reversed safely by simple receipt+allocation reversal.
            if (Schema::hasTable('customer_advance_transfers') &&
                DB::table('customer_advance_transfers')->where('receipt_id', $receipt)->exists()) {
                $this->stop('This receipt is linked to a credit-note advance transfer. Reconcile the transfer first.');
            }
            $value = $this->cents($p->total_amount);
            if ($value <= 0) $this->stop('Receipt amount is invalid. Manual reconciliation required.');

            $entries = DB::table('customer_ledger')->where('payment_id', $receipt)
                ->lockForUpdate()->get();
            if ($entries->count() !== 1) $this->stop('Expected exactly one original receipt credit. Reconcile Customer Ledger.');
            $oldCredit = $entries->first();
            if (strtoupper((string) $oldCredit->entry_type) !== 'CREDIT'
                || strtoupper((string) $oldCredit->reference_type) !== 'PAYMENT'
                || $this->cents($oldCredit->credit) !== $value
                || $this->cents($oldCredit->debit) !== 0
                || (int) $oldCredit->customer_id !== (int) $p->customer_id) {
                $this->stop('Original Customer Ledger credit does not match this receipt.');
            }
            if (DB::table('customer_ledger')->where('reference_type', 'RECEIPT_VOID')
                ->where('reference_id', $receipt)->exists()) {
                $this->stop('A reversal ledger entry already exists for this receipt.');
            }

            $allocations = DB::table('customer_payment_allocations')
                ->where('payment_id', $receipt)->lockForUpdate()->get();
            $totalAllocated = 0;
            $perInvoice = [];
            foreach ($allocations as $row) {
                if (strtolower((string) $row->status) !== 'posted' || $row->reversed_at !== null)
                    $this->stop('A receipt allocation was already altered. Reconcile before voiding.');
                $amount = $this->cents($row->amount);
                if ($amount <= 0) $this->stop('Invalid invoice allocation.');
                $totalAllocated += $amount;
                $invoiceId = (int) $row->invoice_id;
                $perInvoice[$invoiceId] = ($perInvoice[$invoiceId] ?? 0) + $amount;
            }
            if ($totalAllocated !== $this->cents($p->allocated_amount))
                $this->stop('Receipt allocated amount differs from allocation history.');
            if ($totalAllocated > $value)
                $this->stop('Receipt allocations exceed the received amount.');
            if ((string) $p->reference_type === 'against_reference') {
                if (!$p->invoice_id || count($perInvoice) !== 1 ||
                    !isset($perInvoice[(int) $p->invoice_id]) || $totalAllocated !== $value)
                    $this->stop('Receipt invoice allocation is incomplete or inconsistent.');
            } elseif ($allocations->isNotEmpty() || $p->invoice_id !== null) {
                $this->stop('Non-invoice receipt has invoice-linked allocations. Reconcile first.');
            }

            $updates = [];
            foreach ($perInvoice as $invoiceId => $allocated) {
                $invoice = DB::table('invoices')->where('id', $invoiceId)->lockForUpdate()->first();
                if (!$invoice || (int) $invoice->customer_id !== (int) $p->customer_id)
                    $this->stop('Invoice is missing or belongs to a different customer.');
                $status = strtolower(trim((string) $invoice->status));
                if (!in_array($status, ['paid', 'part_paid', 'issued'], true))
                    $this->stop('Invoice has been reversed, credit-noted or otherwise changed. Reconcile before voiding.');

                $active = $this->cents(DB::table('customer_payment_allocations')
                    ->where('invoice_id', $invoiceId)->where('status', 'posted')
                    ->whereNull('reversed_at')->sum('amount'));
                $paid = $this->cents($invoice->paid_total);
                $paidAlias = $this->cents($invoice->paid_amount);
                $due = $this->cents($invoice->balance_total);
                $dueAlias = $this->cents($invoice->balance_due);
                $grand = $this->cents($invoice->grand_total);
                if (abs($paid - $active) > 1 || abs($paid - $paidAlias) > 1 ||
                    abs($due - $dueAlias) > 1 || abs(($paid + $due) - $grand) > 1) {
                    $this->stop('Invoice payment/ledger totals do not reconcile. No reversal was saved.');
                }
                if ($allocated > $paid) $this->stop('Reversal would produce a negative invoice payment.');
                $newPaid = $paid - $allocated;
                $newDue = $grand - $newPaid;
                $updates[$invoiceId] = [
                    'paid_total' => $newPaid / 100,
                    'paid_amount' => $newPaid / 100,
                    'balance_total' => $newDue / 100,
                    'balance_due' => $newDue / 100,
                    'status' => $newPaid === 0 ? 'issued' : ($newDue === 0 ? 'paid' : 'part_paid'),
                ];
            }

            $at = now('Asia/Kolkata');
            DB::table('customer_payment_allocations')->where('payment_id', $receipt)
                ->where('status', 'posted')->whereNull('reversed_at')
                ->update(['status' => 'reversed', 'reversed_at' => $at]);
            foreach ($updates as $invoiceId => $row) {
                DB::table('invoices')->where('id', $invoiceId)->update($row);
            }

            // Keep the original receipt CREDIT; add an equal, separately
            // identifiable DEBIT. This preserves chronological audit trails.
            DB::table('customer_ledger')->insert([
                'customer_id' => (int) $p->customer_id,
                'invoice_id' => $p->invoice_id,
                'entry_type' => 'DEBIT',
                'amount' => $value / 100,
                'reference_no' => 'VOID-' . (string) $p->receipt_no,
                'note' => 'Money Receipt reversal: ' . $reason,
                'created_at' => $at,
                'customer_email' => $oldCredit->customer_email,
                'customer_name' => $oldCredit->customer_name,
                'payment_id' => null,
                'job_card_id' => $p->job_card_id,
                'vehicle_id' => $p->vehicle_id,
                'reference_type' => 'RECEIPT_VOID',
                'reference_id' => $receipt,
                'debit' => $value / 100,
                'credit' => 0,
                'narration' => 'Void ' . $p->receipt_no . ' by ' .
                    mb_substr((string) ($actor->email ?: $actor->name ?: 'Admin'), 0, 80) .
                    ' — ' . $reason,
                'entry_date' => $at->toDateString(),
            ]);

            DB::table('customer_payments')->where('id', $receipt)->update([
                'payment_status' => 'reversed',
                'reversed_at' => $at,
                'reversed_by' => mb_substr((string) ($actor->email ?: $actor->name ?: 'Admin'), 0, 180),
                'reversal_reason' => $reason,
            ]);
        }, 3);

        return redirect()->route('erp.money-receipts.show', $receipt)
            ->with('success', 'Money Receipt voided. Invoice allocation, outstanding and customer ledger reversal recorded.');
    }
}
