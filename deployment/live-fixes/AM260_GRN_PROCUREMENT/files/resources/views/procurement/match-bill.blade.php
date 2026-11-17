@extends('layouts.erp')
@section('title','Match Vendor Bill to GRN · ASSAM MOTORS')
@section('content')
{{-- AM260: Creates real purchase batch once; NO duplicate inventory receipt. --}}
<style>
.am260{max-width:1510px;margin:0 auto;color:#1a4560;font:12px Inter,"Segoe UI",Arial,sans-serif;padding-bottom:32px}.am260 *{box-sizing:border-box}
.am260 h1{font-size:23px;margin:6px 0;letter-spacing:-.03em;color:#153e60}.am260 .muted{color:#758da1;font-size:11px;line-height:1.65}
.am260 .panel{padding:17px;background:#fff;border:1px solid #d9e7ef;border-radius:12px;margin:12px 0}
.am260 .alert{padding:12px;background:#fff7e8;border:1px solid #ebd5b1;border-radius:9px;margin:11px 0;color:#876027;line-height:1.65}
.am260 .err{padding:11px;background:#fff1f1;color:#a43a3a;border:1px solid #e6bbbb;border-radius:8px}
.am260 label{font-size:11px;color:#527086;font-weight:800;display:flex;flex-direction:column;gap:5px}
.am260 input,.am260 select{width:100%;height:37px;background:#fff;border:1px solid #c2d9e7;border-radius:8px;padding:8px 9px;font-size:12px;color:#1a4864}
.am260 .grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
.am260 .btn{padding:10px 13px;display:inline-flex;align-items:center;min-height:39px;border-radius:8px;border:1px solid #c3d7e5;font-size:12px;font-weight:800;background:#fff;color:#235a80;text-decoration:none;cursor:pointer}
.am260 .btn.primary{background:#0a796d;color:white;border-color:#0a796d}.am260 .buttons{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:13px}
.am260 .wrap{overflow:auto;max-height:610px;border:1px solid #e0e9f0;border-radius:9px}.am260 table{border-collapse:collapse;width:100%;min-width:1180px}
.am260 th{font-size:10px;font-weight:850;position:sticky;top:0;background:#17436a;color:#fff;padding:11px;text-align:left}
.am260 td{padding:8px;border-bottom:1px solid #e9f0f6;font-size:11px}.am260 tbody tr:nth-child(even){background:#f7fafb}
.am260 td input[type=number]{max-width:115px}.am260 .qty{max-width:100px}.am260 .muted-badge{padding:5px 8px;color:#805f25;background:#fff2dd;border-radius:6px;font-weight:800}
.am260 .strongsum{display:flex;gap:12px;flex-wrap:wrap;justify-content:space-between;align-items:center;background:#edf8f5;border:1px solid #c3e5da;border-radius:8px;padding:13px;font-weight:800;margin-top:12px;color:#116e5e}
.am260 .strongsum strong{font-size:23px;font-variant-numeric:tabular-nums}
@media(max-width:850px){.am260 .grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:540px){.am260 .grid{grid-template-columns:1fr}}
</style>
<div class="am260">
  <small style="color:#0b8175;font-weight:900;letter-spacing:.11em">ASSAM MOTORS · PROCUREMENT / ACCOUNTS</small>
  <h1>Match Vendor Purchase Bill Against PO / GRN / Job Card</h1>
  <p class="muted">Jupiter Automobiles ya koi bhi Vendor ka actual bill aane par pehle received challans select karke final invoice book kijiye. Stock dobara add nahi hoga.</p>
  @if($errors->any())<div class="err"><strong>Please correct:</strong><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
  <div class="panel">
    <form method="get" action="{{ route('erp.procurement.bill.create') }}">
      <div class="grid" style="grid-template-columns:minmax(250px,490px) auto">
        <label>Select Vendor with Pending GRN
          <select name="vendor_id" required>
            <option value="">Choose Vendor</option>
            @foreach($vendors as $vendor)<option value="{{ $vendor->id }}" @selected((int)$selectedVendor===(int)$vendor->id)>{{ $vendor->vendor_name }} ({{ $vendor->vendor_code }})</option>@endforeach
          </select>
        </label>
        <div style="display:flex;align-items:end"><button class="btn" type="submit">Show Pending Challans</button></div>
      </div>
    </form>
  </div>
  @if($selectedVendor)
    <form method="post" action="{{ route('erp.procurement.bill.store') }}" id="am260-bill-form">
      @csrf
      <input type="hidden" name="vendor_id" value="{{ $selectedVendor }}">
      <div class="panel">
        <h3 style="margin:0 0 12px;color:#184966">Supplier's Actual Purchase Invoice</h3>
        <div class="grid">
          <label>Supplier Invoice Number *
            <input name="supplier_invoice" maxlength="100" value="{{ old('supplier_invoice') }}" required placeholder="Actual vendor invoice no.">
          </label>
          <label>Purchase Bill Date *
            <input type="date" name="purchase_date" value="{{ old('purchase_date',now('Asia/Kolkata')->format('Y-m-d')) }}" required>
          </label>
          <label>Remarks (optional)<input name="notes" maxlength="1000" placeholder="Monthly consolidated supplier bill etc."></label>
        </div>
      </div>
      <div class="panel">
        <h3 style="margin:0 0 9px;color:#184966">Pending GRN Items — Select for This Bill</h3>
        <div class="alert">
          Each row shows <strong>GRN + Vendor Challan + PO Number + Job Card / Vehicle</strong>.
          You can select multiple GRNs from this same vendor, or bill only part of an outstanding quantity.
          Enter actual invoice unit cost, discount %, GST % <strong>on ex-GST cost</strong>.
          Booking a bill creates Purchase Register and vendor payable reference, but <strong>stock increment = ZERO</strong>.
        </div>
        <div class="wrap"><table>
          <thead><tr><th>Select</th><th>GRN / Challan</th><th>Against PO + JC / Vehicle</th><th>Part</th><th>Received Qty</th><th>Billed</th><th>Pending Qty</th><th>Bill Qty</th><th>Rate ₹ (ex-GST)</th><th>Discount %</th><th>GST %</th><th>Line Total</th></tr></thead>
          <tbody>
          @forelse($pending as $l)
            @php $left=round((float)$l->qty_received-(float)$l->qty_billed,3); @endphp
            <tr data-bill-row>
              <td><input type="checkbox" name="lines[{{ $l->id }}][selected]" value="1" checked style="width:18px;height:18px"></td>
              <td><strong>{{ $l->grn_no }}</strong><small class="muted" style="display:block">{{ $l->challan_no }}</small></td>
              <td><strong>{{ $l->job_no??'STOCK' }}</strong><small class="muted" style="display:block">
                {{ $l->vehicle_reg_no??'' }} + {{ $l->po_no??'NO PO' }}
              </small></td>
              <td><strong>{{ $l->part_name }}</strong><small class="muted" style="display:block">{{ $l->part_code }}</small></td>
              <td>{{ number_format((float)$l->qty_received,3) }}</td>
              <td>{{ number_format((float)$l->qty_billed,3) }}</td>
              <td><span class="muted-badge">{{ number_format($left,3) }}</span></td>
              <td><input class="qty" type="number" step=".001" min="0.001" max="{{ number_format($left,3,'.','') }}"
                 name="lines[{{ $l->id }}][qty]" value="{{ number_format($left,3,'.','') }}" required></td>
              <td><input type="number" step=".01" min="0" name="lines[{{ $l->id }}][unit_cost]" value="{{ number_format((float)$l->provisional_unit_cost,2,'.','') }}" required></td>
              <td><input type="number" step=".01" min="0" max="100" name="lines[{{ $l->id }}][discount_percent]" value="0"></td>
              <td><input type="number" step=".01" min="0" max="100" name="lines[{{ $l->id }}][gst_percent]" value="18"></td>
              <td data-total style="font-weight:900;color:#125f5d">₹0.00</td>
            </tr>
          @empty
            <tr><td colspan="12" style="padding:27px;text-align:center">This Vendor has no pending GRN quantities. Receive a challan before posting a linked Purchase Bill.</td></tr>
          @endforelse
          </tbody>
        </table></div>
        <div class="strongsum"><span>Total Selected Invoice Amount (incl. GST; before round-off)</span><strong id="am260-total">₹0.00</strong></div>
        <div class="buttons">
          <button type="submit" class="btn primary" @disabled($pending->isEmpty()) onclick="return confirm('POST SUPPLIER PURCHASE BILL for selected GRN items? Stock will NOT be received again. Financial purchase/payable will be booked.');">Post Purchase Bill — No Duplicate Stock</button>
          <a class="btn" href="{{ route('erp.procurement.index') }}">Back to Challan Register</a>
          <a class="btn" href="{{ route('erp.purchases.index') }}">Purchase Register</a>
        </div>
      </div>
    </form>
  @else
    <div class="alert">Pehle Vendor select kijiye. Us Vendor ke unbilled challans automatically load honge. Purchase Against mein Job Card + PO No + GRN No clearly dikhega.</div>
  @endif
</div>
<script>
(function(){
  var form=document.getElementById('am260-bill-form');
  if(!form)return;
  function calculate(){
    var sum=0;
    Array.from(form.querySelectorAll('[data-bill-row]')).forEach(function(row){
      var fields=row.querySelectorAll('input');
      var check=row.querySelector('input[type=checkbox]');
      var inputQty=row.querySelector('input[name$="[qty]"]');
      var inputRate=row.querySelector('input[name$="[unit_cost]"]');
      var inputDiscount=row.querySelector('input[name$="[discount_percent]"]');
      var inputGst=row.querySelector('input[name$="[gst_percent]"]');
      var qty=Number(inputQty.value),rate=Number(inputRate.value),
          discount=Number(inputDiscount.value),gst=Number(inputGst.value);
      var net=Math.round(qty*rate*100)/100;
      var off=Math.round(net*discount)/100;
      var taxable=Math.round((net-off)*100)/100;
      var line=Math.round((taxable+Math.round(taxable*gst)/100)*100)/100;
      if(!check.checked)line=0;
      sum+=line;
      row.querySelector('[data-total]').textContent='₹'+line.toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});
    });
    document.getElementById('am260-total').textContent='₹'+sum.toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});
  }
  form.addEventListener('input',calculate);
  calculate();
})();
</script>
@endsection
