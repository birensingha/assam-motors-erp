<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Expense Category Master — Assam Motors</title>
<style>body{margin:0;background:#f2f5f9;color:#172b42;font:14px Arial,sans-serif}main{max-width:1040px;margin:24px auto;padding:16px}header{display:flex;align-items:center;justify-content:space-between}.panel{background:#fff;border:1px solid #dae2e9;border-radius:10px;padding:18px;margin:14px 0}label{display:block;font-size:12px;font-weight:700}input,select{margin:5px 0 12px;padding:9px;box-sizing:border-box;border:1px solid #bfcddd;border-radius:5px;width:100%;font:inherit}.grid{display:grid;grid-template-columns:2fr 2fr 1fr 1fr;gap:12px}.primary{background:#145aa1;color:white;border:0;padding:10px 18px;border-radius:5px;cursor:pointer}a{color:#145aa1}.message{background:#e8f4eb;padding:10px}.error{color:#a00}@media(max-width:650px){.grid{grid-template-columns:1fr}}</style></head>
<body><main><header><h2>Expense Category Master</h2><a href="{{ route('erp.expenses.create') }}">Back to Expense Voucher</a></header>
@if(session('success'))<p class="message">{{ session('success') }}</p>@endif
@if($errors->any())<div class="error"><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
<section class="panel"><h3>+ New Expense Category</h3>
<form method="post" action="{{ route('erp.expense-categories.store') }}">@csrf
<div class="grid"><label>Category Name<input name="name" maxlength="140" required value="{{ old('name') }}" placeholder="e.g. Donation"></label>
<label>Ledger / Account Name<input name="ledger_name" maxlength="140" required value="{{ old('ledger_name') }}" placeholder="Donation Expense"></label>
<label>Description<input name="description" maxlength="1000" value="{{ old('description') }}"></label><div><button class="primary" style="margin-top:20px">Add Category</button></div></div></form></section>
@foreach($categories as $category)
<section class="panel"><form method="post" action="{{ route('erp.expense-categories.update',$category->id) }}">@csrf
<div class="grid"><label>Name<input name="name" value="{{ $category->name }}" required></label><label>Ledger Name<input name="ledger_name" value="{{ $category->ledger_name }}" required></label>
<label>Status<select name="is_active"><option value="1" @selected($category->is_active)>Active</option><option value="0" @selected(!$category->is_active)>Inactive</option></select></label>
<div><button class="primary" style="margin-top:20px">Save Changes</button></div></div><label>Notes<input name="description" value="{{ $category->description }}"></label><small>Code: {{ $category->code }} — previous voucher history is retained</small>
</form></section>
@endforeach
</main></body></html>
