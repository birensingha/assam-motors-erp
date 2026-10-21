<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * FIX61B Stage 1 — one audited action, all-or-nothing, UNPAID invoices only.
 * Paid/part-paid/multiple receipt cases intentionally fail closed until
 * an independently tested allocation-ledger engine is available.
 *
 * Composes the EXISTING vetted financial credit-note and JC lifecycle handlers
 * in a single OUTER database transaction. Nested Laravel DB::transaction uses
 * the same connection/savepoint: either both actions persist or neither does.
 */
final class AdminReturnToJobCardController extends Controller
{
    public function post(Request $request,int $job,int $invoice): RedirectResponse
    {
        $actor=$request->user();
        abort_unless($actor instanceof ErpAuthUser && $actor->active && $actor->isAdmin(),403);

        $input=$request->validate([
            'reason'=>['required','string','min:10','max:1000'],
            'credit_date'=>['required','date','before_or_equal:today'],
            'confirmed_finance'=>['required','accepted'],
        ]);

        DB::transaction(function () use ($request,$job,$invoice,$input): void {
            // The original handlers re-check locks, tax totals, customer ledger,
            // payments and duplicate CN. Do not bypass their safeguards.
            $card=DB::table('job_cards')->where('id',$job)->lockForUpdate()->first();
            $inv=DB::table('invoices')->where('id',$invoice)->lockForUpdate()->first();
            if (!$card || !$inv || (int)$inv->job_card_id!==$job ||
                (int)$card->invoice_id!==$invoice ||
                strtolower((string)$card->status)!=='invoiced' ||
                strtolower((string)$inv->status)!=='issued') {
                throw ValidationException::withMessages(['invoice'=>
                    'Only an ISSUED, unpaid, linked invoice may use this single-step Return to JC.']);
            }
            if (abs((float)$inv->paid_amount)>0.009 ||
                abs((float)$inv->paid_total)>0.009 ||
                DB::table('customer_payments')->where('invoice_id',$invoice)->exists() ||
                DB::table('customer_payment_allocations')->where('invoice_id',$invoice)->exists()) {
                throw ValidationException::withMessages(['invoice'=>
                    'Receipt or allocation linked. No reversal posted. Finance must first use the separately audited paid/partial-receipt workflow.']);
            }
            if (DB::table('invoice_credit_notes')->where('invoice_id',$invoice)->exists()) {
                throw ValidationException::withMessages(['invoice'=>
                    'A Credit Note already exists: duplicate reversal blocked.']);
            }

            // These actions each use nested transactions. The outer transaction
            // prevents half-posted CN if the lifecycle reopen fails.
            app(AdminInvoiceCreditNoteController::class)->post($request,$job,$invoice);
            app(AdminJobCardLifecyclePageController::class)->reopenClosed($request,$job);

            $updated=DB::table('job_cards')->where('id',$job)->first();
            $credited=DB::table('invoices')->where('id',$invoice)->first();
            if (!in_array(strtolower((string)($updated->status??'')),
                 ['wip','in-progress','in progress','work in progress'],true) ||
                !empty($updated->invoice_id) ||
                strtolower((string)($credited->status??''))!=='reversed') {
                throw new \RuntimeException('FIX61B reconciliation failed: all changes rolled back.');
            }
        },3);

        return redirect()->route('erp.job-cards.show',$job)->with('success',
            'Job Card returned to WIP. Original invoice fully credited and preserved. '.
            'Edit Parts/Labour, then re-close and issue a NEW invoice.');
    }
}
