<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * AM238 Vendor Debit / On Account balances.
 *
 * Credit source = REAL active money paid to Vendor but not yet allocated to bills.
 * Future debit-adjustment is allocation only, NEVER another cash/bank payment.
 * Called inside the parent voucher DB transaction. Consumer must lock matching
 * voucher source rows before mutating settlement.
 */
final class VendorDebitSettlementService
{
    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['vendor_debits'=>$message]);
    }

    public function requireSchema(): void
    {
        if (!Schema::hasTable('workshop_vendor_debit_uses')) {
            $this->reject('Debit adjustment register not installed. No payment saved.');
        }
    }

    public function sources(?int $vendorId=null): array
    {
        $this->requireSchema();
        $applied=DB::table('workshop_payment_allocations')
            ->selectRaw('payment_voucher_id, SUM(amount) as allocated')
            ->groupBy('payment_voucher_id');
        $used=DB::table('workshop_vendor_debit_uses as u')
            ->join('workshop_payment_vouchers as s','s.id','=','u.settlement_payment_voucher_id')
            ->where('s.record_status','ACTIVE')
            ->selectRaw('u.source_payment_voucher_id, SUM(u.amount) as adjusted')
            ->groupBy('u.source_payment_voucher_id');
        $query=DB::table('workshop_payment_vouchers as p')
            ->join('workshop_vendors as v','v.id','=','p.vendor_id')
            ->leftJoinSub($applied,'a','a.payment_voucher_id','=','p.id')
            ->leftJoinSub($used,'u','u.source_payment_voucher_id','=','p.id')
            ->where('p.record_status','ACTIVE')->where('p.payee_type','VENDOR')
            ->where('p.vendor_id','>',0)
            // Paid purchase invoices and OSL bills are ALREADY settled and are never credits.
            ->where(function($q){$q->whereIn('p.source_type',['ON_ACCOUNT','ADVANCE','NEW_REFERENCE'])
                ->orWhere(function($mix){$mix->where('p.source_type','PURCHASE_INVOICE')
                    ->where('p.source_reference','like','OA-%');});});
        if($vendorId!==null)$query->where('p.vendor_id',$vendorId);
        return $query->orderBy('p.id')->get([
            'p.id','p.voucher_no','p.source_reference','p.source_type','p.payment_date',
            'p.vendor_id','v.vendor_name','p.amount','a.allocated','u.adjusted'
        ])->map(function($p){
            $cash=(int)round((float)$p->amount*100);
            $allocated=(int)round((float)($p->allocated??0)*100);
            $adjusted=(int)round((float)($p->adjusted??0)*100);
            $left=$cash-$allocated-$adjusted;
            return [
                'voucher_id'=>(int)$p->id,
                'vendor_id'=>(int)$p->vendor_id,
                'vendor_name'=>(string)$p->vendor_name,
                'reference'=>str_starts_with((string)($p->source_reference??''),'OA-') ? (string)$p->source_reference : 'OA-'.(string)$p->voucher_no,
                'original_reference'=>(string)($p->source_reference??''),
                'date'=>(string)$p->payment_date,
                'payment_type'=>(string)$p->source_type,
                'paid'=>round($cash/100,2),
                'applied'=>round($allocated/100,2),
                'used'=>round($adjusted/100,2),
                'available'=>round(max(0,$left)/100,2),
            ];
        })->filter(fn($r)=>$r['available']>0.009)->values()->all();
    }

    public function parseAllocations(array $raw): array
    {
        $out=[];
        if(count($raw)>30)$this->reject('Maximum 30 Debit reference allocations per voucher.');
        foreach($raw as $r){
            if(!is_array($r))$this->reject('Invalid Debit reference.');
            $id=(int)($r['voucher_id']??0);
            $value=(float)($r['amount']??0);
            if($id<=0 || !is_finite($value) || $value<=0 || $value>99999999 ||
                round($value,2)!=$value || isset($out[$id]))$this->reject('Invalid or duplicated Debit reference/amount.');
            $out[$id]=(int)round($value*100);
        }
        ksort($out,SORT_NUMERIC);
        return $out;
    }

    /**
     * Validate under lock; call after bill vendor identity is verified but before
     * parent payment inserted, and in the SAME DB transaction as allocation insert.
     */
    public function lockAndValidate(int $vendorId, array $debits): void
    {
        $this->requireSchema();
        if($vendorId<=0)$this->reject('Select a Vendor Master before Debit adjustment.');
        foreach($debits as $sourceId=>$cents){
            $source=DB::table('workshop_payment_vouchers')
                ->where('id',$sourceId)->lockForUpdate()->first();
            if(!$source || $source->record_status!=='ACTIVE' ||
                (int)$source->vendor_id!==$vendorId || $source->payee_type!=='VENDOR' ||
                !(in_array($source->source_type,['ON_ACCOUNT','ADVANCE','NEW_REFERENCE'],true) ||
                  ($source->source_type==='PURCHASE_INVOICE' &&
                   str_starts_with((string)$source->source_reference,'OA-'))))
                $this->reject('Selected Debit reference is invalid, voided or belongs to another Vendor.');
            $allocated=(int)round((float)DB::table('workshop_payment_allocations')
                ->where('payment_voucher_id',$sourceId)->sum('amount')*100);
            $used=(int)round((float)DB::table('workshop_vendor_debit_uses as u')
                ->join('workshop_payment_vouchers as p','p.id','=','u.settlement_payment_voucher_id')
                ->where('u.source_payment_voucher_id',$sourceId)
                ->where('p.record_status','ACTIVE')->sum('u.amount')*100);
            $available=(int)round((float)$source->amount*100)-$allocated-$used;
            if($available<$cents)$this->reject('Debit '.(string)$source->voucher_no.
                ' has only ₹'.number_format(max(0,$available)/100,2).' available. Refresh and reselect.');
        }
    }

    public function recordUses(int $vendorId,int $settlementId,string $requestKey,array $debits): void
    {
        foreach($debits as $sourceId=>$cents){
            DB::table('workshop_vendor_debit_uses')->insert([
                'source_payment_voucher_id'=>$sourceId,
                'settlement_payment_voucher_id'=>$settlementId,
                'vendor_id'=>$vendorId,
                'amount'=>round($cents/100,2),
                'request_key'=>$requestKey,
                'created_at'=>now('Asia/Kolkata')
            ]);
        }
    }

    public function activeUsesForSource(int $paymentId): bool
    {
        $this->requireSchema();
        return DB::table('workshop_vendor_debit_uses as u')
            ->join('workshop_payment_vouchers as p','p.id','=','u.settlement_payment_voucher_id')
            ->where('u.source_payment_voucher_id',$paymentId)
            ->where('p.record_status','ACTIVE')->exists();
    }
}
