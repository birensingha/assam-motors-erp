@extends('layouts.erp')
@section('title','Counter Parts Invoices — Assam Motors')
@section('content')
{{-- P31_COUNTER_PARTS_INVOICE_REGISTER --}}
<div style="max-width:1250px;margin:auto">
 <div class="page-head">
  <div><div class="breadcrumbs"><a href="{{ route('erp.dashboard') }}">Dashboard</a> / Parts Counter</div>
   <h1>Direct Parts Sale Invoices</h1>
   <p>Parts sale without opening any Job Card. Posted invoices are linked to Customer Ledger.</p>
  </div>
  <a class="btn primary" href="{{ route('erp.counter-sales.create') }}">+ New Counter Parts Invoice</a>
 </div>
 @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
 <div class="card section-gap">
  <div class="section-head"><h2 class="section-title">Counter Invoice Register</h2><span class="muted">Recent 150 documents</span></div>
  <div class="line-scroll"><table class="data-table">
   <thead><tr><th>Invoice No</th><th>Date</th><th>Customer</th><th>Status</th><th class="num">Grand Total</th><th class="num">Balance Due</th><th>Actions</th></tr></thead>
   <tbody>
    @forelse($rows as $invoice)
     <tr>
      <td><strong>{{ $invoice->invoice_no }}</strong></td>
      <td>{{ $invoice->invoice_date ? date('d-m-Y',strtotime($invoice->invoice_date)) : '—' }}</td>
      <td>{{ $invoice->customer_name }}</td><td>{{ strtoupper((string)$invoice->status) }}</td>
      <td class="num">₹{{ number_format((float)$invoice->grand_total,2) }}</td>
      <td class="num">₹{{ number_format((float)$invoice->balance_due,2) }}</td>
      <td><a class="btn small primary" href="{{ route('erp.invoices.show',$invoice->id) }}">View</a>
       <a class="btn small" href="{{ route('erp.invoices.print',$invoice->id) }}" target="_blank">Print / PDF</a></td>
     </tr>
    @empty
      <tr><td colspan="7" class="empty">No Direct Parts Invoices yet. Click New Counter Parts Invoice to begin.</td></tr>
    @endforelse
   </tbody>
  </table></div>
 </div>
</div>
@endsection
