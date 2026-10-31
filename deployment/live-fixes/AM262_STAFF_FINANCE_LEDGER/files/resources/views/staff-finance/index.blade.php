@extends('layouts.erp')
@section('title','Staff Financial Ledger · ASSAM MOTORS')
@section('topbar_title','Staff Financial Ledger')
@section('content')
{{-- AM262_STAFF_FINANCE_LEDGER: accounting subledger, explicit posting and separate existing expenses --}}
<style>
.am262{max-width:1450px;margin:auto;color:#153f67}
.am262 *{box-sizing:border-box}
.am262-top{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px}
.am262-top h1{margin:0;color:#113b69;font-size:23px}
.am262-top p{margin:5px 0;font-size:12px;color:#5d7690}
.am262 .am262-card{background:white;border:1px solid #d5e5f2;border-radius:11px;padding:15px;margin-bottom:13px}
.am262 .am262-heading{font-size:13px;font-weight:850;margin:0 0 12px;color:#12456f}
.am262 form.am262-pick{display:grid;grid-template-columns:minmax(230px,2fr) minmax(140px,1fr) auto;align-items:end;gap:9px}
.am262 label{display:flex;flex-direction:column;gap:5px;font-size:11px;font-weight:750;color:#294d70;min-width:0}
.am262 input,.am262 select,.am262 textarea{width:100%;border:1px solid #b7ccdf;border-radius:6px;background:white;color:#1c4363;min-height:36px;padding:8px;font-size:12px}
.am262 .am262-btn{border:1px solid #c1d4e8;background:#f5f9ff;border-radius:6px;padding:9px 12px;color:#144475;font-size:11px;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;min-height:36px}
.am262 .am262-primary{background:#134b80;color:white;border-color:#134b80}
.am262 .am262-cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:13px}
.am262 .am262-metric{background:#fff;border:1px solid #d5e5f2;border-radius:10px;padding:13px}
.am262 .am262-metric small{color:#55728d;font-size:10px;display:block;font-weight:800}
.am262 .am262-metric strong{font-size:19px;color:#103c67;display:block;margin-top:6px;font-variant-numeric:tabular-nums}
.am262 .am262-metric span{color:#668198;font-size:10px}
.am262 .am262-two{display:grid;grid-template-columns:minmax(310px,0.82fr) minmax(0,1.18fr);gap:13px}
.am262 .am262-inputgrid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.am262 .am262-full{grid-column:1/-1}
.am262 .am262-account{font-size:11px;color:#335b7b;padding:10px;border-radius:7px;background:#eef5fc;margin:8px 0}
.am262 .am262-note{font-size:11px;color:#5a7287;line-height:1.6;margin:9px 0}
.am262 .am262-tablewrap{overflow-x:auto}
.am262 table{width:100%;border-collapse:collapse;min-width:750px;font-size:11px}
.am262 th{background:#eaf3fb;color:#244e71;font-size:10px;text-align:left;padding:9px;white-space:nowrap}
.am262 td{padding:9px;border-bottom:1px solid #e7eff6;vertical-align:top}
.am262 .am262-money{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
.am262 .am262-tag{display:inline-block;background:#eaf3fb;color:#1e5780;font-size:9px;border-radius:12px;padding:3px 7px;font-weight:800}
.am262 .am262-linked{background:#edf7ed;color:#32724a}
.am262 .am262-red{color:#9e2e30}
.am262 .am262-alert{border:1px solid #e2b7b2;background:#fdf1ef;color:#8c2a29;padding:11px;border-radius:7px;margin-bottom:12px;font-size:12px}
.am262 .am262-success{border:1px solid #afd8c1;background:#ecf9f0;color:#246b4a;padding:11px;border-radius:7px;margin-bottom:12px;font-size:12px}
.am262 .am262-blank{padding:24px 12px;text-align:center;color:#6a829a}
@media(max-width:960px){.am262 .am262-cards{grid-template-columns:repeat(2,minmax(0,1fr))}.am262 .am262-two{grid-template-columns:1fr}}
@media(max-width:620px){.am262 form.am262-pick{grid-template-columns:1fr}.am262 .am262-inputgrid{grid-template-columns:1fr}.am262 .am262-full{grid-column:1}.am262 .am262-metric strong{font-size:16px}}
</style>
<div class="am262">
<div class="am262-top">
 <div><h1>Staff Financial Ledger</h1><p>Salary Provision · Salary Advance · Salary Payable · Staff Expenses / Reimbursement</p></div>
 <div style="display:flex;gap:7px;flex-wrap:wrap">
  <a class="am262-btn" href="{{ route('erp.expenses.create') }}">+ Staff Expense Entry</a>
  <a class="am262-btn" href="{{ route('erp.payments.index') }}">Payment Register</a>
 </div>
</div>
@if(session('success'))<div class="am262-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="am262-alert" role="alert"><strong>Please correct:</strong>@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

<section class="am262-card">
 <form method="get" action="{{ route('erp.staff-finance.index') }}" class="am262-pick">
  <label>SELECT EMPLOYEE
    <select name="staff_id" required>
    <option value="">Select Staff Code / Name</option>
    @foreach($people as $person)
    <option value="{{ $person->id }}" @selected((int)request('staff_id')===(int)$person->id)>
    {{ $person->staff_code }} — {{ $person->name }} @if(strtolower((string)$person->status)!=='active') (Inactive) @endif
    </option>
    @endforeach
    </select>
  </label>
  <label>Salary Month<input type="month" name="month" value="{{ $month }}" required></label>
  <button type="submit" class="am262-btn am262-primary">Open Staff Ledger</button>
 </form>
</section>

@if(!$selected)
<div class="am262-card am262-blank">Select an employee above to open their financial ledger and accounting entries. No staff payments are posted by merely opening this page.</div>
@else
<div class="am262-cards">
 <div class="am262-metric"><small>SALARY PAYABLE · LIABILITY</small><strong>₹{{ number_format($state['salary_payable'],2) }}</strong><span>All months · credit balance</span></div>
 <div class="am262-metric"><small>STAFF SALARY ADVANCE · ASSET</small><strong>₹{{ number_format($state['salary_advance'],2) }}</strong><span>Outstanding recoverable from staff</span></div>
 <div class="am262-metric"><small>STAFF EXPENSE PAYABLE</small><strong>₹{{ number_format($expenseDue,2) }}</strong><span>Linked Expense Vouchers less active payments</span></div>
 <div class="am262-metric"><small>{{ $month }} SALARY OUTSTANDING</small><strong>₹{{ number_format($monthPayable,2) }}</strong><span>Available for salary payment / adjustment</span></div>
</div>

<div class="am262-two">
 <section class="am262-card">
  <h2 class="am262-heading">Post Staff Salary / Advance Entry</h2>
  <p class="am262-note">Every posting creates a balanced journal. Existing Expenses and Payment Vouchers are not posted again. Salary analysis settings do not automatically create provisions.</p>
  <form method="post" action="{{ route('erp.staff-finance.store') }}" class="am262-inputgrid" id="am262-post-form">
    @csrf
    <input type="hidden" name="staff_id" value="{{ $selected->id }}">
    <input type="hidden" name="request_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
    <label class="am262-full">Entry Type *
      <select name="entry_type" id="am262-type" required>
        <option value="SALARY_PROVISION" @selected(old('entry_type')==='SALARY_PROVISION')>Salary Provision — Expense Dr / Salary Payable Cr</option>
        <option value="SALARY_ADVANCE" @selected(old('entry_type')==='SALARY_ADVANCE')>Advance Paid — Salary Advance Dr / Cash Bank Cr</option>
        <option value="SALARY_PAYMENT" @selected(old('entry_type')==='SALARY_PAYMENT')>Salary Paid — Salary Payable Dr / Cash Bank Cr</option>
        <option value="ADVANCE_ADJUST" @selected(old('entry_type')==='ADVANCE_ADJUST')>Adjust Advance — Salary Payable Dr / Staff Advance Cr</option>
      </select>
    </label>
    <label>Entry Date *<input type="date" name="entry_date" value="{{ old('entry_date',now('Asia/Kolkata')->toDateString()) }}" required></label>
    <label>Salary Month *<input type="month" name="salary_month" value="{{ old('salary_month',$month) }}" required></label>
    <label class="am262-full">Amount ₹ *<input type="number" name="amount" min="0.01" max="99999999.99" step="0.01" value="{{ old('amount') }}" required placeholder="0.00"></label>
    <label class="am262-payment">Payment Mode
      <select name="payment_mode" id="am262-payment-mode">
        <option value="">Select when paying</option>
        @foreach(['Cash','UPI','Bank Transfer','Cheque','Card'] as $method)
        <option value="{{ $method }}" @selected(old('payment_mode')===$method)>{{ $method }}</option>
        @endforeach
      </select>
    </label>
    <label class="am262-payment">Cash / Bank Account<input name="account_name" id="am262-account-name" value="{{ old('account_name') }}" maxlength="120" placeholder="Cash / HDFC / UPI"></label>
    <label class="am262-full">Reference / Cheque No.<input name="reference_no" value="{{ old('reference_no') }}" maxlength="140"></label>
    <label class="am262-full">Reason / Narration *<textarea name="narration" minlength="6" maxlength="600" required rows="2">{{ old('narration') }}</textarea></label>
    <div class="am262-full am262-account" id="am262-entry-preview">Salary / Staff Salary A/c (Indirect Expenses) Dr → Salary Payable (Current Liabilities) Cr</div>
    <button class="am262-btn am262-primary am262-full" type="submit" onclick="return confirm('Post this audited Staff Journal? Posted amounts can only be reversed, not edited.')">Post Balanced Staff Voucher</button>
  </form>
  <div class="am262-note"><strong>Accounting protection:</strong> Salary payments cannot exceed that month's salary payable. Advance adjustment cannot exceed either salary payable or outstanding advance. Corrections use separately audited reversal entries.</div>
 </section>
 <section class="am262-card">
   <h2 class="am262-heading">Account Classification & Reconciliation</h2>
   <div class="am262-tablewrap">
   <table><thead><tr><th>Account</th><th>Group</th><th>Balance / Treatment</th></tr></thead>
   <tbody>
   <tr><td>Staff Salary A/c</td><td>Indirect Expenses</td><td>Salary cost recognised on provision</td></tr>
   <tr><td>Staff Advance Salary</td><td>Loans & Advances (Asset)</td><td class="am262-money">₹{{ number_format($state['salary_advance'],2) }} Dr</td></tr>
   <tr><td>Salary Payable</td><td>Current Liabilities</td><td class="am262-money">₹{{ number_format($state['salary_payable'],2) }} Cr</td></tr>
   <tr><td>Staff Expense Payable</td><td>Current Liabilities</td><td class="am262-money">₹{{ number_format($expenseDue,2) }} Cr</td></tr>
   </tbody></table>
   </div>
   <p class="am262-note">Salary expense in this subledger: ₹{{ number_format($state['salary_expense'],2) }}. The company's general Profit & Loss / Balance Sheet requires a verified General Ledger integration; this page does not silently update unrelated accounting tables.</p>
   <h2 class="am262-heading">Existing Staff Expense Vouchers</h2>
   <div class="am262-tablewrap"><table><tbody>
   <tr><td>Total recorded staff expenses</td><td class="am262-money">₹{{ number_format($expenseTotal,2) }}</td></tr>
   <tr><td>Active payments against those expenses</td><td class="am262-money">₹{{ number_format($expensePaid,2) }}</td></tr>
   <tr><td><strong>Reimbursement payable</strong></td><td class="am262-money"><strong>₹{{ number_format($expenseDue,2) }}</strong></td></tr>
   </tbody></table></div>
   <a class="am262-btn" href="{{ route('erp.expenses.create') }}" style="margin-top:12px">Record Staff Expense / Provision</a>
 </section>
</div>

<section class="am262-card">
 <h2 class="am262-heading">Staff Ledger — {{ $selected->staff_code }} · {{ $selected->name }}</h2>
 <div class="am262-tablewrap">
 <table>
 <thead><tr><th>Date</th><th>Voucher / Source</th><th>Type</th><th>Narration</th><th>Period</th><th class="am262-money">Amount ₹</th><th>Audit / Action</th></tr></thead>
 <tbody>
 @forelse($rows as $entry)
 <tr>
   <td>{{ $entry->date }}</td>
   <td>{{ $entry->ref }}</td>
   <td><span class="am262-tag {{ $entry->source!=='STAFF_JOURNAL'?'am262-linked':'' }}">{{ str_replace('_',' ',$entry->type) }}</span></td>
   <td style="max-width:260px;white-space:normal">{{ $entry->description }}</td>
   <td>{{ $entry->month ?: '—' }}</td>
   <td class="am262-money">₹{{ number_format($entry->amount,2) }}</td>
   <td>
     @if($entry->can_reverse)
     <details><summary style="cursor:pointer;color:#a0312e">Reverse with reason</summary>
       <form method="post" action="{{ route('erp.staff-finance.reverse',$entry->journal_id) }}" style="min-width:180px;margin-top:7px">
         @csrf
         <input type="hidden" name="request_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
         <input name="reason" minlength="8" maxlength="300" required placeholder="Mandatory reversal reason">
         <button type="submit" class="am262-btn" style="margin-top:6px" onclick="return confirm('Post an opposite journal? The original entry will remain in history.')">Post Reversal</button>
       </form>
     </details>
     @elseif($entry->source==='EXISTING_EXPENSE')
       <span class="am262-linked am262-tag">Existing Expense</span>
     @elseif($entry->source==='EXISTING_PAYMENT')
       <span class="am262-linked am262-tag">Existing Payment</span>
     @else
       <span style="color:#677e91">Audited / Reversed</span>
     @endif
   </td>
 </tr>
 @empty
 <tr><td colspan="7" class="am262-blank">No staff-linked accounting entries found.</td></tr>
 @endforelse
 </tbody>
 </table></div>
 <p class="am262-note">Historical Expense / Payment source references are displayed once, not re-posted. Posted Staff Journal vouchers are immutable; reversal creates equal and opposite debit/credit lines.</p>
</section>
@endif
</div>
<script>
(function(){
 const select=document.getElementById('am262-type');
 const summary=document.getElementById('am262-entry-preview');
 const mode=document.getElementById('am262-payment-mode');
 const account=document.getElementById('am262-account-name');
 if(!select||!summary)return;
 const rules={
 SALARY_PROVISION:'Staff Salary (Indirect Expenses) Dr → Salary Payable (Current Liabilities) Cr',
 SALARY_ADVANCE:'Staff Salary Advance (Loans & Advances Asset) Dr → Cash / Bank Cr',
 SALARY_PAYMENT:'Salary Payable (Current Liabilities) Dr → Cash / Bank Cr',
 ADVANCE_ADJUST:'Salary Payable (Current Liabilities) Dr → Staff Advance (Loans & Advances Asset) Cr'
 };
 function update(){
  summary.textContent=rules[select.value]||'Select entry type';
  const pay=['SALARY_ADVANCE','SALARY_PAYMENT'].includes(select.value);
  document.querySelectorAll('.am262-payment').forEach(el=>{el.hidden=!pay;});
  if(mode){mode.required=pay;mode.disabled=!pay;}
  if(account){account.required=pay;account.disabled=!pay;}
 }
 select.addEventListener('change',update);update();
})();
</script>
@endsection
