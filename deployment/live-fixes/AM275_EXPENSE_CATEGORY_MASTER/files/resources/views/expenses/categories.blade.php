@extends('layouts.erp')
@section('title','Expense Category Settings · Assam Motors')
@section('topbar_title','Configuration / Expense Categories')
@section('content')
{{-- AM275_EXPENSE_CATEGORY_MASTER: configurable categories, permanent codes and audited changes. --}}
<style>
.am275{max-width:1490px;margin:0 auto;padding-bottom:30px;color:#184363}
.am275 *{box-sizing:border-box}.am275 .pagehead{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;margin:5px 0 14px}
.am275 h1{font-size:24px;margin:2px 0 4px;letter-spacing:-.02em}.am275 h2{font-size:16px;margin:0 0 12px}
.am275 .muted{font-size:11px;color:#627e91;line-height:1.6}.am275 .panel{background:#fff;border:1px solid #d7e5ef;border-radius:11px;padding:17px;margin:13px 0}
.am275 .newgrid{display:grid;grid-template-columns:minmax(160px,1.5fr) minmax(145px,1fr) minmax(180px,1.8fr) auto;gap:12px;align-items:end}
.am275 label{font-size:11px;display:flex;flex-direction:column;gap:5px;font-weight:800;color:#315c79}
.am275 input,.am275 select{min-height:38px;width:100%;border:1px solid #c3d7e4;border-radius:7px;padding:7px 9px;color:#143e5b;background:#fff;font-size:12px}
.am275 .button{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;border:1px solid #b9cfe0;border-radius:8px;background:#fff;color:#175575;padding:10px 14px;min-height:38px;font-size:11px;font-weight:850;cursor:pointer}
.am275 .button.primary{color:#fff;background:#116ea6;border-color:#116ea6}
.am275 .category{display:grid;grid-template-columns:135px minmax(145px,1.3fr) minmax(145px,1fr) 110px 85px minmax(155px,1.3fr) auto;gap:12px;align-items:end;border-top:1px solid #e1ebf2;padding:10px 0}
.am275 .code{color:#134d79;font:750 11px ui-monospace,Consolas,monospace;overflow-wrap:anywhere;padding-bottom:10px}
.am275 .code small{display:block;color:#738b99;margin-top:3px;font:normal 10px Inter,Arial}
.am275 .check{flex-direction:row;gap:7px;align-items:center;justify-content:center;padding-bottom:10px}
.am275 .check input{width:17px;height:17px;min-height:17px}
.am275 .hint{background:#f0f7fc;border:1px solid #d1e4f2;border-radius:8px;padding:11px 13px;margin:12px 0;font-size:11px;line-height:1.7}
.am275 details summary{cursor:pointer;color:#176297;font-weight:800;font-size:12px}
.am275 table{width:100%;border-collapse:collapse;margin-top:10px;font-size:11px}.am275 th,.am275 td{padding:8px;border-bottom:1px solid #e2edf4;text-align:left;vertical-align:top}
.am275 th{background:#ebf4fa;font-size:10px}.am275 .error{padding:10px;border-radius:8px;background:#fff1f0;color:#a12d2d}
@media(max-width:1150px){.am275 .category{grid-template-columns:repeat(3,minmax(0,1fr))}.am275 .code{padding-top:16px}.am275 .newgrid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:600px){.am275 .category,.am275 .newgrid{grid-template-columns:1fr}.am275 .check{justify-content:start}.am275 .code{padding-bottom:0}}
</style>
<div class="am275">
 <div class="pagehead">
   <div>
     <p class="muted" style="color:#127c96;font-weight:900;letter-spacing:.06em">ASSAM MOTORS · FINANCE CONFIGURATION</p>
     <h1>Expense Categories — Add / Edit</h1>
     <p class="muted">Create category for Donation or any workshop expense. Names, Direct/Indirect classification and Active status can be updated without rewriting saved transactions.</p>
   </div>
   <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a class="button" href="{{ route('erp.configuration') }}">← Configuration</a>
      <a class="button" href="{{ route('erp.expenses.create') }}">New Expense Voucher</a>
   </div>
 </div>
 @if($errors->any())
   <div class="error"><strong>Please correct:</strong>
     <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
   </div>
 @endif
 <div class="hint">
  <strong>Donation:</strong> Default category is <strong>DONATION</strong>, under <strong>Indirect Expenses</strong>. For a normal donation, use an Expense Voucher against GENERAL / REFERENCE, record the payee and receipt reference, and pay by Cash/Bank as appropriate. Don't claim input GST credit merely because a donation was paid.
  <div><strong>History protection:</strong> Category Code is permanent. Rename changes only the displayed category name; existing Expense Vouchers keep their original code. An in-use category can be deactivated for new entries without deleting the old ledger.</div>
 </div>
 <section class="panel">
   <h2>＋ Add New Expense Category</h2>
   <form method="post" action="{{ route('erp.expense-categories.store') }}" class="newgrid">
     @csrf
     <label>Category Name *
       <input type="text" name="display_name" maxlength="100" value="{{ old('display_name') }}" placeholder="Example: Donation / Vehicle Parking / Repairs" required>
     </label>
     <label>Account Group *
       <select name="ledger_group">
         <option value="INDIRECT_EXPENSES">Indirect Expenses</option>
         <option value="DIRECT_EXPENSES" @selected(old('ledger_group')==='DIRECT_EXPENSES')>Direct Expenses</option>
       </select>
     </label>
     <label>Creation Reason / Narration *
       <input name="reason" type="text" minlength="8" maxlength="400" required placeholder="Why is this expense category needed?">
     </label>
     <button class="button primary" type="submit">Add Category</button>
   </form>
 </section>
 <section class="panel">
   <h2>Manage Categories <span class="muted">({{ $categories->count() }} total)</span></h2>
   <p class="muted">Edit any category below, enter a reason, then save. Unchecking Active hides it from new Expense Voucher selection, not historical reports.</p>
   @foreach($categories as $category)
     <form class="category" method="post" action="{{ route('erp.expense-categories.update',['category'=>$category->id]) }}">
       @csrf
       <div class="code">{{ $category->code }}
         <small>{{ $category->voucher_count }} saved voucher(s)</small>
       </div>
       <label>Display Name
         <input name="display_name" maxlength="100" required value="{{ $category->display_name }}">
       </label>
       <label>Account Group
         <select name="ledger_group">
           <option value="INDIRECT_EXPENSES" @selected($category->ledger_group==='INDIRECT_EXPENSES')>Indirect Expenses</option>
           <option value="DIRECT_EXPENSES" @selected($category->ledger_group==='DIRECT_EXPENSES')>Direct Expenses</option>
         </select>
       </label>
       <label class="check">
          <input name="is_active" value="1" type="checkbox" @checked($category->is_active) @disabled($category->code==='OTHER')>
          Active
       </label>
       @if($category->code==='OTHER')<input type="hidden" name="is_active" value="1">@endif
       <div class="muted" style="font-weight:800;padding-bottom:10px">
         {{ $category->is_system?'Built-in':'Custom' }}
       </div>
       <label>Reason for Edit
         <input name="reason" type="text" minlength="8" maxlength="400" required placeholder="Required if saving changes">
       </label>
       <button class="button" type="submit">Save</button>
     </form>
   @endforeach
 </section>
 <section class="panel">
   <details>
     <summary>Category Change Audit — {{ $audits->count() }} recent events</summary>
     <div style="overflow-x:auto">
       <table><thead><tr><th>Date / Time</th><th>Category</th><th>Action</th><th>Changed By</th><th>Reason</th><th>Before / After</th></tr></thead>
       <tbody>
       @forelse($audits as $audit)
         <tr>
           <td>{{ $audit->created_at }}</td><td>{{ $audit->code }}</td>
           <td>{{ $audit->action }}</td><td>{{ $audit->actor }}</td>
           <td>{{ $audit->reason }}</td>
           <td><details><summary>Show changes</summary>
             <div class="muted" style="word-break:break-word">Before: {{ $audit->before_json??'NEW' }}</div>
             <div class="muted" style="word-break:break-word">After: {{ $audit->after_json }}</div>
           </details></td>
         </tr>
       @empty
         <tr><td colspan="6">No category edits yet.</td></tr>
       @endforelse
       </tbody></table>
     </div>
   </details>
 </section>
</div>
@endsection
