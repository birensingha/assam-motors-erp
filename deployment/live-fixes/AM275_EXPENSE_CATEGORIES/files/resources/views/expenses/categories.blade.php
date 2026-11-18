@extends('layouts.erp')
@section('title','Expense Categories · ASSAM MOTORS')
@section('topbar_title','Expense Category Settings')
@section('content')
{{-- AM275_EXPENSE_CATEGORY_SETTINGS: category label changes never mutate posted Expenses. --}}
<style>
.am275-wrap{display:grid;gap:15px}
.am275-head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap}
.am275-sub{color:#5b7288;font-size:12px;margin-top:5px}
.am275-panel{border:1px solid #d7e5f0;background:#fff;border-radius:11px;padding:16px}
.am275-panel h2{font-size:17px;color:#184468;margin:0 0 11px}
.am275-fields{display:grid;grid-template-columns:minmax(230px,1.4fr) minmax(220px,1.5fr) auto;gap:12px;align-items:end}
.am275-fields label,.am275-table label{display:flex;flex-direction:column;gap:5px;font-size:11px;font-weight:800;color:#466780}
.am275-fields input,.am275-table input,.am275-table select{width:100%;border:1px solid #c6d8e8;padding:8px 9px;border-radius:7px;min-height:36px;color:#173e5d;background:white}
.am275-table{width:100%;border-collapse:collapse;min-width:850px;font-size:12px}
.am275-table th,.am275-table td{border-bottom:1px solid #e2ecf4;padding:10px 8px;text-align:left;vertical-align:middle}
.am275-table th{background:#eef5fb;color:#24547b;font-size:11px;white-space:nowrap}
.am275-table small{font-size:10px;color:#73889a}
.am275-overflow{overflow:auto;border:1px solid #dce7ee;border-radius:9px}
.am275-code{font:700 11px ui-monospace,monospace;color:#184a79}
.am275-warning{border:1px solid #e9d7ad;background:#fff9ec;color:#806326;padding:10px 13px;border-radius:8px;font-size:12px}
.am275-history{width:100%;font-size:11px;border-collapse:collapse}
.am275-history td,.am275-history th{padding:8px;border-bottom:1px solid #edf1f4;text-align:left}
@media(max-width:780px){.am275-fields{grid-template-columns:1fr}}
</style>
<div class="am275-wrap">
 <div class="am275-head">
  <div><h1>Expense Category Settings</h1>
   <p class="am275-sub">Donation, Office, Fuel, Staff Welfare aur apni nayi categories banaiye. Naam edit karein, inactive karein—original voucher records safe rahenge.</p>
  </div>
  <div class="actions">
   <a class="btn" href="{{ route('erp.configuration') }}">← Configuration</a>
   <a class="btn primary" href="{{ route('erp.expenses.create') }}">＋ New Expense Voucher</a>
  </div>
 </div>
 @if($errors->any())
   <div class="am275-warning" role="alert"><strong>Please correct:</strong>
     @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
   </div>
 @endif
 <div class="am275-panel">
  <h2>＋ Add New Expense Category</h2>
  <form method="POST" action="{{ route('erp.expense-categories.create') }}" class="am275-fields">
   @csrf
   <label>Category Name *
    <input type="text" name="display_name" maxlength="100" minlength="2" required
       placeholder="Example: Donation / Charity" value="{{ old('display_name') }}">
   </label>
   <label>Reason / Remarks *
    <input type="text" name="reason" minlength="8" maxlength="300" required
       placeholder="Example: New workshop donation expense head" value="{{ old('reason') }}">
   </label>
   <div><button class="btn primary" type="submit">Create Category</button></div>
  </form>
  <p class="am275-sub">Category code automatically generate hoga. Duplicate code block hoga. System-generated code baad mein badlega nahi, taaki historical report accurate rahe.</p>
 </div>
 <div class="am275-panel">
  <h2>Existing Expense Categories ({{ $categories->count() }})</h2>
  <div class="am275-warning">**Safe Edit Rule:** Category ka visible name aur Active/Inactive status edit hoga; posted Expense ka category code, amount, GST aur payments change nahi honge. Disabled category purane registers mein dikhegi, nayi entry ke liye available nahi hogi. Lubricash Coupon workflow protected hai.</div>
  <div class="am275-overflow" style="margin-top:12px">
    <table class="am275-table">
     <thead><tr><th>Category Code / Used</th><th>Display Name (Editable)</th><th>Status</th><th>Correction Reason</th><th>Action</th></tr></thead>
     <tbody>
      @foreach($categories as $c)
      <tr>
       <td><span class="am275-code">{{ $c->code }}</span>
         <div><small>{{ number_format((int)($usage[$c->code]??0)) }} recorded Expense(s)</small></div>
       </td>
       <td>
        <input aria-label="Display name for {{ $c->code }}" form="am275-edit-{{ $c->id }}"
           type="text" name="display_name" maxlength="100" minlength="2"
           value="{{ $c->display_name }}" required>
       </td>
       <td>
        <select form="am275-edit-{{ $c->id }}" name="is_active" aria-label="Status for {{ $c->code }}">
         <option value="1" @selected($c->is_active)>Active</option>
         <option value="0" @selected(!$c->is_active)>Inactive — Hide for New Entry</option>
        </select>
       </td>
       <td>
        <input form="am275-edit-{{ $c->id }}" type="text" name="reason"
           aria-label="Audit reason for {{ $c->code }}" placeholder="Why is the category changing?"
           minlength="8" maxlength="300" required>
       </td>
       <td>
        <form id="am275-edit-{{ $c->id }}" method="POST"
          action="{{ route('erp.expense-categories.update',['category'=>$c->id]) }}">
          @csrf
          <button class="btn small" type="submit">Save Edit</button>
        </form>
       </td>
      </tr>
      @endforeach
     </tbody>
    </table>
  </div>
 </div>
 <div class="am275-panel">
  <h2>Category Changes / Audit History</h2>
  @if($history->isEmpty())
    <p class="am275-sub">No category edits yet.</p>
  @else
   <div class="am275-overflow">
    <table class="am275-history">
     <thead><tr><th>Date / Time</th><th>Action</th><th>Category Code</th><th>Reason</th><th>Changed By</th></tr></thead>
     <tbody>
      @foreach($history as $h)
       <tr>
        <td>{{ $h->created_at }}</td><td>{{ $h->action }}</td>
        <td class="am275-code">{{ $h->category_code }}</td>
        <td>{{ $h->reason }}</td><td>{{ $h->actor }}</td>
       </tr>
      @endforeach
     </tbody>
    </table>
   </div>
  @endif
 </div>
</div>
@endsection
