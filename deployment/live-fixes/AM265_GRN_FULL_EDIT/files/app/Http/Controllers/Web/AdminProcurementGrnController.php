<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use App\Models\PartMaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * AM260: Goods received now / supplier bill later.
 * GRN updates stock precisely ONCE at physical receipt; bill matching
 * records a normal PART purchase batch but NEVER changes stock again.
 * PO has no stock or ledger impact.
 *
 * Existing invoicing, payments, purchase and job issue are untouched.
 */
final class AdminProcurementGrnController extends Controller
{
    private function admin(Request $request): ErpAuthUser
    {
        $u=$request->user();
        abort_unless($u instanceof ErpAuthUser && $u->active && $u->isAdmin()
            && in_array(strtolower(trim((string)$u->role)),['admin','owner'],true),
            403,'Owner/Admin permission required for Vendor Challan Procurement.');
        foreach(['am260_purchase_orders','am260_po_lines','am260_grns','am260_grn_lines',
          'am260_grn_bills','am260_grn_bill_allocations','am262_grn_opening_exceptions','am264_grn_line_edits','am265_grn_header_audits'] as $t)
            abort_unless(Schema::hasTable($t),503,'Procurement schema missing: '.$t);
        return $u;
    }

    private function vendor(int $id): object
    {
        $v=DB::table('workshop_vendors')->where('id',$id)->where('active',1)->first();
        if(!$v)throw ValidationException::withMessages(['vendor_id'=>'Choose an active vendor from Vendor Master.']);
        return $v;
    }

    private function job(int $id): ?object
    {
        if(!$id)return null;
        $j=DB::table('job_cards')->where('id',$id)->first();
        if(!$j || strtolower((string)$j->status)==='cancelled')
            throw ValidationException::withMessages(['job_card_id'=>'Select an existing non-cancelled Job Card.']);
        return $j;
    }

    private function selections(): array
    {
        return [
          'vendors'=>DB::table('workshop_vendors')->where('active',1)
              ->orderBy('vendor_name')->get(['id','vendor_name','vendor_code']),
          'jobs'=>DB::table('job_cards')->whereRaw("LOWER(COALESCE(status,''))<>'cancelled'")
              ->orderByDesc('id')->limit(250)->get(['id','job_no','vehicle_reg_no']),
          'parts'=>DB::table('parts_master')->whereRaw("LOWER(TRIM(COALESCE(active,''))) IN ('yes','1','active','true')")->orderBy('part_name')
              ->get(['id','part_code','part_name','unit','stock_qty','purchase_price']),
        ];
    }

    private function num(string $prefix): string
    {
        return $prefix.'-'.now('Asia/Kolkata')->format('ymdHis').'-'.Str::upper(Str::random(7));
    }

    private function effectiveLines(array $lines,string $mode='po'): array
    {
        if(count($lines)<1 || count($lines)>100)
            throw ValidationException::withMessages(['lines'=>'Enter 1–100 Part lines.']);
        $found=[];
        foreach($lines as $line){
            if(!is_array($line))continue;
            $part=(int)($line['part_master_id']??0);
            $qty=round((float)($line['qty']??0),3);
            $rate=round((float)($line['unit_cost']??0),2);
            // Partial PO delivery can legitimately skip ordered lines (qty=0).
            if($qty<=0 && $mode==='receipt')continue;
            if($part<=0 && $qty<=0)continue;
            if($part<=0 || $qty<=0 || $qty>999999 || $rate<0 || $rate>100000000)
                throw ValidationException::withMessages(['lines'=>'Select a Part, positive received/order quantity and valid cost.']);
            if(isset($found[$part]))
                throw ValidationException::withMessages(['lines'=>'The same Part is entered twice. Combine into one row.']);
            $found[$part]=['part_master_id'=>$part,'qty'=>$qty,'unit_cost'=>$rate,
                'po_line_id'=>(int)($line['po_line_id']??0)];
        }
        if(!$found)throw ValidationException::withMessages(['lines'=>'Add at least one Part with quantity.']);
        return array_values($found);
    }

    public function index(Request $request): View
    {
        $this->admin($request);
        $orders=DB::table('am260_purchase_orders as o')
            ->join('workshop_vendors as v','v.id','=','o.vendor_id')
            ->leftJoin('job_cards as j','j.id','=','o.job_card_id')
            ->orderByDesc('o.id')->limit(80)
            ->get(['o.*','v.vendor_name','j.job_no','j.vehicle_reg_no']);
        $receipts=DB::table('am260_grns as g')
            ->join('workshop_vendors as v','v.id','=','g.vendor_id')
            ->leftJoin('am260_purchase_orders as p','p.id','=','g.po_id')
            ->leftJoin('job_cards as j','j.id','=','g.job_card_id')
            ->orderByDesc('g.id')->limit(80)
            ->get(['g.*','v.vendor_name','p.po_no','j.job_no','j.vehicle_reg_no']);
        $bills=DB::table('am260_grn_bills as b')
            ->join('workshop_vendors as v','v.id','=','b.vendor_id')
            ->orderByDesc('b.id')->limit(80)->get(['b.*','v.vendor_name']);
        $pending=DB::table('am260_grn_lines as l')
            ->join('am260_grns as g','g.id','=','l.grn_id')
            ->join('workshop_vendors as v','v.id','=','g.vendor_id')
            ->join('parts_master as p','p.id','=','l.part_master_id')
            ->leftJoin('am260_purchase_orders as po','po.id','=','g.po_id')
            ->whereRaw('(l.qty_received-l.qty_billed)>0.0005')
            ->select(['l.*','g.grn_no','g.challan_no','g.vendor_id','g.receipt_date',
              'v.vendor_name','p.part_code','p.part_name','p.unit','po.po_no'])
            ->orderByDesc('g.id')->limit(200)->get();
        return view('procurement.index',compact('orders','receipts','bills','pending'));
    }

