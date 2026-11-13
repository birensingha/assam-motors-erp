<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * AM253 Purchase Return / Supplier Debit Note.
 *
 * One original bill -> one or more separately numbered return documents.
 * No original purchase rows, customer billing or payment vouchers are altered.
 * Only unallocated/unpaid supplier liability may be credited (no phantom refund).
 * PART returns also reverse physical stock with a separate inventory movement.
 */
final class AdminPurchaseReturnController extends Controller
{
    private function reject(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function admin(Request $request): ErpAuthUser
    {
        $u = $request->user();
        abort_unless($u instanceof ErpAuthUser && $u->active && $u->isAdmin() &&
            in_array(strtolower(trim((string) $u->role)), ['admin','owner'], true),
            403, 'Only Owner/Admin may post or void Purchase Returns.');
        foreach (['am_purchase_returns', 'am_purchase_return_lines',
                  'workshop_purchases', 'workshop_purchase_batches', 'workshop_vendors',
                  'inventory_movements', 'parts_master', 'workshop_payment_vouchers',
                  'workshop_payment_allocations'] as $table) {
            abort_unless(Schema::hasTable($table), 503, 'Purchase Return schema is not installed.');
        }
        return $u;
    }

    private function actor(ErpAuthUser $user): string
    {
        return mb_substr(trim((string)($user->email ?: $user->name ?: 'Admin')),0,180);
    }

    private function cents(mixed $value): int
    {
        return (int)round(((float)$value)*100);
    }

    private function milli(mixed $value): int
    {
        return (int)round(((float)$value)*1000);
    }

    private function activeLines(int $purchase, bool $lock = false): array
    {
        $anchorQuery = DB::table('workshop_purchases')->where('id',$purchase);
        $anchor = ($lock ? $anchorQuery->lockForUpdate() : $anchorQuery)->first();
        abort_unless($anchor,404,'Purchase line not found.');
        if (strtoupper(trim((string)$anchor->record_status)) === 'DELETED')
            $this->reject('purchase','Original purchase is deleted.');
        $type = strtoupper(trim((string)$anchor->purchase_type));
        if (!in_array($type,['PART','OSL'],true))
            $this->reject('purchase','Only Parts / OSL purchases may be returned.');
        $batch = trim((string)($anchor->purchase_batch_no ?? ''));
        $linesQ = DB::table('workshop_purchases')->where('purchase_type',$type)
            ->whereRaw("COALESCE(record_status,'ACTIVE') <> 'DELETED'");
        if ($batch !== '') $linesQ->where('purchase_batch_no',$batch);
        else $linesQ->where('id',$purchase);
        $lines = ($lock ? $linesQ->orderBy('id')->lockForUpdate() : $linesQ->orderBy('id'))->get();
        if ($lines->isEmpty()) $this->reject('purchase','Purchase Bill has no active lines.');
        if ($lines->pluck('vendor_id')->unique()->count()!==1 ||
            (int)$lines->first()->vendor_id<=0)
            $this->reject('purchase','Purchase Bill contains inconsistent vendor identities.');
        if ($lines->pluck('purchase_type')->unique()->count()!==1)
            $this->reject('purchase','Purchase Bill contains mixed Parts and OSL lines.');
        $vendor = DB::table('workshop_vendors')->where('id',(int)$lines->first()->vendor_id)->first();
        if (!$vendor) $this->reject('purchase','Vendor Master does not exist.');
        $billRef = $batch !== '' ? $batch : (string)$anchor->purchase_no;
        return [$anchor,$lines,$vendor,$billRef,$type,$batch];
    }

    private function returnedQty(int $lineId): int
    {
        return $this->milli(DB::table('am_purchase_return_lines as l')
            ->join('am_purchase_returns as r','r.id','=','l.return_id')
            ->where('l.purchase_id',$lineId)->where('r.record_status','POSTED')
            ->sum('l.qty'));
    }

    private function priorAmount(int $lineId, string $column): int
    {
        if (!in_array($column,['discount_amount','taxable_amount','gst_amount','total_amount'],true))
            $this->reject('items','Invalid return financial field.');
        return $this->cents(DB::table('am_purchase_return_lines as l')
            ->join('am_purchase_returns as r','r.id','=','l.return_id')
            ->where('l.purchase_id',$lineId)->where('r.record_status','POSTED')
            ->sum('l.'.$column));
    }

    private function moneyFor(object $line, int $requestedMilli, int $availableMilli): array
    {
        $originalMilli = $this->milli($line->qty);
        if ($originalMilli<=0) $this->reject('items','Original purchase quantity is invalid.');
        $out = [];
        foreach (['discount_amount','taxable_amount','gst_amount','total_amount'] as $column) {
            $original = $this->cents($line->{$column} ?? 0);
            $prior = $this->priorAmount((int)$line->id,$column);
            if ($prior > $original + 1)
                $this->reject('items','Earlier returns exceed the original '.$column.'. Reconcile first.');
            $value = $requestedMilli === $availableMilli
                ? ($original-$prior)
                : (int)round($original*$requestedMilli/$originalMilli);
            if ($value<0 || $value > ($original-$prior)+1)
                $this->reject('items','Original purchase amount/previous return does not reconcile.');
            $out[$column] = max(0,$value)/100;
        }
        return $out;
    }

    private function billPaid(string $ref, string $type, int $purchaseId = 0): int
    {
        if ($type === 'PART') {
            $direct = DB::table('workshop_payment_vouchers')
                ->where('source_type','PURCHASE_INVOICE')->where('source_reference',$ref)
                ->where('record_status','ACTIVE')->sum('amount');
            $multi = DB::table('workshop_payment_allocations as a')
                ->join('workshop_payment_vouchers as v','v.id','=','a.payment_voucher_id')
                ->where('a.purchase_ref',$ref)->where('v.record_status','ACTIVE')->sum('a.amount');
        } else {
            $direct = DB::table('workshop_payment_vouchers')
                ->where('source_type','OSL_JOB')->where('source_id',$purchaseId)
                ->where('record_status','ACTIVE')->sum('amount');
            $multi = DB::table('workshop_payment_allocations as a')
                ->join('workshop_payment_vouchers as v','v.id','=','a.payment_voucher_id')
                ->where('a.purchase_ref','OSL_JOB::'.$purchaseId)
                ->where('v.record_status','ACTIVE')->sum('a.amount');
        }
        return $this->cents($direct)+$this->cents($multi);
    }

    private function priorPartCredits(string $billRef): int
    {
        return $this->cents(DB::table('am_purchase_returns')
            ->where('source_batch_no',$billRef)->where('purchase_type','PART')
            ->where('record_status','POSTED')->sum('grand_total'));
    }

    public function create(Request $request, PurchaseService $service): View
    {
        $this->admin($request);
        $q = mb_substr(trim((string)$request->query('q','')),0,120);
        $bills = $service->historyBills(['q'=>$q,'limit'=>120]);
        return view('purchase-returns.choose',compact('bills','q'));
    }

    public function form(Request $request,int $purchase): View
    {
        $this->admin($request);
        [$anchor,$lines,$vendor,$billRef,$type] = $this->activeLines($purchase);
        $details = [];
        foreach ($lines as $line) {
            $originalMilli = $this->milli($line->qty);
            $returnedMilli = $this->returnedQty((int)$line->id);
            $remaining = max(0,$originalMilli-$returnedMilli);
            $details[] = [
                'line'=>$line,'returned_qty'=>$returnedMilli/1000,
                'available_qty'=>$remaining/1000,
                'part'=> $line->part_master_id
                    ? DB::table('parts_master')->where('id',$line->part_master_id)->first()
                    : null,
            ];
        }
        $batch = $type==='PART'
            ? DB::table('workshop_purchase_batches')->where('batch_no',$billRef)->first()
            : null;
        $past = DB::table('am_purchase_returns')->where('source_batch_no',$billRef)
            ->where('purchase_type',$type)->orderByDesc('id')->limit(30)->get();
        $requestKey = (string)Str::uuid();
        return view('purchase-returns.create',
            compact('anchor','lines','vendor','billRef','type','details','batch','past','requestKey'));
    }

    public function store(Request $request,int $purchase): RedirectResponse
    {
        $actor = $this->admin($request);
        $data = $request->validate([
            'request_key'=>['required','uuid'],
            'return_date'=>['required','date_format:Y-m-d','before_or_equal:today'],
            'supplier_credit_note'=>['nullable','string','max:120'],
            'reason'=>['required','string','min:10','max:1500'],
            'items'=>['required','array','min:1','max:100'],
            'items.*'=>['nullable','numeric','min:0','max:9999999'],
        ]);
        $date = (string)$data['return_date'];
        $reason = trim((string)$data['reason']);
        $key = (string)$data['request_key'];
        $creditNote = trim((string)($data['supplier_credit_note']??'')) ?: null;

        $returnId = DB::transaction(function() use($purchase,$actor,$data,$date,$reason,$key,$creditNote): int {
            [$anchor,$source,$vendor,$billRef,$type,$batch] = $this->activeLines($purchase,true);
            $previous = DB::table('am_purchase_returns')->where('request_key',$key)->first();
            if ($previous) {
                if ((int)$previous->source_purchase_id === $purchase &&
                    $previous->record_status==='POSTED') return (int)$previous->id;
                $this->reject('request_key','The return request ID was already used. Reload the return form.');
            }
            if ($date < (string)$anchor->purchase_date)
                $this->reject('return_date','Return date cannot be before original Purchase Bill date.');
            $byId = $source->keyBy('id');
            $items = [];
            $seenSourceIds = [];
            $stockChanges = [];
            $totalQtyMilli=0;
            $taxableCents=0;$gstCents=0;$grossCents=0;
            foreach ($data['items'] as $id => $qty) {
                if (!ctype_digit((string)$id)) $this->reject('items','Invalid purchase line selection.');
                if (isset($seenSourceIds[(int)$id])) $this->reject('items','Duplicate Purchase line in return request.');
                $seenSourceIds[(int)$id]=true;
                $p=$byId->get((int)$id);
                if (!$p) $this->reject('items','Selected purchase line does not belong to this bill.');
                if (!is_numeric($qty) || !is_finite((float)$qty))
                    $this->reject('items','Invalid return quantity.');
                $qMilli=$this->milli($qty);
                if ($qMilli===0) continue;
                if ($qMilli<0 || abs((float)$qty-$qMilli/1000)>0.00005)
                    $this->reject('items','Return quantities may have at most three decimal places.');
                $originalMilli=$this->milli($p->qty);
                $availableMilli=$originalMilli-$this->returnedQty((int)$p->id);
                if ($availableMilli<=0 || $qMilli>$availableMilli)
                    $this->reject('items','Return quantity exceeds remaining purchased quantity on line '.$p->id.'.');
                $jobId=(int)($p->job_card_id??0);
                if ($jobId>0) {
                    $job=DB::table('job_cards')->where('id',$jobId)->first();
                    if (!$job || in_array(strtolower(trim((string)$job->status)),
                        ['closed','invoiced','cancelled'],true))
                        $this->reject('items','Job-linked purchase return requires an open Job Card.');
                }
                $partId=(int)($p->part_master_id??0);
                if ($type==='PART') {
                    if ($partId<=0) $this->reject('items','Source Parts Master identity is missing.');
                    if ($jobId>0 && DB::table('job_card_parts')
                        ->where('job_card_id',$jobId)->where('part_master_id',$partId)
                        ->whereRaw("UPPER(TRIM(COALESCE(issue_status,''))) IN ('ISSUED','FITTED','INSTALLED','USED','CONSUMED')")
                        ->exists())
                        $this->reject('items','Part is issued/fitted on the Job Card. Reverse job issue before Purchase Return.');
                    $part=DB::table('parts_master')->where('id',$partId)->lockForUpdate()->first();
                    if (!$part) $this->reject('items','Part Master no longer exists.');
                    $stockBeforeMilli=$this->milli($part->stock_qty);
                    if ($qMilli>$stockBeforeMilli)
                        $this->reject('items','Insufficient available stock to return '.(string)$p->description.'.');
                    $stockChanges[$partId]=['before'=>$stockBeforeMilli,'qty'=>$qMilli,'job_id'=>$jobId?:null];
                }
                $money=$this->moneyFor($p,$qMilli,$availableMilli);
                $line=[
                    'purchase_id'=>(int)$p->id, 'part_master_id'=>$partId?:null,
                    'job_card_id'=>$jobId?:null, 'item_description'=>(string)$p->description,
                    'hsn_code'=>$p->hsn_code?:null,'unit'=>$p->unit?:'PCS',
                    'qty'=>$qMilli/1000, 'unit_cost'=>(float)$p->unit_cost,
                    'discount_percent'=>(float)($p->discount_percent??0),
                    'discount_amount'=>$money['discount_amount'],
                    'gst_percent'=>(float)($p->gst_percent??0),
                    'taxable_amount'=>$money['taxable_amount'],
                    'gst_amount'=>$money['gst_amount'],
                    'total_amount'=>$money['total_amount'],
                ];
                $items[]=$line;
                $totalQtyMilli+=$qMilli;
                $taxableCents+=$this->cents($line['taxable_amount']);
                $gstCents+=$this->cents($line['gst_amount']);
                $grossCents+=$this->cents($line['total_amount']);
            }
            if (!$items) $this->reject('items','Enter a positive Return Qty for at least one purchase line.');
            if (count($items)>100) $this->reject('items','Maximum 100 return lines.');
            $pre=$grossCents;
            $grand = $type==='PART' ? (int)round($pre/100,0,PHP_ROUND_HALF_UP)*100 : $pre;
            $roundOff=$grand-$pre;
            if ($grand<=0) $this->reject('items','Return amount must be positive.');

            // A supplier credit may only reduce unpaid bill liability in this release.
            // Do not generate an artificial payment/refund or negative bill outstanding.
            if ($type==='PART') {
                $batchRow= $batch!=='' ? DB::table('workshop_purchase_batches')
                    ->where('batch_no',$billRef)->where('purchase_type','PART')
                    ->lockForUpdate()->first() : null;
                $billCents=$batchRow && $batchRow->record_status==='ACTIVE'
                    ? $this->cents($batchRow->grand_total)
                    : $this->cents($source->sum('total_amount'));
                $paid=$this->billPaid($billRef,'PART');
                $returned=$this->priorPartCredits($billRef);
                if ($grand+$paid+$returned > $billCents+1)
                    $this->reject('items','Return exceeds unpaid Purchase Bill amount. Settle/reverse the existing vendor payments or reduce return quantity first.');
            } else {
                foreach($items as $item) {
                    $p=$byId->get($item['purchase_id']);
                    $paid=$this->billPaid('','OSL',(int)$p->id);
                    $returned=$this->priorAmount((int)$p->id,'total_amount');
                    if ($this->cents($item['total_amount'])+$paid+$returned >
                        $this->cents($p->total_amount)+1)
                        $this->reject('items','OSL return on line '.$p->id.' exceeds its unpaid supplier amount.');
                }
            }

            $now=now('Asia/Kolkata');
            $returnId=(int)DB::table('am_purchase_returns')->insertGetId([
                'return_no'=>'TMP-PR-'.strtoupper(bin2hex(random_bytes(7))),
                'request_key'=>$key,
                'source_purchase_id'=>$purchase,
                'source_batch_no'=>$billRef,
                'purchase_type'=>$type,
                'vendor_id'=>(int)$vendor->id,
                'vendor_name'=>(string)$vendor->vendor_name,
                'return_date'=>$date,'supplier_credit_note'=>$creditNote,'reason'=>$reason,
                'item_count'=>count($items),'total_qty'=>$totalQtyMilli/1000,
                'taxable_amount'=>$taxableCents/100,'gst_amount'=>$gstCents/100,
                'pre_round_total'=>$pre/100,'round_off'=>$roundOff/100,
                'grand_total'=>$grand/100,'record_status'=>'POSTED',
                'created_by'=>$this->actor($actor),'created_at'=>$now,
            ]);
            $returnNo='PR-'.$now->format('Y').'-'.str_pad((string)$returnId,6,'0',STR_PAD_LEFT);
            DB::table('am_purchase_returns')->where('id',$returnId)->update(['return_no'=>$returnNo]);
            foreach($items as $line) {
                DB::table('am_purchase_return_lines')->insert(array_merge($line,[
                    'return_id'=>$returnId,'created_at'=>$now
                ]));
                if ($type==='PART') {
                    $id=(int)$line['part_master_id'];
                    $change=$stockChanges[$id];
                    $after=($change['before']-$change['qty'])/1000;
                    DB::table('parts_master')->where('id',$id)->update([
                        'stock_qty'=>$after
                    ]);
                    DB::table('inventory_movements')->insert([
                        'part_master_id'=>$id,
                        'job_card_id'=>$change['job_id'],
                        'movement_type'=>'PURCHASE_RETURN_OUT',
                        'qty_in'=>0,'qty_out'=>$line['qty'],
                        'balance_after'=>$after,
                        'reference_no'=>$returnNo,
                        'note'=>'Supplier Purchase Return '.$returnNo.' against '.$billRef.
                            ' · '.$reason,'created_at'=>$now
                    ]);
                }
                if (Schema::hasTable('purchase_audit_logs')) {
                    DB::table('purchase_audit_logs')->insert([
                        'purchase_id'=>$line['purchase_id'],
                        'purchase_no'=>(string)$byId->get($line['purchase_id'])->purchase_no,
                        'batch_no'=>$billRef,
                        'action'=>'PURCHASE_RETURN_POSTED',
                        'actor'=>$this->actor($actor),
                        'reason'=>mb_substr($reason,0,255),
                        'before_json'=>json_encode((array)$byId->get($line['purchase_id']),JSON_INVALID_UTF8_SUBSTITUTE),
                        'after_json'=>json_encode(['return_id'=>$returnId,'return_no'=>$returnNo,
                            'qty'=>$line['qty'],'amount'=>$line['total_amount']],JSON_INVALID_UTF8_SUBSTITUTE),
                        'created_at'=>$now
                    ]);
                }
            }
            return $returnId;
        },3);

        return redirect()->route('erp.purchase-returns.show',$returnId)
            ->with('success','Purchase Return posted. Supplier payable and stock returns have been recorded with audit history.');
    }

    public function show(Request $request,int $return): View
    {
        $this->admin($request);
        $doc=DB::table('am_purchase_returns')->where('id',$return)->first();
        abort_unless($doc,404,'Purchase Return not found.');
        $lines=DB::table('am_purchase_return_lines')->where('return_id',$return)
            ->orderBy('id')->get();
        $original=DB::table('workshop_purchases')
            ->where('id',(int)$doc->source_purchase_id)->first();
        return view('purchase-returns.show',compact('doc','lines','original'));
    }

    public function print(Request $request,int $return): View
    {
        $this->admin($request);
        $doc=DB::table('am_purchase_returns')->where('id',$return)->first();
        abort_unless($doc,404,'Purchase Return not found.');
        $lines=DB::table('am_purchase_return_lines')->where('return_id',$return)
            ->orderBy('id')->get();
        $original=DB::table('workshop_purchases')
            ->where('id',(int)$doc->source_purchase_id)->first();
        return view('purchase-returns.print',compact('doc','lines','original'));
    }

    public function void(Request $request,int $return): RedirectResponse
    {
        $actor=$this->admin($request);
        $d=$request->validate(['reason'=>['required','string','min:10','max:300']]);
        $reason=trim((string)$d['reason']);
        DB::transaction(function() use($return,$actor,$reason): void {
            $doc=DB::table('am_purchase_returns')->where('id',$return)
                ->lockForUpdate()->first();
            abort_unless($doc,404,'Purchase Return not found.');
            if ($doc->record_status!=='POSTED')
                $this->reject('reason','Purchase Return already voided.');
            $lines=DB::table('am_purchase_return_lines')->where('return_id',$return)
                ->orderBy('id')->lockForUpdate()->get();
            if ($lines->count()!== (int)$doc->item_count)
                $this->reject('reason','Purchase Return line count differs from posted document.');
            $original=DB::table('workshop_purchases')
                ->where('id',$doc->source_purchase_id)->lockForUpdate()->first();
            if (!$original) $this->reject('reason','Original Purchase Bill missing.');
            $now=now('Asia/Kolkata');
            foreach($lines as $line) {
                if ($doc->purchase_type==='PART') {
                    $part=DB::table('parts_master')
                        ->where('id',$line->part_master_id)->lockForUpdate()->first();
                    if (!$part) $this->reject('reason','Parts Master missing; contact Admin before reversal.');
                    $after=($this->milli($part->stock_qty)+$this->milli($line->qty))/1000;
                    DB::table('parts_master')->where('id',$line->part_master_id)
                        ->update(['stock_qty'=>$after]);
                    DB::table('inventory_movements')->insert([
                        'part_master_id'=>$line->part_master_id,'job_card_id'=>$line->job_card_id,
                        'movement_type'=>'PURCHASE_RETURN_VOID_IN',
                        'qty_in'=>$line->qty,'qty_out'=>0,
                        'balance_after'=>$after,'reference_no'=>$doc->return_no,
                        'note'=>'Void supplier return: '.$reason,'created_at'=>$now,
                    ]);
                }
                if(Schema::hasTable('purchase_audit_logs')) {
                    $p=DB::table('workshop_purchases')->where('id',$line->purchase_id)->first();
                    DB::table('purchase_audit_logs')->insert([
                        'purchase_id'=>$line->purchase_id,
                        'purchase_no'=>$p->purchase_no??null,'batch_no'=>$doc->source_batch_no,
                        'action'=>'PURCHASE_RETURN_VOID',
                        'actor'=>$this->actor($actor),
                        'reason'=>mb_substr($reason,0,255),
                        'before_json'=>json_encode(['return_no'=>$doc->return_no,'status'=>'POSTED']),
                        'after_json'=>json_encode(['return_no'=>$doc->return_no,'status'=>'VOID']),
                        'created_at'=>$now
                    ]);
                }
            }
            DB::table('am_purchase_returns')->where('id',$return)->update([
                'record_status'=>'VOID','voided_at'=>$now,
                'voided_by'=>$this->actor($actor),'void_reason'=>$reason
            ]);
        },3);

        return redirect()->route('erp.purchase-returns.show',$return)
            ->with('success','Purchase Return voided with audit; stock and payable credit reversal recorded.');
    }
}
