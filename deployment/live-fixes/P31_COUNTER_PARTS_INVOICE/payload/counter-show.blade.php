@extends('layouts.erp')
@section('title',$inv->invoice_no.' · Direct Parts Invoice')
@section('content')
{{-- P31_COUNTER_PARTS_INVOICE_SHOW --}}
<div style="max-width:1240px;margin:auto">
 <div class="page-head">
  <div><div class="breadcrumbs"><a href="{{ route('erp.counter-sales.index') }}">Counter Sales</a> / Invoice</div>
   <h1>{{ $inv->invoice_no }}</h1>
   <p>Direct Parts Sale · No Job Card · {{ $inv->customer_name }} · {{ date('d-m-Y',strtotime($inv->invoice_date)) }}</p>
  </div>
  <div class="actions"><a class="btn primary" href="{{ route('erp.invoices.print',$inv->id) }}" target="_blank">🖨 Print / Save PDF</a>
   <a class="btn" href="{{ route('erp.counter-sales.create') }}">+ New Parts Sale</a>
   <a class="btn" href="{{ route('erp.counter-sales.index') }}">Register</a></div>
 </div>
 @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
 <div class="card section-gap">
  <div class="section-head"><h2 class="section-title">Customer / Billing</h2><span>COUNTER PARTS SALE · {{ strtoupper($inv->status) }}</span></div>
  <div class="form-grid header-grid">
   <div><span class="muted">Customer</span><strong style="display:block">{{ $inv->customer_name }}</strong><small>{{ $inv->customer_phone ?: '—' }}</small></div>
   <div><span class="muted">Invoice Date</span><strong style="display:block">{{ date('d-m-Y',strtotime($inv->invoice_date)) }}</strong></div>
   <div><span class="muted">Grand Total</span><strong style="display:block">₹{{ number_format((float)$inv->grand_total,2) }}</strong></div>
   <div><span class="muted">Paid / Due</span><strong style="display:block">₹{{ number_format((float)$inv->paid_amount,2) }} / ₹{{ number_format((float)$inv->balance_due,2) }}</strong></div>
  </div>
 </div>
 <div class="card section-gap">
  <div class="section-head"><h2 class="section-title">Sold Parts — Stock Issued</h2></div>
  <div class="line-scroll"><table class="data-table">
   <thead><tr><th>#</th><th>Part</th><th>HSN</th><th class="num">Qty</th><th class="num">Rate</th><th class="num">Disc %</th><th class="num">GST %</th><th class="num">Line Total</th></tr></thead><tbody>
   @foreach($lines as $l)<tr>
    <td>{{ $loop->iteration }}</td>
    <td><strong>{{ $l->part_name }}</strong><br><small>{{ $l->part_code }} @if($l->note)· {{ $l->note }}@endif</small></td>
    <td>{{ $l->hsn_code ?: '—' }}</td>
    <td class="num">{{ number_format((float)$l->qty,3) }} {{ $l->unit }}</td>
    <td class="num">₹{{ number_format((float)$l->rate,2) }}</td>
    <td class="num">{{ number_format((float)$l->discount_percent,2) }}%</td>
    <td class="num">{{ number_format((float)$l->gst_percent,2) }}%</td>
    <td class="num">₹{{ number_format((float)$l->total_amount,2) }}</td>
   </tr>@endforeach
   </tbody>
  </table></div>
 </div>
 <div class="card section-gap">
  <div class="section-head"><h2 class="section-title">Invoice Totals</h2></div>
  <div class="form-grid header-grid">
   <div><span class="muted">Taxable</span><strong style="display:block">₹{{ number_format((float)$inv->subtotal,2) }}</strong></div>
   <div><span class="muted">GST</span><strong style="display:block">₹{{ number_format((float)$inv->gst_total,2) }}</strong></div>
   <div><span class="muted">Round Off</span><strong style="display:block">₹{{ number_format((float)$inv->round_off,2) }}</strong></div>
   <div><span class="muted">Grand Total</span><strong style="display:block">₹{{ number_format((float)$inv->grand_total,2) }}</strong></div>
  </div>
 </div>
 <div class="card section-gap">
  <div class="section-head"><h2 class="section-title">Customer Ledger Entries</h2><p>Payments can be recorded through existing Customer Receipt / Payment module against this Invoice.</p></div>
  <div class="line-scroll"><table class="data-table"><thead><tr><th>Date</th><th>Reference</th><th>Entry</th><th class="num">Debit</th><th class="num">Credit</th></tr></thead><tbody>
    @foreach($ledger as $e)<tr>
     <td>{{ date('d-m-Y',strtotime($e->entry_date ?: $e->created_at)) }}</td>
     <td>{{ $e->reference_no }}</td><td>{{ $e->narration ?: $e->note }}</td>
     <td class="num">₹{{ number_format((float)$e->debit,2) }}</td><td class="num">₹{{ number_format((float)$e->credit,2) }}</td>
    </tr>@endforeach
   </tbody></table></div>
 </div>
</div>
@endsection