    /**
     * AM261: confirmed GRN receipt audit page. A PO or unsubmitted challan
     * cannot masquerade as a stock-in. Read actual posted inventory movements.
     */
    public function showReceipt(Request $request,int $grn): View
    {
        $this->admin($request);
        $receipt=DB::table('am260_grns as g')
            ->join('workshop_vendors as v','v.id','=','g.vendor_id')
            ->leftJoin('am260_purchase_orders as po','po.id','=','g.po_id')
            ->leftJoin('job_cards as jc','jc.id','=','g.job_card_id')
            ->where('g.id',$grn)
            ->select(['g.*','v.vendor_name','po.po_no','jc.job_no','jc.vehicle_reg_no'])->first();
        abort_unless($receipt,404,'Posted GRN not found. Purchase Order is not proof of receipt.');
        $lines=DB::table('am260_grn_lines as l')
            ->join('parts_master as p','p.id','=','l.part_master_id')
            ->where('l.grn_id',$receipt->id)
            ->orderBy('l.id')
            ->get(['l.id','l.part_master_id','l.qty_received','l.qty_billed',
                'l.provisional_unit_cost','p.part_code','p.part_name','p.unit','p.stock_qty']);
        // AM264: physical initial GRN movement + later immutable edit deltas.
        $movements=DB::table('inventory_movements')
            ->where('reference_no',$receipt->grn_no)
            ->whereIn('movement_type',['GRN_RECEIPT_IN','GRN_EDIT_IN','GRN_EDIT_OUT'])
            ->whereIn('part_master_id',$lines->pluck('part_master_id')->all())
            ->orderBy('id')->get()->groupBy('part_master_id');
        $openingExceptions=DB::table('am262_grn_opening_exceptions')
            ->where('grn_id',$receipt->id)->get()->keyBy('part_master_id');
        $lines=$lines->map(function($line)use($movements,$openingExceptions){
            $line->opening_exception=$openingExceptions->get($line->part_master_id);
            $line->opening_unverified=(bool)$line->opening_exception;
            $matches=$movements->get($line->part_master_id,collect());
            $quantity=round((float)$matches->sum('qty_in')-(float)$matches->sum('qty_out'),3);
            $line->confirmed_stock_in=$quantity;
            $line->movement_count=$matches->count();
            $originalCount=$matches->where('movement_type','GRN_RECEIPT_IN')->count();
            $line->movement_verified=$originalCount===1 &&
                abs($quantity-(float)$line->qty_received)<0.0005;
            return $line;
        });
        $allVerified=$lines->isNotEmpty() && $lines->every(
            static fn($line)=>(bool)$line->movement_verified);
        $hasUnverifiedOpenings=$lines->contains(
            static fn($line)=>(bool)$line->opening_unverified);
        $edits=DB::table('am264_grn_line_edits as e')
            ->leftJoin('parts_master as p','p.id','=','e.part_master_id')
            ->where('e.grn_id',$receipt->id)->orderByDesc('e.id')->limit(80)
            ->get(['e.*','p.part_name','p.part_code']);
        // Job Card required Parts may exceed the available stock even after
        // a successful GRN. Display exact shortage; never auto-issue.
        $jobStock=collect();
        if($receipt->job_card_id){
            $jobStock=DB::table('job_card_parts as j')
              ->leftJoin('parts_master as p','p.id','=','j.part_master_id')
              ->where('j.job_card_id',$receipt->job_card_id)
              ->whereRaw("UPPER(TRIM(COALESCE(j.issue_status,''))) NOT IN ('ISSUED','FITTED','RETURNED','REVERSED')")
              ->orderBy('j.id')->get([
                'j.id','j.part_master_id','j.part_name','j.qty','j.issue_status',
                'p.part_name as master_name','p.stock_qty'
              ]);
        }
        $shortages=$jobStock->groupBy('part_master_id')->map(function($group){
            $available=$group->first()->stock_qty;
            $required=round((float)$group->sum('qty'),3);
            return (object)[
                'part_name'=>$group->first()->master_name??$group->first()->part_name,
                'required'=>$required,
                'available'=>$available===null?null:round((float)$available,3),
                'shortage'=>$available===null?$required:max(0,round($required-(float)$available,3)),
            ];
        })->filter(static fn($line)=>$line->shortage>0.0005)->values();
        $headerEdits=DB::table('am265_grn_header_audits')
            ->where('grn_id',$receipt->id)->orderByDesc('id')->limit(80)->get();
        return view('procurement.grn-show',compact(
          'receipt','lines','allVerified','hasUnverifiedOpenings','edits','shortages','headerEdits'
        ));
    }


    /**
     * AM264: audit-safe item edit. Stock deltas are appended, never rewrite or
     * delete original physical receipt movements. Previously issued stock
     * cannot be "reversed" by editing a received quantity below availability.
     */

    /**
     * AM265 versioned header snapshot; guards against stale multiple-tab
     * edits, and records the exact before/after business document metadata.
     */
    private function grnHeaderSnapshot(object $receipt): array
    {
        return [
            'vendor_id'=>(int)$receipt->vendor_id,
            'job_card_id'=>$receipt->job_card_id===null?null:(int)$receipt->job_card_id,
            'po_id'=>$receipt->po_id===null?null:(int)$receipt->po_id,
            'challan_no'=>(string)$receipt->challan_no,
            'challan_date'=>substr((string)$receipt->challan_date,0,10),
            'receipt_date'=>substr((string)$receipt->receipt_date,0,10),
            'notes'=>trim((string)($receipt->notes??''))?:null,
            'status'=>(string)$receipt->status,
            'updated_at'=>(string)$receipt->updated_at,
        ];
    }

    private function grnHeaderToken(object $receipt): string
    {
        return hash('sha256',json_encode(
            $this->grnHeaderSnapshot($receipt),
            JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE
        ));
    }

    public function editReceipt(Request $request,int $grn): View
    {
        $this->admin($request);
        $receipt=DB::table('am260_grns as g')
          ->join('workshop_vendors as v','v.id','=','g.vendor_id')
          ->leftJoin('am260_purchase_orders as po','po.id','=','g.po_id')
          ->leftJoin('job_cards as j','j.id','=','g.job_card_id')
          ->where('g.id',$grn)
          ->select(['g.*','v.vendor_name','po.po_no','j.job_no','j.vehicle_reg_no'])->first();
        abort_unless($receipt,404,'GRN not found.');
        $lines=DB::table('am260_grn_lines as l')
          ->join('parts_master as p','p.id','=','l.part_master_id')
          ->where('l.grn_id',$receipt->id)->orderBy('l.id')
          ->get(['l.*','p.part_code','p.part_name','p.stock_qty','p.unit']);
        $parts=DB::table('parts_master')
          ->whereRaw("LOWER(TRIM(COALESCE(active,''))) IN ('yes','1','active','true')")
          ->orderBy('part_name')->get(['id','part_code','part_name','stock_qty','unit']);
        $vendors=DB::table('workshop_vendors')
          ->where('active',1)->orWhere('id',$receipt->vendor_id)
          ->orderBy('vendor_name')
          ->get(['id','vendor_name','vendor_code']);
        $jobs=DB::table('job_cards')
          ->whereRaw("LOWER(TRIM(COALESCE(status,''))) NOT IN ('cancelled','canceled')")
          ->orWhere('id',$receipt->job_card_id)
          ->orderByDesc('id')->limit(1000)
          ->get(['id','job_no','vehicle_reg_no','status']);
        $headerToken=$this->grnHeaderToken($receipt);
        $hasBilled=$lines->contains(static fn($line)=>(float)$line->qty_billed>0.0005);
        return view('procurement.grn-edit',compact(
          'receipt','lines','parts','vendors','jobs','headerToken','hasBilled'
        ));
    }

