@extends('layouts.erp')
@section('title','Vendor Challan & GRN · ASSAM MOTORS')
@section('content')
{{-- AM260: posted stock receipt and bill linking, no auto issue to JC --}}
<style>
.am260{max-width:1500px;margin:0 auto;color:#1b425c;font-family:Inter,"Segoe UI",Arial,sans-serif;font-size:12px;padding-bottom:28px}
.am260 *{box-sizing:border-box}.am260 h1{font-size:23px;letter-spacing:-.03em;margin:5px 0 7px;color:#153b5c}.am260 h2{font-size:15px;color:#184c68;margin:0 0 12px}
.am260 .header{display:flex;justify-content:space-between;flex-wrap:wrap;align-items:center;gap:10px;padding:17px;border:1px solid #d9e5ee;border-radius:12px;background:#f4f9fd;margin-bottom:12px}
.am260 .eyebrow{font-size:10px;letter-spacing:.11em;font-weight:900;color:#0e8272}.am260 .muted{color:#73899b;font-size:11px;line-height:1.6}
.am260 .actions{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0 15px}.am260 a.btn,.am260 button.btn{border:1px solid #c4d9e7;background:#fff;padding:10px 13px;border-radius:8px;text-decoration:none;color:#205778;font-size:11px;font-weight:850;cursor:pointer;min-height:38px;display:inline-flex;align-items:center}
.am260 a.btn.primary,.am260 button.btn.primary{background:#0c7a6f;color:#fff;border-color:#0c7a6f}.am260 a.btn.blue{background:#174d76;color:#fff;border-color:#174d76}
.am260 .success{border:1px solid #b7e7d7;background:#edf9f4;color:#116c54;border-radius:9px;padding:12px;margin:10px 0;font-size:11px}
.am260 .note{border:1px solid #e5d3aa;background:#fff8e9;color:#775924;border-radius:9px;padding:12px;margin:10px 0;line-height:1.6}
.am260 .stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:11px 0}.am260 .stat{border:1px solid #deebf2;background:white;border-radius:10px;padding:14px}
.am260 .stat small{font-size:10px;font-weight:850;color:#738da1}.am260 .stat strong{display:block;font-size:23px;color:#15496a;margin-top:7px}
.am260 .panel{background:#fff;border:1px solid #dce8f0;border-radius:11px;padding:15px;margin:12px 0}.am260 .tablewrap{overflow:auto;max-height:420px;border:1px solid #e0eaf0;border-radius:8px}
.am260 table{width:100%;min-width:900px;border-collapse:collapse}.am260 th{position:sticky;top:0;padding:10px;font-size:10px;color:#fff;background:#173f68;text-align:left;z-index:1}.am260 td{padding:10px;border-bottom:1px solid #e9eef4;font-size:11px}
.am260 tbody tr:nth-child(even){background:#f7fafc}.am260 .num{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}.am260 .link{color:#155c86;font-weight:800;text-decoration:none}
.am260 .tag{font-size:10px;border-radius:6px;padding:5px 8px;font-weight:800;background:#fff2d7;color:#946627}.am260 .tag.done{background:#e3f6ee;color:#117451}.am260 .foot{color:#8096a5;font-size:10px;line-height:1.8}
@media(max-width:760px){.am260 .stats{grid-template-columns:repeat(2,minmax(0,1fr))}.am260 h1{font-size:20px}}
@media print{.am260 .actions{display:none!important}.am260 .tablewrap{max-height:none;overflow:visible}.am260 table{min-width:0}}
</style>
<div class="am260">
  <header class="header"><div>
    <div class="eyebrow">ASSAM MOTORS · PROCUREMENT & INVENTORY</div>
    <h1>Vendor Challan / Goods Receipt (GRN)</h1>
    <p class="muted">Purchase Order → Physically Received Stock → Issue to Job Card → Book Supplier Invoice Later</p>
  </div><span class="tag done">AM260 · STAGING</span></header>
  @if(session('success'))<div class="success" role="status">{{ session('success') }}</div>@endif
  <div class="actions">
    <a class="btn primary" href="{{ route('erp.procurement.po.create') }}">+ New Purchase Order</a>
    <a class="btn blue" href="{{ route('erp.procurement.receive') }}">+ Receive Vendor Challan (GRN)</a>
    <a class="btn" href="{{ route('erp.procurement.bill.create') }}">Match Vendor Purchase Bill</a>
    <a class="btn" href="{{ route('erp.purchases.index') }}">Purchase Register</a>
  </div>
  <div class="note"><strong>Stock Control:</strong> A Purchase Order does not change stock. A confirmed GRN increases stock exactly once. The later supplier Purchase Bill creates a purchase/payable reference with <strong>zero additional stock-in</strong>. Job Card issue remains an independent, existing stock-controlled action. Previously negative or unknown stock must be reconciled; it is not silently reset.</div>
  <div class="stats">
    <div class="stat"><small>Total Purchase Orders</small><strong>{{ $orders->count() }}</strong><small class="muted">Recent 80 shown</small></div>
    <div class="stat"><small>Received Challans / GRNs</small><strong>{{ $receipts->count() }}</strong><small class="muted">Recent 80 shown</small></div>
    <div class="stat"><small>Unbilled GRN Part Lines</small><strong>{{ $pending->count() }}</strong><small class="muted">Recent 200 shown</small></div>
    <div class="stat"><small>Matched Supplier Bills</small><strong>{{ $bills->count() }}</strong><small class="muted">Recent 80 shown</small></div>
  </div>
  <section class="panel">
    <h2>Bill Pending — Received Goods Not Yet Matched</h2>
    <div class="tablewrap"><table>
      <thead><tr><th>Date</th><th>Vendor</th><th>GRN / Challan</th><th>PO</th><th>Part</th><th class="num">Received Qty</th><th class="num">Billed Qty</th><th class="num">Unbilled Qty</th><th>Action</th></tr></thead>
      <tbody>@forelse($pending as $p)
        <tr><td>{{ $p->receipt_date }}</td><td>{{ $p->vendor_name }}</td>
          <td><a class="link" href="{{ route('erp.procurement.grn.show',['grn'=>$p->grn_id]) }}">{{ $p->grn_no }}</a><span class="muted" style="display:block">Challan {{ $p->challan_no }}</span></td>
          <td>{{ $p->po_no??'Urgent / No PO' }}</td><td>{{ $p->part_name }}<span class="muted" style="display:block">{{ $p->part_code }}</span></td>
          <td class="num">{{ number_format((float)$p->qty_received,3) }}</td>
          <td class="num">{{ number_format((float)$p->qty_billed,3) }}</td>
          <td class="num"><strong>{{ number_format((float)$p->qty_received-(float)$p->qty_billed,3) }}</strong></td>
          <td><a class="link" href="{{ route('erp.procurement.bill.create',['vendor_id'=>$p->vendor_id]) }}">Match Bill</a></td>
        </tr>
      @empty<tr><td colspan="9" style="text-align:center;padding:23px">No pending GRN lines.</td></tr>@endforelse</tbody>
    </table></div>
  </section>
  <section class="panel">
    <h2>Purchase Orders — Open / Received</h2>
    <div class="tablewrap"><table>
      <thead><tr><th>PO No.</th><th>Order Date</th><th>Vendor</th><th>Against JC / Vehicle</th><th>Status</th><th>Receive Challan</th></tr></thead>
      <tbody>@forelse($orders as $o)<tr>
        <td><strong>{{ $o->po_no }}</strong></td><td>{{ $o->order_date }}</td><td>{{ $o->vendor_name }}</td>
        <td>{{ $o->job_no?:'STOCK' }} {{ $o->vehicle_reg_no?:'' }}</td>
        <td><span class="tag {{ $o->status==='RECEIVED'?'done':'' }}">{{ $o->status }}</span></td>
        <td>@if($o->status==='OPEN')<a class="link" href="{{ route('erp.procurement.receive',['po_id'=>$o->id]) }}">Receive Against PO</a>@else Completed @endif</td>
      </tr>@empty<tr><td colspan="6" style="text-align:center;padding:23px">No Purchase Orders yet.</td></tr>@endforelse</tbody>
    </table></div>
  </section>
  <section class="panel">
    <h2>Stock Received — Vendor Challan History</h2>
    <div class="tablewrap"><table>
      <thead><tr><th>GRN No.</th><th>Receive Date</th><th>Vendor</th><th>Vendor Challan</th><th>PO</th><th>Against JC / Vehicle</th><th>Stock</th></tr></thead>
      <tbody>@forelse($receipts as $g)<tr>
        <td><a class="link" href="{{ route('erp.procurement.grn.show',['grn'=>$g->id]) }}">{{ $g->grn_no }}</a></td><td>{{ $g->receipt_date }}</td>
        <td>{{ $g->vendor_name }}</td><td>{{ $g->challan_no }}</td><td>{{ $g->po_no??'No PO' }}</td>
        <td>{{ $g->job_no?:'STOCK' }} {{ $g->vehicle_reg_no?:'' }}</td>
        <td><a class="link" href="{{ route('erp.procurement.grn.show',['grn'=>$g->id]) }}">Verify Item Ledger / Stock-In</a>
          <span style="margin:0 6px;color:#98aab7">|</span>
          <a class="link" href="{{ route('erp.procurement.grn.edit',['grn'=>$g->id]) }}">Edit GRN Items</a></td>
      </tr>@empty<tr><td colspan="7" style="text-align:center;padding:23px">No posted GRN yet. Saving only a Purchase Order does not add stock or create Item Ledger transactions. Open Receive Vendor Challan, enter physically received quantities, and confirm GRN.</td></tr>@endforelse</tbody>
    </table></div>
  </section>
  <section class="panel">
    <h2>Supplier Bills Matched from GRNs</h2>
    <div class="tablewrap"><table>
      <thead><tr><th>Purchase Batch</th><th>Supplier Bill No.</th><th>Bill Date</th><th>Vendor</th><th>Purchase Register</th></tr></thead>
      <tbody>@forelse($bills as $b)<tr>
        <td><strong>{{ $b->batch_no }}</strong></td><td>{{ $b->supplier_invoice }}</td><td>{{ $b->purchase_date }}</td>
        <td>{{ $b->vendor_name }}</td><td><a class="link" href="{{ route('erp.purchases.index',['q'=>$b->batch_no]) }}">Open Purchase</a></td>
      </tr>@empty<tr><td colspan="5" style="text-align:center;padding:23px">No linked Purchase Bills yet.</td></tr>@endforelse</tbody>
    </table></div>
  </section>
  <p class="foot">This is a stock and supplier-invoice matching system, not a direct stock adjustment utility. Every physical receipt requires a real vendor challan and user confirmation; stock receipt and invoice booking are separate immutable events. One real vendor invoice may match several GRNs from the same vendor, including partial quantities.</p>
</div>
@endsection
