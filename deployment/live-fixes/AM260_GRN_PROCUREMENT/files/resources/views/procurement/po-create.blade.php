@extends('layouts.erp')
@section('title','New Purchase Order · ASSAM MOTORS')
@section('content')
{{-- AM260 PO: no stock-in and no supplier bill posted. --}}
<style>
.am260{max-width:1420px;margin:0 auto;padding-bottom:30px;color:#19435e;font-size:12px;font-family:Inter,"Segoe UI",Arial,sans-serif}
.am260 *{box-sizing:border-box}.am260 .panel{background:#fff;border:1px solid #d6e5ef;border-radius:12px;padding:17px;margin-top:12px}
.am260 h1{font-size:23px;letter-spacing:-.03em;color:#143e5e;margin:5px 0}.am260 p{color:#6d8698;line-height:1.6}
.am260 .grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:11px}.am260 label{display:flex;flex-direction:column;gap:5px;font-weight:800;color:#496b83;font-size:11px}
.am260 select,.am260 input,.am260 textarea{width:100%;min-height:38px;padding:8px 9px;font-size:12px;border:1px solid #c5d9e7;border-radius:7px;color:#1b4e6c;background:#fff}
.am260 .wrap{overflow:auto;border:1px solid #dce8f0;border-radius:8px}.am260 table{width:100%;min-width:860px;border-collapse:collapse}.am260 th{background:#16436c;text-align:left;color:#fff;padding:11px;font-size:10px}
.am260 td{padding:6px;border-bottom:1px solid #e3edf4}.am260 .part-search{min-width:340px}.am260 .btn{border:1px solid #bcd3e1;border-radius:8px;padding:10px 13px;background:#fff;font-size:12px;font-weight:800;color:#235876;cursor:pointer;text-decoration:none;display:inline-block}
.am260 .btn.primary{background:#0b806f;color:#fff;border-color:#0b806f}.am260 .warn{background:#fff8eb;border:1px solid #efdcba;border-radius:8px;padding:12px;color:#835d2b;margin:12px 0}
.am260 .err{background:#fff0f0;border:1px solid #ebbbbb;padding:12px;border-radius:8px;color:#9b3232;margin:10px 0}
.am260 .actions{display:flex;flex-wrap:wrap;align-items:center;gap:9px;margin-top:14px}
@media(max-width:850px){.am260 .grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:500px){.am260 .grid{grid-template-columns:1fr}}
</style>
<div class="am260">
  <div><small style="color:#0c8375;font-weight:900;letter-spacing:.1em">ASSAM MOTORS · PROCUREMENT</small><h1>New Purchase Order (PO)</h1>
    <p>Vendor ko Parts order record kijiye. PO save karne par stock aur supplier payable change nahi honge.</p></div>
  @if(session('success'))<div style="padding:11px;background:#e8f8f0;margin:10px 0">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="err"><strong>Correct these entries:</strong><ul>@foreach($errors->all() as $msg)<li>{{ $msg }}</li>@endforeach</ul></div>@endif
  <form method="post" action="{{ route('erp.procurement.po.store') }}" id="am260-po-form">
    @csrf
    <div class="panel">
      <div class="grid">
        <label>Vendor *
          <select name="vendor_id" required>
            <option value="">Select Vendor</option>
            @foreach($vendors as $vendor)<option value="{{ $vendor->id }}" @selected((int)old('vendor_id',$selectedVendor)===(int)$vendor->id)>{{ $vendor->vendor_name }} ({{ $vendor->vendor_code }})</option>@endforeach
          </select>
        </label>
        <label>Purchase Against: Job Card / Vehicle
          <select name="job_card_id">
            <option value="">STOCK (no linked Job Card)</option>
            @foreach($jobs as $j)<option value="{{ $j->id }}" @selected((int)old('job_card_id',$selectedJob)===(int)$j->id)>{{ $j->job_no }} · {{ $j->vehicle_reg_no }}</option>@endforeach
          </select>
        </label>
        <label>PO Date *<input type="date" name="order_date" required value="{{ old('order_date',now('Asia/Kolkata')->format('Y-m-d')) }}"></label>
        <label>Remarks <input name="notes" maxlength="1200" placeholder="Urgent parts, expected delivery, etc."></label>
      </div>
    </div>
    <div class="panel">
      <h3 style="margin:0 0 12px;color:#164666">Parts to Order</h3>
      <div class="warn">Part selection se physical stock abhi nahi badhega. Delivery challan aane par next screen mein actual received quantities enter karenge.</div>
      <datalist id="am260-parts">
        @foreach($parts as $part)<option value="{{ $part->part_code }} | {{ $part->part_name }}" data-id="{{ $part->id }}"></option>@endforeach
      </datalist>
      <div class="wrap">
        <table><thead><tr><th style="width:54%">Part Code / Name — Type to Search</th><th>Order Qty</th><th>Expected Unit Cost ₹ (ex-GST)</th><th>Action</th></tr></thead>
          <tbody id="am260-lines">
            <tr class="line">
              <td><input class="part-search" list="am260-parts" placeholder="Type Part Code or Name · select matching entry" autocomplete="off">
                <input type="hidden" name="lines[0][part_master_id]" class="part-id"></td>
              <td><input type="number" min="0" step=".001" name="lines[0][qty]" class="part-qty" placeholder="0.000"></td>
              <td><input type="number" min="0" step=".01" name="lines[0][unit_cost]" value="0"></td>
              <td><button class="btn" type="button" onclick="this.closest('tr').remove();am260EnsureRow()">Remove</button></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="actions">
        <button type="button" class="btn" onclick="am260AddRow()">+ Add Part Line</button>
        <button type="submit" class="btn primary" onclick="return confirm('Create Purchase Order? This will NOT change stock or book supplier payable.');">Save Purchase Order</button>
        <a href="{{ route('erp.procurement.index') }}" class="btn">Back to Procurement</a>
      </div>
    </div>
  </form>
</div>
<script>
(function(){
  var rows=document.getElementById('am260-lines');
  var options=Array.from(document.getElementById('am260-parts').options);
  var next=1;
  function sync(el){
    var target=el.closest('tr').querySelector('.part-id');
    var found=options.find(function(x){return x.value.trim()===el.value.trim()});
    target.value=found?found.dataset.id:'';
  }
  window.am260AddRow=function(){
    if(rows.children.length>=100)return alert('Maximum 100 rows per PO.');
    var tr=rows.querySelector('tr.line');
    if(!tr){location.reload();return;}
    var clone=tr.cloneNode(true);
    Array.from(clone.querySelectorAll('input')).forEach(function(el){
      if(el.name)el.name=el.name.replace(/\[\d+\]/,'['+next+']');
      el.value=el.name&&el.name.includes('[unit_cost]')?'0':'';
    });
    next++;rows.appendChild(clone);
  };
  window.am260EnsureRow=function(){if(!rows.children.length)location.reload();};
  rows.addEventListener('input',function(e){
    if(e.target.classList.contains('part-search'))sync(e.target);
    var tr=e.target.closest('tr');
    if(tr===rows.lastElementChild && tr.querySelector('.part-id').value && Number(tr.querySelector('.part-qty').value)>0)am260AddRow();
  });
  document.getElementById('am260-po-form').addEventListener('submit',function(e){
     Array.from(rows.querySelectorAll('.part-search')).forEach(sync);
     if(!Array.from(rows.querySelectorAll('.part-id')).some(function(x){return Number(x.value)>0})){
       e.preventDefault();alert('At least one Part Code/Name must be selected from search suggestions.');
     }
  });
})();
</script>
@endsection