    public function updateReceipt(Request $request,int $grn): RedirectResponse
    {
        $actor=$this->admin($request);
        $input=$request->validate([
            'reason'=>['required','string','min:8','max:1000'],
            'physical_verified'=>['required','in:1'],
            // AM265: the COMPLETE GRN header is edited together with items
            // in a single transaction; GRN number/status/PO identity are immutable.
            'vendor_id'=>['required','integer','min:1'],
            'job_card_id'=>['nullable','integer','min:1'],
            'challan_no'=>['required','string','min:1','max:100'],
            'challan_date'=>['required','date_format:Y-m-d'],
            'receipt_date'=>['required','date_format:Y-m-d'],
            'notes'=>['nullable','string','max:1200'],
            'header_token'=>['required','string','size:64'],
            'opening_acknowledged'=>['nullable','in:1'],
            'lines'=>['required','array','min:1','max:100'],
            'lines.*.qty'=>['required','numeric','min:0','max:999999'],
            'lines.*.unit_cost'=>['required','numeric','min:0','max:100000000'],
            'lines.*.original_qty'=>['required','numeric','min:0'],
            'lines.*.original_cost'=>['required','numeric','min:0'],
            'new_lines'=>['nullable','array','max:50'],
            'new_lines.*.part_master_id'=>['nullable','integer','min:1'],
            'new_lines.*.part_search_label'=>['nullable','string','max:220'],
            'new_lines.*.qty'=>['nullable','numeric','min:0','max:999999'],
            'new_lines.*.unit_cost'=>['nullable','numeric','min:0','max:100000000'],
        ]);
        $result=DB::transaction(function()use($grn,$input,$actor){
            $receipt=DB::table('am260_grns')->where('id',$grn)->lockForUpdate()->first();
            abort_unless($receipt,404,'GRN not found.');
            if($receipt->status!=='RECEIVED')
                throw ValidationException::withMessages(['reason'=>'Only a posted RECEIVED GRN can be corrected.']);


            // AM265 header corrections: fully auditable and source-preserving.
            // Do not let a second browser tab overwrite updated GRN metadata.
            if(!hash_equals($this->grnHeaderToken($receipt),(string)$input['header_token']))
                throw ValidationException::withMessages([
                    'header'=>'This GRN header changed since the edit form was opened. Refresh the form before saving.'
                ]);
            $headerBefore=$this->grnHeaderSnapshot($receipt);
            $headerAfter=$headerBefore;
            $headerAfter['vendor_id']=(int)$input['vendor_id'];
            $headerAfter['job_card_id']=((int)($input['job_card_id']??0))?:null;
            $headerAfter['challan_no']=trim((string)$input['challan_no']);
            $headerAfter['challan_date']=$input['challan_date'];
            $headerAfter['receipt_date']=$input['receipt_date'];
            $headerAfter['notes']=trim((string)($input['notes']??''))?:null;
            $headerKeys=['vendor_id','job_card_id','challan_no','challan_date','receipt_date','notes'];
            $headerChanges=[];
            foreach($headerKeys as $field)
                if($headerAfter[$field]!==$headerBefore[$field])$headerChanges[]=$field;

            if($headerChanges){
                $changeVendor=in_array('vendor_id',$headerChanges,true);
                $changeJob=in_array('job_card_id',$headerChanges,true);
                $changeChallan=in_array('challan_no',$headerChanges,true);
                $changeLink=$changeVendor||$changeJob;
                $billed=DB::table('am260_grn_lines')->where('grn_id',$grn)
                    ->where('qty_billed','>',0.0005)->exists();
                // Later Purchase Bill is already tied to original Vendor/JC
                // and original delivery reference; never silently relink it.
                if($billed && ($changeLink||$changeChallan))
                    throw ValidationException::withMessages([
                        'header'=>'This GRN is already matched to a vendor Purchase Bill. Vendor, Job Card and Challan Number are locked. Only dates/remarks can be corrected with an audit reason.'
                    ]);
                if($changeVendor)$this->vendor($headerAfter['vendor_id']);
                if($changeJob && $headerAfter['job_card_id']!==null){
                    $newJob=$this->job($headerAfter['job_card_id']);
                    if($newJob && (DB::table('invoices')->where('job_card_id',$newJob->id)
                        ->whereRaw("LOWER(TRIM(COALESCE(status,''))) NOT IN ('void','reversed','cancelled')")
                        ->exists() || in_array(
                        strtolower(trim((string)$newJob->status)),
                        ['closed','invoiced','cancelled','canceled'],true)))
                        throw ValidationException::withMessages([
                            'job_card_id'=>'Cannot relink a received GRN to a closed or invoiced Job Card.'
                        ]);
                }
                if($changeLink && $receipt->po_id){
                    $po=DB::table('am260_purchase_orders')->where('id',$receipt->po_id)
                        ->lockForUpdate()->first();
                    if(!$po || (int)$po->vendor_id!==$headerAfter['vendor_id'] ||
                        ($po->job_card_id===null?null:(int)$po->job_card_id)!==$headerAfter['job_card_id'])
                        throw ValidationException::withMessages([
                            'header'=>'PO-linked GRN Vendor/Job Card cannot differ from the existing Purchase Order. Correct PO linkage through a controlled procurement reversal.'
                        ]);
                }
                if($changeJob && $headerBefore['job_card_id']!==null){
                    $receivedPartIds=DB::table('am260_grn_lines')
                        ->where('grn_id',$grn)->pluck('part_master_id')->all();
                    // These parts may have been allocated/issued to the
                    // originally linked job; reassignment requires a controlled
                    // job stock reversal, not a silent metadata relink.
                    $used=DB::table('job_card_parts')
                        ->where('job_card_id',$headerBefore['job_card_id'])
                        ->whereIn('part_master_id',$receivedPartIds)
                        ->whereRaw("UPPER(TRIM(COALESCE(issue_status,''))) IN ('ISSUED','FITTED')")
                        ->exists();
                    if($used)throw ValidationException::withMessages([
                        'job_card_id'=>'Linked Job Card already issued/fitted one of these GRN Parts. Reverse or reconcile the issue before changing the GRN Job Card.'
                    ]);
                }
                if(($changeVendor||$changeChallan) &&
                   DB::table('am260_grns')
                       ->where('vendor_id',$headerAfter['vendor_id'])
                       ->where('challan_no',$headerAfter['challan_no'])
                       ->where('id','<>',$grn)->exists())
                    throw ValidationException::withMessages([
                        'challan_no'=>'Another GRN already exists with this Vendor + Challan Number. Duplicate receipt reference blocked.'
                    ]);
            }

            $oldLines=DB::table('am260_grn_lines')->where('grn_id',$grn)
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if($oldLines->isEmpty())
                throw ValidationException::withMessages(['lines'=>'This GRN has no item lines.']);
            // Prevent stale browser form or malicious omission of receipt lines.
            $submittedIds=array_map('intval',array_keys($input['lines']));
            $actualIds=array_map('intval',$oldLines->keys()->all());
            sort($submittedIds,SORT_NUMERIC);
            sort($actualIds,SORT_NUMERIC);
            if($submittedIds!==$actualIds)
                throw ValidationException::withMessages(['lines'=>'GRN lines changed since this page was opened. Refresh and review.']);

            $entries=[];$partIds=[];
            $existingPartIds=[];
            $finalSum=0.0;
            foreach($oldLines as $line){
                $partId=(int)$line->part_master_id;
                $existingPartIds[$partId]=true;
                $requestLine=$input['lines'][$line->id]??null;
                if(!is_array($requestLine))
                    throw ValidationException::withMessages(['lines'=>'Missing GRN line.']);
                $oldQty=round((float)$line->qty_received,3);
                $oldCost=round((float)$line->provisional_unit_cost,2);
                if(abs($oldQty-(float)$requestLine['original_qty'])>0.0005 ||
                   abs($oldCost-(float)$requestLine['original_cost'])>0.005)
                    throw ValidationException::withMessages(['lines'=>'Someone already edited this GRN item. Refresh to avoid duplicate stock movements.']);
                $newQty=round((float)$requestLine['qty'],3);
                $newCost=round((float)$requestLine['unit_cost'],2);
                $finalSum+=$newQty;
                if($newQty!==$oldQty || $newCost!==$oldCost){
                    if((float)$line->qty_billed>0.0005)
                        throw ValidationException::withMessages(['lines'=>'Already billed item cannot be edited: Part Master '.$partId.'. Use supplier bill correction.']);
                    $entries[]=['line'=>$line,'part_id'=>$partId,'old_qty'=>$oldQty,
                        'new_qty'=>$newQty,'old_cost'=>$oldCost,'new_cost'=>$newCost,
                        'delta'=>round($newQty-$oldQty,3),'is_new'=>false];
                    $partIds[]=$partId;
                }
            }
            foreach(($input['new_lines']??[]) as $row){
                if(!is_array($row))continue;
                $id=(int)($row['part_master_id']??0);
                $qty=round((float)($row['qty']??0),3);
                if($qty<=0 && $id===0)continue;
                if($id<=0 || $qty<=0)
                    throw ValidationException::withMessages(['new_lines'=>'New item must have a valid Part Master and positive physical quantity.']);
                if(isset($existingPartIds[$id]))
                    throw ValidationException::withMessages(['new_lines'=>'This Part is already present on this GRN. Edit existing quantity instead.']);
                $existingPartIds[$id]=true;
                $cost=round((float)($row['unit_cost']??0),2);
                $finalSum+=$qty;
                $entries[]=['line'=>null,'part_id'=>$id,'old_qty'=>0.0,
                    'new_qty'=>$qty,'old_cost'=>0.0,'new_cost'=>$cost,
                    'delta'=>$qty,'is_new'=>true];
                $partIds[]=$id;
            }
            if($finalSum<0.0005)
                throw ValidationException::withMessages(['lines'=>'Cannot clear the entire posted GRN through item edit. Use controlled return/reversal.']);
            if(!$entries && !$headerChanges)return ['changed'=>0,'header_changed'=>false,'no'=>$receipt->grn_no];

            // PO-linked corrections must preserve the original PO line mapping.
            if($receipt->po_id){
                foreach($entries as $edit){
                    if($edit['is_new'])
                        throw ValidationException::withMessages(['new_lines'=>'This GRN is against a PO. Add the new part to the PO and receive it through a new GRN.']);
                }
            }

            $poLines=collect();
            if($receipt->po_id){
                $poLines=DB::table('am260_po_lines')
                   ->where('po_id',$receipt->po_id)->orderBy('id')
                   ->lockForUpdate()->get()->keyBy('id');
            }

            // Deterministic row locks across concurrent GRN/Purchase/JC transactions.
            $partIds=array_values(array_unique($partIds));
            sort($partIds,SORT_NUMERIC);
            $masters=PartMaster::query()->whereIn('id',$partIds)->orderBy('id')
               ->lockForUpdate()->get()->keyBy('id');
            foreach($entries as $edit){
                $master=$masters->get($edit['part_id']);
                if(!$master)
                    throw ValidationException::withMessages(['lines'=>'Part Master #'.$edit['part_id'].' missing.']);
                if($edit['is_new'] &&
                    !in_array(strtolower(trim((string)$master->active)),['yes','1','active','true'],true))
                    throw ValidationException::withMessages(['new_lines'=>'Inactive Part Master cannot be received: '.$master->part_name]);
                if($edit['delta']<0){
                    // Current available stock is the unconsumed portion. Never
                    // claw back parts already issued to JC or spent from stock.
                    if($master->stock_qty===null ||
                       (float)$master->stock_qty+0.0005<abs($edit['delta']))
                        throw ValidationException::withMessages([
                           'lines'=>'Cannot reduce '.$master->part_name.' by '.abs($edit['delta']).
                           '. Current available stock is '.
                           ($master->stock_qty===null?'not recorded':number_format((float)$master->stock_qty,3)).
                           '. Earlier stock is already issued or negative. Correct stock history separately.',
                        ]);
                }elseif($edit['delta']>0 && $master->stock_qty===null &&
                        ($input['opening_acknowledged']??'')!=='1')
                    throw ValidationException::withMessages([
                        'opening_acknowledged'=>$master->part_name.' has no opening stock; confirm the provisional opening acknowledgement for newly added quantity.',
                    ]);
                if($receipt->po_id && !$edit['is_new']){
                    $po=$poLines->get($edit['line']->po_line_id);
                    if(!$po || (int)$po->part_master_id!==$edit['part_id'])
                        throw ValidationException::withMessages(['lines'=>'Cannot match existing GRN item to its PO line.']);
                    if($edit['delta']>0 &&
                       (float)$po->qty_ordered+0.0005<(float)$po->qty_received+$edit['delta'])
                        throw ValidationException::withMessages(['lines'=>'Item increase exceeds outstanding PO balance.']);
                }
                // Corruption/duplicate movement check BEFORE appending correction.
                if(!$edit['is_new']){
                    $m=DB::table('inventory_movements')
                        ->where('reference_no',$receipt->grn_no)
                        ->where('part_master_id',$edit['part_id'])
                        ->whereIn('movement_type',['GRN_RECEIPT_IN','GRN_EDIT_IN','GRN_EDIT_OUT'])
                        ->lockForUpdate()->get();
                    if($m->where('movement_type','GRN_RECEIPT_IN')->count()!==1 ||
                        abs((float)$m->sum('qty_in')-(float)$m->sum('qty_out')-$edit['old_qty'])>0.0005)
                        throw ValidationException::withMessages(['lines'=>'GRN stock movements already differ for '.$master->part_name.'. Manual audit required.']);
                }
            }

            $editNo=$this->num('GRNEDIT');
            foreach($entries as $edit){
                $master=$masters->get($edit['part_id']);
                $delta=$edit['delta'];
                $before=$master->stock_qty===null?null:round((float)$master->stock_qty,3);
                $after=$before;
                $movementId=null;
                if(abs($delta)>0.0005){
                    $openingUnknown=$before===null;
                    $beforeForMath=$openingUnknown?0.0:$before;
                    $after=round($beforeForMath+$delta,3);
                    if($after<0 && $delta<0)
                        throw ValidationException::withMessages(['lines'=>'Stock would go negative after GRN correction.']);
                    if($openingUnknown){
                        $prior=DB::table('inventory_movements')
                            ->where('part_master_id',$edit['part_id'])->orderByDesc('id')
                            ->first(['id','balance_after']);
                        DB::table('am262_grn_opening_exceptions')->insert([
                            'grn_id'=>$grn,'part_master_id'=>$edit['part_id'],
                            'prior_stock_qty'=>null,'provisional_base_qty'=>0,
                            'received_qty'=>$edit['new_qty'],'provisional_after_qty'=>$after,
                            'prior_movement_id'=>$prior->id??null,
                            'prior_movement_balance'=>$prior->balance_after??null,
                            'status'=>'PENDING_RECONCILIATION',
                            'acknowledged_by'=>(string)($actor->name??$actor->id),
                            'created_at'=>now(),'updated_at'=>now(),
                        ]);
                    }
                    // Original physical receipt is preserved. Corrections are
                    // additional movement events with unchanged GRN reference.
                    $movementId=DB::table('inventory_movements')->insertGetId([
                        'part_master_id'=>$edit['part_id'],
                        'job_card_id'=>$receipt->job_card_id?:null,
                        'movement_type'=>$delta>0
                           ?($edit['is_new']?'GRN_RECEIPT_IN':'GRN_EDIT_IN')
                           :'GRN_EDIT_OUT',
                        'qty_in'=>$delta>0?$delta:0,
                        'qty_out'=>$delta<0?abs($delta):0,
                        'balance_after'=>$after,
                        'reference_no'=>$receipt->grn_no,
                        'note'=>mb_strcut('AM264 GRN correction '.$editNo.
                           ' | '.($openingUnknown?'AM262_OPENING_UNVERIFIED | ':'').
                           $input['reason'],0,180,'UTF-8'),
                        'created_at'=>now(),
                    ]);
                    DB::table('parts_master')->where('id',$master->id)->update([
                        'stock_qty'=>$after
                    ]);
                    $master->stock_qty=$after;
                }
                if($edit['is_new']){
                    $lineId=DB::table('am260_grn_lines')->insertGetId([
                        'grn_id'=>$grn,'po_line_id'=>null,'part_master_id'=>$edit['part_id'],
                        'job_card_id'=>$receipt->job_card_id?:null,
                        'qty_received'=>$edit['new_qty'],'qty_billed'=>0,
                        'provisional_unit_cost'=>$edit['new_cost'],
                        'created_at'=>now(),'updated_at'=>now()
                    ]);
                }else{
                    $lineId=$edit['line']->id;
                    DB::table('am260_grn_lines')->where('id',$lineId)->update([
                        'qty_received'=>$edit['new_qty'],
                        'provisional_unit_cost'=>$edit['new_cost'],
                        'updated_at'=>now()
                    ]);
                    if($receipt->po_id && abs($delta)>0.0005){
                        $poLine=$poLines->get($edit['line']->po_line_id);
                        DB::table('am260_po_lines')->where('id',$poLine->id)->update([
                           'qty_received'=>round((float)$poLine->qty_received+$delta,3),
                           'updated_at'=>now()
                        ]);
                        $poLine->qty_received=round((float)$poLine->qty_received+$delta,3);
                    }
                }
                DB::table('am264_grn_line_edits')->insert([
                    'edit_no'=>$editNo,'grn_id'=>$grn,'grn_line_id'=>$lineId,
                    'part_master_id'=>$edit['part_id'],
                    'old_received_qty'=>$edit['old_qty'],
                    'new_received_qty'=>$edit['new_qty'],
                    'qty_delta'=>$delta,
                    'old_provisional_cost'=>$edit['old_cost'],
                    'new_provisional_cost'=>$edit['new_cost'],
                    'stock_before'=>$before,
                    'stock_after'=>$after,
                    'movement_id'=>$movementId,
                    'action'=>$edit['is_new']?'ADD_PART':
                       ($delta<0?'REDUCE_RECEIPT':($delta>0?'INCREASE_RECEIPT':'COST_CORRECTION')),
                    'actor'=>(string)($actor->name??$actor->id),
                    'reason'=>$input['reason'],'created_at'=>now(),
                ]);
            }
            if($receipt->po_id){
                $remaining=DB::table('am260_po_lines')->where('po_id',$receipt->po_id)
                  ->whereRaw('qty_received+0.0005<qty_ordered')->exists();
                DB::table('am260_purchase_orders')->where('id',$receipt->po_id)->update([
                    'status'=>$remaining?'OPEN':'RECEIVED',
                    'updated_at'=>now(),
                ]);
            }

            if($headerChanges){
                // The canonical GRN ID/number and stock balances stay
                // unchanged. Only linked Job Card metadata is synchronized.
                DB::table('am260_grns')->where('id',$grn)->update([
                    'vendor_id'=>$headerAfter['vendor_id'],
                    'job_card_id'=>$headerAfter['job_card_id'],
                    'challan_no'=>$headerAfter['challan_no'],
                    'challan_date'=>$headerAfter['challan_date'],
                    'receipt_date'=>$headerAfter['receipt_date'],
                    'notes'=>$headerAfter['notes'],
                    'updated_at'=>now(),
                ]);
                if(in_array('job_card_id',$headerChanges,true)){
                    DB::table('am260_grn_lines')->where('grn_id',$grn)->update([
                        'job_card_id'=>$headerAfter['job_card_id'],
                        'updated_at'=>now(),
                    ]);
                    DB::table('inventory_movements')
                        ->where('reference_no',$receipt->grn_no)
                        ->whereIn('movement_type',['GRN_RECEIPT_IN','GRN_EDIT_IN','GRN_EDIT_OUT'])
                        ->update(['job_card_id'=>$headerAfter['job_card_id']]);
                }
                DB::table('am265_grn_header_audits')->insert([
                    'grn_id'=>$grn,
                    'grn_no'=>$receipt->grn_no,
                    'edit_no'=>$this->num('GRNHDR'),
                    'before_json'=>json_encode($headerBefore,
                       JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE),
                    'after_json'=>json_encode($headerAfter,
                       JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE),
                    'changed_fields'=>implode(',',$headerChanges),
                    'reason'=>$input['reason'],
                    'actor'=>(string)($actor->name??$actor->id),
                    'created_at'=>now(),
                    'updated_at'=>now(),
                ]);
            }
            if(!$headerChanges)DB::table('am260_grns')->where('id',$grn)
                ->update(['updated_at'=>now()]);
            return [
                'changed'=>count($entries),
                'header_changed'=>!empty($headerChanges),
                'no'=>$receipt->grn_no,
            ];
        },3);
        return redirect()->route('erp.procurement.grn.show',['grn'=>$grn])->with(
            'success',$result['changed']
            ?'GRN '.$result['no'].' corrected: '.$result['changed'].
               ' line(s). New stock movements were posted only for quantity changes. Item Ledger and audit history updated.'
            :($result['header_changed']
                ?'GRN '.$result['no'].' header updated (Vendor/Job/Challan/Date/Remarks as edited). Stock quantities unchanged. Audit saved.'
                :'GRN unchanged. No duplicate inventory movement created.')
        );
    }

