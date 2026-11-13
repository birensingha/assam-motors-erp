@extends('layouts.erp')
@section('title','New Purchase Return · ASSAM MOTORS')
@section('content')
{{-- AM253_PURCHASE_RETURN_SELECT_ORIGINAL_BILL --}}
<style>
.am253-select{max-width:1400px;margin:0 auto;color:#163754}
.am253-head{display:flex;align-items:center;justify-content:space-between;gap:15px;flex-wrap:wrap;background:#f1f7ff;border:1px solid #d4e3f2;border-radius:13px;padding:17px 21px;margin-bottom:14px}
.am253-head h1{margin:0;font-size:23px}.am253-head p{margin:5px 0 0;color:#657e97;font-size:12px}
.am253-head a,.am253-btn{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;background:#17568b;color:#fff;border-radius:8px;padding:9px 13px;font-size:12px;font-weight:780;border:0}
.am253-form{display:flex;gap:8px;align-items:center;padding:14px;background:#fff;border:1px solid #d7e4f2;border-radius:11px;margin-bottom:12px}
.am253-form input{flex:1 1 400px;padding:11px 13px;min-width:160px;border:1px solid #bfcedd;border-radius:8px}
.am253-scroll{border:1px solid #d6e3ee;border-radius:11px;background:#fff;overflow:auto}
.am253-table{min-width:900px;width:100%;border-collapse:collapse}
.am253-table th{background:#173b69;color:#fff;font-size:11px;padding:12px;text-align:left;white-space:nowrap}
.am253-table td{padding:11px 12px;border-bottom:1px solid #e9f0f7;font-size:12px}
.am253-table tbody tr:nth-child(even){background:#f8fbfe}
.am253-table .num{text-align:right;font-variant-numeric:tabular-nums}
.am253-table small{display:block;color:#71869c;margin-top:3px}
.am253-table .am253-btn{padding:8px 11px;white-space:nowrap}
</style>
<div class="am253-select">
  <div class="am253-head">
    <div><h1>New Purchase Return</h1><p>Choose an original Parts / OSL Purchase Bill. Purchase Returns retain the supplier bill and create a separate negative debit-note document.</p></div>
    <a href="{{ route('erp.purchases.index') }}">← Purchase Register</a>
  </div>
  <form method="get" class="am253-form" action="{{ route('erp.purchase-returns.create') }}">
    <input name="q" value="{{ $q }}" maxlength="120" placeholder="Search Purchase Bill / vendor / supplier invoice / JC / part">
    <button class="am253-btn" type="submit">Search</button>
  </form>
  <div class="am253-scroll"><table class="am253-table">
    <thead><tr><th>Date</th><th>Original Purchase Bill</th><th>Vendor / Supplier Invoice</th><th>Type</th><th>Items</th><th class="num">Original Total</th><th>Return</th></tr></thead>
    <tbody>
    @forelse($bills as $bill)
      <tr>
        <td>{{ $bill['purchase_date'] }}</td>
        <td><strong>{{ $bill['purchase_batch_no'] ?: $bill['purchase_no'] }}</strong><small>{{ $bill['against_summary'] ?? '—' }}</small></td>
        <td><strong>{{ $bill['vendor_name'] ?: $bill['supplier_name'] }}</strong><small>{{ $bill['supplier_invoice'] ?: 'No invoice reference' }}</small></td>
        <td>{{ $bill['purchase_type'] }}</td>
        <td>{{ $bill['item_count'] }}</td>
        <td class="num">₹{{ number_format((float)$bill['total_amount'],2) }}</td>
        <td><a class="am253-btn" href="{{ route('erp.purchases.return.form',$bill['id']) }}">Select & Return</a></td>
      </tr>
    @empty
      <tr><td colspan="7" style="text-align:center;padding:30px">No matching Purchase Bills. Adjust the search.</td></tr>
    @endforelse
    </tbody>
  </table></div>
  <p style="font-size:11px;color:#73849a;margin:12px 2px">A return cannot exceed the purchased quantity, current physical stock or unpaid supplier bill amount. Paid/used purchases require reconciliation first.</p>
</div>
@endsection
