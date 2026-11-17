@extends('layouts.erp')
@section('title','Receive Vendor Challan / GRN · ASSAM MOTORS')
@section('content')
{{-- AM260 GRN: physical quantity verified; stock-in once, invoice NOT booked. --}}
<style>
.am260{max-width:1460px;margin:0 auto;color:#1a4662;font-family:Inter,"Segoe UI",Arial,sans-serif;font-size:12px;padding-bottom:30px}
.am260 *{box-sizing:border-box}.am260 h1{font-size:23px;margin:5px 0;letter-spacing:-.03em;color:#174361}.am260 .muted{color:#6c8297;line-height:1.6}
.am260 .panel{border:1px solid #d6e4ed;background:#fff;border-radius:12px;padding:16px;margin:12px 0}
.am260 .fields{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:11px}.am260 label{display:flex;flex-direction:column;gap:5px;font-size:11px;font-weight:800;color:#456b83}
.am260 input,.am260 select{border:1px solid #bfd5e5;border-radius:8px;min-height:38px;padding:8px 10px;font-size:12px;width:100%;color:#1d4b69;background:#fff}
.am260 .wrap{overflow:auto;max-height:550px;border:1px solid #dbe8ef;border-radius:8px}.am260 table{width:100%;min-width:960px;border-collapse:collapse}.am260 th{background:#153e67;color:#fff;font-size:10px;padding:10px;text-align:left;position:sticky;top:0;z-index:1}.am260 td{border-bottom:1px solid #e5eff5;padding:7px;font-size:11px}.am260 tr:nth-child(even){background:#f7fafc}
.am260 .search{min-width:340px}.am260 .alert{border-radius:9px;background:#fff9ea;border:1px solid #efddbd;padding:12px;color:#7c5e26;margin:10px 0;line-height:1.6}
.am260 .errors{border-radius:9px;background:#fff1f2;border:1px solid #f0b9bc;padding:12px;color:#a43232;margin:10px 0}
.am260 .btn{background:#fff;border:1px solid #c2d8e7;border-radius:8px;padding:10px 13px;min-height:39px;color:#235877;font-weight:800;font-size:12px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center}
.am260 .btn.primary{background:#0a8070;color:#fff;border-color:#0a8070}.am260 .btn.blue{background:#185581;color:#fff;border-color:#185581}
.am260 .actions{display:flex;flex-wrap:wrap;gap:9px;margin:14px 0;align-items:center}
.am260 .stock-bad{color:#ad4141;font-weight:900}.am260 .stock-good{color:#167965;font-weight:800}
@media(max-width:780px){.am260 .fields{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:490px){.am260 .fields{grid-template-columns:1fr}}
</style>
<div class="am260">
 <small style="color:#0c867b;font-weight:900;letter-spacing:.1em">ASSAM MOTORS · PROCUREMENT / INVENTORY</small>
 <h1>Receive Vendor Challan / GRN</h1>
 <p class="muted">Physically received Parts ko stock mein add kijiye. Vendor Purchase Bill jab bhi aaye, pending GRN se match hoga.</p>
 @if(session('success'))<div style="border:1px solid #b6e4d4;background:#eaf9f0;padding:12px;border-radius:8px">{{ session('success') }}</div>@endif
 @if($errors->any())<div class="errors"><strong>Please correct:</strong><ul>@foreach($errors->all() as $msg)<li>{{ $msg }}</li>@endforeach</ul></div>@endif
 @if(!$order)
 <div class="panel" style="background:#eff7fc">
   <label style="max-width:520px">Receive Against Existing Purchase Order (optional)
     <select onchange="if(this.value)location.href=this.value">
       <option value="">Urgent vendor challan without PO</option>
       @foreach($poOptions as $po)<option value="{{ route('erp.procurement.receive',['po_id'=>$po->id]) }}">{{ $po->po_no }} · {{ $po->vendor_name }}</option>@endforeach
     </select>
   </label>
 </div>
 @else
 <div class="alert"><strong>Against Purchase Order:</strong> {{ $order->po_no }} · {{ $order->vendor_name }}. Only its outstanding lines may be received, and actual qty cannot exceed PO balance.</div>
 @endif
 <form method="post" action="{{ route('erp.procurement.receive.store') }}" id="am260-grn-form">
   @csrf
   <input type="hidden" name="po_id" value="{{ $order->id??'' }}">
   <div class="panel">
     <div class="fields">
       @if($order)
         <label>Vendor from PO<input value="{{ $order->vendor_name }}" readonly><input type="hidden" name="vendor_id" value="{{ $order->vendor_id }}"></label>
         <label>Against JC from PO<input value="{{ $order->job_card_id?'Job Card ID '.$order->job_card_id:'STOCK' }}" readonly></label>
       @else
         <label>Vendor *
          <select name="vendor_id" required><option value="">Select Vendor</option>@foreach($vendors as $vendor)
           <option value="{{ $vendor->id }}" @selected((int)old('vendor_id',$selectedVendor)===(int)$vendor->id)>{{ $vendor->vendor_name }} ({{ $vendor->vendor_code }})</option>
          @endforeach</select>
         </label>
         <label>Purchase Against — JC / Vehicle
          <select name="job_card_id"><option value="">STOCK</option>
           @foreach($jobs as $j)<option value="{{ $j->id }}" @selected((int)old('job_card_id',$selectedJob)===(int)$j->id)>{{ $j->job_no }} · {{ $j->vehicle_reg_no }}</option>@endforeach
          </select>
         </label>
       @endif
       <label>Vendor Challan Number *<input name="challan_no" maxlength="100" placeholder="Supplier's delivery challan no" value="{{ old('challan_no') }}" required></label>
       <label>Challan Date *<input type="date" name="challan_date" value="{{ old('challan_date',now('Asia/Kolkata')->format('Y-m-d')) }}" required></label>
       <label>Physically Received Date *<input type="date" name="receipt_date" value="{{ old('receipt_date',now('Asia/Kolkata')->format('Y-m-d')) }}" required></label>
       <label>Remarks <input name="notes" maxlength="1200" placeholder="Delivered by / vehicle / condition"></label>
     </div>
   </div>
   <div class="panel">
     <h3 style="margin:0 0 8px">Parts Received — Actual Physical Qty</h3>
     <div class="alert"><strong>Stock-In can proceed even when Opening Stock is Not Recorded.</strong> Enter only the actual physically received quantity. For items with blank opening balance, ERP will use a <strong>provisional zero baseline for the newly received stock</strong>, mark the historical opening <strong>UNVERIFIED / Reconciliation Pending</strong> and keep an audit record. It does not claim the old physical stock was zero. Existing recorded/negative balances remain unchanged before adding receipt quantity. Vendor Purchase Bill is not booked at GRN stage.</div>
     <datalist id="am260-part-options">
       @foreach($parts as $p)<option value="{{ $p->part_code }} | {{ $p->part_name }}" data-id="{{ $p->id }}"></option>@endforeach
     </datalist>
     <div class="wrap"><table>
       <thead><tr><th style="width:42%">Part Code / Name</th><th>Current ERP Stock</th><th>Received Qty *</th><th>Provisional Unit Cost ₹ (ex-GST)</th><th>Line</th></tr></thead>
       <tbody id="am260-grn-rows">
       @if($order)
         @foreach($orderLines as $idx=>$line)
           <tr class="line">
             <td><strong>{{ $line->part_name }}</strong><small style="display:block;color:#7892a5">{{ $line->part_code }} · PO remaining {{ number_format((float)$line->qty_ordered-(float)$line->qty_received,3) }}</small>
               <input type="hidden" name="lines[{{ $idx }}][part_master_id]" value="{{ $line->part_master_id }}">
               <input type="hidden" name="lines[{{ $idx }}][po_line_id]" value="{{ $line->id }}">
             </td>
             <td class="{{ $line->stock_qty===null || $line->stock_qty<0?'stock-bad':'stock-good' }}">{{ $line->stock_qty===null?'Not recorded':number_format((float)$line->stock_qty,3) }} {{ $line->unit }}</td>
             <td><input type="number" min="0" max="{{ number_format((float)$line->qty_ordered-(float)$line->qty_received,3,'.','') }}" step=".001" name="lines[{{ $idx }}][qty]" value="{{ number_format((float)$line->qty_ordered-(float)$line->qty_received,3,'.','') }}"></td>
             <td><input type="number" min="0" step=".01" name="lines[{{ $idx }}][unit_cost]" value="{{ number_format((float)$line->expected_unit_cost,2,'.','') }}"></td><td>PO Line</td>
           </tr>
         @endforeach
       @else
         <tr class="line">
           <td><input class="search part-search" list="am260-part-options" autocomplete="off" placeholder="Type Part Code / Name; select option">
              <input type="hidden" name="lines[0][part_master_id]" class="part-id">
           </td>
           <td class="muted">From Parts Master</td>
           <td><input type="number" min="0" step=".001" name="lines[0][qty]" class="qty" placeholder="0.000"></td>
           <td><input type="number" min="0" step=".01" name="lines[0][unit_cost]" value="0"></td>
           <td><button class="btn" type="button" onclick="this.closest('tr').remove();am260CheckRows()">Remove</button></td>
         </tr>
       @endif
       </tbody>
     </table></div>
     <div style="padding:11px 13px;border:1px solid #e5d5ad;background:#fff8e9;border-radius:8px;margin:12px 0">
       <label style="display:flex;flex-direction:row;gap:10px;align-items:flex-start;line-height:1.55;color:#76531d;font-weight:750">
         <input type="checkbox" name="opening_acknowledged" value="1" @checked(old('opening_acknowledged')==='1') style="width:18px;min-width:18px;height:18px;min-height:18px;margin-top:1px">
         <span><strong>Opening Not Recorded — Allow Provisional Receipt:</strong> I confirm the physical received quantities shown above. If any selected Part has no opening stock, record it as <strong>Opening Unverified / Pending Reconciliation</strong> and receive its stock against this GRN. This acknowledgement is required only for affected Parts.</span>
       </label>
     </div>
     <div class="actions">
       @if(!$order)<button type="button" class="btn" onclick="am260AddGrnRow()">+ Add Received Part</button>@endif
       <button type="submit" class="btn primary" onclick="return confirm('CONFIRM PHYSICAL RECEIPT? Only received quantity will be added. Blank openings are NOT verified; if acknowledged, a provisional zero baseline and pending reconciliation audit will be recorded. Purchase Bill remains pending.');">Confirm GRN & Stock-In Once</button>
       <a href="{{ route('erp.procurement.index') }}" class="btn">Back to Procurement</a>
       @if($order)<a href="{{ route('erp.procurement.receive') }}" class="btn">Receive Without PO</a>@endif
     </div>
     <p class="muted">Posting GRN does NOT issue Parts to JC automatically. For blank openings, provisional quantity is made available from the physical challan receipt and permanently flagged for opening reconciliation. Item Ledger will show the GRN movement; confirm any older stock separately.</p>
   </div>
 </form>
</div>
@if(!$order)
<script>
(function(){
 var tbody=document.getElementById('am260-grn-rows');
 var options=Array.from(document.getElementById('am260-part-options').options);
 var next=1;
 function sync(input){
    var option=options.find(function(x){return x.value.trim()===input.value.trim()});
    input.closest('tr').querySelector('.part-id').value=option?option.dataset.id:'';
 }
 window.am260AddGrnRow=function(){
    if(tbody.children.length>=100)return alert('Maximum 100 GRN lines.');
    var first=tbody.querySelector('.line');
    if(!first){location.reload();return;}
    var clone=first.cloneNode(true);
    Array.from(clone.querySelectorAll('input')).forEach(function(el){
       if(el.name)el.name=el.name.replace(/\[\d+\]/,'['+next+']');
       el.value=el.name&&el.name.includes('[unit_cost]')?'0':'';
    });
    next++;tbody.appendChild(clone);
 };
 window.am260CheckRows=function(){if(!tbody.children.length)location.reload();};
 tbody.addEventListener('input',function(e){
     if(e.target.classList.contains('part-search'))sync(e.target);
     var row=e.target.closest('tr');
     if(row===tbody.lastElementChild && row.querySelector('.part-id').value &&
         Number(row.querySelector('.qty').value)>0)am260AddGrnRow();
 });
 document.getElementById('am260-grn-form').addEventListener('submit',function(e){
     Array.from(tbody.querySelectorAll('.part-search')).forEach(sync);
     var selected=Array.from(tbody.querySelectorAll('.part-id')).filter(function(x){return Number(x.value)>0});
     if(!selected.length){e.preventDefault();alert('Select a Part from matching search suggestions.');}
 });
})();
</script>
@endif
@endsection