    public function newPo(Request $request): View
    {
        $this->admin($request);
        return view('procurement.po-create',$this->selections()+[
            'selectedJob'=>max(0,(int)$request->query('job_card_id',0)),
            'selectedVendor'=>max(0,(int)$request->query('vendor_id',0)),
        ]);
    }

    public function savePo(Request $request): RedirectResponse
    {
        $u=$this->admin($request);
        $d=$request->validate([
           'vendor_id'=>['required','integer','min:1'],
           'job_card_id'=>['nullable','integer','min:1'],
           'order_date'=>['required','date_format:Y-m-d'],
           'notes'=>['nullable','string','max:1200'],
           'lines'=>['required','array','min:1','max:100'],
           'lines.*.part_master_id'=>['nullable','integer','min:1'],
           'lines.*.qty'=>['nullable','numeric','min:0'],
           'lines.*.unit_cost'=>['nullable','numeric','min:0'],
        ]);
        $lines=$this->effectiveLines($d['lines']);
        $id=DB::transaction(function()use($d,$lines,$u){
            $vendor=$this->vendor((int)$d['vendor_id']);
            $job=$this->job((int)($d['job_card_id']??0));
            $po=DB::table('am260_purchase_orders')->insertGetId([
                'po_no'=>$this->num('PO'),'vendor_id'=>$vendor->id,
                'job_card_id'=>$job->id??null,
                'order_date'=>$d['order_date'],'notes'=>trim((string)($d['notes']??''))?:null,
                'status'=>'OPEN','created_by'=>(string)($u->name??$u->id),
                'created_at'=>now(),'updated_at'=>now()
            ]);
            foreach($lines as $line){
                $p=DB::table('parts_master')->where('id',$line['part_master_id'])->whereRaw("LOWER(TRIM(COALESCE(active,''))) IN ('yes','1','active','true')")->first();
                if(!$p)throw ValidationException::withMessages(['lines'=>'One Part is missing or inactive.']);
                DB::table('am260_po_lines')->insert([
                    'po_id'=>$po,'part_master_id'=>$p->id,
                    'qty_ordered'=>$line['qty'],'qty_received'=>0,
                    'expected_unit_cost'=>$line['unit_cost'],
                    'created_at'=>now(),'updated_at'=>now()
                ]);
            }
            return $po;
        },3);
        return redirect()->route('erp.procurement.receive',['po_id'=>$id])
            ->with('success','Purchase Order created. No stock or supplier payable posted yet. Record actual physical challan receipt now.');
    }

