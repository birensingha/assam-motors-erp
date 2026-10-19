<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AdminInvoiceCreditNoteController extends Controller
{
    private function admin(Request $r): ErpAuthUser {
        $u=$r->user();
        abort_unless($u instanceof ErpAuthUser && $u->active && $u->isAdmin(),403);
        return $u;
    }

    public function post(Request $request,int $job,int $invoice): RedirectResponse {
        $actor=$this->admin($request);
        $data=$request->validate([
            'reason'=>['required','string','min:10','max:1000'],
            'credit_date'=>['required','date','before_or_equal:today'],
            'confirmed_finance'=>['required','accepted'],
        ]);
        $cnId=DB::transaction(function()use($job,$invoice,$data,$actor){
            $card=DB::table('job_cards')->where('id',$job)->lockForUpdate()->first();
            $inv=DB::table('invoices')->where('id',$invoice)->lockForUpdate()->first();
            if(!$card||!$inv||(int)$inv->job_card_id!==$job ||
                (int)$card->invoice_id!==$invoice ||
                strtolower((string)$card->status)!=='invoiced' ||
                strtolower((string)$inv->status)!=='issued')
                throw ValidationException::withMessages(['invoice'=>'Only a linked ISSUED invoice can be credit-noted.']);
            if(DB::table('invoice_credit_notes')->where('invoice_id',$invoice)->exists())
                throw ValidationException::withMessages(['invoice'=>'Credit Note already exists; duplicate blocked.']);
            if(abs((float)$inv->paid_amount)>0.009||abs((float)$inv->paid_total)>0.009||
               abs((float)$inv->balance_due-(float)$inv->grand_total)>0.02)
                throw ValidationException::withMessages(['invoice'=>'Invoice is paid or adjusted. Resolve receipts/allocations in Finance before credit note.']);
            if(DB::table('customer_payments')->where('invoice_id',$invoice)->exists() ||
               DB::table('customer_payment_allocations')->where('invoice_id',$invoice)->exists())
                throw ValidationException::withMessages(['invoice'=>'Payment/receipt reference exists; Finance must reconcile before credit note.']);
            $ledgers=DB::table('customer_ledger')->where('invoice_id',$invoice)->lockForUpdate()->get();
            if($ledgers->count()!==1)
                throw ValidationException::withMessages(['invoice'=>'Invoice ledger changed; requires Finance review.']);
            $debit=$ledgers->first();
            if(strtoupper((string)$debit->entry_type)!=='DEBIT'||
               abs((float)$debit->debit-(float)$inv->grand_total)>0.02 ||
               abs((float)$debit->credit)>0.009 ||
               (int)$debit->customer_id!==(int)$inv->customer_id)
                throw ValidationException::withMessages(['invoice'=>'Original invoice debit mismatch.']);
            // A reversed invoice must retain its ORIGINAL line snapshot, even after Job Card re-edit.
            $parts=DB::table('job_card_parts')->where('job_card_id',$job)->orderBy('id')->get()->toArray();
            $labour=DB::table('job_card_labour')->where('job_card_id',$job)->orderBy('id')->get()->toArray();
            $expected=\App\Services\JobCardTaxTotals::forJob($job);
            if(abs($expected['grand_total']-(float)$inv->grand_total)>0.02 ||
               abs($expected['gst_total']-(float)$inv->gst_total)>0.02)
                throw ValidationException::withMessages(['invoice'=>'Invoice and Job Card lines differ. Finance reconciliation required before credit note.']);
            $now=now('Asia/Kolkata');
            $snapshot=['invoice'=>(array)$inv,'job'=>(array)$card,
                'parts'=>$parts,'labour'=>$labour];
            $cnId=(int)DB::table('invoice_credit_notes')->insertGetId([
                'credit_note_no'=>'TMP-CN-'.$invoice,
                'invoice_id'=>$invoice,'job_card_id'=>$job,
                'customer_id'=>$inv->customer_id,'credit_date'=>$data['credit_date'],
                'taxable_amount'=>(float)$inv->subtotal,'gst_amount'=>(float)$inv->gst_total,
                'credit_amount'=>(float)$inv->grand_total,
                'reason'=>trim($data['reason']),'status'=>'posted',
                'original_snapshot'=>json_encode($snapshot,JSON_INVALID_UTF8_SUBSTITUTE|JSON_UNESCAPED_UNICODE),
                'posted_by'=>$actor->id,'created_at'=>$now,
            ]);
            $cnNo='CN-'.$now->format('Y').'-'.str_pad((string)$cnId,5,'0',STR_PAD_LEFT);
            DB::table('invoice_credit_notes')->where('id',$cnId)->update(['credit_note_no'=>$cnNo]);
            DB::table('customer_ledger')->insert([
                'customer_id'=>$inv->customer_id,'invoice_id'=>$invoice,
                'job_card_id'=>$job,'vehicle_id'=>$card->vehicle_id,
                'entry_type'=>'CREDIT','amount'=>$inv->grand_total,'reference_no'=>$cnNo,
                'reference_type'=>'CREDIT_NOTE','reference_id'=>$cnId,
                'debit'=>0,'credit'=>$inv->grand_total,
                'note'=>'Full invoice credit for '.$inv->invoice_no,
                'narration'=>'Credit Note '.$cnNo.' against '.$inv->invoice_no,
                'created_at'=>$now,'entry_date'=>$data['credit_date'],
                'customer_name'=>$inv->customer_name,'customer_email'=>$inv->customer_email,
            ]);
            DB::table('invoices')->where('id',$invoice)->update([
                'status'=>'reversed','returned_at'=>$now,'return_reason'=>$data['reason'],
                'balance_due'=>0,'balance_total'=>0,
            ]);
            $before=(array)$card;
            DB::table('job_cards')->where('id',$job)->update([
                'status'=>'closed','invoice_id'=>null,
                'customer_public_status'=>'Invoice credited — JC closed for correction',
                'updated_at'=>$now,
            ]);
            DB::table('job_card_change_audits')->insert([
                'job_card_id'=>$job,'entity_type'=>'INVOICE','entity_id'=>$invoice,
                'action'=>'FULL_INVOICE_CREDIT_NOTE_POST',
                'before_json'=>json_encode($before,JSON_INVALID_UTF8_SUBSTITUTE),
                'after_json'=>json_encode(['credit_note_id'=>$cnId,'credit_note_no'=>$cnNo,
                    'invoice_status'=>'reversed','job_status'=>'closed','credited'=>$inv->grand_total]),
                'reason'=>$data['reason'],'actor_id'=>$actor->id,
                'actor_name'=>(string)($actor->name??'Admin'),
                'created_at'=>$now,'updated_at'=>$now,
            ]);
            return $cnId;
        },3);
        return redirect()->route('erp.job-cards.reversal',$job)
           ->with('success','Credit Note posted and ledger credited. Now enter reason and reopen CLOSED JC to WIP.');
    }

    public function print(Request $request,int $credit): View {
        $this->admin($request);
        $cn=DB::table('invoice_credit_notes')->where('id',$credit)->first();
        abort_unless($cn,404);
        $snapshot=json_decode((string)$cn->original_snapshot,true,512,JSON_THROW_ON_ERROR);
        return view('invoices.credit-note',compact('cn','snapshot'));
    }
}
