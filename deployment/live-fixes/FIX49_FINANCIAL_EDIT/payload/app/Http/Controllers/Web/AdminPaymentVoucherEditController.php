<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use App\Services\ExpensePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * FIX49 — audited correction of existing Payment Vouchers.
 * No deletes or new payment rows. No edits to an invoice, purchase or stock.
 * The original payment is preserved in before_json and the correction is logged.
 * LIVE INSTALL REQUIRES preflight verifying VendorService derives payments from
 * workshop_payment_vouchers (the documented P11 source) and no parallel posting.
 */
final class AdminPaymentVoucherEditController extends Controller
{
    private function admin(Request $request): ErpAuthUser
    {
        $user=$request->user();
        abort_unless($user instanceof ErpAuthUser && $user->active && $user->isAdmin(),403,'Admin permission required.');
        return $user;
    }

    private function columns(string $table): array
    {
        return Schema::hasTable($table) ? Schema::getColumnListing($table) : [];
    }

    private function row(int $payment): object
    {
        abort_unless(Schema::hasTable('workshop_payment_vouchers'),503,'Voucher table unavailable.');
        $row=DB::table('workshop_payment_vouchers')->where('id',$payment)->first();
        abort_unless($row,404);
        return $row;
    }

    private function stamp(object $row): string
    {
        return hash('sha256',implode('|',[
            $row->id, (string)($row->payment_date??''),
            number_format((float)($row->amount??0),2,'.',''),
            (string)($row->source_type??''),(string)($row->source_reference??''),
            (string)($row->record_status??''),(string)($row->payment_mode??''),
            (string)($row->account_name??''), (string)($row->reference_no??'')
        ]));
    }

    public function register(Request $request): View
    {
        $this->admin($request);
        abort_unless(Schema::hasTable('workshop_payment_vouchers'),503);
        $query=DB::table('workshop_payment_vouchers')->orderByDesc('id');
        $term=trim((string)$request->query('q',''));
        if($term!=='') {
            $cols=$this->columns('workshop_payment_vouchers');
            $filter=array_values(array_intersect(['voucher_no','payment_no','reference_no','source_reference','payee_name'],$cols));
            if($filter){
                $query->where(function($q)use($filter,$term){
                    foreach($filter as $col) $q->orWhere($col,'like','%'.$term.'%');
                });
            }
        }
        return view('workshop-controls.payment-voucher-register',[
            'rows'=>$query->paginate(40)->withQueryString(), 'term'=>$term,
        ]);
    }

    public function edit(Request $request, int $payment): View
    {
        $this->admin($request);
        $voucher=$this->row($payment);
        $active=strtoupper((string)($voucher->record_status??'ACTIVE'))==='ACTIVE' && empty($voucher->voided_at);
        return view('workshop-controls.payment-voucher-edit',[
            'voucher'=>$voucher,'active'=>$active,'stamp'=>$this->stamp($voucher),
            'sourceTypes'=>ExpensePaymentService::SOURCE_TYPES,
        ]);
    }

    private function sourceCap(object $old, ExpensePaymentService $service): ?float
    {
        $type=strtoupper((string)($old->source_type??''));
        if(in_array($type,['ADVANCE','ON_ACCOUNT','OTHER'],true)) return null;
        $reference=(string)($old->source_reference??'');
        if($reference==='') return null;
        $known=['PURCHASE_INVOICE','OSL_JOB','EXPENSE_VOUCHER'];
        if(!in_array($type,$known,true)) return null;
        // payableSources() already nets posted payment vouchers, vendor and
        // purchase round-off by the authoritative service. Add back current
        // payment amount to determine the permitted replacement ceiling.
        foreach($service->payableSources() as $source){
            if(($source['type']??'')===$type && ($source['key']??'')===$type.'::'.$reference){
                return round((float)($source['outstanding']??0)+(float)$old->amount,2);
            }
        }
        // Fully paid purchase sources can disappear from the outstanding list.
        // For PART purchase use authoritative rounded batch total, never line
        // sum (P9 settlement fix). Fail closed for unknown source schemas.
        if($type==='PURCHASE_INVOICE' && Schema::hasTable('workshop_purchase_batches')){
            $batch=DB::table('workshop_purchase_batches')
                ->where('batch_no',$reference)->where('record_status','ACTIVE')
                ->where('purchase_type','PART')->first();
            if($batch && is_numeric($batch->grand_total??null)){
                $otherPaid=DB::table('workshop_payment_vouchers')
                    ->where('source_type',$type)->where('source_reference',$reference)
                    ->where('record_status','ACTIVE')->where('id','<>',$old->id)->sum('amount');
                return round(max(0,(float)$batch->grand_total-(float)$otherPaid),2);
            }
        }
        return -1.0;
    }