    public function newReceipt(Request $request): View
    {
        $this->admin($request);
        $poId=max(0,(int)$request->query('po_id',0));
        $order=null;$orderLines=collect();
        if($poId){
            $order=DB::table('am260_purchase_orders as o')
              ->join('workshop_vendors as v','v.id','=','o.vendor_id')
              ->where('o.id',$poId)->select(['o.*','v.vendor_name'])->first();
            abort_unless($order,404);
            $orderLines=DB::table('am260_po_lines as l')
                ->join('parts_master as p','p.id','=','l.part_master_id')
                ->where('l.po_id',$poId)
                ->whereRaw('(l.qty_ordered-l.qty_received)>0.0005')
                ->get(['l.*','p.part_code','p.part_name','p.stock_qty','p.unit']);
        }
        $all=$this->selections();
        $all['order']=$order;
        $all['orderLines']=$orderLines;
        $all['poOptions']=DB::table('am260_purchase_orders as o')
           ->join('workshop_vendors as v','v.id','=','o.vendor_id')
           ->where('o.status','OPEN')->orderByDesc('o.id')->limit(100)
           ->get(['o.id','o.po_no','v.vendor_name']);
        $all['selectedJob']=max(0,(int)$request->query('job_card_id',0));
        $all['selectedVendor']=max(0,(int)$request->query('vendor_id',0));
        return view('procurement.receive',$all);
    }

