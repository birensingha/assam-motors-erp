<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Ramsey\Uuid\Uuid;

/**
 * AM254: source-line-first Purchase Return.
 *
 * Search any part/OSL, display original vendor/bill/customer JC/invoice,
 * select exact source purchase lines and enter quantities. The original AM253
 * posting engine is reused unchanged, with one auditable return document per
 * original supplier bill (even when a single entry spans several bills/vendors).
 * All grouped posts are atomic in the outer database transaction.
 */
final class AdminPurchaseReturnItemwiseController extends Controller
{
    private function admin(Request $request): ErpAuthUser
    {
        $user=$request->user();
        abort_unless($user instanceof ErpAuthUser && $user->active && $user->isAdmin() &&
            in_array(strtolower(trim((string)$user->role)),['owner','admin'],true),
            403,'Owner/Admin permission required for Purchase Return.');
        return $user;
    }

    private function reject(string $field,string $message): never
    {
        throw ValidationException::withMessages([$field=>$message]);
    }

    public function create(Request $request): View
    {
        $this->admin($request);
        return view('purchase-returns.itemwise',[
            'requestKey'=>(string)Str::uuid(),
            'today'=>now('Asia/Kolkata')->format('Y-m-d'),
        ]);
    }

    /**
     * AM256_VENDOR_PICKER: selectable Vendor Master records with posted purchase history.
     * No free-text vendor creation on a supplier debit note.
     */
    public function vendors(Request $request): JsonResponse
    {
        $this->admin($request);
        $filter=$request->validate(['q'=>['nullable','string','max:120']]);
        $q=trim((string)($filter['q']??''));
        if(mb_strlen($q)<1)return response()->json(['vendors'=>[]]);
        $like='%'.str_replace(['%','_'],['\\%','\\_'],$q).'%';
        $vendors=DB::table('workshop_vendors as v')
            ->where('v.vendor_name','like',$like)
            ->whereExists(function($sub){
                $sub->selectRaw('1')
                    ->from('workshop_purchases as p')
                    ->whereColumn('p.vendor_id','v.id')
                    ->whereIn('p.purchase_type',['PART','OSL'])
                    ->whereRaw("COALESCE(p.record_status,'ACTIVE') <> 'DELETED'");
            })
            ->orderBy('v.vendor_name')
            ->limit(25)
            ->get(['v.id','v.vendor_name'])
            ->map(static fn($v)=>[
                'id'=>(int)$v->id,
                'name'=>(string)$v->vendor_name
            ])->all();
        return response()->json(['vendors'=>$vendors]);
    }

