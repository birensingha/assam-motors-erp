@extends('layouts.erp')
@section('title','Edit GRN Items · ASSAM MOTORS')
@section('content')
{{-- AM264: GRN edit appends audited stock deltas; no original receipt movement delete. --}}
<style>
.am264{max-width:1450px;margin:0 auto;color:#163e5b;font:12px Inter,"Segoe UI",Arial,sans-serif;padding-bottom:30px}
.am264 *{box-sizing:border-box}.am264 .panel{background:#fff;border:1px solid #d9e6ee;border-radius:12px;padding:17px;margin:12px 0}
.am264 h1{font-size:24px;color:#173f63;letter-spacing:-.03em;margin:7px 0}.am264 h2{font-size:15px;color:#1a4b6a;margin:0 0 12px}
.am264 .heading{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px}
.am264 .hint{color:#748b9d;font-size:11px;line-height:1.7}.am264 .warning{border:1px solid #e8d4a6;background:#fff9ea;color:#815e27;padding:11px 13px;border-radius:9px;line-height:1.7;margin:11px 0}
.am264 .error{border:1px solid #e7b9b9;color:#a73c3c;background:#fff2f2;padding:11px;border-radius:9px}
.am264 .tablewrap{overflow:auto;border:1px solid #dbe8f0;border-radius:8px;max-height:640px}
.am264 table{width:100%;border-collapse:collapse;min-width:900px}
.am264 th{background:#174267;color:white;position:sticky;top:0;z-index:1;text-align:left;padding:11px 9px;font-size:10px;white-space:nowrap}
.am264 td{padding:9px;border-bottom:1px solid #e7eef4;vertical-align:middle}
.am264 tbody tr:nth-child(even){background:#f8fbfe}
.am264 input,.am264 textarea{border:1px solid #c6d9e6;background:#fff;padding:8px;border-radius:7px;font-size:12px;color:#214f6d;min-height:36px}
.am264 input[type=number]{width:110px}.am264 input.partinput{width:100%;min-width:250px}
.am264 .num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
.am264 small{color:#8194a4}.am264 .strong{font-weight:850;color:#1f4c68}.am264 .button{font-size:11px;font-weight:850;border:1px solid #c3d5e3;padding:10px 15px;display:inline-flex;align-items:center;min-height:39px;text-decoration:none;border-radius:8px;color:#195479;background:#fff;cursor:pointer}
.am264 .button.primary{background:#0c7c6e;border-color:#0c7c6e;color:white}.am264 .buttons{display:flex;gap:9px;flex-wrap:wrap;margin-top:12px}
.am264 .check{display:flex;gap:8px;align-items:flex-start;font-size:11px;line-height:1.6;font-weight:700}
.am264 .check input{width:18px;height:18px;min-height:18px;margin:0;flex:none}
.am264 .noteedit{width:100%;min-height:90px;resize:vertical}
.am264 details summary{cursor:pointer;font-weight:850;color:#165a7a;line-height:1.4}
</style>
<div class="am264">
 <div class="heading">
   <div><small style="color:#0c8376;font-weight:900;letter-spacing:.11em">ASSAM MOTORS · PROCUREMENT / GRN CORRECTIONS</small>
     <h1>Edit Received GRN Items</h1>
     <p class="hint"><strong>{{ $receipt->grn_no }}</strong> · {{ $receipt->vendor_name }} · Challan {{ $receipt->challan_no }} · {{ $receipt->job_no?:'STOCK' }} {{ $receipt->vehicle_reg_no?:'' }}</p>
   </div>
   <a class="button" href="{{ route('erp.procurement.grn.show',['grn'=>$receipt->id]) }}">Back to GRN Detail</a>
 </div>
 <div class="warning">
  <strong>Audited GRN Correction:</strong> Change quantities or provisional rates, or add physically received missing Parts. Receipt quantity increases/decreases create <strong>separate GRN correction movements</strong> in Item Transaction Ledger; the original receipt is never deleted. Quantity decrease is blocked if stock was already issued, is negative, or insufficient. Previously billed lines cannot be changed here. Do not add goods not physically received.
  @if($receipt->po_id)<div><strong>PO-linked receipt:</strong> Existing quantities may be corrected within PO limits, but extra Parts need PO update and a separate physical receipt.</div>@endif
 </div>
 @if($errors->any())<div class="error"><strong>Please correct:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
 <form method="post" action="{{ route('erp.procurement.grn.update',['grn'=>$receipt->id]) }}" id="am264-grn-edit-form" data-no-autosave>
   @csrf
   <section class="panel">
     <h2>Existing Received Items — Edit Qty / Provisional Rate</h2>
     <div class="tablewrap"><table>
       <thead><tr><th>Part Master</th><th>Current Stock</th><th class="num">Original GRN Qty</th><th>Correct Received Qty</th><th>Provisional Rate (₹)</th><th>Vendor Billed Qty</th><th>Safety</th></tr></thead>
       <tbody>
         @foreach($lines as $line)
         @php $isBilled=(float)$line->qty_billed>0.0005; @endphp
         <tr>
           <td><span class="strong">{{ $line->part_name }}</span><small style="display:block">{{ $line->part_code }} · {{ $line->unit }}</small>
             <input type="hidden" name="lines[{{ $line->id }}][original_qty]" value="{{ number_format((float)$line->qty_received,3,'.','') }}">
             <input type="hidden" name="lines[{{ $line->id }}][original_cost]" value="{{ number_format((float)$line->provisional_unit_cost,2,'.','') }}"></td>
           <td>{{ $line->stock_qty===null?'Unrecorded':number_format((float)$line->stock_qty,3) }}</td>
           <td class="num">{{ number_format((float)$line->qty_received,3) }}</td>
           <td><input name="lines[{{ $line->id }}][qty]" type="number" min="0" step=".001"
                 value="{{ old('lines.'.$line->id.'.qty',number_format((float)$line->qty_received,3,'.','')) }}"
                 required @disabled($isBilled)></td>
           <td><input name="lines[{{ $line->id }}][unit_cost]" type="number" min="0" step=".01"
                 value="{{ old('lines.'.$line->id.'.unit_cost',number_format((float)$line->provisional_unit_cost,2,'.','')) }}"
                 required @disabled($isBilled)></td>
           <td>{{ number_format((float)$line->qty_billed,3) }}</td>
           <td>@if($isBilled)<small style="color:#ba5b4d;font-weight:850">BILL MATCHED / LOCKED</small>
                <input type="hidden" name="lines[{{ $line->id }}][qty]" value="{{ number_format((float)$line->qty_received,3,'.','') }}">
                <input type="hidden" name="lines[{{ $line->id }}][unit_cost]" value="{{ number_format((float)$line->provisional_unit_cost,2,'.','') }}">
               @else <small>Qty 0 = reverse entire received amount, if unused stock allows</small>@endif</td>
         </tr>
         @endforeach
       </tbody>
     </table></div>
   </section>
   @if(!$receipt->po_id)
   <section class="panel">
     <details open><summary>+ Add Missing Physically Received Part to This GRN</summary>
     <p class="hint">For example, add Brake Wheel Cylinder only if it was actually delivered under this same challan. For additional supplier delivery under a different challan, use <strong>New GRN</strong> instead.</p>
     <datalist id="am264-edit-part-master">
       @foreach($parts as $p)<option value="{{ $p->part_code }} | {{ $p->part_name }}" data-id="{{ $p->id }}"></option>@endforeach
     </datalist>
     <div class="tablewrap"><table>
       <thead><tr><th style="width:56%">Search Part Master</th><th>Actual Extra Qty</th><th>Unit Cost ₹ (ex-GST)</th></tr></thead>
       <tbody>
       @for($index=0;$index<8;$index++)
         <tr>
           <td><input type="search" class="partinput am264-part-search" name="new_lines[{{ $index }}][part_search_label]"
              list="am264-edit-part-master" maxlength="220" autocomplete="off"
              value="{{ old('new_lines.'.$index.'.part_search_label') }}"
              placeholder="Type Code / Part Name, choose matching Part">
             <input type="hidden" class="am264-part-id" name="new_lines[{{ $index }}][part_master_id]"
                 value="{{ old('new_lines.'.$index.'.part_master_id','') }}"></td>
           <td><input name="new_lines[{{ $index }}][qty]" min="0" step=".001" type="number"
               value="{{ old('new_lines.'.$index.'.qty','') }}" placeholder="0.000"></td>
           <td><input name="new_lines[{{ $index }}][unit_cost]" min="0" step=".01" type="number"
               value="{{ old('new_lines.'.$index.'.unit_cost','0') }}"></td>
         </tr>
       @endfor
       </tbody>
     </table></div></details>
   </section>
   @endif
   <section class="panel">
     <h2>Required Correction Audit</h2>
     <label style="display:block;font-weight:800;margin-bottom:5px">Reason for GRN Correction (minimum 8 characters)</label>
     <textarea name="reason" class="noteedit" minlength="8" maxlength="1000"
       required placeholder="Why are received quantities/rates being corrected?">{{ old('reason') }}</textarea>
     <div style="display:grid;gap:10px;margin-top:12px">
       <label class="check"><input type="checkbox" required name="physical_verified" value="1">
         <span>I physically verified this vendor challan and the corrected received quantities. I understand stock-in/out adjustments will be recorded with my Admin identity and reason.</span></label>
       <label class="check"><input type="checkbox" name="opening_acknowledged" value="1" @checked(old('opening_acknowledged')==='1')>
         <span>Allow adding a Part without recorded opening stock as <strong>provisional received quantity</strong>, with Opening Unverified / Reconciliation Pending audit.</span></label>
     </div>
     <div class="buttons">
       <button class="button primary" type="submit"
          onclick="return confirm('SAVE AUDITED GRN CORRECTION? Stock quantities will change only by the recorded received-quantity differences. Confirm physical challan before continuing.');">
          Save GRN Item Correction
       </button>
       <a class="button" href="{{ route('erp.procurement.grn.show',['grn'=>$receipt->id]) }}">Cancel</a>
     </div>
   </section>
 </form>
</div>
<script>
(function(){
 const form=document.getElementById('am264-grn-edit-form');
 if(!form)return;
 const options=Array.from(document.querySelectorAll('#am264-edit-part-master option'));
 function sync(input){
   const row=input.closest('tr');
   const partId=row&&row.querySelector('.am264-part-id');
   if(!partId)return;
   const choice=options.find(function(o){return o.value===input.value.trim();});
   partId.value=choice?choice.dataset.id:'';
 }
 form.querySelectorAll('.am264-part-search').forEach(function(input){
   // Restore after a failed validation while preserving Part ID.
   if(!input.value && input.closest('tr').querySelector('.am264-part-id').value){
     const id=input.closest('tr').querySelector('.am264-part-id').value;
     const match=options.find(function(o){return o.dataset.id===id;});
     if(match)input.value=match.value;
   }
   input.addEventListener('input',function(){sync(input);});
   input.addEventListener('change',function(){sync(input);});
 });
 form.addEventListener('submit',function(e){
    form.querySelectorAll('.am264-part-search').forEach(sync);
    const invalid=Array.from(form.querySelectorAll('.am264-part-search')).some(function(input){
       const row=input.closest('tr');
       const qty=Number(row.querySelector('input[name$="[qty]"]').value);
       const part=row.querySelector('.am264-part-id').value;
       return qty>0 && !part;
    });
    if(invalid){e.preventDefault();alert('Select the Part from matching Master Code / Name suggestions for every positive new quantity.');}
 });
})();
</script>
@endsection