    public function postReceipt(Request $request): RedirectResponse
    {
        $u=$this->admin($request);
        $d=$request->validate([
          'po_id'=>['nullable','integer','min:1'],
          'vendor_id'=>['required','integer','min:1'],
          'job_card_id'=>['nullable','integer','min:1'],
          'challan_no'=>['required','string','min:1','max:100'],
          'challan_date'=>['required','date_format:Y-m-d'],
          'receipt_date'=>['required','date_format:Y-m-d'],
          'notes'=>['nullable','string','max:1200'],
          'lines'=>['required','array','min:1','max:100'],
          'lines.*.part_master_id'=>['nullable','integer','min:1'],
          // AM263: autosave-friendly typed Part label; validated for length but never trusted as master ID.
          'lines.*.part_search_label'=>['nullable','string','max:220'],
          'lines.*.po_line_id'=>['nullable','integer','min:1'],
          'lines.*.qty'=>['nullable','numeric','min:0'],
          'lines.*.unit_cost'=>['nullable','numeric','min:0'],
          'opening_acknowledged'=>['nullable','in:1'],
        ]);
        $lines=$this->effectiveLines($d['lines'],'receipt');
        $receipt=DB::transaction(function()use($d,$lines,$u){
            $orderId=(int)($d['po_id']??0);
            $order=$orderId?DB::table('am260_purchase_orders')->where('id',$orderId)->lockForUpdate()->first():null;
            if($orderId && !$order)
                throw ValidationException::withMessages(['po_id'=>'Selected PO does not exist.']);
            if($order && $order->status!=='OPEN')
                throw ValidationException::withMessages(['po_id'=>'PO is closed; cannot receive additional items.']);
            $vendor=$this->vendor((int)$d['vendor_id']);
            if($order && (int)$order->vendor_id!==(int)$vendor->id)
                throw ValidationException::withMessages(['vendor_id'=>'Challan vendor must match Purchase Order vendor.']);
            $jobId=$order?(int)($order->job_card_id??0):(int)($d['job_card_id']??0);
            $this->job($jobId);
            $challan=trim($d['challan_no']);
            if(DB::table('am260_grns')->where('vendor_id',$vendor->id)
                   ->where('challan_no',$challan)->exists())
                throw ValidationException::withMessages(['challan_no'=>'This vendor challan was already received. Duplicate stock-in blocked.']);

            $poLines=collect();
            if($order) {
                $poLines=DB::table('am260_po_lines')->where('po_id',$order->id)
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            }
            $partIds=array_column($lines,'part_master_id');
            sort($partIds,SORT_NUMERIC);
            $parts=PartMaster::query()->whereIn('id',$partIds)->orderBy('id')
                ->lockForUpdate()->get()->keyBy('id');
            foreach($lines as $l){
                $part=$parts->get($l['part_master_id']);
                if(!$part || !in_array(strtolower(trim((string)$part->active)),['yes','1','active','true'],true))
                    throw ValidationException::withMessages(['lines'=>'Received Part missing or inactive.']);
                // AM262: physical stock-in must be possible for a new Part with
                // no historical opening. Require informed operator acknowledgement;
                // never record unknown opening as a verified zero.
                if($part->stock_qty===null && ($d['opening_acknowledged']??'')!=='1')
                    throw ValidationException::withMessages([
                        'opening_acknowledged'=>$part->part_name.' has unrecorded opening stock. Tick acknowledgement to receive the physical quantity as PROVISIONAL stock, with opening marked Unverified.',
                    ]);
                if($order){
                    $pl=$poLines->get($l['po_line_id']);
                    if(!$pl || (int)$pl->part_master_id!==(int)$part->id)
                       throw ValidationException::withMessages(['lines'=>'Part does not match selected PO line.']);
                    if((float)$pl->qty_ordered-(float)$pl->qty_received+0.0005<$l['qty'])
                       throw ValidationException::withMessages(['lines'=>$part->part_name.' exceeds outstanding PO quantity.']);
                }
            }
            $grnNo=$this->num('GRN');
            $grnId=DB::table('am260_grns')->insertGetId([
                'grn_no'=>$grnNo,'vendor_id'=>$vendor->id,
                'po_id'=>$order->id??null,'job_card_id'=>$jobId?:null,
                'challan_no'=>$challan,'challan_date'=>$d['challan_date'],
                'receipt_date'=>$d['receipt_date'],'status'=>'RECEIVED',
                'notes'=>trim((string)($d['notes']??''))?:null,
                'created_by'=>(string)($u->name??$u->id),
                'created_at'=>now(),'updated_at'=>now()
            ]);

            foreach($lines as $l){
                $part=$parts->get($l['part_master_id']);
                $openingUnrecorded=$part->stock_qty===null;
                // With no recorded opening, the provisional operational baseline
                // is zero ONLY for receipt arithmetic. Source opening remains
                // explicitly unverified in AM262 exceptions and movement notes.
                // Existing non-null negative and positive balances are preserved.
                $before=$openingUnrecorded?0.0:(float)$part->stock_qty;
                $after=round($before+$l['qty'],3);
                DB::table('am260_grn_lines')->insert([
                   'grn_id'=>$grnId,
                   'po_line_id'=>$order?$l['po_line_id']:null,
                   'part_master_id'=>$part->id,'job_card_id'=>$jobId?:null,
                   'qty_received'=>$l['qty'],'qty_billed'=>0,
                   'provisional_unit_cost'=>$l['unit_cost'],
                   'created_at'=>now(),'updated_at'=>now()
                ]);
                if($openingUnrecorded){
                    $prior=DB::table('inventory_movements')
                        ->where('part_master_id',$part->id)->orderByDesc('id')
                        ->first(['id','balance_after']);
                    DB::table('am262_grn_opening_exceptions')->insert([
                        'grn_id'=>$grnId,'part_master_id'=>$part->id,
                        'prior_stock_qty'=>null,'provisional_base_qty'=>0,
                        'received_qty'=>$l['qty'],'provisional_after_qty'=>$after,
                        'prior_movement_id'=>$prior->id??null,
                        'prior_movement_balance'=>$prior->balance_after??null,
                        'status'=>'PENDING_RECONCILIATION',
                        'acknowledged_by'=>(string)($u->name??$u->id),
                        'created_at'=>now(),'updated_at'=>now(),
                    ]);
                }
                $part->stock_qty=$after;
                $part->save();
                if($order){
                    DB::table('am260_po_lines')->where('id',$l['po_line_id'])
                      ->update(['qty_received'=>round((float)$poLines->get($l['po_line_id'])->qty_received+$l['qty'],3),'updated_at'=>now()]);
                }
                DB::table('inventory_movements')->insert([
                  'part_master_id'=>$part->id,'job_card_id'=>$jobId?:null,
                  'movement_type'=>'GRN_RECEIPT_IN',
                  'qty_in'=>$l['qty'],'qty_out'=>0,'balance_after'=>$after,
                  'reference_no'=>$grnNo,
                  // Inventory movement.note is length-limited: immutable full
                  // supplier/PO/GRN details are held in linked GRN tables.
                  // Put opening exception at START so even truncated notes
                  // retain the financial-audit warning.
                  'note'=>mb_strcut(
                     ($openingUnrecorded
                      ?'AM262_OPENING_UNRECORDED RECONCILIATION_PENDING BASE0_PROVISIONAL | '
                      :'GRN_RECEIPT | ').
                     'Vendor '.$vendor->vendor_name.
                     ' | Challan '.$challan.
                     ($order?' | PO '.$order->po_no:' | NO PO').
                     ' | BILL PENDING',0,180,'UTF-8'),
                  'created_at'=>now()
                ]);
            }
            if($order){
                $remaining=DB::table('am260_po_lines')->where('po_id',$order->id)
                  ->whereRaw('qty_received+0.0005<qty_ordered')->exists();
                if(!$remaining)DB::table('am260_purchase_orders')->where('id',$order->id)
                    ->update(['status'=>'RECEIVED','updated_at'=>now()]);
            }
            return $grnNo;
        },3);
        $savedReceipt=DB::table('am260_grns')->where('grn_no',$receipt)->first(['id']);
        abort_unless($savedReceipt,500,'GRN receipt was not found after posting.');
        return redirect()->route('erp.procurement.grn.show',['grn'=>$savedReceipt->id])
             ->with('success','GRN '.$receipt.' posted. Verify item-wise Stock In and open the Item Transaction Ledger below. Vendor Purchase Bill remains pending.');
    }