    public function search(Request $request): JsonResponse
    {
        $this->admin($request);
        // AM256_VENDOR_PICKER: mandatory actual Vendor Master selection on item search.
        $filter=$request->validate([
            'q'=>['nullable','string','max:120'],
            'vendor_id'=>['required','integer','min:1','exists:workshop_vendors,id']
        ]);
        $vendorId=(int)$filter['vendor_id'];
        $q=trim((string)($filter['q']??''));
        if(mb_strlen($q)<2)return response()->json(['items'=>[],'query'=>$q,'vendor_id'=>$vendorId]);
        foreach(['am_purchase_returns','am_purchase_return_lines','workshop_purchases'] as $t)
            abort_unless(Schema::hasTable($t),503,'Purchase Return source is not ready.');

        $posted=DB::table('am_purchase_return_lines as rl')
            ->join('am_purchase_returns as rh','rh.id','=','rl.return_id')
            ->where('rh.record_status','POSTED')
            ->selectRaw('rl.purchase_id, COALESCE(SUM(rl.qty),0) AS returned_qty')
            ->groupBy('rl.purchase_id');

        $lastInv=DB::table('invoices')->whereNotNull('job_card_id')
            ->selectRaw('job_card_id, MAX(id) AS latest_id')->groupBy('job_card_id');

        $query=DB::table('workshop_purchases as p')
            ->leftJoin('parts_master as pm','pm.id','=','p.part_master_id')
            ->leftJoin('workshop_vendors as v','v.id','=','p.vendor_id')
            ->leftJoin('job_cards as jc','jc.id','=','p.job_card_id')
            ->leftJoinSub($posted,'returned','returned.purchase_id','=','p.id')
            ->leftJoinSub($lastInv,'newest','newest.job_card_id','=','p.job_card_id')
            ->leftJoin('invoices as inv','inv.id','=','newest.latest_id')
            ->whereRaw("COALESCE(p.record_status,'ACTIVE') <> 'DELETED'")
            ->where('p.vendor_id',$vendorId)
            ->whereIn('p.purchase_type',['PART','OSL'])
            ->where(function($w)use($q){
                $like='%'.str_replace(['%','_'],['\%','\_'],$q).'%';
                $w->where('p.description','like',$like)
                  ->orWhere('pm.part_name','like',$like)
                  ->orWhere('pm.part_code','like',$like)
                  ->orWhere('v.vendor_name','like',$like)
                  ->orWhere('p.supplier_invoice','like',$like)
                  ->orWhere('p.purchase_batch_no','like',$like)
                  ->orWhere('p.purchase_no','like',$like)
                  ->orWhere('jc.job_no','like',$like)
                  ->orWhere('jc.vehicle_reg_no','like',$like)
                  ->orWhere('inv.invoice_no','like',$like);
            })
            ->orderByDesc('p.id')
            ->limit(30)
            ->get([
                'p.id','p.purchase_type','p.purchase_no','p.purchase_batch_no','p.purchase_date',
                'p.description','p.qty','p.unit_cost','p.discount_percent','p.gst_percent',
                'p.total_amount','p.supplier_invoice','p.job_card_id','p.vendor_id',
                'v.vendor_name','pm.part_code','pm.part_name','pm.stock_qty',
                'jc.job_no','jc.vehicle_reg_no','jc.customer_name','inv.invoice_no as customer_invoice',
                'returned.returned_qty',
            ]);

        $items=$query->map(function($p){
            $purchased=(int)round((float)$p->qty*1000);
            $prior=(int)round((float)($p->returned_qty??0)*1000);
            $available=max(0,$purchased-$prior);
            $physical=$p->purchase_type==='PART' && $p->stock_qty!==null
                ? max(0,(int)round((float)$p->stock_qty*1000)):null;
            return [
                'purchase_id'=>(int)$p->id,
                'purchase_type'=>(string)$p->purchase_type,
                'item_name'=>(string)($p->part_name ?: $p->description),
                'item_code'=>(string)($p->part_code??''),
                'vendor_name'=>(string)($p->vendor_name??''),
                'vendor_id'=>(int)$p->vendor_id,
                'purchase_bill'=>(string)($p->purchase_batch_no ?: $p->purchase_no),
                'purchase_date'=>(string)$p->purchase_date,
                'supplier_invoice'=>(string)($p->supplier_invoice??''),
                'job_no'=>(string)($p->job_no??''),
                'vehicle_no'=>(string)($p->vehicle_reg_no??''),
                'customer_name'=>(string)($p->customer_name??''),
                'customer_invoice'=>(string)($p->customer_invoice??''),
                'purchased_qty'=>round($purchased/1000,3),
                'already_returned_qty'=>round($prior/1000,3),
                'remaining_qty'=>round($available/1000,3),
                'physical_stock'=>$physical!==null?round($physical/1000,3):null,
                'unit_cost'=>(float)$p->unit_cost,
                'discount_percent'=>(float)$p->discount_percent,
                'gst_percent'=>(float)$p->gst_percent,
                'line_amount'=>(float)$p->total_amount,
                'selectable'=>$available>0 && ($physical===null || $physical>0),
            ];
        })->all();

        return response()->json(['items'=>$items,'query'=>$q,'vendor_id'=>$vendorId]);
    }

