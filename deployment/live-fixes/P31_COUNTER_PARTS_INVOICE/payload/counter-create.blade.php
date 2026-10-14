@extends('layouts.erp')
@section('title','Direct Parts Sale Invoice — Assam Motors')
@section('content')
{{-- P31_COUNTER_PARTS_INVOICE_CREATE --}}
@push('head')
<style>
.p31-wrap{max-width:1390px;margin:0 auto;color:#163c61}.p31-hero{padding:20px 22px;border-radius:13px;background:linear-gradient(110deg,#103b70,#156c92);color:white;border-bottom:5px solid #e7aa34;margin:12px 0 16px}
.p31-hero h1{font-size:24px;color:white;margin:5px 0}.p31-hero p{margin:4px 0;color:#d8eaf4;font-size:13px}
.p31-card{background:#fff;border:1px solid #d7e4f1;border-radius:13px;padding:17px;margin:13px 0;box-shadow:0 4px 13px rgba(15,46,81,.06)}
.p31-card h2{font-size:17px;color:#123e6a;margin:0 0 12px}.p31-header{display:grid;grid-template-columns:2fr 1fr 2fr;gap:12px;align-items:end}
.p31-header label,.p31-foot label{font-size:12px;font-weight:750;color:#456480}.p31-header select,.p31-header input,.p31-foot textarea{width:100%;box-sizing:border-box;min-height:44px;padding:10px;border:1px solid #c0d1e2;border-radius:8px;background:#fff;margin-top:5px}
.p31-line-box{width:100%;min-height:360px;height:auto;max-height:none;overflow-x:auto;border:1px solid #d5e1ee;border-radius:12px;background:#fff;padding:8px 9px 20px;box-sizing:border-box}
.p31-table{width:100%;min-width:1080px;border-collapse:separate;border-spacing:0 7px}
.p31-table th{font-size:11px;background:#eef5fb;color:#284a67;padding:10px 6px;text-align:left;white-space:nowrap;border-block:1px solid #d7e3ef}
.p31-table td{padding:6px 4px;background:#f7fbff}.p31-table input,.p31-table select{width:100%;box-sizing:border-box;min-height:43px;border:1px solid #bed0e2;padding:8px;border-radius:7px;background:white;font-size:13px}
.p31-table select{min-width:300px}.p31-table td:nth-child(2){min-width:290px}
.p31-table td:nth-child(3){width:70px}.p31-table td:nth-child(4){width:105px}.p31-table td:nth-child(5){width:70px}
.p31-num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}.p31-btn{display:inline-flex;align-items:center;justify-content:center;min-height:44px;border:0;border-radius:9px;padding:10px 15px;background:#135890;color:#fff;font-weight:800;font-size:13px;cursor:pointer}
.p31-btn.green{background:#0b7e5d}.p31-btn.red{background:#c63d38}.p31-btn.outline{background:#fff;color:#17486e;border:1px solid #a9c2d8}
.p31-toolbar{display:flex;align-items:center;justify-content:space-between;gap:9px;flex-wrap:wrap;margin-bottom:12px}
.p31-foot{display:grid;grid-template-columns:1fr minmax(260px,360px);gap:19px;align-items:start}
.p31-foot textarea{min-height:100px;resize:vertical}
.p31-totals{background:#f4f9ff;border:1px solid #d6e4f1;border-radius:11px;overflow:hidden}.p31-totals div{padding:12px 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #d6e4f1;font-size:13px}
.p31-totals div:last-child{background:#e5f2ff;color:#104577;font-size:18px;font-weight:850}
.p31-note{padding:12px;border-radius:9px;background:#fff7e3;border-left:4px solid #e3a629;font-size:12px;color:#735322;margin:12px 0}
.p31-error{padding:12px;border-radius:9px;background:#ffecea;border-left:4px solid #c43f35;color:#9f2a20;margin:12px 0}
.p31-actions{display:flex;justify-content:flex-end;gap:12px;margin:16px 0;flex-wrap:wrap}
@media(max-width:720px){.p31-header{grid-template-columns:1fr}.p31-foot{grid-template-columns:1fr}.p31-actions>*{width:100%}}
</style>
@endpush
<div class="p31-wrap">
 <header class="p31-hero"><small>ASSAM MOTORS · PARTS COUNTER</small>
  <h1>Direct Parts Sale Invoice — Without Job Card</h1>
  <p>Quick sale from Parts Master. Stock is issued, Invoice is created, and Customer Ledger is posted in one transaction.</p>
 </header>
 @if($errors->any())<div class="p31-error"><strong>Cannot create invoice:</strong> {{ $errors->first() }}</div>@endif
 @if(session('success'))<div class="p31-note">{{ session('success') }}</div>@endif
 <form id="p31-counter-invoice-form" method="post" action="{{ route('erp.counter-sales.store') }}">
  @csrf
  <input type="hidden" name="request_key" value="{{ old('request_key',$requestKey) }}">
  <section class="p31-card"><h2>Customer / Invoice Details</h2>
   <div class="p31-header">
    <label>Customer Master *<select name="customer_id" required>
      <option value="">Select Customer / Walk-in Customer Master</option>
      @foreach($customers as $c)<option value="{{ $c->id }}" @selected((string)old('customer_id')===(string)$c->id)>{{ $c->name }} · {{ $c->phone ?: ($c->customer_code ?: '—') }}</option>@endforeach
    </select></label>
    <label>Invoice Date *<input type="date" name="invoice_date" required max="{{ now('Asia/Kolkata')->toDateString() }}" value="{{ old('invoice_date',now('Asia/Kolkata')->toDateString()) }}"></label>
    <div class="p31-note">Customer first? <a href="{{ route('erp.customers') }}">Create/choose a Customer</a>. A Job Card is not required.</div>
   </div>
  </section>
  <section class="p31-card">
   <div class="p31-toolbar"><div><h2>Parts Sale Lines</h2><small>Part name/code search · Qty · Rate · Disc % · GST auto from Master</small></div>
    <button class="p31-btn" type="button" id="p31-add-line">+ Add Part Line</button></div>
   <div class="p31-line-box"><table class="p31-table">
    <thead><tr><th>#</th><th>Part Search / Selection *</th><th>Qty *</th><th>Rate ₹ *</th><th>Disc %</th><th>GST %</th><th>Stock</th><th class="p31-num">Preview ₹</th><th></th></tr></thead>
    <tbody id="p31-lines"></tbody>
   </table></div>
  </section>
  <section class="p31-card">
   <div class="p31-foot">
    <label>Narration / Sale Details<textarea name="narration" maxlength="500" placeholder="Counter sale, walk-in retail, PO reference etc">{{ old('narration') }}</textarea></label>
    <div class="p31-totals">
      <div><span>Taxable Amount</span><strong id="p31-taxable">₹0.00</strong></div>
      <div><span>GST Amount</span><strong id="p31-gst">₹0.00</strong></div>
      <div><span>Round Off</span><strong id="p31-round">₹0.00</strong></div>
      <div><span>Grand Total</span><strong id="p31-grand">₹0.00</strong></div>
    </div>
   </div>
   <p class="p31-note">Preview is for convenience only. Server verifies stock, rate, discount and GST and recalculates final amount. This action creates a posted invoice and issues stock. Customer payment is recorded separately through the existing Money Receipt/Payment module.</p>
  </section>
  <div class="p31-actions"><a class="p31-btn outline" href="{{ route('erp.counter-sales.index') }}">Counter Invoice Register</a>
   <button type="submit" id="p31-submit" class="p31-btn green">✓ Create Invoice &amp; Issue Stock</button></div>
 </form>
</div>

<template id="p31-part-row-template">
 <tr><td class="p31-sl"></td>
 <td><select data-part name="items[ROW][part_master_id]" required>
  <option value="">Search / Select Part...</option>
  @foreach($parts as $p)<option value="{{ $p->id }}" data-rate="{{ $p->selling_price }}" data-tax="{{ $p->gst_percent }}" data-stock="{{ $p->stock_qty }}">{{ $p->part_code }} · {{ $p->part_name }}</option>@endforeach
 </select><input type="text" data-note name="items[ROW][note]" maxlength="500" placeholder="Optional Part Note" style="margin-top:5px"></td>
 <td><input data-qty name="items[ROW][qty]" type="number" min="0.001" step="0.001" value="1" required></td>
 <td><input data-rate name="items[ROW][rate]" type="number" min="0.01" step="0.01" required></td>
 <td><input data-disc name="items[ROW][discount_percent]" type="number" min="0" max="100" step="0.01" value="0" required></td>
 <td><input data-gst value="0%" readonly></td><td data-stock>—</td>
 <td class="p31-num"><strong data-total>₹0.00</strong></td><td><button class="p31-btn red" type="button" data-remove aria-label="Remove part line">×</button></td>
 </tr>
</template>
<script>
(function(){
'use strict';
const body=document.getElementById('p31-lines'),temp=document.getElementById('p31-part-row-template'),form=document.getElementById('p31-counter-invoice-form');
const money=n=>'₹'+Number(n||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});
let next=0;
function reindex(){
 [...body.querySelectorAll('tr')].forEach((tr,i)=>{tr.querySelector('.p31-sl').textContent=i+1;});
}
function total(){
 let taxable=0,gst=0;
 body.querySelectorAll('tr').forEach(tr=>{
  const opt=tr.querySelector('[data-part]').selectedOptions[0];
  const quantity=Number(tr.querySelector('[data-qty]').value)||0;
  const rate=Number(tr.querySelector('[data-rate]').value)||0;
  const discount=Number(tr.querySelector('[data-disc]').value)||0;
  const gstPct=Number(opt?.dataset?.tax)||0;
  const raw=Math.round(quantity*rate*100)/100;
  const d=Math.round(raw*discount)/100;
  const t=Math.round((raw-d)*100)/100;
  const tax=Math.round(t*gstPct)/100;
  taxable+=t;gst+=tax;
  tr.querySelector('[data-gst]').value=gstPct.toFixed(2)+'%';
  tr.querySelector('[data-stock]').textContent=opt?.value ? Number(opt.dataset.stock||0).toFixed(3):'—';
  tr.querySelector('[data-total]').textContent=money(t+tax);
 });
 taxable=Math.round(taxable*100)/100;gst=Math.round(gst*100)/100;
 const pre=Math.round((taxable+gst)*100)/100;
 const grand=Math.floor(pre+0.5);
 document.getElementById('p31-taxable').textContent=money(taxable);
 document.getElementById('p31-gst').textContent=money(gst);
 document.getElementById('p31-round').textContent=money(grand-pre);
 document.getElementById('p31-grand').textContent=money(grand);
}
function add(){
 const row=temp.content.firstElementChild.cloneNode(true);const idx=next++;
 row.querySelectorAll('[name]').forEach(node=>node.name=node.name.replace('ROW',idx));
 row.querySelector('[data-part]').addEventListener('change',()=>{
   const sel=row.querySelector('[data-part]').selectedOptions[0];
   row.querySelector('[data-rate]').value=Number(sel?.dataset?.rate||0).toFixed(2);
   total();
 });
 row.querySelectorAll('input').forEach(n=>n.addEventListener('input',total));
 row.querySelector('[data-remove]').onclick=()=>{
    if(body.children.length<=1){alert('At least one Part line is mandatory.');return;}
    row.remove();reindex();total();
 };
 body.appendChild(row);reindex();total();
}
document.getElementById('p31-add-line').onclick=add;
form.addEventListener('submit',e=>{
 const rows=[...body.querySelectorAll('tr')];
 const ids=rows.map(r=>r.querySelector('[data-part]').value);
 if(!ids.length || ids.some(x=>!x)||new Set(ids).size!==ids.length){
    e.preventDefault();alert('Select a different valid Part in every line.');return;
 }
 for(const row of rows){
   const opt=row.querySelector('[data-part]').selectedOptions[0];
   if(Number(row.querySelector('[data-qty]').value)>Number(opt.dataset.stock||0)){
      e.preventDefault();alert('Not enough stock for '+(opt.textContent||'selected Part'));return;
   }
 }
 if(!confirm('Create Direct Parts Invoice, issue stock and debit Customer Ledger?')){
    e.preventDefault();return;
 }
 document.getElementById('p31-submit').disabled=true;
});
add();
})();
</script>
@endsection
