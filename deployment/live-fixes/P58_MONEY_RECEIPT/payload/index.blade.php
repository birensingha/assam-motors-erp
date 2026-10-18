@extends('layouts.erp')
@section('title','Money Receipt Register · ASSAM MOTORS')
@section('topbar_title','Money Receipt Register')
@section('content')
<style>
.p58-reg{max-width:1450px;margin:0 auto}
.p58-reg-head{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px}
.p58-reg-head h1{color:#123f76;margin:0;font-size:25px}.p58-reg-head p{margin:5px 0;color:#637d92}
.p58-reg .btn58{background:#16619b;color:white;border:1px solid #16619b;display:inline-flex;align-items:center;gap:7px;padding:10px 13px;border-radius:9px;font-size:12px;font-weight:800;text-decoration:none}
.p58-reg .btn58.alt{background:#f3f9ff;color:#1a5484;border-color:#cbdceb}
.p58-reg .metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:13px;margin:16px 0}
.p58-reg .metric58{padding:15px;background:#fff;border:1px solid #d9e5ee;border-radius:12px;box-shadow:0 5px 16px #133e6308}
.p58-reg .metric58 small{display:block;font-size:11px;color:#6b839a;font-weight:750}.p58-reg .metric58 strong{display:block;color:#124a7c;font-size:24px;margin-top:5px}
.p58-reg .card58{background:#fff;border:1px solid #dae4ef;border-radius:12px;padding:13px;margin-top:10px}
.p58-reg .search58{display:flex;gap:9px;align-items:center;flex-wrap:wrap}
.p58-reg .search58 input{flex:1;min-width:200px;max-width:440px;padding:10px 12px;border:1px solid #cbdce9;border-radius:9px}
.p58-reg .search58 button{padding:10px 15px;border:0;background:#155a93;color:#fff;font-weight:800;border-radius:9px}
.p58-reg .wrap-table{max-width:100%;overflow-x:auto}
.p58-reg table{width:100%;border-collapse:collapse;min-width:980px}
.p58-reg th{background:#edf4fa;padding:11px 9px;text-align:left;font-size:10px;color:#305773;text-transform:uppercase}
.p58-reg td{padding:11px 9px;border-bottom:1px solid #e4ebf2;font-size:12px;vertical-align:top}
.p58-reg td strong{color:#123d6c}.p58-reg .num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
.p58-reg .pill{padding:4px 9px;border-radius:100px;background:#e8f6ef;color:#167044;font-size:10px;font-weight:800;text-transform:uppercase}
.p58-reg .pill.reversed{background:#f8e9e9;color:#9d3939}
.p58-reg .ref{font-size:10px;color:#718499}
.p58-reg .page58{margin-top:15px}
@media(max-width:720px){.p58-reg .metrics{grid-template-columns:1fr}.p58-reg-head h1{font-size:20px}}
</style>
<div class="p58-reg">
 <div class="p58-reg-head">
  <div><h1>Money Receipt Register</h1><p>Customer Collections · New Reference · Against Reference · On Account</p></div>
  <div style="display:flex;flex-wrap:wrap;gap:8px">
    <a class="btn58 alt" href="{{ route('erp.payments.index') }}">Supplier Payment Vouchers</a>
    <a class="btn58" href="{{ route('erp.money-receipts.create') }}">＋ New Money Receipt</a>
  </div>
 </div>
 <div class="metrics">
  <div class="metric58"><small>All Receipt Records</small><strong>{{ number_format($receipts->total()) }}</strong></div>
  <div class="metric58"><small>Posted Receipts — Running Total</small><strong>₹{{ number_format((float)$total,2) }}</strong></div>
  <div class="metric58"><small>Accounting Mode</small><strong style="font-size:17px">Customer Ledger Credit</strong></div>
 </div>
 @if(session('success'))<div class="card58" style="border-left:4px solid #188a57;color:#146a44" role="status">{{ session('success') }}</div>@endif
 <div class="card58">
   <form class="search58" method="get" action="{{ route('erp.money-receipts.index') }}">
    <input name="q" value="{{ $filter }}" placeholder="Search Receipt No / Reference / Customer / Phone">
    <button type="submit">Search</button>
    @if($filter!=='')<a href="{{ route('erp.money-receipts.index') }}">Clear</a>@endif
   </form>
 </div>
 <div class="card58 wrap-table">
  <table><thead><tr><th>Date / Receipt</th><th>Customer</th><th>Receipt Type</th><th>Against / New Ref</th><th>Payment Mode</th><th class="num">Received</th><th class="num">Allocated</th><th>Status</th><th>Actions</th></tr></thead><tbody>
   @forelse($receipts as $p)
    <tr>
      <td><strong>{{ $p->receipt_no }}</strong><div class="ref">{{ $p->receipt_date ? date('d M Y',strtotime($p->receipt_date)):date('d M Y',strtotime($p->created_at)) }}</div></td>
      <td><strong>{{ $p->customer_name??'Unknown Customer' }}</strong><div class="ref">{{ $p->customer_phone??$p->customer_email??'—' }}</div></td>
      <td>{{ str_replace('_',' ',ucwords((string)$p->reference_type,'_')) }}</td>
      <td>{{ $p->reference_no??'—' }}<div class="ref">{{ $p->reference_kind??'' }}</div></td>
      <td>{{ $p->payment_mode??'—' }}<div class="ref">{{ $p->online_reference??'' }}</div></td>
      <td class="num"><strong>₹{{ number_format((float)($p->total_amount??$p->amount??0),2) }}</strong></td>
      <td class="num">₹{{ number_format((float)($p->allocated_amount??0),2) }}</td>
      <td><span class="pill {{ $p->reversed_at?'reversed':'' }}">{{ $p->reversed_at?'REVERSED':($p->payment_status??'—') }}</span></td>
      <td><a class="btn58 alt" href="{{ route('erp.money-receipts.show',$p->id) }}">Open</a> <a class="btn58 alt" target="_blank" rel="noopener" href="{{ route('erp.money-receipts.print',$p->id) }}">Print</a></td>
    </tr>
   @empty <tr><td colspan="9" style="color:#71859b;text-align:center;padding:25px">No Money Receipts found. Use New Money Receipt to register the first collection.</td></tr>
   @endforelse
  </tbody></table>
 </div>
 <div class="page58">{{ $receipts->links() }}</div>
 <p style="font-size:11px;color:#6f8294;margin-top:10px">Existing receipts remain historical. Receipt creation, invoice allocation and customer ledger posting use an atomic transaction. Vendor payments are handled separately.</p>
</div>
@endsection
