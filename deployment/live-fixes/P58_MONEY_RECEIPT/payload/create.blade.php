@extends('layouts.erp')
@section('title','New Money Receipt · ASSAM MOTORS')
@section('topbar_title','Money Receipt')
@section('content')
<style>
.p58-wrap{max-width:1270px;margin:auto}
.p58-heading{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:16px}
.p58-heading h1{margin:0;font-size:25px;color:#103d70}.p58-heading p{color:#64778b;margin:4px 0}
.p58-link{display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;background:#f3f8fe;border:1px solid #cbddec;color:#134b7b;border-radius:9px;text-decoration:none;font-size:12px;font-weight:800}
.p58-sheet{background:white;border:1px solid #d5e2ee;box-shadow:0 5px 20px #153f6609;border-radius:13px;overflow:hidden;margin-bottom:18px}
.p58-band{padding:15px 18px;background:linear-gradient(110deg,#114176,#15649a);color:#fff;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.p58-band strong{font-size:16px}.p58-band small{color:#d2e9fc}
.p58-body{padding:17px;display:grid;grid-template-columns:1.4fr 1fr;gap:17px}
.p58-group{padding:13px;border:1px solid #dde8f1;border-radius:12px;min-width:0}
.p58-group h2{font-size:14px;margin:0 0 11px;color:#154976}
.p58-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px}
.p58-full{grid-column:1/-1}
.p58-label{display:flex;flex-direction:column;gap:5px;font-size:10.8px;letter-spacing:.01em;color:#345775;font-weight:800;text-transform:uppercase}
.p58-label select,.p58-label input,.p58-label textarea{min-height:40px;border:1px solid #cbdcea;border-radius:8px;padding:8px 9px;background:#fff;color:#193c5e;font:normal 13px Arial,sans-serif;max-width:100%;width:100%}
.p58-label input:focus,.p58-label select:focus,.p58-label textarea:focus{outline:2px solid #73a9d4}
.p58-label textarea{min-height:76px}
.p58-types{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-bottom:13px}
.p58-types label{border:1px solid #cbd9e7;padding:10px;border-radius:9px;cursor:pointer;font-size:12px;font-weight:800;color:#164b74;display:flex;gap:7px;align-items:flex-start;background:#f9fcff}
.p58-types input{margin:2px 0 0;flex:none}
.p58-types label:has(input:checked){background:#e6f3ff;border-color:#398dcc;box-shadow:inset 0 0 0 1px #398dcc}
.p58-help{font-size:11px;color:#58718a;margin:7px 0}
.p58-summary{display:grid;gap:9px;margin-top:13px}
.p58-value{display:flex;justify-content:space-between;gap:13px;border:1px solid #dce7f0;padding:10px 12px;border-radius:9px;color:#3a5b75;font-size:12px}
.p58-value strong{font-size:15px;color:#114c7b}
.p58-total{background:#124779;color:white;border:0;font-size:13px}
.p58-total strong{font-size:22px;color:white}
.p58-actions{display:flex;gap:12px;justify-content:space-between;align-items:center;flex-wrap:wrap;padding:14px 17px;border-top:1px solid #dce7f1;background:#fafcff}
.p58-actions button{border:0;background:#15764d;border-radius:9px;color:#fff;font-weight:800;font-size:13px;min-height:44px;padding:10px 20px;cursor:pointer}
.p58-actions button:disabled{opacity:.5}.p58-warning{padding:10px 13px;border:1px solid #e4c985;background:#fffae9;border-radius:9px;color:#785922;font-size:11px;max-width:540px}
.p58-errors{padding:12px 14px;background:#fff1ef;border-left:4px solid #c34a47;border-radius:9px;margin-bottom:14px;color:#9b3537}
.p58-muted{font-size:11px;color:#64809a}
.p58-hide{display:none!important}
@media(max-width:970px){.p58-body{grid-template-columns:1fr}}
@media(max-width:650px){.p58-types{grid-template-columns:1fr}.p58-grid{grid-template-columns:1fr}.p58-body{padding:10px}.p58-heading h1{font-size:20px}.p58-band{padding:13px}}
</style>
<div class="p58-wrap">
 <div class="p58-heading">
   <div><h1>New Money Receipt</h1><p>Customer Collections · Invoice Settlement · Advance · On Account</p></div>
   <a class="p58-link" href="{{ route('erp.money-receipts.index') }}">← Receipt Register</a>
 </div>
 @if($errors->any())<div class="p58-errors" role="alert"><strong>Receipt not saved — please correct:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
 <form action="{{ route('erp.money-receipts.store') }}" method="post" class="p58-sheet" id="p58-receipt-form">
 @csrf
 <input type="hidden" name="request_key" value="{{ old('request_key',$requestKey) }}">
 <div class="p58-band"><strong>ASSAM MOTORS · MONEY RECEIPT</strong><small>Receipt No. automatically generated upon posting · No GST charged on receipt</small></div>
 <div class="p58-body">
  <div>
   <section class="p58-group">
    <h2>1. Customer and Payment Reference</h2>
    <div class="p58-grid">
     <label class="p58-label">Receipt Date *
       <input type="date" name="receipt_date" value="{{ old('receipt_date',now('Asia/Kolkata')->toDateString()) }}" max="{{ now('Asia/Kolkata')->toDateString() }}" required>
     </label>
     <label class="p58-label">Customer *
       <select name="customer_id" id="p58-customer" required>
        <option value="">— Select Customer —</option>
        @foreach($customers as $c)
          <option value="{{ $c->id }}" @selected((int)old('customer_id',$selectedCustomer)===(int)$c->id)>{{ $c->name }} ({{ $c->customer_code??'CUST' }} · {{ $c->phone??'—' }})</option>
        @endforeach
       </select>
     </label>
     <div class="p58-full">
       <div class="p58-label" style="margin-bottom:7px">Receipt Against *</div>
       <div class="p58-types" role="group" aria-label="Receipt reference type">
        <label><input type="radio" name="reference_type" value="against_reference" @checked(old('reference_type','against_reference')==='against_reference') required> Against Reference (Invoice)</label>
        <label><input type="radio" name="reference_type" value="new_reference" @checked(old('reference_type')==='new_reference')> New Reference / Advance</label>
        <label><input type="radio" name="reference_type" value="on_account" @checked(old('reference_type')==='on_account')> On Account</label>
       </div>
       <p class="p58-help" id="p58-mode-help">Invoice ke against receive ki gayi amount Invoice Balance se adjust hogi.</p>
     </div>
     <div class="p58-full" id="p58-invoice-group">
      <label class="p58-label">Against Invoice Reference *
       <select name="invoice_id" id="p58-invoice">
        <option value="">— Choose Customer Invoice —</option>
        @foreach($invoices as $i)
        <option data-customer="{{ $i->customer_id }}" data-balance="{{ number_format((float)($i->balance_due??$i->balance_total??0),2,'.','') }}" value="{{ $i->id }}"
          @selected((int)old('invoice_id',$selectedInvoice)===(int)$i->id)>{{ $i->invoice_no }} · {{ $i->vehicle_reg_no??'' }} · Due ₹{{ number_format((float)($i->balance_due??$i->balance_total??0),2) }}</option>
        @endforeach
       </select>
      </label>
      <p class="p58-muted">Only matching customer invoices are selectable. Server rechecks balance and posted allocations before saving.</p>
     </div>
     <div class="p58-full p58-hide" id="p58-new-reference-group">
      <div class="p58-grid">
       <label class="p58-label">New Reference Number *
        <input name="reference_no" id="p58-ref-no" value="{{ old('reference_no') }}" placeholder="ADV-2026-001 / JOB-REF / Customer request" maxlength="100">
       </label>
       <label class="p58-label">Related Job Card (optional)
        <select name="job_card_id" id="p58-job-card">
         <option value="">— Optional Job Card —</option>
         @foreach($jobCards as $jc)
          <option data-customer="{{ $jc->customer_id }}" value="{{ $jc->id }}" @selected((int)old('job_card_id')===(int)$jc->id)>{{ $jc->job_no }} · {{ $jc->vehicle_reg_no }}</option>
         @endforeach
        </select>
       </label>
      </div>
     </div>
     <div class="p58-full p58-hide" id="p58-on-account-group">
       <div class="p58-warning">On Account payment customer credit mein save hoga. Kisi invoice se automatically adjust nahi hoga; allocation separately authorised hona chahiye.</div>
     </div>
    </div>
   </section>
   <section class="p58-group" style="margin-top:13px">
    <h2>2. Payment Details</h2>
    <div class="p58-grid">
     <label class="p58-label">Payment Mode *
      <select name="payment_mode" id="p58-mode" required>
       @foreach(['Cash','UPI','Bank Transfer','Cheque','Card','Cash + Online'] as $mode)
        <option value="{{ $mode }}" @selected(old('payment_mode','Cash')===$mode)>{{ $mode }}</option>
       @endforeach
      </select>
     </label>
     <label class="p58-label">Online / Bank Transaction Reference
       <input name="online_reference" id="p58-bank-ref" maxlength="150" value="{{ old('online_reference') }}" placeholder="UPI Txn / UTR / Cheque No.">
     </label>
     <label class="p58-label">Cash Received (₹) *
       <input type="number" id="p58-cash" name="cash_amount" min="0" step=".01" value="{{ old('cash_amount','0.00') }}" required>
     </label>
     <label class="p58-label">Online / Bank Received (₹) *
       <input type="number" id="p58-online" name="online_amount" min="0" step=".01" value="{{ old('online_amount','0.00') }}" required>
     </label>
     <label class="p58-label p58-full">Narration / Notes
       <textarea name="notes" maxlength="1500" placeholder="Payment details or customer reference">{{ old('notes') }}</textarea>
     </label>
    </div>
   </section>
  </div>
  <aside>
    <section class="p58-group">
     <h2>3. Receipt Preview</h2>
     <div class="p58-summary">
       <div class="p58-value"><span>Customer</span><b id="p58-sum-customer">Select Customer</b></div>
       <div class="p58-value"><span>Receipt Type</span><b id="p58-sum-type">Against Reference</b></div>
       <div class="p58-value"><span>Invoice Outstanding (display only)</span><b id="p58-sum-due">—</b></div>
       <div class="p58-value"><span>Cash</span><strong id="p58-sum-cash">₹0.00</strong></div>
       <div class="p58-value"><span>Online / Bank</span><strong id="p58-sum-online">₹0.00</strong></div>
       <div class="p58-value p58-total"><span>Amount Received</span><strong id="p58-sum-total">₹0.00</strong></div>
     </div>
     <p class="p58-help">Save creates one Money Receipt and one Customer Ledger CREDIT. Against Reference additionally creates one Invoice Payment Allocation. Preview is informational; server calculation is final.</p>
     <div class="p58-warning"><strong>Do not receive payments against a reversed invoice.</strong> Existing historical receipts are preserved and can be viewed in the Register.</div>
    </section>
  </aside>
 </div>
 <div class="p58-actions">
  <div class="p58-warning">Financial posting is immediate and audited by receipt number, Customer Ledger entry and optional allocation. Confirm customer, amount and payment reference before saving.</div>
  <button id="p58-submit" type="submit">✓ POST MONEY RECEIPT</button>
 </div>
 </form>
</div>
<script>
(function(){
const $=id=>document.getElementById(id);
const form=$('p58-receipt-form'),customer=$('p58-customer'),invoice=$('p58-invoice'),job=$('p58-job-card'),mode=$('p58-mode');
const cash=$('p58-cash'),online=$('p58-online'),reference=$('p58-ref-no');
const fmt=v=>'₹'+(Number(v||0)).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});
const type=()=>document.querySelector('[name=reference_type]:checked')?.value||'against_reference';
function choiceFilter(select){const cust=customer.value; Array.from(select.options).forEach((opt,i)=>{if(i===0)return;const allow=!cust||opt.dataset.customer===cust; opt.hidden=!allow;opt.disabled=!allow;}); if(select.selectedOptions[0]?.disabled)select.value='';}
function refresh(){
 const t=type();choiceFilter(invoice);choiceFilter(job);
 $('p58-invoice-group').classList.toggle('p58-hide',t!=='against_reference');
 $('p58-new-reference-group').classList.toggle('p58-hide',t!=='new_reference');
 $('p58-on-account-group').classList.toggle('p58-hide',t!=='on_account');
 invoice.disabled=t!=='against_reference';invoice.required=t==='against_reference';
 reference.disabled=t!=='new_reference';reference.required=t==='new_reference';
 job.disabled=t!=='new_reference';
 const helps={against_reference:'Invoice ke against amount invoice balance se adjust hogi.',new_reference:'New Reference / Advance customer credit mein rahega, kisi issued invoice ko adjust nahi karega.',on_account:'On Account customer credit hai, koi invoice directly allocate nahi hoga.'};
 $('p58-mode-help').textContent=helps[t];
 $('p58-sum-type').textContent={against_reference:'Against Reference',new_reference:'New Reference / Advance',on_account:'On Account'}[t];
 $('p58-sum-customer').textContent=customer.selectedOptions[0]?.textContent?.trim()||'Select Customer';
 const o=invoice.selectedOptions[0];
 $('p58-sum-due').textContent=t==='against_reference'&&o?.dataset.balance?fmt(o.dataset.balance):'—';
 const cc=Number(cash.value||0),oo=Number(online.value||0);
 $('p58-sum-cash').textContent=fmt(cc);
 $('p58-sum-online').textContent=fmt(oo);
 $('p58-sum-total').textContent=fmt(Math.round((cc+oo)*100)/100);
 if(t==='against_reference'&&o?.dataset.balance&&(cc+oo)>Number(o.dataset.balance)+.001)
   $('p58-submit').title='Receipt amount exceeds displayed invoice outstanding; choose a smaller amount';
 else $('p58-submit').title='';
}
function modeChange(){
 const m=mode.value;
 if(m==='Cash'){online.value='0.00';online.readOnly=true;cash.readOnly=false;}
 else if(m==='Cash + Online'){cash.readOnly=false;online.readOnly=false;}
 else {cash.value='0.00';cash.readOnly=true;online.readOnly=false;}
 $('p58-bank-ref').required=m!=='Cash';
 refresh();
}
customer.addEventListener('change',()=>{invoice.value='';job.value='';refresh()});
document.querySelectorAll('[name=reference_type]').forEach(r=>r.addEventListener('change',refresh));
[invoice,job,cash,online,reference].forEach(x=>x.addEventListener('input',refresh));
mode.addEventListener('change',modeChange);
form.addEventListener('submit',function(ev){
 const cc=Math.round(Number(cash.value||0)*100),oo=Math.round(Number(online.value||0)*100);
 if(cc+oo<=0){ev.preventDefault();alert('Positive receipt amount required.');return;}
 if(type()==='against_reference' && !invoice.value){ev.preventDefault();alert('Select Customer Invoice.');return;}
 $('p58-submit').disabled=true;
});
modeChange();refresh();
})();
</script>
@endsection
