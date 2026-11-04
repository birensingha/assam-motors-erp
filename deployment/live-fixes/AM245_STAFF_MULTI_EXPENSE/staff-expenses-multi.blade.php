{{-- AM245_STAFF_MULTI_EXPENSE: one voucher, multiple Staff expense references, no duplicate Expense posting. --}}
@extends('layouts.erp')
@section('title','Pay Multiple Staff Expenses · ASSAM MOTORS ERP')
@section('topbar_title','Payment Voucher')
@section('content')
<style>
.am245{max-width:1300px;margin:0 auto;color:#193955}
.am245-head{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:14px}
.am245-head h1{font-size:23px;color:#103964;margin:0}.am245-head p{color:#63809a;font-size:12px;margin:5px 0 0}
.am245-back{font-size:12px;text-decoration:none;border:1px solid #bad0e3;background:white;color:#174d80;padding:10px 14px;border-radius:8px;font-weight:800}
.am245-card{border:1px solid #d5e2ef;background:white;padding:16px;border-radius:12px;margin-bottom:14px;box-shadow:0 2px 12px rgba(20,62,103,.025)}
.am245-card h2{font-size:14px;color:#153c64;margin:0 0 11px}
.am245-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
.am245 label{font-size:11px;color:#466781;font-weight:750}
.am245 input,.am245 select,.am245 textarea{box-sizing:border-box;width:100%;border:1px solid #bed0e3;border-radius:7px;background:white;color:#173d66;padding:8px;font-size:12px;min-height:35px;display:block;margin-top:5px}
.am245 input:focus,.am245 select:focus,.am245 textarea:focus{outline:2px solid rgba(27,106,189,.20);border-color:#4785c7}
.am245-table-scroll{overflow:auto;border:1px solid #dce7f2;border-radius:10px;max-height:460px}
.am245-table{min-width:910px;width:100%;border-collapse:collapse}
.am245-table th{position:sticky;top:0;background:#eaf2fb;color:#244a71;font-size:10px;padding:11px 8px;text-transform:uppercase;text-align:left;z-index:1}
.am245-table td{border-bottom:1px solid #e6edf6;padding:9px 8px;vertical-align:middle;font-size:12px}
.am245-table tr:hover{background:#f7faff}
.am245-table .num{text-align:right;font-variant-numeric:tabular-nums}
.am245-table td input[type=checkbox]{width:18px;height:18px;min-height:0;margin:auto;accent-color:#165aaa}
.am245-table td input[type=number]{width:112px;margin-left:auto;text-align:right;height:32px}
.am245-table .ref{color:#154b89;font-size:12px;font-weight:800;text-decoration:none}
.am245-table .sub{color:#698098;font-size:10px;margin-top:3px;display:block}
.am245-toolbar{display:flex;gap:10px;align-items:center;justify-content:space-between;margin-bottom:10px;flex-wrap:wrap}
.am245-toolbar span{color:#536f88;font-size:11px}
.am245-btn{border:1px solid #b9cee3;background:#f1f7ff;color:#184b7d;padding:9px 13px;border-radius:8px;font-size:12px;font-weight:800;cursor:pointer}
.am245-btn.primary{background:#1654a3;border-color:#1654a3;color:white}
.am245-btn:disabled{opacity:.5;cursor:not-allowed}
.am245-amount{display:flex;align-items:center;gap:13px;flex-wrap:wrap;margin-top:14px;padding:14px;border:1px solid #c8ddef;background:#eff7ff;border-radius:9px}
.am245-amount label{min-width:210px}
.am245-amount input{font-size:17px;font-weight:800;height:41px}
.am245-amount strong{font-size:12px;color:#1e536f}
.am245-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin-top:12px}
.am245-stat{background:#f8fbfe;border:1px solid #e1e9f1;border-radius:8px;padding:12px}
.am245-stat span{font-size:10px;color:#5f7991;display:block}
.am245-stat strong{font-size:18px;color:#164b7f;display:block;margin-top:3px;font-variant-numeric:tabular-nums}
.am245-help{color:#587690;font-size:11px;line-height:1.55;margin:10px 0 0}
.am245-bottom{display:grid;grid-template-columns:2fr 1fr 1fr;gap:12px}
.am245-save{display:flex;gap:10px;align-items:center;justify-content:flex-end;padding-top:13px}
.am245-feedback{font-size:12px;font-weight:700;color:#995010}
.am245-error{border:1px solid #edbbb8;background:#fff3f2;border-radius:8px;color:#993232;padding:12px;margin-bottom:12px;font-size:12px}
.am245-empty{padding:22px;text-align:center;color:#6d8399;font-size:12px}
@media(max-width:900px){.am245-grid,.am245-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.am245-bottom{grid-template-columns:1fr 1fr}}
@media(max-width:620px){.am245-grid,.am245-summary,.am245-bottom{grid-template-columns:1fr}.am245-head h1{font-size:20px}}
</style>
<div class="am245">
 <div class="am245-head">
  <div><h1>Multiple Staff Expenses — One Payment Voucher</h1>
   <p>Like Vendor multiple-bill settlement: Payment Amount first → select Expenses → allocate → one audited Payment Voucher.</p></div>
  <a class="am245-back" href="{{ route('erp.payments.create') }}">← New Payment Voucher</a>
 </div>
 @if($errors->any())<div class="am245-error" role="alert"><strong>Please correct:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
 <form method="post" action="{{ route('erp.payments.staff-multi.store') }}" id="am245-form" autocomplete="off">
  @csrf
  <input type="hidden" name="payee_category" value="STAFF">
  <input type="hidden" name="staff_multi_expense" value="1">
  <input type="hidden" name="staff_payment_kind" value="EXPENSE_REIMBURSEMENT">
  <input type="hidden" name="staff_payment_against" value="AGAINST">
  <input type="hidden" name="request_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
  <div class="am245-card">
   <h2>1. Payment To · Select Staff</h2>
   <div class="am245-grid">
    <label style="grid-column:span 2">Staff Name *
     <select name="staff_id" id="am245-staff" required><option value="">Select Staff Name / Staff Code</option>
     @foreach($staff as $person)
      <option value="{{ $person->id }}" @selected((int)old('staff_id',$staffId)===(int)$person->id)>{{ $person->staff_code }} — {{ $person->name }}</option>
     @endforeach
     </select>
    </label>
    <label>Payment Date *<input type="date" name="payment_date" value="{{ old('payment_date',now('Asia/Kolkata')->toDateString()) }}" required></label>
    <label>Payment Against<select disabled><option>AGAINST REFERENCE — STAFF EXPENSES</option></select></label>
   </div>
   <div class="am245-amount">
    <label>Amount Paid ₹ *
     <input id="am245-amount" type="number" name="amount" step="0.01" min="0.01" value="{{ old('amount','') }}" placeholder="Enter payment amount first" required>
    </label>
    <button type="button" class="am245-btn" id="am245-auto">↔ Auto Allocate Selected Expenses</button>
    <strong id="am245-staff-due">Select Staff to see outstanding expenses</strong>
   </div>
  </div>
  <div class="am245-card">
   <div class="am245-toolbar">
    <div><h2 style="margin:0">2. Adjust Staff Expense References</h2><span>Select 2 or more Expense Vouchers, or settle a single reference partially</span></div>
    <div><button type="button" class="am245-btn" id="am245-select-all">Select All Outstanding</button>
    <button type="button" class="am245-btn" id="am245-clear">Clear</button></div>
   </div>
   <div class="am245-table-scroll">
    <table class="am245-table">
      <thead><tr><th style="width:34px">✓</th><th>Expense Voucher</th><th>Category / JC</th><th class="num">Total ₹</th><th class="num">Already Paid ₹</th><th class="num">Outstanding ₹</th><th class="num">Allocate ₹</th></tr></thead>
      <tbody id="am245-expenses"><tr><td colspan="7" class="am245-empty">Choose a Staff member to load outstanding expense references.</td></tr></tbody>
    </table>
   </div>
   <div class="am245-summary">
    <div class="am245-stat"><span>Selected Expenses</span><strong id="am245-count">0</strong></div>
    <div class="am245-stat"><span>Selected Outstanding</span><strong id="am245-due">₹0.00</strong></div>
    <div class="am245-stat"><span>Allocated Amount</span><strong id="am245-allocated">₹0.00</strong></div>
    <div class="am245-stat"><span>Unallocated Payment</span><strong id="am245-difference">₹0.00</strong></div>
   </div>
   <p class="am245-help">Example: Three Staff Expenses total ₹3,942. Amount Paid ₹3,000. Tick all three, then Auto Allocate. Each reference gets an allocation and the remaining ₹942 stays outstanding. The Staff receives only one Payment Voucher.</p>
  </div>
  <div class="am245-card">
   <h2>3. Payment Method & Audit</h2>
   <div class="am245-bottom">
    <label>Payment Mode *
      <select name="payment_mode" required><option value="Cash">Cash</option><option value="UPI">UPI</option><option value="Bank Transfer">Bank Transfer</option><option value="Cheque">Cheque</option><option value="Card">Card</option></select>
    </label>
    <label>Account / Cash Book
      <input name="account_name" maxlength="120" placeholder="Cash / HDFC / UPI">
    </label>
    <label>Bank / UPI Reference
      <input name="reference_no" maxlength="140" placeholder="Optional transaction reference">
    </label>
   </div>
   <label style="display:block;margin-top:12px">Narration / Remarks *
    <textarea name="notes" rows="3" maxlength="600" minlength="6" required placeholder="Payment against selected staff expense vouchers...">{{ old('notes','') }}</textarea>
   </label>
   <p class="am245-help">Original Expense entries remain unchanged. This payment is linked by reference allocations, with partial balances and an audited Void/reversal path.</p>
   <div class="am245-save"><span class="am245-feedback" id="am245-feedback" role="status">Select Staff and enter Payment Amount.</span>
     <a class="am245-btn" href="{{ route('erp.payments.create') }}">Cancel</a>
     <button class="am245-btn primary" type="submit" id="am245-post" disabled>✓ Post One Staff Payment Voucher</button>
   </div>
  </div>
 </form>
</div>
<script id="am245-multi-script">
(function(){
'use strict';
const dataset=@json($rows),initial=@json((int)$staffId);
const form=document.getElementById('am245-form'),staff=document.getElementById('am245-staff');
const amount=document.getElementById('am245-amount'),body=document.getElementById('am245-expenses');
const post=document.getElementById('am245-post'),feedback=document.getElementById('am245-feedback');
if(!form||!staff||!body||!post)return;
const cents=v=>Math.round((Number(v)||0)*100);
const money=v=>'₹'+(v/100).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});
function items(){return dataset.filter(x=>String(x.staff_id)===String(staff.value)&&cents(x.outstanding)>0);}
function selected(){return [...body.querySelectorAll('tr[data-id]')].filter(r=>r.querySelector('.am245-check')?.checked);}
function render(){
 body.replaceChildren();
 const due=items();
 document.getElementById('am245-staff-due').textContent=staff.value?due.length+' unpaid Expense Voucher(s) · '+money(due.reduce((s,x)=>s+cents(x.outstanding),0)):'Select Staff to see outstanding expenses';
 if(!due.length){const tr=document.createElement('tr'),td=document.createElement('td');td.colSpan=7;td.className='am245-empty';td.textContent=staff.value?'No unpaid staff Expense Vouchers available.':'Choose Staff to load unpaid expenses.';tr.append(td);body.append(tr);refresh();return;}
 for(const item of due){
   const tr=document.createElement('tr');tr.dataset.id=String(item.id);tr.dataset.due=String(cents(item.outstanding));
   const add=(cls,txt)=>{
     const td=document.createElement('td');if(cls)td.className=cls;td.textContent=txt;tr.append(td);return td;
   };
   const cell=add('', '');
   const tick=document.createElement('input');tick.type='checkbox';tick.className='am245-check';tick.setAttribute('aria-label','Select '+item.expense_no);cell.append(tick);
   const ref=add('','');const a=document.createElement('a');a.href='/erp/expenses/'+item.id;a.target='_blank';a.rel='noopener';a.className='ref';a.textContent=item.expense_no;ref.append(a);
   const date=document.createElement('span');date.className='sub';date.textContent=item.date||'';ref.append(date);
   const category=add('',String(item.category||'OTHER'));
   const context=document.createElement('span');context.className='sub';context.textContent=[item.job_no,item.vehicle].filter(Boolean).join(' · ')||'General';category.append(context);
   add('num',money(cents(item.total)));add('num',money(cents(item.paid)));add('num',money(cents(item.outstanding)));
   const allocation=add('num','');
   const entry=document.createElement('input');entry.type='number';entry.step='0.01';entry.min='0.01';entry.max=Number(item.outstanding).toFixed(2);entry.value='0.00';entry.disabled=true;entry.className='am245-line-allocation';entry.setAttribute('aria-label','Allocation for '+item.expense_no);allocation.append(entry);
   const hidden=document.createElement('input');hidden.type='hidden';hidden.value=String(item.id);hidden.name='allocations['+item.id+'][expense_id]';hidden.disabled=true;tr.append(hidden);
   entry.name='allocations['+item.id+'][amount]';
   tick.addEventListener('change',()=>{entry.disabled=hidden.disabled=!tick.checked;if(!tick.checked)entry.value='0.00';auto();});
   entry.addEventListener('input',refresh);
   body.append(tr);
 }
 refresh();
}
function auto(){
 let remaining=cents(amount.value);
 for(const r of selected()){
   const input=r.querySelector('.am245-line-allocation');
   const allocate=Math.min(remaining,Number(r.dataset.due));
   input.value=(Math.max(0,allocate)/100).toFixed(2);
   remaining=Math.max(0,remaining-allocate);
 }
 refresh();
}
function refresh(){
 const rows=selected(),pay=cents(amount.value);
 const sum=rows.reduce((s,r)=>s+cents(r.querySelector('.am245-line-allocation').value),0);
 const due=rows.reduce((s,r)=>s+Number(r.dataset.due),0);
 const bad=rows.some(r=>cents(r.querySelector('.am245-line-allocation').value)<=0||
             cents(r.querySelector('.am245-line-allocation').value)>Number(r.dataset.due));
 document.getElementById('am245-count').textContent=String(rows.length);
 document.getElementById('am245-due').textContent=money(due);
 document.getElementById('am245-allocated').textContent=money(sum);
 document.getElementById('am245-difference').textContent=money(Math.abs(pay-sum))+(pay<sum?' (OVER)':'');
 const ok=!!staff.value&&pay>0&&rows.length>0&&!bad&&sum===pay;
 post.disabled=!ok;
 feedback.textContent=!staff.value?'Select Staff first':!pay?'Enter Amount Paid first':
   !rows.length?'Tick at least one outstanding Expense Voucher':
   bad?'Each selected allocation must be positive and not exceed outstanding':
   sum!==pay?'Allocation total must equal Amount Paid.':'Ready: '+rows.length+' reference(s), '+money(pay)+', ONE payment voucher.';
}
staff.addEventListener('change',render);
amount.addEventListener('input',auto);
document.getElementById('am245-auto').addEventListener('click',auto);
document.getElementById('am245-select-all').addEventListener('click',()=>{body.querySelectorAll('.am245-check:not(:checked)').forEach(x=>{x.checked=true;x.closest('tr').querySelectorAll('input').forEach(y=>{if(y!==x)y.disabled=false;});});auto();});
document.getElementById('am245-clear').addEventListener('click',()=>{body.querySelectorAll('.am245-check:checked').forEach(x=>{x.checked=false;let r=x.closest('tr');r.querySelector('.am245-line-allocation').value='0.00';r.querySelectorAll('input').forEach(y=>{if(y!==x)y.disabled=true;});});refresh();});
form.addEventListener('submit',e=>{refresh();if(post.disabled){e.preventDefault();feedback.scrollIntoView({block:'center'});return;}post.disabled=true;post.textContent='Posting one voucher...';});
render();
})();
</script>
@endsection
