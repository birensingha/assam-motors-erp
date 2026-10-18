@extends('layouts.erp')
@section('title',$receipt->receipt_no.' · ASSAM MOTORS')
@section('topbar_title','Money Receipt')
@section('content')
<style>
.p58detail{max-width:1150px;margin:auto}
.p58detail .heading{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:17px}
.p58detail h1{color:#164a7e;margin:0;font-size:24px}
.p58detail p{color:#607a91;margin:6px 0}
.p58detail .action{display:inline-flex;padding:10px 13px;background:#155a94;border:1px solid #155a94;color:white;border-radius:8px;text-decoration:none;font-size:12px;font-weight:800}
.p58detail .action.alt{background:#f5faff;border-color:#cee0ee;color:#165384}
.p58detail .stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:11px;margin-bottom:15px}
.p58detail .stat{background:#fff;border:1px solid #d8e5ee;border-radius:11px;padding:15px}
.p58detail .stat small{display:block;color:#6c8397;font-size:11px}.p58detail .stat strong{display:block;margin-top:5px;color:#144d7d;font-size:21px}
.p58detail .card{background:#fff;border:1px solid #d9e5ee;border-radius:12px;padding:17px;margin-top:14px}
.p58detail h2{color:#1b4b77;font-size:16px;margin:0 0 12px}
.p58detail .row58{display:grid;grid-template-columns:150px 1fr;gap:10px;border-bottom:1px solid #e6edf3;padding:8px 0;font-size:13px}
.p58detail .row58 span{color:#73879b}
.p58detail .safe{padding:11px 14px;background:#effaf4;color:#257249;border-left:4px solid #1c9a60;border-radius:9px}
.p58detail .warn{padding:11px 14px;background:#fff5e5;border-left:4px solid #d2a03f;border-radius:9px;color:#885b11}
@media(max-width:850px){.p58detail .stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:600px){.p58detail .stats{grid-template-columns:1fr}.p58detail .row58{grid-template-columns:1fr;gap:2px}.p58detail h1{font-size:20px}}
</style>
<div class="p58detail">
 <div class="heading">
   <div><h1>Money Receipt — {{ $receipt->receipt_no }}</h1><p>{{ $receipt->customer_name??'Customer' }} · {{ strtoupper((string)$receipt->payment_status) }}</p></div>
   <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a class="action alt" href="{{ route('erp.money-receipts.index') }}">← Register</a>
    <a class="action" target="_blank" rel="noopener" href="{{ route('erp.money-receipts.print',$receipt->id) }}">🖨 Print / Save PDF</a>
    <a class="action alt" href="{{ route('erp.money-receipts.create',['customer'=>$receipt->customer_id]) }}">＋ New Receipt</a>
   </div>
 </div>
 @if(session('success'))<div class="safe" role="status">{{ session('success') }}</div>@endif
 @if($receipt->reversed_at)<div class="warn"><strong>REVERSED</strong> · {{ $receipt->reversal_reason?:'Historical receipt marked reversed' }}</div>@endif
 <div class="stats">
   <div class="stat"><small>Received Amount</small><strong>₹{{ number_format($receiptAmount,2) }}</strong></div>
   <div class="stat"><small>Invoice Allocated</small><strong>₹{{ number_format((float)$allocation,2) }}</strong></div>
   <div class="stat"><small>Unallocated Credit</small><strong>₹{{ number_format(max(0,$receiptAmount-(float)$allocation),2) }}</strong></div>
   <div class="stat"><small>Receipt Date</small><strong style="font-size:16px">{{ date('d M Y',strtotime((string)($receipt->receipt_date??$receipt->created_at))) }}</strong></div>
 </div>
 <div class="card">
  <h2>Customer & Reference</h2>
  <div class="row58"><span>Customer</span><strong>{{ $receipt->customer_name }} · {{ $receipt->customer_code }}</strong></div>
  <div class="row58"><span>Phone</span><strong>{{ $receipt->customer_phone?:'—' }}</strong></div>
  <div class="row58"><span>Receipt Type</span><strong>{{ strtoupper(str_replace('_',' ',$receipt->reference_type)) }}</strong></div>
  <div class="row58"><span>Reference</span><strong>{{ $receipt->reference_no?:'—' }}</strong></div>
  <div class="row58"><span>Against Invoice</span><strong>
    @if($invoice)<a href="{{ route('erp.invoices.show',$invoice->id) }}">{{ $invoice->invoice_no }}</a>
    @else — @endif
  </strong></div>
  <div class="row58"><span>Job Card / Vehicle</span><strong>{{ $job->job_no??'—' }} / {{ $invoice->vehicle_reg_no??$job->vehicle_reg_no??'—' }}</strong></div>
 </div>
 <div class="card">
  <h2>Payment Details</h2>
  <div class="row58"><span>Payment Mode</span><strong>{{ $receipt->payment_mode }}</strong></div>
  <div class="row58"><span>Cash Amount</span><strong>₹{{ number_format((float)$receipt->cash_amount,2) }}</strong></div>
  <div class="row58"><span>Online / Bank Amount</span><strong>₹{{ number_format((float)$receipt->online_amount,2) }}</strong></div>
  <div class="row58"><span>Transaction Reference</span><strong>{{ $receipt->online_reference?:'—' }}</strong></div>
  <div class="row58"><span>Notes</span><strong>{{ $receipt->notes?:'—' }}</strong></div>
  <div class="row58"><span>Received By</span><strong>{{ $receipt->received_by?:'—' }}</strong></div>
  <div class="row58"><span>Amount In Words</span><strong>{{ $words }}</strong></div>
 </div>
 <div class="{{ $ledger?'safe':'warn' }}" style="margin-top:14px">
  @if($ledger)<strong>Customer Ledger:</strong> CREDIT ₹{{ number_format((float)$ledger->credit,2) }} recorded against receipt {{ $receipt->receipt_no }}.
  @else <strong>Historical receipt:</strong> Matching Customer Ledger entry was not found. Check reconciliation before treating this as a newly posted payment. @endif
 </div>
 <p style="font-size:11px">Receipt documents do not recalculate GST. GST remains on the Tax Invoice, and the receipt acknowledges payment only.</p>
</div>
@endsection
