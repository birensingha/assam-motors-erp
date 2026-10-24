<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use App\Services\JobCardTaxTotals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * AM121 — Void customer billing for a COMPLETED ROT, never erase work history.
 * No migration is required. An immutable before/after audit event records void.
 * The labour row is retained with its billing amount zeroed; all ROT session,
 * mechanic, attendance, GPS and work event records are left untouched.
 */
final class AdminCompletedRotVoidController extends Controller
{
    private const AUDIT_ACTION = 'COMPLETED_ROT_VOID_AM121';

    private function stop(string $message, string $field='reason'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    public function store(Request $request, int $job, int $line): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof ErpAuthUser && (bool)$actor->active
            && strtolower(trim((string)$actor->role)) === 'admin', 403,
            'Admin permission required for Completed ROT Void.');

        $data = $request->validate([
            'reason' => ['required','string','min:12','max:500'],
            'admin_note' => ['required','string','min:5','max:1000'],
            'confirmation' => ['required','string','max:100'],
            'history_ack' => ['required','accepted'],
            'expected_old_total' => ['required','numeric','min:0'],
        ]);

        DB::transaction(function() use ($job,$line,$actor,$data): void {
            foreach (['job_cards','job_card_labour','rot_sessions','job_card_change_audits'] as $table) {
                if (!Schema::hasTable($table)) $this->stop('Required ROT/audit table missing. Nothing changed.');
            }
            $card = DB::table('job_cards')->where('id',$job)->lockForUpdate()->first();
            abort_unless($card,404);
            $state = strtolower(trim((string)($card->status ?? '')));
            if (!in_array($state,['wip','in progress','in-progress','work in progress'],true)) {
                $this->stop('Only an IN-PROGRESS/WIP Job Card can void a completed ROT.');
            }
            if (!empty($card->invoice_id)) {
                $this->stop('Job Card has an invoice reference. Use Credit Note and reviewed JC reopening.');
            }
            // Conservative gate: if ANY historic invoice exists, a finance reviewer
            // must verify previous credit note / receipt reversal before voiding.
            if (Schema::hasTable('invoices') && DB::table('invoices')
                ->where('job_card_id',$job)->exists()) {
                $this->stop('This JC has invoice history. Financial review is required; no direct ROT Void.');
            }
            foreach (['money_receipts','customer_payments','customer_payment_allocations'] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table,'job_card_id')
                    && DB::table($table)->where('job_card_id',$job)->exists()) {
                    $this->stop('Payment/receipt records reference this JC. Review allocations before voiding ROT billing.');
                }
            }
            $old = DB::table('job_card_labour')->where('job_card_id',$job)
                ->where('id',$line)->lockForUpdate()->first();
            abort_unless($old,404);
            $code = strtoupper(trim((string)($old->rot_code ?? '')));
            if ($code === '' || $code === 'OSL' || strtoupper(trim((string)($old->mechanic_name ?? ''))) === 'OSL') {
                $this->stop('Outside service and non-ROT items need their separate audited workflow.');
            }
            if (trim((string)$data['confirmation']) !== 'VOID '.$code) {
                $this->stop('Type exactly VOID '.$code.' to confirm.', 'confirmation');
            }
            if (abs((float)$data['expected_old_total'] - (float)($old->total ?? 0)) > 0.009) {
                $this->stop('Labour amount changed in another session. Refresh before trying again.');
            }
            if ((float)($old->total ?? 0) <= 0) {
                $this->stop('Only a positive billed ROT can be voided. Check history for an earlier reversal.');
            }
            if (DB::table('job_card_change_audits')->where('job_card_id',$job)
                ->where('entity_type','JOB_CARD_LABOUR')->where('entity_id',$line)
                ->where('action',self::AUDIT_ACTION)->exists()) {
                $this->stop('Already voided. Duplicate reversal is blocked.');
            }
            // A completed work record MUST exist. Never fabricate a completion.
            foreach (['job_card_id','labour_line_id','status'] as $required) {
                if (!Schema::hasColumn('rot_sessions',$required)) {
                    $this->stop('ROT session schema needs review before Completed Void.');
                }
            }
            $sessionQuery = DB::table('rot_sessions')->where('job_card_id',$job)
                ->where('labour_line_id',$line)->lockForUpdate();
            $sessions = $sessionQuery->get();
            if ($sessions->isEmpty()) {
                $this->stop('No ROT session found. Unstarted Labour uses separate Delete control.');
            }
            foreach ($sessions as $session) {
                if (strtolower(trim((string)$session->status)) !== 'completed') {
                    $this->stop('All linked ROT sessions must be Completed, with no running/assigned/paused work.');
                }
            }
            // Never void a purchased/subcontracted line without dedicated reversal.
            foreach (['workshop_purchases','job_card_purchases'] as $table) {
                if (!Schema::hasTable($table)) continue;
                foreach (['labour_line_id','job_card_labour_id','billing_line_id','customer_billing_line_id'] as $column) {
                    if (!Schema::hasColumn($table,$column)) continue;
                    $purchase = DB::table($table)->where($column,$line);
                    if (Schema::hasColumn($table,'job_card_id')) $purchase->where('job_card_id',$job);
                    if ($purchase->exists()) $this->stop('ROT is linked to a purchase. Reverse purchase first.');
                }
            }
            // A narrow financial-field allowlist intentionally does not touch
            // standard_hours, mechanic_id, rot_start/end, status or work seconds.
            $cols = Schema::getColumnListing('job_card_labour');
            if (!in_array('total',$cols,true) || !in_array('labour_rate',$cols,true)) {
                $this->stop('Labour amount schema changed. Stop without writing.');
            }
            $allowed = [
                'labour_rate', 'total', 'amount', 'line_amount','line_total',
                'gross', 'gross_amount', 'gross_total',
                'discount_amount', 'discount_amt', 'discount_rs',
                'taxable', 'taxable_amount', 'taxable_total',
                'gst_amount', 'gst_amt', 'tax_amount','tax_total',
                'final_amount','final_total', 'net_amount', 'net_total',
                'cess_amount', 'cgst_amount', 'sgst_amount', 'igst_amount',
            ];
            foreach ($cols as $col) {
                if (preg_match('/(?:^|_)(?:amount|amt|total|taxable|gross)$/i',$col)
                    && !in_array($col,$allowed,true)) {
                    $this->stop('Unrecognised labour financial column '.$col.'. Review totals logic before void.');
                }
            }
            $zero = [];
            foreach ($allowed as $col) if (in_array($col,$cols,true)) $zero[$col]=0;
            if (in_array('updated_at',$cols,true)) $zero['updated_at']=now();
            $beforeTotals = JobCardTaxTotals::forJob($job);
            foreach (['parts_total','labour_total','gst_total','pre_round_total','round_off','grand_total'] as $key) {
                if (abs((float)($card->{$key} ?? 0)-(float)($beforeTotals[$key] ?? 0)) > 0.021) {
                    $this->stop('Existing JC tax/GST totals disagree. Reconcile before voiding.');
                }
            }
            DB::table('job_card_labour')->where('job_card_id',$job)->where('id',$line)->update($zero);
            $afterTotals = JobCardTaxTotals::forJob($job);
            if (abs((float)$afterTotals['parts_total']-(float)$beforeTotals['parts_total']) > 0.009) {
                $this->stop('Parts total unexpectedly changed. Transaction rolled back.');
            }
            if ((float)$afterTotals['labour_total'] >= (float)$beforeTotals['labour_total']-0.009) {
                $this->stop('Labour billing total did not decrease. Transaction rolled back.');
            }
            if ((float)$afterTotals['gst_total'] > (float)$beforeTotals['gst_total']+0.009) {
                $this->stop('GST total unexpectedly increased. Transaction rolled back.');
            }
            $delta = (float)$beforeTotals['grand_total'] - (float)$afterTotals['grand_total'];
            if ($delta <= 0.009) {
                $this->stop('Void did not reduce the JC financial total. Check GST calculation; rolled back.');
            }
            if ((float)$afterTotals['grand_total'] < -0.021) {
                $this->stop('Invalid post-void grand total. Rolled back.');
            }
            $afterLine = DB::table('job_card_labour')->where('job_card_id',$job)->where('id',$line)->first();
            DB::table('job_card_change_audits')->insert([
                'job_card_id'=>$job,'entity_type'=>'JOB_CARD_LABOUR','entity_id'=>$line,
                'action'=>self::AUDIT_ACTION,
                'before_json'=>json_encode([
                    'labour'=>(array)$old,
                    'rot_sessions'=>$sessions->map(fn($row)=>(array)$row)->all(),
                    'job_card_totals'=>$beforeTotals,
                ],JSON_INVALID_UTF8_SUBSTITUTE),
                'after_json'=>json_encode([
                    'billing_voided'=>true,
                    'work_history_preserved'=>true,
                    'labour'=>(array)$afterLine,
                    'job_card_totals'=>$afterTotals,
                    'grand_total_reduction'=>round($delta,2),
                ],JSON_INVALID_UTF8_SUBSTITUTE),
                'reason'=>trim((string)$data['reason']).' | Admin note: '.trim((string)$data['admin_note']),
                'actor_id'=>$actor->id,
                'actor_name'=>mb_substr((string)($actor->email ?: $actor->name ?: 'Admin'),0,180),
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            $updated = [
                'parts_total'=>$afterTotals['parts_total'],
                'labour_total'=>$afterTotals['labour_total'],
                'gst_total'=>$afterTotals['gst_total'],
                'pre_round_total'=>$afterTotals['pre_round_total'],
                'round_off'=>$afterTotals['round_off'],
                'grand_total'=>$afterTotals['grand_total'],
                'updated_at'=>now(),
            ];
            DB::table('job_cards')->where('id',$job)->update($updated);
        },3);
        return redirect()->route('erp.job-cards.show',$job)
            ->with('success','Completed ROT billing voided with Admin audit. Original work history is preserved.')
            ->withFragment('am90-labour-billing');
    }
}
