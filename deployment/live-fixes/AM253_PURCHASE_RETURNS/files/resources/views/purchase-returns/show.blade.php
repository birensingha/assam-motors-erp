@extends('layouts.erp')
@section('title','Purchase Return · ASSAM MOTORS')
@section('content')
{{-- AM253_PURCHASE_RETURN_DETAIL --}}
<style>
.am253-detail{max-width:1350px;margin:0 auto;color:#193c5c}
.am253-detail *{box-sizing:border-box}
.am253-detail .head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;padding:18px;background:#f4f8ff;border:1px solid #d5e4f2;border-radius:12px;margin-bottom:13px}
.am253-detail h1{font-size:24px;margin:3px 0}.am253-detail .muted{color:#657f99;font-size:12px}
.am253-detail .tools{display:flex;gap:7px;flex-wrap:wrap;align-items:center}
.am253-detail .btnx{display:inline-flex;padding:9px 12px;border-radius:8px;border:1px solid #b8d0e6;background:#edf6ff;color:#1b537f;text-decoration:none;font-size:12px;font-weight:800;cursor:pointer}
.am253-detail .btnx.danger{color:#a32634;background:#fff1f1;border-color:#e8b8bc}
.am253-detail .cardx{border:1px solid #dce6f0;border-radius:12px;padding:17px;background:#fff;margin-bottom:13px}
.am253-detail .gridx{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.am253-detail .gridx small{display:block;color:#73849b;font-size:11px;margin-bottom:4px}
.am253-detail .gridx strong{font-size:13px}
.am253-detail table{width:100%;min-width:900px;border-collapse:collapse}
.am253-detail .scroll{overflow:auto}
.am253-detail th{background:#193f71;color:#fff;padding:11px 9px;text-align:left;font-size:11px}
.am253-detail td{padding:12px 9px;border-bottom:1px solid #e3ecf5;font-size:12px}
.am253-detail .num{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
.am253-detail .total{text-align:right;padding:15px;font-size:20px;font-weight:850;color:#ac2433}
.am253-detail .warn{border-left:4px solid #e6a43a;background:#fff8ea;color:#855c1a;padding:11px 13px;border-radius:8px;margin:12px 0;font-size:12px}
.am253-detail .void{border-left:4px solid #b83241;background:#fff1f2;color:#9d2936;padding:11px 13px;border-radius:8px;margin:12px 0;font-size:12px}
.am253-detail form{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.am253-detail form input{padding:9px;border:1px solid #ccadb2;border-radius:8px;min-width:260px}
@media(max-width:800px){.am253-detail .gridx{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:460px){.am253-detail .gridx{grid-template-columns:1fr}}
</style>
<div class="am253-detail">
  <div class="head">
    <div><small class="muted">ASSAM MOTORS · PURCHASE RETURN / SUPPLIER DEBIT NOTE</small><h1>{{ $doc->return_no }}</h1>
      <span class="muted">{{ $doc->return_date }} · {{ $doc->purchase_type }} · {{ $doc->record_status }}</span>
    </div>
    <div class="tools">
      <a class="btnx" href="{{ route('erp.purchases.index') }}">← Purchase Register</a>
      <a class="btnx" href="{{ route('erp.purchases.show',$doc->source_purchase_id) }}">Original Bill</a>
      <a class="btnx" href="{{ route('erp.purchase-returns.print',$doc->id) }}" target="_blank" rel="noopener">Print / PDF</a>
      <a class="btnx" href="{{ route('erp.purchase-returns.create') }}">+ New Return</a>
    </div>
  </div>
  @if(session('success'))<div class="cardx" style="border-left:4px solid #298160;color:#1c6d51">{{ session('success') }}</div>@endif
  @if($doc->record_status==='VOID')
    <div class="void"><strong>VOID / CANCELLED</strong> · {{ $doc->void_reason ?: 'Cancelled by Admin' }}<div style="margin-top:4px">By {{ $doc->voided_by }} at {{ $doc->voided_at }}</div></div>
  @endif
  <div class="cardx">
    <div class="gridx">
      <div><small>Vendor</small><strong>{{ $doc->vendor_name }}</strong></div>
      <div><small>Original Purchase Bill</small><strong>{{ $doc->source_batch_no ?: ($original->purchase_no ?? '—') }}</strong></div>
      <div><small>Supplier Credit Note No.</small><strong>{{ $doc->supplier_credit_note ?: 'Not entered' }}</strong></div>
      <div><small>Created By</small><strong>{{ $doc->created_by }}</strong></div>
    </div>
    <div style="margin-top:16px"><small class="muted">Reason</small><div>{{ $doc->reason }}</div></div>
  </div>
  <div class="cardx">
    <div class="scroll"><table>
      <thead><tr><th>SL</th><th>Returned Part / OSL</th><th>HSN/SAC</th><th class="num">Qty</th><th class="num">Rate</th><th class="num">Disc</th><th class="num">Taxable</th><th class="num">GST</th><th class="num">Return Value</th></tr></thead>
      <tbody>
        @foreach($lines as $i=>$l)
          <tr><td>{{ $i+1 }}</td><td><strong>{{ $l->item_description }}</strong><br><small style="color:#74869a">Original line #{{ $l->purchase_id }}</small></td>
          <td>{{ $l->hsn_code ?: '—' }}</td><td class="num">{{ number_format((float)$l->qty,3) }}</td>
          <td class="num">₹{{ number_format((float)$l->unit_cost,2) }}</td>
          <td class="num">₹{{ number_format((float)$l->discount_amount,2) }}</td>
          <td class="num">₹{{ number_format((float)$l->taxable_amount,2) }}</td>
          <td class="num">₹{{ number_format((float)$l->gst_amount,2) }}</td>
          <td class="num" style="color:#a42738;font-weight:750">−₹{{ number_format((float)$l->total_amount,2) }}</td></tr>
        @endforeach
      </tbody>
    </table></div>
    <div style="text-align:right;padding:12px 15px;font-size:12px;color:#365b7c">
      Taxable: −₹{{ number_format((float)$doc->taxable_amount,2) }} · GST: −₹{{ number_format((float)$doc->gst_amount,2) }} · Round Off: {{ number_format((float)$doc->round_off,2) }}
    </div>
    <div class="total">Net Purchase Return: −₹{{ number_format((float)$doc->grand_total,2) }}</div>
  </div>
  <div class="warn">
    Original purchase remains in history. Supplier credit is recognised only while this return is POSTED.
    For OSL returns, customer billing is not automatically altered. Printed documents are not a substitute for the supplier-issued GST credit note.
  </div>
  @if($doc->record_status==='POSTED')
    <div class="cardx">
      <strong style="display:block;margin-bottom:8px">Owner/Admin — Void Incorrect Purchase Return</strong>
      <form method="post" action="{{ route('erp.purchase-returns.void',$doc->id) }}"
            onsubmit="return confirm('VOID this Purchase Return? Its stock and supplier credit effects will reverse.');">
        @csrf
        <input name="reason" minlength="10" maxlength="300" required placeholder="Mandatory void reason (min 10 chars)">
        <button type="submit" class="btnx danger">Void Purchase Return</button>
      </form>
    </div>
  @endif
</div>
@endsection
