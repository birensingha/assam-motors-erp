@extends('layouts.erp')
@section('title','Payment Voucher Corrections — ASSAM MOTORS')
@section('content')
<div class="page-head">
  <div><div class="breadcrumbs">Accounts / Payment Vouchers</div><h1>Payment Voucher Register — Edit</h1><p>Search historical vouchers and correct amount/date/mode with a reason.</p></div>
  <div class="actions"><a class="btn" href="{{ route('erp.payments.index') }}">← Payment Register</a><a class="btn primary" href="{{ route('erp.payments.create') }}">+ Create Voucher</a></div>
</div>
<form method="get" class="card" style="padding:14px;display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px">
  <input type="search" name="q" placeholder="Search voucher, payee, source or reference" value="{{ $term }}" style="flex:1;min-width:230px">
  <button class="btn primary">Search</button><a class="btn" href="{{ route('erp.fix49.payment-register') }}">Reset</a>
</form>
<div class="card table-card"><div class="line-scroll"><table class="data-table">
<thead><tr><th>Voucher No</th><th>Date</th><th>Source</th><th>Reference</th><th>Payment Mode</th><th class="num">Amount</th><th>Status</th><th>Action</th></tr></thead>
<tbody>
@forelse($rows as $p)
<tr><td>{{ $p->voucher_no??$p->payment_no??('#'.$p->id) }}</td><td>{{ substr((string)$p->payment_date,0,10) }}</td>
<td>{{ $p->source_type??'—' }}</td><td>{{ $p->source_reference??'—' }}</td>
<td>{{ $p->payment_mode??'—' }}</td><td class="num">₹{{ number_format((float)$p->amount,2) }}</td>
<td>{{ $p->record_status??'ACTIVE' }}</td><td><a class="btn small" href="{{ route('erp.fix49.payment-edit',$p->id) }}">✎ Edit</a></td></tr>
@empty<tr><td colspan="8">No matching vouchers.</td></tr>@endforelse
</tbody></table></div></div>
<div style="margin-top:12px">{{ $rows->links() }}</div>
@endsection