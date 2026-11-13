@extends('layouts.erp')
@section('title','Purchase Return Entry · ASSAM MOTORS')
@section('content')
{{-- AM253_PURCHASE_RETURN_ENTRY: supplier credit; original purchase and customer bill are retained. --}}
<style>
.am253-entry{max-width:1450px;margin:0 auto;color:#173a5b}
.am253-entry *{box-sizing:border-box}
.am253-title{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:17px 20px;border:1px solid #d5e3f1;border-radius:14px;background:#f2f7ff;margin-bottom:12px}
.am253-title h1{margin:3px 0;font-size:24px;color:#163d63}.am253-title small{font-size:11px;color:#66809a;letter-spacing:.06em;font-weight:800}
.am253-title a{display:inline-flex;align-items:center;justify-content:center;background:#e2eef8;border-radius:9px;color:#1e527e;text-decoration:none;padding:10px 12px;font-size:12px;font-weight:750}
.am253-note{margin:12px 0;padding:11px 14px;font-size:12px;line-height:1.6;background:#fff7e6;color:#7b571b;border-left:4px solid #e5a63a;border-radius:8px}
.am253-formbox{padding:18px;background:#fff;border:1px solid #d8e5f2;border-radius:12px;margin-bottom:14px}
.am253-formbox h2{font-size:15px;margin:0 0 14px;color:#204368}
.am253-params{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:11px}
.am253-params label,.am253-reason{display:flex;flex-direction:column;font-weight:750;font-size:11px;color:#4b6881;gap:5px}
.am253-params input,.am253-reason textarea{width:100%;font-family:inherit;font-size:13px;padding:10px 11px;border:1px solid #c7d8e9;border-radius:9px;min-height:42px}
.am253-reason{margin-top:13px}.am253-reason textarea{min-height:76px;resize:vertical}
.am253-scroll{overflow:auto;border:1px solid #dbe6ef;border-radius:10px}
.am253-items{width:100%;min-width:990px;border-collapse:collapse}
.am253-items th{background:#173e6e;color:#fff;padding:12px 10px;text-align:left;font-size:11px;white-space:nowrap}
.am253-items td{padding:12px 10px;border-top:1px solid #e5eef6;font-size:12px;vertical-align:middle}
.am253-items tr:nth-child(even){background:#f8fbff}
.am253-items .num{text-align:right;font-variant-numeric:tabular-nums}
.am253-items input{max-width:114px;width:100%;min-height:36px;border:1px solid #b7c9df;border-radius:7px;padding:7px 9px;text-align:right;font-variant-numeric:tabular-nums;font-size:13px}
.am253-items input:focus{outline:2px solid #337fc7}
.am253-items input:disabled{background:#f2f4f7;color:#8594a4}
.am253-items small{display:block;font-size:10px;color:#73869a;margin-top:4px}
.am253-entry .am253-footer{display:flex;flex-wrap:wrap;gap:14px;align-items:center;justify-content:space-between;border-top:1px solid #d7e3ef;padding-top:14px;margin-top:17px}
.am253-preview{color:#2d4f6e;font-size:12px}
.am253-preview strong{font-variant-numeric:tabular-nums;font-size:22px;color:#123f68;display:block;margin-top:3px}
.am253-submit{border:0;background:#16765c;color:#fff;font-weight:850;padding:13px 22px;border-radius:9px;cursor:pointer;font-size:13px}
.am253-submit:hover{background:#0f6048}
.am253-history a{color:#175c91;text-decoration:none;font-weight:800}
.am253-history table{width:100%;border-collapse:collapse}.am253-history td,.am253-history th{padding:9px;border-bottom:1px solid #e7edf4;font-size:12px;text-align:left}
@media(max-width:850px){.am253-params{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:460px){.am253-params{grid-template-columns:1fr}.am253-title h1{font-size:20px}}
</style>
<div class="am253-entry">
  <div class="am253-title">
    <div><small>ASSAM MOTORS · SUPPLIER DEBIT NOTE</small><h1>Purchase Return Entry</h1><div>{{ $billRef }} · {{ $vendor->vendor_name }} · {{ $type }}</div></div>
    <div style="display:flex;gap:7px;flex-wrap:wrap">
      <a href="{{ route('erp.purchase-returns.create') }}">Choose Another Bill</a>
      <a href="{{ route('erp.purchases.show',$anchor->id) }}">Open Original Bill</a>
    </div>
  </div>
  <div class="am253-note">
    Return Qty cannot exceed the original quantity less earlier returns, available physical stock (Parts), or unpaid vendor bill value.
    Existing supplier payments and original Purchase Bill remain unchanged.
    @if($type==='OSL')
      <strong>OSL:</strong> This is a supplier cost credit, not an automatic cancellation of labour or customer invoice.
    @endif
  </div>
  <form method="post" action="{{ route('erp.purchases.return.store',$anchor->id) }}" id="am253-return-form" autocomplete="off">
    @csrf
    <input type="hidden" name="request_key" value="{{ $requestKey }}">
    <div class="am253-formbox">
      <h2>Return / Supplier Credit Details</h2>
      <div class="am253-params">
        <label>Original Purchase Bill<input readonly value="{{ $billRef }}"></label>
        <label>Vendor<input readonly value="{{ $vendor->vendor_name }}"></label>
        <label>Return Date<input type="date" name="return_date" value="{{ old('return_date',now('Asia/Kolkata')->format('Y-m-d')) }}" min="{{ $anchor->purchase_date }}" max="{{ now('Asia/Kolkata')->format('Y-m-d') }}" required></label>
        <label>Supplier Credit Note No. (optional)<input name="supplier_credit_note" value="{{ old('supplier_credit_note') }}" maxlength="120" placeholder="Credit Note reference"></label>
      </div>
      <label class="am253-reason">Reason for Purchase Return (mandatory)
        <textarea name="reason" maxlength="1500" minlength="10" required placeholder="Example: Defective spare part returned to supplier, credit approved.">{{ old('reason') }}</textarea>
      </label>
    </div>
    <div class="am253-formbox">
      <h2>Original Bill Lines — Enter Return Qty Only</h2>
      <div class="am253-scroll"><table class="am253-items">
        <thead><tr><th>SL</th><th>Part / OSL Service</th><th>Job / Stock</th><th class="num">Purchased</th><th class="num">Earlier Return</th><th class="num">Available Qty</th><th class="num">Rate</th><th class="num">GST%</th><th>Return Qty</th><th class="num">Est. Return Amount</th></tr></thead>
        <tbody>
        @foreach($details as $idx=>$d)
          @php $l=$d['line']; @endphp
          <tr data-am253-line>
            <td>{{ $idx+1 }}</td>
            <td><strong>{{ $l->description }}</strong><small>{{ $l->purchase_no }} · {{ $l->hsn_code ?: 'HSN/SAC as per original' }}</small></td>
            <td>{{ $l->job_card_id ? 'JC #'.$l->job_card_id : 'STOCK' }}</td>
            <td class="num">{{ number_format((float)$l->qty,3) }}</td>
            <td class="num">{{ number_format((float)$d['returned_qty'],3) }}</td>
            <td class="num"><strong>{{ number_format((float)$d['available_qty'],3) }}</strong>
              @if($d['part'])<small>Stock: {{ number_format((float)$d['part']->stock_qty,3) }}</small>@endif
            </td>
            <td class="num">₹{{ number_format((float)$l->unit_cost,2) }}</td>
            <td class="num">{{ number_format((float)$l->gst_percent,2) }}%</td>
            <td>
              <input type="number" name="items[{{ $l->id }}]" value="{{ old('items.'.$l->id,'0') }}" min="0" max="{{ number_format((float)$d['available_qty'],3,'.','') }}" step="0.001"
                aria-label="Return Qty for {{ $l->description }}"
                data-qty="{{ (float)$l->qty }}" data-total="{{ (float)$l->total_amount }}" data-max="{{ (float)$d['available_qty'] }}"
                @disabled($d['available_qty']<=0)>
            </td>
            <td class="num" data-am253-line-amount>₹0.00</td>
          </tr>
        @endforeach
        </tbody>
      </table></div>
      <div class="am253-footer">
        <div class="am253-preview"><span>Estimated Return Credit (server will calculate final GST and round-off)</span><strong id="am253-preview-value">₹0.00</strong></div>
        <button class="am253-submit" type="submit" onclick="return confirm('Post Purchase Return? Stock and supplier outstanding will change. Original Purchase Bill remains unchanged.');">Post Purchase Return</button>
      </div>
    </div>
  </form>
  @if($past->isNotEmpty())
    <div class="am253-formbox am253-history">
      <h2>Earlier Return History for this Purchase Bill</h2>
      <table><thead><tr><th>Return No.</th><th>Date</th><th>Amount</th><th>Status</th><th></th></tr></thead><tbody>
        @foreach($past as $p)
          <tr><td><a href="{{ route('erp.purchase-returns.show',$p->id) }}">{{ $p->return_no }}</a></td><td>{{ $p->return_date }}</td><td>−₹{{ number_format((float)$p->grand_total,2) }}</td><td>{{ $p->record_status }}</td><td><a href="{{ route('erp.purchase-returns.show',$p->id) }}">View</a></td></tr>
        @endforeach
      </tbody></table>
    </div>
  @endif
</div>
<script>
(function(){
  var form=document.getElementById('am253-return-form');
  if(!form)return;
  function calc(){
    var subtotal=0;
    form.querySelectorAll('tr[data-am253-line]').forEach(function(row){
      var input=row.querySelector('input[type="number"]');
      var q=Number(input.value||0),original=Number(input.dataset.qty||0);
      var allowed=Number(input.dataset.max||0),total=Number(input.dataset.total||0);
      var v=(q>0&&q<=allowed&&original>0)?Math.round((total*q/original)*100)/100:0;
      subtotal+=v;
      row.querySelector('[data-am253-line-amount]').textContent='₹'+v.toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});
      input.setCustomValidity(q>allowed?'Return quantity exceeds available quantity.':'');
    });
    document.getElementById('am253-preview-value').textContent='₹'+subtotal.toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});
  }
  form.addEventListener('input',calc);calc();
})();
</script>
@endsection
