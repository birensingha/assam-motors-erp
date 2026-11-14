<?php
declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * AM256_EXPENSE_AMOUNT_CORRECTION.
 *
 * Financially safe change to an UNPAID and ACTIVE Expense Voucher only.
 * No payment/receipt/staff allocation/JC totals/paid status updates.
 * Preserves a permanent old/new audit row in the same transaction.
 */
final class ExpenseAmountCorrectionService
{
    public function correct(
        int $expenseId,
        float $newTaxable,
        float $expectedTaxable,
        string $reason,
        string $actor
    ): array {
        return DB::transaction(function () use (
            $expenseId, $newTaxable, $expectedTaxable, $reason, $actor
        ): array {
            $row = DB::table('workshop_expenses')
                ->where('id', $expenseId)->lockForUpdate()->first();
            if (!$row || (string)$row->record_status !== 'ACTIVE') {
                throw ValidationException::withMessages([
                    'expense' => 'Only an ACTIVE Expense Voucher may be corrected.'
                ]);
            }

            $paid = (float) $row->paid_amount;
            if (abs($paid) > 0.009 || strtoupper((string)$row->payment_status) !== 'UNPAID') {
                throw ValidationException::withMessages([
                    'expense' => 'Expense has a paid balance or payment status. Reverse the payment through its audited workflow before any correction.'
                ]);
            }

            // Include both active AND voided voucher references; correcting a
            // previously paid/voided expense needs a separate reversal review.
            $hasDirectPayment = DB::table('workshop_payment_vouchers')
                ->where(function ($query) use ($expenseId, $row) {
                    $query->where(function ($inner) use ($expenseId) {
                        $inner->where('source_type', 'EXPENSE_VOUCHER')
                            ->where('source_id', $expenseId);
                    })->orWhere(function ($inner) use ($row) {
                        $inner->where('source_type', 'EXPENSE_VOUCHER')
                            ->where('source_reference', (string)$row->expense_no);
                    });
                })->exists();

            $hasAllocatedPayment = DB::table('workshop_payment_allocations')
                ->where('purchase_ref', 'EXPENSE_VOUCHER::'.$expenseId)
                ->exists();

            if ($hasDirectPayment || $hasAllocatedPayment) {
                throw ValidationException::withMessages([
                    'expense' => 'A payment or allocation reference exists for this Expense. Direct amount editing is locked; use audited adjustment/reversal.'
                ]);
            }

            $oldTaxable = round((float)$row->taxable_amount, 2);
            $oldTotal = round((float)$row->total_amount, 2);
            $newTaxable = round($newTaxable, 2);
            $expectedTaxable = round($expectedTaxable, 2);
            $reason = trim($reason);
            $actor = mb_substr(trim($actor), 0, 180);
            if (mb_strlen($reason) < 8 || mb_strlen($reason) > 300) {
                throw ValidationException::withMessages([
                    'reason' => 'Enter an audit reason of 8 to 300 characters.'
                ]);
            }
            if ($actor === '') {
                throw ValidationException::withMessages([
                    'expense' => 'An authenticated Admin identity is required for this correction.'
                ]);
            }
            if ($newTaxable <= 0 || $newTaxable > 99999999) {
                throw ValidationException::withMessages([
                    'taxable_amount' => 'Corrected expense amount must be above zero.'
                ]);
            }
            if (abs($oldTaxable - $expectedTaxable) > 0.009) {
                throw ValidationException::withMessages([
                    'expense' => 'Expense amount changed since opening the editor. Refresh before editing.'
                ]);
            }
            if (abs($oldTaxable - $newTaxable) < 0.009) {
                throw ValidationException::withMessages([
                    'taxable_amount' => 'Enter a new amount different from the saved amount.'
                ]);
            }

            // GST % is never altered by a value-only correction.
            $gst = round((float)$row->gst_percent, 2);
            $newGstAmount = round($newTaxable * $gst / 100, 2);
            $newTotal = round($newTaxable + $newGstAmount, 2);
            DB::table('workshop_expenses')->where('id', $expenseId)->update([
                'taxable_amount' => $newTaxable,
                'gst_amount' => $newGstAmount,
                'total_amount' => $newTotal,
                // Unpaid and zero remain unchanged. Do not fabricate a payment.
            ]);
            DB::table('am_expense_amount_corrections')->insert([
                'expense_id' => $expenseId,
                'expense_no' => (string)$row->expense_no,
                'old_taxable_amount' => $oldTaxable,
                'new_taxable_amount' => $newTaxable,
                'old_gst_percent' => $gst,
                'new_gst_percent' => $gst,
                'old_gst_amount' => round((float)$row->gst_amount,2),
                'new_gst_amount' => $newGstAmount,
                'old_total_amount' => $oldTotal,
                'new_total_amount' => $newTotal,
                'reason' => $reason,
                'changed_by' => $actor,
                'created_at' => now('Asia/Kolkata'),
            ]);
            return [
                'expense_id' => $expenseId,
                'expense_no' => (string)$row->expense_no,
                'old_total' => $oldTotal,
                'new_total' => $newTotal,
            ];
        }, 3);
    }
}
