@extends('layouts.erp')
@section('title',$inv->invoice_no.' — ASSAM MOTORS')
@section('content')
@php
$balance=(float)($inv->balance_due??$inv->balance_total??0);
$paid=(float)($inv->paid_amount??$inv->paid_total??0);
@endphp
<div class="page-head">
  <div>
    <div class="breadcrumbs"><a href="{{ route('erp.job-cards.show',$job->id) }}">{{ $job->job_no }}</a> / Invoice</div>
    <h1>{{ $inv->invoice_no }}</h1>
    <p>{{ $inv->customer_name }} · {{ $inv->vehicle_reg_no }}</p>
  </div>
  <div class="actions wrap">
    <a class="btn" href="{{ route('erp.job-cards.show',$job->id) }}">← Job Card</a>
    <a class="btn primary" target="_blank" href="{{ route('erp.invoices.print',$inv->id) }}">🖨 Print / Save PDF</a>
    @if($job->customer_id)
      <a class="btn" href="{{ route('erp.customers.show',$job->customer_id) }}">Customer Ledger</a>
    @endif
  </div>
</div>

<div class="summary-grid job-summary">
  <div class="metric"><span>Status</span><strong>{{ strtoupper((string)$inv->status) }}</strong></div>
  <div class="metric"><span>Invoice Date</span><strong class="metric-small">{{ \Carbon\Carbon::parse($inv->invoice_date)->format('d-m-Y') }}</strong></div>
  <div class="metric"><span>Grand Total</span><strong>₹{{ number_format((float)$inv->grand_total,2) }}</strong></div>
  <div class="metric"><span>Balance Due</span><strong>₹{{ number_format($balance,2) }}</strong></div>
</div>

<div class="card section-gap">
  <div class="section-head"><div><h2 class="section-title">Customer & Vehicle</h2><p class="muted">Invoice linked to locked Job Card {{ $job->job_no }}.</p></div></div>
  <div class="info-grid six">
    <div><span>Customer</span><strong>{{ $inv->customer_name }}</strong><br>{{ $inv->customer_phone ?: '—' }}</div>
    <div><span>Vehicle</span><strong>{{ $inv->vehicle_reg_no }}</strong><br>{{ trim(($job->vehicle_make??'').' '.($job->vehicle_model??'')) }}</div>
    <div><span>Job Card</span><strong>{{ $job->job_no }}</strong></div>
    <div><span>Parts</span><strong>₹{{ number_format((float)$inv->parts_total,2) }}</strong></div>
    <div><span>Labour</span><strong>₹{{ number_format((float)$inv->labour_total,2) }}</strong></div>
    <div><span>Paid / Due</span><strong>₹{{ number_format($paid,2) }} / ₹{{ number_format($balance,2) }}</strong></div>
  </div>
</div>

<div class="card table-card section-gap">
  <div class="section-head"><div><h2 class="section-title">Parts</h2></div></div>
  <div class="line-scroll"><table class="data-table"><thead><tr><th>#</th><th>Part</th><th>Part No.</th><th class="num">Qty</th><th class="num">Rate</th><th class="num">GST</th><th class="num">Total</th></tr></thead><tbody>
    @forelse($parts as $p)
      <tr><td>{{ $loop->iteration }}</td><td><strong>{{ $p->part_name }}</strong>@if(!empty($p->part_note))<br><small>{{ $p->part_note }}</small>@endif</td><td>{{ $p->part_number ?: $p->part_code }}</td><td class="num">{{ $p->qty }}</td><td class="num">₹{{ number_format((float)$p->rate,2) }}</td><td class="num">{{ number_format((float)($p->gst_percent??0),2) }}%</td><td class="num">₹{{ number_format((float)$p->total,2) }}</td></tr>
    @empty<tr><td colspan="7" class="empty">No Parts lines.</td></tr>@endforelse
  </tbody></table></div>
</div>

<div class="card table-card section-gap">
  <div class="section-head"><div><h2 class="section-title">Labour / ROT</h2></div></div>
  <div class="line-scroll"><table class="data-table"><thead><tr><th>#</th><th>ROT</th><th>Description</th><th class="num">Std Hrs</th><th class="num">Rate</th><th class="num">GST</th><th class="num">Total</th></tr></thead><tbody>
    @forelse($labour as $l)
      <tr><td>{{ $loop->iteration }}</td><td><strong>{{ $l->rot_code }}</strong></td><td>{{ $l->description }}</td><td class="num">{{ number_format((float)$l->standard_hours,2) }}</td><td class="num">₹{{ number_format((float)$l->labour_rate,2) }}</td><td class="num">{{ number_format((float)($l->gst_percent??0),2) }}%</td><td class="num">₹{{ number_format((float)$l->total,2) }}</td></tr>
    @empty<tr><td colspan="7" class="empty">No Labour lines.</td></tr>@endforelse
  </tbody></table></div>
</div>

<div class="card section-gap">
  <div class="section-head"><div><h2 class="section-title">Invoice Totals</h2></div></div>
  <div class="info-grid six">
    <div><span>Subtotal</span><strong>₹{{ number_format((float)($inv->subtotal??(($inv->parts_total??0)+($inv->labour_total??0))),2) }}</strong></div>
    <div><span>GST</span><strong>₹{{ number_format((float)($inv->gst_total??$inv->tax_total??0),2) }}</strong></div>
    <div><span>Pre Round</span><strong>₹{{ number_format((float)($inv->pre_round_total??$inv->grand_total),2) }}</strong></div>
    <div><span>Round Off</span><strong>₹{{ number_format((float)($inv->round_off??0),2) }}</strong></div>
    <div><span>Grand Total</span><strong>₹{{ number_format((float)$inv->grand_total,2) }}</strong></div>
    <div><span>Balance</span><strong>₹{{ number_format($balance,2) }}</strong></div>
  </div>
</div>

<div class="card table-card section-gap">
  <div class="section-head"><div><h2 class="section-title">Customer Ledger Entries for this Invoice</h2></div></div>
  <div class="line-scroll"><table class="data-table"><thead><tr><th>Date</th><th>Type</th><th>Reference</th><th>Narration</th><th class="num">Debit</th><th class="num">Credit</th></tr></thead><tbody>
    @forelse($ledger as $e)
      <tr><td>{{ CarbonCarbon::parse($e->entry_date ?: $e->created_at)->format('d-m-Y') }}</td><td>{{ $e->entry_type }}</td><td>{{ $e->reference_no }}</td><td>{{ $e->narration ?: $e->note }}</td><td class="num">₹{{ number_format((float)($e->debit??0),2) }}</td><td class="num">₹{{ number_format((float)($e->credit??0),2) }}</td></tr>
    @empty<tr><td colspan="6" class="empty">No ledger entries.</td></tr>@endforelse
  </tbody></table></div>
</div>
@endsection