    private function pendingRows(int $vendorId): \Illuminate\Support\Collection
    {
        if(!$vendorId)return collect();
        return DB::table('am260_grn_lines as l')
           ->join('am260_grns as g','g.id','=','l.grn_id')
           ->join('parts_master as p','p.id','=','l.part_master_id')
           ->leftJoin('am260_purchase_orders as po','po.id','=','g.po_id')
           ->leftJoin('job_cards as jc','jc.id','=','l.job_card_id')
           ->where('g.vendor_id',$vendorId)->whereRaw('(l.qty_received-l.qty_billed)>0.0005')
           ->orderBy('g.receipt_date')->orderBy('l.id')
           ->get(['l.*','g.grn_no','g.challan_no','p.part_name','p.part_code','p.unit',
               'po.po_no','jc.job_no','jc.vehicle_reg_no']);
    }

    public function newBill(Request $request): View
    {
        $this->admin($request);
        $vendorId=max(0,(int)$request->query('vendor_id',0));
        $all=$this->selections();
        $all['selectedVendor']=$vendorId;
        $all['pending']=$this->pendingRows($vendorId);
        return view('procurement.match-bill',$all);
    }

    private function price(float $qty,float $rate,float $discountPercent,float $gstPercent): array
    {
        $gross=round($qty*$rate,2);
        $discount=round($gross*$discountPercent/100,2);
        $taxable=round($gross-$discount,2);
        $gst=round($taxable*$gstPercent/100,2);
        $cgst=round($gst/2,2);
        return ['gross'=>$gross,'discount'=>$discount,'taxable'=>$taxable,'gst'=>$gst,
            'cgst_percent'=>$gstPercent/2,'cgst'=>$cgst,'sgst_percent'=>$gstPercent/2,
            'sgst'=>round($gst-$cgst,2),'total'=>round($taxable+$gst,2)];
    }

