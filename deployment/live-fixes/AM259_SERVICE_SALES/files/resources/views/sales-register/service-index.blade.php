@extends('layouts.erp')
@section('title',($category==='osl'?'OSL Sales Register':'Labour Sales Register').' · ASSAM MOTORS')
@section('content')
{{-- AM259_SERVICE_SALES: read-only invoiced Labour and OSL sales; NO cost/P&L assumptions. --}}
<style>
.am259{max-width:1590px;margin:0 auto;color:#153954;font-family:Inter,"Segoe UI",Arial,sans-serif;font-size:12px;padding-bottom:28px}
.am259 *{box-sizing:border-box}
.am259 .hero{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;background:#f5f9ff;border:1px solid #d3e4f0;border-radius:14px;padding:19px;margin-bottom:13px}
.am259 .eyebrow{font-size:10px;letter-spacing:.09em;color:#517c9c;text-transform:uppercase;font-weight:850}
.am259 h1{font-size:23px;font-weight:900;letter-spacing:-.03em;margin:7px 0;color:#164466}
.am259 p{margin:4px 0;line-height:1.6;color:#658198}
.am259 .tabs{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:12px 0 16px}
.am259 .tab{display:inline-block;text-decoration:none;border:1px solid #ccdae6;border-radius:8px;padding:10px 14px;color:#285778;font-weight:850;font-size:12px;background:#fff;min-height:39px}
.am259 .tab.active{color:#fff;background:#185b88;border-color:#185b88}
.am259 .tab.print{margin-left:auto;background:#e9f5f4;color:#16796e;border-color:#b3ded7;cursor:pointer}
.am259 .metrics{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px;margin:12px 0 14px}
.am259 .metric{background:#fff;border:1px solid #dce7ef;border-radius:11px;padding:15px;min-width:0}
.am259 .metric small{display:block;color:#7890a0;font-size:11px;font-weight:800;line-height:1.45}
.am259 .metric strong{display:block;margin-top:9px;color:#174565;font-size:22px;letter-spacing:-.035em;font-variant-numeric:tabular-nums;overflow-wrap:anywhere}
.am259 .metric.main{border-top:3px solid #108d80}.am259 .metric.main strong{color:#0c8477}
.am259 .filters{display:flex;flex-wrap:wrap;align-items:end;gap:9px;padding:13px;background:#fff;border:1px solid #dce7ef;border-radius:11px;margin-bottom:14px}
.am259 .filters label{display:flex;flex-direction:column;gap:5px;flex:1 1 130px;font-size:11px;color:#4a6d82;font-weight:850}
.am259 .filters label.search{flex:2 1 260px}
.am259 .filters input{width:100%;height:38px;border:1px solid #c7d9e6;border-radius:7px;padding:7px 10px;font-size:12px;color:#1b4b65;min-width:0}
.am259 button,.am259 .reset{font-size:12px;text-decoration:none;border:0;border-radius:8px;background:#145984;color:#fff;font-weight:850;padding:10px 15px;height:38px;cursor:pointer}
.am259 .reset{background:#ecf3f9;color:#335e79}
.am259 .tablearea{overflow:auto;border:1px solid #dce7ef;border-radius:11px;background:#fff;max-height:680px}
.am259 table{width:100%;min-width:1110px;border-collapse:collapse}
.am259 thead th{position:sticky;top:0;background:#173c64;color:#fff;font-size:10px;letter-spacing:.015em;padding:11px 10px;white-space:nowrap;text-align:left;z-index:1}
.am259 td{padding:11px 10px;border-bottom:1px solid #e8eef4;font-size:11px;vertical-align:middle}
.am259 tbody tr:nth-child(even){background:#f7fbfe}
.am259 td small{display:block;color:#798fa1;margin-top:4px;font-size:10px}
.am259 .num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
.am259 .link{color:#16598b;font-weight:800;text-decoration:none}.am259 .link:hover{text-decoration:underline}
.am259 .alert{padding:12px 14px;background:#fff9ef;border:1px solid #f0dcb6;color:#87602a;border-radius:8px;line-height:1.65;margin:12px 0;font-size:11px}
.am259 .info{padding:12px 14px;background:#f1f7fa;border:1px solid #d8e7ef;color:#557b94;border-radius:8px;line-height:1.65;margin:13px 0;font-size:11px}
.am259 .foot{font-size:11px;line-height:1.75;color:#6d8497;margin-top:14px}
.am259 .pagination{margin-top:12px}
@media(max-width:1200px){.am259 .metrics{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:680px){.am259 .metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.am259 h1{font-size:20px}.am259 .metric strong{font-size:17px}}
@media print{.am259 .filters,.am259 .tab.print,.am259 .pagination{display:none!important}.am259 .tablearea{max-height:none;overflow:visible}.am259 table{min-width:0}.am259{color:#000}}
</style>
@php
 $isOsl=$category==='osl';
 $label=$isOsl?'OSL':'Labour / ROT';
 $registerRoute=$isOsl?'erp.sales-register.osl':'erp.sales-register.labour';
@endphp
<div class="am259">
 <header class="hero">
   <div>
     <div class="eyebrow">ASSAM MOTORS · SALES REGISTER</div>
     <h1>{{ $isOsl?'OSL Sales Register':'Labour / ROT Sales Register' }}</h1>
     <p>Invoiced {{ $label }} sales • GST excluded • Default shows all available dates</p>
   </div>
   <span style="background:#e3f3ef;border-radius:8px;color:#16775c;padding:8px 12px;font-weight:850;font-size:11px">READ-ONLY REGISTER</span>
 </header>
 <nav class="tabs" aria-label="Sales Registers">
   <a class="tab" href="{{ route('erp.sales-register.index') }}">Sales & Credit Notes</a>
   <a class="tab {{ !$isOsl?'active':'' }}" href="{{ route('erp.sales-register.labour') }}">Labour / ROT Register</a>
   <a class="tab {{ $isOsl?'active':'' }}" href="{{ route('erp.sales-register.osl') }}">OSL Register</a>
   <button class="tab print" type="button" onclick="window.print()">Print Register</button>
 </nav>
 <div class="metrics">
   <div class="metric main"><small>Total {{ $label }} Sales (ex-GST)</small><strong>₹{{ number_format((float)($summary->net_sales??0),2) }}</strong></div>
   <div class="metric"><small>Sales Before Discount (ex-GST)</small><strong>₹{{ number_format((float)($summary->gross_before_discount??0),2) }}</strong></div>
   <div class="metric"><small>Total Customer Discount</small><strong>₹{{ number_format((float)($summary->discounts??0),2) }}</strong></div>
   <div class="metric"><small>Number of Invoices</small><strong>{{ number_format((int)($summary->invoice_count??0)) }}</strong></div>
   <div class="metric"><small>{{ $label }} Entries</small><strong>{{ number_format((int)($summary->item_count??0)) }}</strong></div>
 </div>
 <form class="filters" action="{{ route($registerRoute) }}" method="get">
   <label class="search">Search Invoice / Customer / Vehicle / ROT or OSL
     <input type="search" name="q" maxlength="120" value="{{ $search }}" placeholder="Type invoice, JC, vehicle or work name">
   </label>
   <label>From Date<input type="date" name="from" value="{{ $from }}"></label>
   <label>To Date<input type="date" name="to" value="{{ $to }}"></label>
   <button type="submit">Filter Register</button>
   <a class="reset" href="{{ route($registerRoute) }}">Show All Dates</a>
 </form>
 @if($unallocatedCreditCount>0)
 <div class="alert"><strong>Credit Note Notice:</strong> {{ $unallocatedCreditCount }} posted Credit Note(s) on active source invoices found in this date period.
    Their Labour/OSL category-wise amounts cannot be confirmed from saved source data. This register therefore shows traceable invoiced service sales <strong>before any unallocated category-specific credit adjustments</strong>, not guaranteed final net revenue.</div>
 @endif
 <div class="info">Amounts are from billed Job Card {{ $label }} lines linked to the latest active non-reversed invoice per Job Card.
   OSL lines are kept separate from workshop Labour/ROT; they are never counted in both registers. Entries can reflect later Job Card corrections, because immutable item-level invoice snapshots are not yet available.</div>
 <div class="tablearea">
   <table>
     <thead>
       <tr><th>Invoice Date</th><th>Invoice No.</th><th>Job Card No.</th><th>Customer / Vehicle</th><th>{{ $isOsl?'OSL Service':'ROT Code / Labour' }}</th><th class="num">Std Hrs / Qty</th><th class="num">Rate</th><th class="num">Discount %</th><th class="num">Discount (₹)</th><th class="num">Sales (₹, ex-GST)</th><th>View</th></tr>
     </thead>
     <tbody>
       @forelse($rows as $r)
         <tr>
           <td>{{ $r->invoice_date ? date('d M Y',strtotime((string)$r->invoice_date)) : '—' }}</td>
           <td><a class="link" href="{{ route('erp.invoices.show',$r->invoice_id) }}">{{ $r->invoice_no }}</a></td>
           <td>
             @if($r->job_card_id)
             <a class="link" href="{{ route('erp.job-cards.show',$r->job_card_id) }}">{{ $r->job_no?:'#'.$r->job_card_id }}</a>
             @else —
             @endif
           </td>
           <td>{{ $r->customer_name?:'—' }}<small>{{ $r->vehicle_reg_no?:'—' }}</small></td>
           <td><strong>{{ $r->description?:'—' }}</strong><small>{{ $r->rot_code?:'—' }}</small></td>
           <td class="num">{{ number_format((float)$r->standard_hours,2) }}</td>
           <td class="num">₹{{ number_format((float)$r->labour_rate,2) }}</td>
           <td class="num">{{ number_format((float)$r->discount_percent,2) }}%</td>
           <td class="num">₹{{ number_format((float)$r->discount_amount,2) }}</td>
           <td class="num"><strong>₹{{ number_format((float)$r->total,2) }}</strong></td>
           <td><a class="link" href="{{ route('erp.invoices.show',$r->invoice_id) }}">Invoice</a></td>
         </tr>
       @empty
         <tr><td colspan="11" style="padding:27px;text-align:center;color:#8093a4">No invoiced {{ $label }} entries match these filters.</td></tr>
       @endforelse
     </tbody>
   </table>
 </div>
 <div class="pagination">{{ $rows->links() }}</div>
 <p class="foot"><strong>{{ $label }} Sales Register · AM259.</strong> This report excludes reversed and void invoices, and unbilled Job Card lines. It displays saved/current JC line net amounts (pre-GST), not cash collected or operating profit. OSL vendor cost is not deducted in this sales-only register. The invoice-level Sales Register remains the authoritative document history, including separately signed credit notes.</p>
</div>
@endsection
