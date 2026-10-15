@extends('layouts.erp')
@section('title','Payment Voucher Corrections — ASSAM MOTORS')
@section('content')
@include('workshop-controls.enterprise-theme')
<div class="am-modern">@include('workshop-controls.enterprise-navigation')<main class="am-content">
<div class="page-head">
  <div><span class="am-eyebrow">FINANCE / PAYABLES REGISTER</span><div class="breadcrumbs">Accounts / Payment Vouchers</div><h1>Payment Voucher Register</h1><p>Search historical vouchers and correct amount/date/mode with a reason.</p></div>
  <div class="actions"><a class="btn" href="{{ route('erp.payments.index') }}">← Payment Register</a><a class="btn primary" href="{{ route('erp.payments.create') }}">+ Create Voucher</a></div>
</div>
<form method="get" aria-label="Search payment vouchers" class="card am-surface" style="padding:14px;display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px">
  <input type="search" name="q" placeholder="Search voucher, payee, source or reference" value="{{ $term }}" style="flex:1;min-width:230px">
  <button class="btn primary">Search</button><a class="btn" href="{{ route('erp.fix49.payment-register') }}">Reset</a>
</form>
<div class="card table-card am-surface"><div class="am-toolbar" style="padding:17px 17px 0"><h2>Recorded Payments</h2><span class="am-chip am-chip--ok">Audited corrections</span></div><div class="line-scroll"><table class="data-table">
<thead><tr><th>Voucher No</th><th>Date</th><th>Source</th><th>Reference</th><th>Payment Mode</th><th class="num">Amount</th><th>Status</th><th>Action</th></tr></thead>
<tbody>
@forelse($rows as $p)
<tr><td>{{ $p->voucher_no??$p->payment_no??('#'.$p->id) }}</td><td>{{ substr((string)$p->payment_date,0,10) }}</td>
<td>{{ $p->source_type??'—' }}</td><td>{{ $p->source_reference??'—' }}</td>
<td>{{ $p->payment_mode??'—' }}</td><td class="num">₹{{ number_format((float)$p->amount,2) }}</td>
<td><span class="am-chip {{ strtoupper((string)($p->record_status??'ACTIVE'))==='ACTIVE'?'am-chip--ok':'am-chip--hold' }}">{{ $p->record_status??'ACTIVE' }}</span></td><td><a class="btn small" href="{{ route('erp.fix49.payment-edit',$p->id) }}">Edit Voucher →</a></td></tr>
@empty<tr><td colspan="8">No matching vouchers.</td></tr>@endforelse
</tbody></table></div></div>
<div style="margin-top:12px">{{ $rows->links() }}</div>
</main></div>
@endsection