    public function postBill(Request $request): RedirectResponse
    {
        $u=$this->admin($request);
        $d=$request->validate([
          'vendor_id'=>['required','integer','min:1'],
          'supplier_invoice'=>['required','string','max:100'],
          'purchase_date'=>['required','date_format:Y-m-d'],
          'notes'=>['nullable','string','max:1000'],
          'lines'=>['required','array','min:1','max:100'],
          'lines.*.selected'=>['nullable','in:1'],
          'lines.*.qty'=>['nullable','numeric','min:0'],
          'lines.*.unit_cost'=>['nullable','numeric','min:0'],
          'lines.*.discount_percent'=>['nullable','numeric','between:0,100'],
          'lines.*.gst_percent'=>['nullable','numeric','between:0,100'],
        ]);
        $prepared=[];
        foreach($d['lines'] as $lineId=>$row){
            if(!is_array($row)||($row['selected']??'')!=='1')continue;
            $lineId=(int)$lineId;
            $qty=round((float)($row['qty']??0),3);
            $rate=round((float)($row['unit_cost']??0),2);
            $discount=round((float)($row['discount_percent']??0),2);
            $gst=round((float)($row['gst_percent']??0),2);
            if($lineId<=0||$qty<=0||$rate<0||$rate>100000000 || $discount<0||$discount>100||$gst<0||$gst>100)
                throw ValidationException::withMessages(['lines'=>'Selected GRN lines need valid positive quantities, rates, discounts and GST.']);
            $prepared[$lineId]=['qty'=>$qty,'rate'=>$rate,'discount'=>$discount,'gst'=>$gst];
        }
        if(!$prepared)throw ValidationException::withMessages(['lines'=>'Select at least one pending GRN item.']);
        if(count($prepared)>100)throw ValidationException::withMessages(['lines'=>'Maximum 100 GRN lines per supplier bill.']);

        $batch=DB::transaction(function()use($d,$prepared,$u){
            $vendor=$this->vendor((int)$d['vendor_id']);
            $invoice=trim($d['supplier_invoice']);
            if(DB::table('am260_grn_bills')->where('vendor_id',$vendor->id)
                ->where('supplier_invoice',$invoice)->exists() ||
               DB::table('workshop_purchases')->where('vendor_id',$vendor->id)
                ->where('supplier_invoice',$invoice)
                ->whereRaw("COALESCE(record_status,'ACTIVE')<>'DELETED'")->exists())
                throw ValidationException::withMessages(['supplier_invoice'=>'Supplier invoice already booked for this vendor. Duplicate Purchase Bill blocked.']);

            $grnLines=DB::table('am260_grn_lines as l')
                ->join('am260_grns as g','g.id','=','l.grn_id')
                ->whereIn('l.id',array_keys($prepared))->orderBy('l.id')
                ->select(['l.*','g.vendor_id','g.grn_no','g.challan_no','g.po_id'])
                ->lockForUpdate()->get()->keyBy('id');
            $matched=[];
            $parts=[];
            $totals=['qty'=>0.0,'gross'=>0.0,'discount'=>0.0,'taxable'=>0.0,'gst'=>0.0,'total'=>0.0];
            foreach($prepared as $lineId=>$input){
                $grn=$grnLines->get($lineId);
                if(!$grn || (int)$grn->vendor_id!==(int)$vendor->id)
                    throw ValidationException::withMessages(['lines'=>'One GRN line is not pending for the selected vendor.']);
                $pending=round((float)$grn->qty_received-(float)$grn->qty_billed,3);
                if($input['qty']>$pending+0.0005)
                    throw ValidationException::withMessages(['lines'=>'GRN '.$grn->grn_no.' pending quantity is only '.$pending.'.']);
                $part=DB::table('parts_master')->where('id',$grn->part_master_id)->first();
                if(!$part)throw ValidationException::withMessages(['lines'=>'GRN-linked Part Master missing.']);
                $money=$this->price($input['qty'],$input['rate'],$input['discount'],$input['gst']);
                foreach($totals as $key=>$x)$totals[$key]+=$key==='qty'?$input['qty']:$money[$key];
                $matched[]=['grn'=>$grn,'part'=>$part,'input'=>$input,'money'=>$money];
            }

            $batchNo=$this->num('PB');
            $billId=DB::table('am260_grn_bills')->insertGetId([
                'vendor_id'=>$vendor->id,'supplier_invoice'=>$invoice,
                'purchase_date'=>$d['purchase_date'],'batch_no'=>$batchNo,
                'created_by'=>(string)($u->name??$u->id),
                'created_at'=>now(),'updated_at'=>now()
            ]);
            $links=[];
            foreach($matched as $n=>$line){
                $grn=$line['grn'];$part=$line['part'];$input=$line['input'];$money=$line['money'];
                $po=$grn->po_id?DB::table('am260_purchase_orders')->where('id',$grn->po_id)->first():null;
                $job=$grn->job_card_id?DB::table('job_cards')->where('id',$grn->job_card_id)->first():null;
                $reference='AGAINST '.($job?($job->job_no.' / '.$job->vehicle_reg_no):'STOCK')
                    .' + '.($po?$po->po_no:'NO PO').' + '.$grn->grn_no.' + CHALLAN '.$grn->challan_no;
                $lineNo=$n+1;
                $purchaseNo=$batchNo.'-L'.str_pad((string)$lineNo,3,'0',STR_PAD_LEFT);
                $id=DB::table('workshop_purchases')->insertGetId([
                    'purchase_no'=>$purchaseNo,'purchase_batch_no'=>$batchNo,
                    'purchase_line_no'=>$lineNo,'purchase_type'=>'PART',
                    'purchase_date'=>$d['purchase_date'],'vendor_id'=>$vendor->id,
                    'supplier_name'=>$vendor->vendor_name,'supplier_invoice'=>$invoice,
                    'job_card_id'=>$grn->job_card_id?:null,
                    'part_master_id'=>$part->id,'description'=>$part->part_name,
                    'hsn_code'=>$part->hsn_code?:null,'unit'=>$part->unit?:'PCS',
                    'qty'=>$input['qty'],'unit_cost'=>$input['rate'],
                    'discount_percent'=>$input['discount'],'discount_amount'=>$money['discount'],
                    'gst_percent'=>$input['gst'],'taxable_amount'=>$money['taxable'],
                    'gst_amount'=>$money['gst'],'cgst_percent'=>$money['cgst_percent'],
                    'cgst_amount'=>$money['cgst'],'sgst_percent'=>$money['sgst_percent'],
                    'sgst_amount'=>$money['sgst'],'total_amount'=>$money['total'],
                    'notes'=>Str::limit($reference,780),
                    'record_status'=>'ACTIVE',
                    'created_by'=>(string)($u->name??$u->id),
                    'created_at'=>now(),'updated_at'=>now()
                ]);
                DB::table('am260_grn_bill_allocations')->insert([
                    'bill_id'=>$billId,'grn_line_id'=>$grn->id,'purchase_id'=>$id,
                    'matched_qty'=>$input['qty'],'created_at'=>now(),'updated_at'=>now()
                ]);
                DB::table('am260_grn_lines')->where('id',$grn->id)
                   ->update(['qty_billed'=>round((float)$grn->qty_billed+$input['qty'],3),'updated_at'=>now()]);
                DB::table('purchase_audit_logs')->insert([
                    'purchase_id'=>$id,'purchase_no'=>$purchaseNo,'batch_no'=>$batchNo,
                    'action'=>'AM260_GRN_MATCHED_NO_STOCK_IN',
                    'actor'=>(string)($u->name??$u->id),
                    'reason'=>$reference,
                    'before_json'=>null,
                    'after_json'=>json_encode(['grn_line_id'=>$grn->id,'qty'=>$input['qty'],
                       'stock_movement'=>'NONE','invoice'=>$invoice],JSON_UNESCAPED_UNICODE),
                    'created_at'=>now()
                ]);
                $links[]=$reference;
            }
            $pre=round($totals['total'],2);
            $grand=round($pre,0,PHP_ROUND_HALF_UP);
            DB::table('workshop_purchase_batches')->insert([
                'batch_no'=>$batchNo,'purchase_type'=>'PART',
                'purchase_date'=>$d['purchase_date'],'vendor_id'=>$vendor->id,
                'supplier_name'=>$vendor->vendor_name,'supplier_invoice'=>$invoice,
                'item_count'=>count($matched),'total_qty'=>round($totals['qty'],3),
                'gross_amount'=>round($totals['gross'],2),
                'discount_amount'=>round($totals['discount'],2),
                'taxable_amount'=>round($totals['taxable'],2),
                'gst_amount'=>round($totals['gst'],2),
                'pre_round_total'=>$pre,'round_off'=>round($grand-$pre,2),
                'grand_total'=>$grand,'notes'=>'AM260 GRN MATCHED · '.Str::limit(implode(' ; ',array_unique($links)),600),
                'record_status'=>'ACTIVE',
                'created_by'=>(string)($u->name??$u->id),
                'created_at'=>now(),'updated_at'=>now()
            ]);
            return $batchNo;
        },3);
        return redirect()->route('erp.procurement.index')
           ->with('success','Purchase Bill '.$batch.' posted and linked to GRN/PO/Job Card. No additional stock received (0 stock movements). Vendor bill now appears in Purchase Register.');
    }
}