    public function store(Request $request,AdminPurchaseReturnController $poster): RedirectResponse
    {
        $user=$this->admin($request);
        $data=$request->validate([
            'vendor_id'=>['required','integer','min:1','exists:workshop_vendors,id'],
            'request_key'=>['required','uuid'],
            'return_date'=>['required','date_format:Y-m-d','before_or_equal:today'],
            'supplier_credit_note'=>['nullable','string','max:120'],
            'reason'=>['required','string','min:10','max:1500'],
            'lines'=>['required','array','min:1','max:60'],
            'lines.*.purchase_id'=>['required','integer','min:1','distinct'],
            'lines.*.qty'=>['required','numeric','gt:0','max:9999999'],
        ]);
        $selectedVendor=(int)$data['vendor_id'];
        $lineEntries=array_values($data['lines']);
        $ids=array_map(static fn($l)=>(int)$l['purchase_id'],$lineEntries);
        if(count(array_unique($ids))!==count($ids))
            $this->reject('lines','A purchased source line can appear only once.');
        $sources=DB::table('workshop_purchases')->whereIn('id',$ids)->get([
            'id','purchase_type','purchase_batch_no','purchase_no','vendor_id',
            'record_status'
        ])->keyBy('id');
        if($sources->count()!==count($ids))
            $this->reject('lines','One or more selected purchase sources no longer exist.');
        $groups=[];
        foreach($lineEntries as $l){
            $id=(int)$l['purchase_id'];$source=$sources->get($id);
            // Client-supplied purchase IDs cannot override selected Vendor identity.
            if((int)$source->vendor_id!==$selectedVendor)
                $this->reject('vendor_id','Selected item is from a different Vendor. Choose the correct original supplier.');
            if(strtoupper(trim((string)$source->record_status))==='DELETED')
                $this->reject('lines','Selected purchase was deleted. Refresh the item search.');
            $ref=trim((string)($source->purchase_batch_no ?: $source->purchase_no));
            if($ref==='' || (int)$source->vendor_id<=0)
                $this->reject('lines','Original Purchase Bill or vendor reference missing.');
            $type=strtoupper((string)$source->purchase_type);
            if(!in_array($type,['PART','OSL'],true))
                $this->reject('lines','Only Parts or OSL purchase source lines are supported.');
            $group=(int)$source->vendor_id.'|'.$type.'|'.$ref;
            if(!isset($groups[$group]))$groups[$group]=['anchor'=>$id,'items'=>[],'ref'=>$ref];
            $groups[$group]['items'][$id]=$l['qty'];
        }
        if(count($groups)>15)
            $this->reject('lines','Select at most 15 different supplier purchase bills per entry.');
        ksort($groups,SORT_STRING);
        $created=DB::transaction(function()use($groups,$data,$user,$poster,$request): array{
            $documents=[];
            foreach($groups as $groupName=>$group){
                // UUID v5 per source bill avoids duplicate posts on form re-submission.
                $uniqueKey=Uuid::uuid5((string)$data['request_key'],$groupName)->toString();
                $prior=DB::table('am_purchase_returns')->where('request_key',$uniqueKey)->first();
                if($prior){
                    if($prior->record_status!=='POSTED' ||
                       (string)$prior->source_batch_no!==(string)$group['ref'])
                        $this->reject('request_key','Previous request was changed. Open the register to reconcile.');
                    $actual=DB::table('am_purchase_return_lines')->where('return_id',$prior->id)
                        ->pluck('qty','purchase_id')->all();
                    if(count($actual)!==count($group['items']))
                        $this->reject('request_key','Existing return line count differs from submitted request.');
                    foreach($group['items'] as $lineId=>$qty){
                        if(!array_key_exists($lineId,$actual) ||
                           abs((float)$actual[$lineId]-(float)$qty)>0.0001)
                            $this->reject('request_key','Existing return differs from this submission.');
                    }
                    $documents[]=['id'=>(int)$prior->id,'no'=>(string)$prior->return_no];
                    continue;
                }
                $payload=[
                    'request_key'=>$uniqueKey,
                    'return_date'=>$data['return_date'],
                    'supplier_credit_note'=>$data['supplier_credit_note']??null,
                    'reason'=>$data['reason'],
                    'items'=>$group['items'],
                ];
                $sub=Request::create('/erp/purchases/'.$group['anchor'].'/return','POST',$payload);
                $sub->setUserResolver(static fn()=>$user);
                // Preserve the same session for flash/redirect operations; no separate HTTP request.
                if($request->hasSession())$sub->setLaravelSession($request->session());
                $poster->store($sub,(int)$group['anchor']);
                $saved=DB::table('am_purchase_returns')->where('request_key',$uniqueKey)->first();
                if(!$saved || $saved->record_status!=='POSTED')
                    $this->reject('lines','Purchase Return did not post; transaction rolled back.');
                $documents[]=['id'=>(int)$saved->id,'no'=>(string)$saved->return_no];
            }
            return $documents;
        },3);

        if(count($created)===1){
            return redirect()->route('erp.purchase-returns.show',$created[0]['id'])
                ->with('success','Item-wise Purchase Return '.$created[0]['no'].
                    ' posted with original Vendor, Purchase Bill and stock/payable audit.');
        }
        return redirect()->route('erp.purchases.index')->with('success',
            'Item-wise Purchase Return posted: '.implode(', ',array_column($created,'no')).
            '. One supplier debit-note document per original Purchase Bill; each appears as a negative register row.');
    }
}
