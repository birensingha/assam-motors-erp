@extends('layouts.erp')
@section('title','Sales Register · ASSAM MOTORS')
@section('content')
{{-- AM252_UNIFIED_SALES_REGISTER: invoices positive, posted Credit Notes negative. Read only. --}}
<style>
.am252-sales{color:#173553;max-width:1600px;margin:0 auto}
.am252-sales *{box-sizing:border-box}
.am252-hero{display:flex;flex-wrap:wrap;gap:14px;justify-content:space-between;align-items:center;padding:18px 21px;border:1px solid #d3e3f1;background:#f5f9ff;border-radius:15px;margin:0 0 13px}
.am252-eyebrow{font-size:11px;text-transform:uppercase;letter-spacing:.09em;color:#527395;font-weight:800}
.am252-hero h1{font-size:24px;color:#123657;line-height:1.2;margin:6px 0}
.am252-hero p{color:#5b7690;font-size:12px;margin:5px 0;max-width:700px;line-height:1.55}
.am252-hero a{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:9px 14px;background:#12548f;border-radius:9px;color:#fff;text-decoration:none;font-weight:750;font-size:12px}
.am252-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:10px 0 15px}
.am252-stat{padding:13px 15px;background:#fff;border:1px solid #dce8f2;border-radius:11px}
.am252-stat small{display:block;color:#657e95;font-weight:700;font-size:11px;margin:0 0 6px}
.am252-stat strong{display:block;font-size:19px;line-height:1.35;color:#123b65;font-variant-numeric:tabular-nums}
.am252-stat .am252-negative{color:#a92c38}
.am252-filters{display:flex;gap:9px;flex-wrap:wrap;align-items:end;padding:13px 14px;background:#fff;border:1px solid #dce8f2;border-radius:12px;margin-bottom:13px}
.am252-filters label{display:flex;flex-direction:column;gap:5px;font-weight:750;color:#415f7e;font-size:11px;flex:1 1 132px}
.am252-filters label.am252-search{flex:2 1 270px}
.am252-filters input,.am252-filters select{width:100%;min-height:39px;padding:8px 10px;border:1px solid #c5d7e7;border-radius:8px;background:#fff;color:#1a3d5c;font-size:12px}
.am252-filters button,.am252-filters a{border:0;border-radius:8px;min-height:39px;padding:9px 14px;font-size:12px;font-weight:780;background:#124f87;color:#fff;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center}
.am252-filters a{background:#eaf2fa;color:#234b70}
.am252-scroll{overflow:auto;border:1px solid #d9e6f2;border-radius:12px;background:#fff}
.am252-table{width:100%;min-width:950px;border-collapse:collapse}
.am252-table th{position:sticky;top:0;z-index:1;background:#173b6b;color:#fff;padding:12px 10px;font-size:11px;font-weight:800;text-align:left;white-space:nowrap}
.am252-table td{padding:12px 10px;border-top:1px solid #e6eef6;vertical-align:middle;font-size:12px}
.am252-table tbody tr:nth-child(even){background:#f8fbff}
.am252-table .amount{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
.am252-table .negative{color:#b32b3b;font-weight:850}
.am252-type{display:inline-block;border-radius:6px;background:#e5f0fc;color:#174e8b;font-size:10px;font-weight:850;padding:5px 8px;white-space:nowrap}
.am252-type.credit{background:#fff0f1;color:#a12635}
.am252-small{display:block;color:#6b8299;font-size:10px;margin-top:4px}
.am252-rowlink{color:#17598d;font-weight:800;text-decoration:none}
.am252-rowlink:hover{text-decoration:underline}
.am252-action{display:inline-block;border:1px solid #bad2e5;border-radius:7px;padding:6px 10px;color:#195583;text-decoration:none;font-weight:750;white-space:nowrap}
.am252-footnote{color:#607a93;font-size:11px;line-height:1.6;margin:12px 1px}
.am252-paging{margin-top:14px}
@media(max-width:830px){.am252-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.am252-hero h1{font-size:21px}}
@media(max-width:420px){.am252-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.am252-stat strong{font-size:15px}}
</style>
<div class="am252-sales">
  <div class="am252-hero">
    <div>
      <div class="am252-eyebrow">ASSAM MOTORS · WORKSHOP FINANCE</div>
      <h1>Sales Register</h1>
      <p>Sales invoices and posted Credit Notes together. Original invoices stay visible as positive sales, while Credit Notes appear as negative entries on their own posting dates.</p>
    </div>
    <a href="{{ route('erp.counter-sales.create') }}">+ New Counter Parts Sale</a>
  </div>
  <div class="am252-stats">
    <div class="am252-stat"><small>Total Documents</small><strong>{{ number_format((int)($summary->document_count ?? 0)) }}</strong></div>
    <div class="am252-stat"><small>Gross Sales</small><strong>₹{{ number_format((float)($summary->sales_total ?? 0),2) }}</strong></div>
    <div class="am252-stat"><small>Credit Notes (Less)</small><strong class="am252-negative">−₹{{ number_format((float)($summary->credit_total ?? 0),2) }}</strong></div>
    <div class="am252-stat"><small>Net Sales</small><strong>₹{{ number_format((float)($summary->net_total ?? 0),2) }}</strong></div>
  </div>
  <form method="get" action="{{ route('erp.sales-register.index') }}" class="am252-filters">
    <label class="am252-search">Search invoice, credit note, customer or vehicle
      <input type="search" name="q" maxlength="120" value="{{ $search }}" placeholder="Invoice / CN / customer / vehicle">
    </label>
    <label>Document Type
      <select name="type">
        <option value="all" @selected($type==='all')>All Sales & Credit Notes</option>
        <option value="sale" @selected($type==='sale')>Sales Invoices</option>
        <option value="credit_note" @selected($type==='credit_note')>Credit Notes</option>
      </select>
    </label>
    <label>From<input type="date" name="from" value="{{ $from }}"></label>
    <label>To<input type="date" name="to" value="{{ $to }}"></label>
    <button type="submit">Filter</button>
    <a href="{{ route('erp.sales-register.index') }}">Reset</a>
  </form>
  <div class="am252-scroll">
    <table class="am252-table">
      <thead><tr><th>Date</th><th>Type</th><th>Document No</th><th>Customer / Vehicle</th><th>Against Invoice</th><th class="amount">GST</th><th class="amount">Amount (+/−)</th><th>Status</th><th>Open</th></tr></thead>
      <tbody>
      @forelse($rows as $r)
        <tr>
          <td>{{ $r->document_date ? date('d M Y',strtotime((string)$r->document_date)) : '—' }}</td>
          <td><span class="am252-type {{ $r->document_type==='CREDIT_NOTE'?'credit':'' }}">{{ $r->document_type==='CREDIT_NOTE'?'Credit Note':'Sale' }}</span></td>
          <td>
            @if($r->document_type==='CREDIT_NOTE')
              <a class="am252-rowlink" href="{{ route('erp.invoice-credit-note.print',$r->doc_id) }}" target="_blank" rel="noopener">{{ $r->document_no }}</a>
            @else
              <a class="am252-rowlink" href="{{ route('erp.invoices.show',$r->doc_id) }}">{{ $r->document_no }}</a>
            @endif
          </td>
          <td>{{ $r->customer_name ?: '—' }}<span class="am252-small">{{ $r->vehicle_no ?: '—' }}</span></td>
          <td>
            @if($r->document_type==='CREDIT_NOTE' && $r->invoice_id)
              <a class="am252-rowlink" href="{{ route('erp.invoices.show',$r->invoice_id) }}">{{ $r->invoice_no ?: '#'.$r->invoice_id }}</a>
            @else
              <span class="am252-small">—</span>
            @endif
          </td>
          <td class="amount {{ (float)$r->signed_gst < 0?'negative':'' }}">{{ (float)$r->signed_gst<0?'−':'' }}₹{{ number_format(abs((float)$r->signed_gst),2) }}</td>
          <td class="amount {{ (float)$r->signed_amount<0?'negative':'' }}">{{ (float)$r->signed_amount<0?'−':'' }}₹{{ number_format(abs((float)$r->signed_amount),2) }}</td>
          <td>{{ strtoupper(str_replace('_',' ',(string)$r->document_status)) }}</td>
          <td>
            @if($r->document_type==='CREDIT_NOTE')
              <a class="am252-action" href="{{ route('erp.invoice-credit-note.print',$r->doc_id) }}" target="_blank" rel="noopener">Credit Note PDF</a>
            @else
              <a class="am252-action" href="{{ route('erp.invoices.show',$r->doc_id) }}">View Invoice</a>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="9" style="padding:30px;text-align:center;color:#687e94">No Sales or Credit Notes match the selected filters.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="am252-paging">{{ $rows->links() }}</div>
  <p class="am252-footnote">Amounts are taken from saved transaction records; this register does not create, alter or recalculate invoices, GST, Credit Notes or customer ledgers. A reversed original invoice and its posted Credit Note remain separate rows to preserve financial history.</p>
</div>
@endsection