    public function update(Request $request,int $payment,ExpensePaymentService $service): RedirectResponse
    {
        $user=$this->admin($request);
        $input=$request->validate([
            'expected_stamp'=>['required','string','size:64'],
            'payment_date'=>['required','date_format:Y-m-d'],
            'amount'=>['required','numeric','gt:0','lte:100000000'],
            'payment_mode'=>['required','string','max:30'],
            'account_name'=>['nullable','string','max:120'],
            'reference_no'=>['nullable','string','max:120'],
            'narration'=>['nullable','string','max:1000'],
            'reason'=>['required','string','min:5','max:1000'],
        ]);
        abort_unless(Schema::hasTable('workshop_payment_edit_audits'),503,'Payment edit audit migration missing.');
        $mandatory=['payment_date','amount','payment_mode','record_status','source_type','source_reference'];
        if(array_diff($mandatory,$this->columns('workshop_payment_vouchers'))){
            throw ValidationException::withMessages(['amount'=>'Voucher schema not verified. No amount changed.']);
        }
        DB::transaction(function()use($payment,$input,$user,$service){
            $voucher=DB::table('workshop_payment_vouchers')->where('id',$payment)->lockForUpdate()->first();
            abort_unless($voucher,404);
            if(strtoupper((string)$voucher->record_status)!=='ACTIVE'||!empty($voucher->voided_at)){
                throw ValidationException::withMessages(['amount'=>'Voided/inactive voucher cannot be edited.']);
            }
            if(!hash_equals($this->stamp($voucher),(string)$input['expected_stamp'])){
                throw ValidationException::withMessages(['amount'=>'Voucher changed on another screen. Refresh before saving.']);
            }
            $old=(array)$voucher;
            $newAmount=round((float)$input['amount'],2);
            $cap=$this->sourceCap($voucher,$service);
            if($cap!==null && ($cap<0||$newAmount>$cap+0.009)){
                throw ValidationException::withMessages(['amount'=>'Amount exceeds available invoice/OSL/expense outstanding, or original source is not verifiable. No changes saved.']);
            }
            $cols=$this->columns('workshop_payment_vouchers');
            $changes=[
                'payment_date'=>$input['payment_date'], 'amount'=>$newAmount,
                'payment_mode'=>$input['payment_mode'],
                'account_name'=>trim((string)($input['account_name']??'')),
                'reference_no'=>trim((string)($input['reference_no']??'')),
                'narration'=>trim((string)($input['narration']??'')),
            ];
            // The current payment table may use 'notes' instead of 'narration'.
            // Preserve the real column rather than discarding the user's note.
            if(!in_array('narration',$cols,true)) {
                unset($changes['narration']);
                if(in_array('notes',$cols,true)) $changes['notes']=trim((string)($input['narration']??''));
                elseif(in_array('note',$cols,true)) $changes['note']=trim((string)($input['narration']??''));
            }
            $changes=array_intersect_key($changes,array_flip($cols));
            if(count($changes)<3){
                throw ValidationException::withMessages(['amount'=>'Required Voucher columns unavailable.']);
            }
            // Note: changing amount here is permitted only when the staging
            // preflight has certified there are NO materialized parallel ledger
            // postings needing separate updates. Otherwise installation aborts.
            DB::table('workshop_payment_vouchers')->where('id',$payment)->update($changes);
            $after=(array)DB::table('workshop_payment_vouchers')->where('id',$payment)->first();
            DB::table('workshop_payment_edit_audits')->insert([
                'payment_voucher_id'=>$payment,
                'voucher_no'=>(string)($voucher->voucher_no??$voucher->payment_no??''),
                'previous_amount'=>(float)$voucher->amount,
                'corrected_amount'=>$newAmount,
                'before_json'=>json_encode($old,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'after_json'=>json_encode($after,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'reason'=>trim((string)$input['reason']),
                'actor'=>(string)($user->email?:$user->name?:'ADMIN'),
                'created_at'=>now(),
            ]);
        },3);
        return redirect()->route('erp.fix49.payment-edit',$payment)->with('success','Voucher corrected with full before/after audit. Verify Vendor Ledger balance.');
    }
}