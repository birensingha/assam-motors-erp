@extends('layouts.erp')
@section('title','Job Card Register — ASSAM MOTORS NEW Admin Panel')
@section('topbar_title','Job Card Register')
@section('content')
<style id="AM62_PROTOTYPE_MATCH">
.am62-root{font-family:inherit;color:#112b51;max-width:1500px;margin:0 auto;padding-bottom:35px}
.am62-root *{box-sizing:border-box}
.am62-topline{display:flex;justify-content:space-between;gap:14px;align-items:center;margin:3px 0 15px}
.am62-topline h1{font-size:23px;color:#102b50;margin:0 0 4px;font-weight:850}
.am62-topline p{font-size:12px;color:#677f9a;margin:0}
.am62-actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.am62-danger,.am62-white,.am62-blue{display:inline-flex;align-items:center;justify-content:center;gap:5px;font-size:12px;min-height:37px;padding:8px 12px;border-radius:7px;font-weight:850;text-decoration:none!important;border:1px solid transparent;cursor:pointer;white-space:nowrap}
.am62-danger{background:#df2840;color:#fff!important}
.am62-danger:hover{background:#c71f36}
.am62-white{background:#fff;color:#15417a!important;border-color:#c9d7ef}
.am62-blue{background:#225ebc;color:#fff!important}
.am62-danger[aria-disabled="true"]{background:#b8bfd0;cursor:not-allowed;pointer-events:none}
.am62-card{background:#fff;border:1px solid #d6e2f4;border-radius:14px;box-shadow:0 2px 5px rgba(11,53,102,.035);margin-bottom:13px}
.am62-searchbar{display:flex;align-items:center;flex-wrap:wrap;gap:9px;padding:13px 15px}
.am62-searchbar form{display:flex;gap:8px;align-items:center;flex:1;flex-wrap:wrap}
.am62-searchbar input{height:39px;border:1px solid #c8d8ee;border-radius:8px;padding:8px 12px;font:inherit;font-size:12px;min-width:190px;flex:1;max-width:430px}
.am62-searchbar input:focus{outline:2px solid #74a7e9}
.am62-count{background:#e8efff;border-radius:999px;padding:5px 10px;color:#174593;font-size:11px;font-weight:850}
.am62-register{overflow:auto}
.am62-table{border-collapse:collapse;min-width:790px;width:100%;font-size:12px}
.am62-table th{text-align:left;text-transform:uppercase;letter-spacing:.065em;font-size:10px;background:#f0f5fe;border-bottom:1px solid #d6e1f3;color:#3e5e89;padding:12px 11px;white-space:nowrap}
.am62-table td{padding:11px;border-bottom:1px solid #e3ecf8;vertical-align:middle}
.am62-table tbody tr{cursor:pointer}
.am62-table tbody tr:hover,.am62-table tbody tr.am62-picked{background:#f6f9ff}
.am62-table .muted{display:block;color:#75879f;font-size:11px;margin-top:4px}
.am62-table a.am62-inv{font-weight:750;color:#1457a1}
.am62-chip{display:inline-block;padding:5px 9px;border-radius:999px;background:#e9f0ff;color:#255bc0;font-size:10px;font-weight:900;white-space:nowrap}
.am62-chip.am62-alert{background:#fff0d9;color:#995c08}
.am62-chip.am62-fin{background:#f3edf9;color:#654795}
.am62-btncell{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.am62-btncell .am62-danger,.am62-btncell .am62-white{min-height:31px;padding:6px 10px;font-size:11px}
.am62-preview{padding:15px 17px}
.am62-preview-head{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:12px}
.am62-preview-head h2{font-size:16px;margin:0;color:#102c54}
.am62-profile{display:grid;grid-template-columns:1fr 1fr 1fr;gap:18px;margin-bottom:14px}
.am62-profile small{display:block;text-transform:uppercase;color:#7888a0;font-size:10px;letter-spacing:.04em;margin-bottom:2px}
.am62-profile strong{font-size:12px;color:#17345a;line-height:1.4;overflow-wrap:anywhere}
.am62-notice{background:#fff8ea;border:1px solid #f4dfb2;border-radius:8px;color:#82511a;padding:9px 11px;font-size:12px;margin:8px 0}
.am62-profileactions{display:flex;gap:7px;align-items:center;flex-wrap:wrap}
.am62-more{padding:13px 17px;font-size:12px}
.am62-more summary{cursor:pointer;font-weight:800;color:#204574}
.am62-existing{display:grid;grid-template-columns:repeat(5,minmax(85px,1fr));gap:8px;margin:12px 0}
.am62-stat{background:#f5f8ff;border-radius:8px;padding:10px;font-size:11px}
.am62-stat b{display:block;font-size:17px;color:#15345e}
.am62-filters{display:flex;flex-wrap:wrap;gap:9px;align-items:center;margin:9px 0}
.am62-filters select{min-width:120px;max-width:200px;border:1px solid #c7d9ed;border-radius:7px;background:white;padding:8px}
.am62-footnote{font-size:11px;color:#6a7a90;margin-top:7px}
@media(max-width:820px){.am62-topline{align-items:flex-start;flex-wrap:wrap}.am62-profile{grid-template-columns:1fr 1fr}.am62-existing{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:530px){.am62-topline h1{font-size:19px}.am62-profile{grid-template-columns:1fr}.am62-topline .am62-danger{width:100%}.am62-searchbar input{max-width:none}.am62-profileactions .am62-danger{width:100%}}
</style>
@php
 $am62Invoiced = $rows->first(fn($r)=>!empty($r->am62_invoice) && strtolower(trim((string)$r->status))==='invoiced');
 $am62Initial = $am62Invoiced ?: $rows->first();
 $am62InitialInv = $am62Initial->am62_invoice ?? null;
 $am62InitialFinance = $am62Initial ? ($am62Initial->am62_finance_text ?? 'NO INVOICE') : 'NO RECORD';
@endphp
<div class="am62-root" id="am62-admin-job-cards">
 <div class="am62-topline">
   <div>
     <h1>Job Cards</h1>
     <p>Select an invoiced Job Card and click RETURN TO JC. Original invoice stays preserved.</p>
   </div>
   <div class="am62-actions">
     <a class="am62-white" href="{{ route('erp.job-cards.create') }}">＋ Create Job Card</a>
     <a class="am62-danger" id="am62-top-return" href="{{ $am62Invoiced ? route('erp.job-cards.credit-correction',$am62Invoiced->id) : '#' }}" @if(!$am62Invoiced) aria-disabled="true" @endif>↩ RETURN TO JC</a>
   </div>
 </div>

 <div class="am62-card am62-searchbar">
   <form method="get" action="{{ route('erp.job-cards.index') }}" id="am62-search-form">
      <input id="am62-search" name="q" value="{{ $filters['q'] }}" autocomplete="off" placeholder="Search JC / Vehicle / Customer / Invoice" aria-label="Search Job Card register">
      <button class="am62-white" type="submit">Search All</button>
   </form>
   <span class="am62-count" id="am62-count">{{ $rows->count() }} JOB CARDS</span>
 </div>

 <div class="am62-card am62-register" id="am62-register">
   <table class="am62-table">
     <thead><tr><th>Job Card</th><th>Customer</th><th>Vehicle</th><th>Original Invoice</th><th>JC Status</th><th>Finance Status</th><th>Actions</th></tr></thead>
     <tbody id="am62-tbody">
       @forelse($rows as $r)
       @php
        $am62Inv=$r->am62_invoice??null;
        $am62CanReview=$am62Inv && strtolower(trim((string)$r->status))==='invoiced';
       @endphp
       <tr tabindex="0" data-am62-row data-jobid="{{ $r->id }}"
          data-jc="{{ $r->job_no }}" data-customer="{{ $r->customer_name }}"
          data-vehicle="{{ trim(($r->vehicle_make??'').' '.($r->vehicle_model??'')) }} · {{ $r->vehicle_reg_no }}"
          data-status="{{ strtoupper((string)$r->status) }}"
          data-invoice="{{ $am62Inv->invoice_no??'' }}" data-invoiceid="{{ $am62Inv->id??'' }}"
          data-grand="{{ $am62Inv ? number_format((float)$am62Inv->grand_total,2) : '' }}"
          data-finance="{{ $r->am62_finance_text??'NO INVOICE' }}"
          data-review="{{ $am62CanReview ? route('erp.job-cards.credit-correction',$r->id) : '' }}"
          data-show="{{ route('erp.job-cards.show',$r->id) }}"
          data-invoicelink="{{ $am62Inv ? route('erp.invoices.show',$am62Inv->id) : '' }}"
          data-search="{{ strtolower($r->job_no.' '.$r->customer_name.' '.$r->customer_phone.' '.$r->vehicle_reg_no.' '.($am62Inv->invoice_no??'')) }}"
          @if($am62Initial && (int)$am62Initial->id===(int)$r->id) class="am62-picked" @endif>
        <td><a href="{{ route('erp.job-cards.show',$r->id) }}"><strong>{{ $r->job_no }}</strong></a><span class="muted">{{ $r->job_date ?: optional($r->created_at)->format('d M Y') }}</span></td>
        <td><strong>{{ $r->customer_name }}</strong><span class="muted">{{ $r->customer_phone }}</span></td>
        <td>{{ trim(($r->vehicle_make??'').' '.($r->vehicle_model??'')) }}<span class="muted">{{ $r->vehicle_reg_no }}</span></td>
        <td>@if($am62Inv)<a class="am62-inv" href="{{ route('erp.invoices.show',$am62Inv->id) }}">{{ $am62Inv->invoice_no }}</a><span class="muted">₹{{ number_format((float)$am62Inv->grand_total,2) }}</span>@else<span class="muted">— No issued invoice —</span>@endif</td>
        <td><span class="am62-chip">{{ strtoupper((string)$r->status) }}</span></td>
        <td><span class="am62-chip {{ !empty($r->am62_payment_linked)?'am62-alert':'am62-fin' }}">{{ $r->am62_finance_text??'NO INVOICE' }}</span></td>
        <td>
          <div class="am62-btncell">
            @if($am62CanReview)
              <a class="am62-danger" href="{{ route('erp.job-cards.credit-correction',$r->id) }}">↩ Return to JC</a>
            @endif
            <a class="am62-white" href="{{ route('erp.job-cards.show',$r->id) }}">Open JC</a>
            @if($am62Inv)<a class="am62-white" href="{{ route('erp.invoices.show',$am62Inv->id) }}">Invoice</a>@endif
          </div>
        </td>
       </tr>
       @empty
       <tr><td colspan="7" style="padding:18px;text-align:center">No Job Cards found. Adjust search filters or create a Job Card.</td></tr>
       @endforelse
     </tbody>
   </table>
 </div>

 <section class="am62-card am62-preview" id="am62-selected">
   <div class="am62-preview-head">
     <h2>Job Card — <span id="am62-title">{{ $am62Initial->job_no??'Select a record' }}</span></h2>
     <span class="am62-chip" id="am62-jc-status">{{ strtoupper((string)($am62Initial->status??'—')) }}</span>
   </div>
   <div class="am62-profile">
      <div><small>Customer</small><strong id="am62-customer">{{ $am62Initial->customer_name??'—' }}</strong></div>
      <div><small>Vehicle</small><strong id="am62-vehicle">{{ $am62Initial?trim(($am62Initial->vehicle_make??'').' '.($am62Initial->vehicle_model??'')).' · '.$am62Initial->vehicle_reg_no:'—' }}</strong></div>
      <div><small>Original Invoice</small><strong id="am62-invoice">{{ $am62InitialInv ? $am62InitialInv->invoice_no.' · ₹'.number_format((float)$am62InitialInv->grand_total,2) : 'No issued invoice' }}</strong></div>
   </div>
   <div class="am62-profileactions">
     <a class="am62-white" id="am62-open" href="{{ $am62Initial ? route('erp.job-cards.show',$am62Initial->id) : '#' }}">▣ Open Job Card</a>
     <a class="am62-white" id="am62-viewinvoice" href="{{ $am62InitialInv ? route('erp.invoices.show',$am62InitialInv->id) : '#' }}" @if(!$am62InitialInv) aria-disabled="true" @endif>▣ View Original Invoice</a>
     <a class="am62-danger" id="am62-return" href="{{ $am62Invoiced ? route('erp.job-cards.credit-correction',$am62Invoiced->id) : '#' }}" @if(!$am62Invoiced) aria-disabled="true" @endif>↩ RETURN TO JC</a>
     <a class="am62-white" href="{{ route('erp.money-receipts.index') }}">Money Receipts</a>
   </div>
   <div class="am62-notice" id="am62-finance-msg" @if(!$am62Initial||!$am62Initial->am62_payment_linked) hidden @endif>
      Payment linked: original receipt must be preserved. Automated return is unavailable until allocation reconciliation is implemented. Opening Return to JC will display Finance instructions, not reverse payment.
   </div>
   <p class="am62-footnote">Return opens a reason/confirmation screen. Only fully unpaid, validated invoices currently support one-step posting. Paid/partial receipt corrections remain locked.</p>
 </section>

 <details class="am62-card am62-more">
  <summary>Register totals & advanced filters (Status / Service / Inspection)</summary>
  <div class="am62-existing">
   <div class="am62-stat">TOTAL JC<b>{{ $summary['total'] }}</b></div>
   <div class="am62-stat">OPEN<b>{{ $summary['open'] }}</b></div>
   <div class="am62-stat">WIP<b>{{ $summary['in_progress'] }}</b></div>
   <div class="am62-stat">COMPLETED/CLOSED<b>{{ $summary['completed_closed'] }}</b></div>
   <div class="am62-stat">INVOICED<b>{{ $summary['invoiced'] }}</b></div>
  </div>
  <form class="am62-filters" method="get" action="{{ route('erp.job-cards.index') }}">
   <input type="hidden" name="q" value="{{ $filters['q'] }}">
   <select name="status"><option value="">All Status</option>@foreach(['open','in-progress','completed','closed','invoiced','cancelled'] as $s)<option value="{{ $s }}" @selected(strtolower($filters['status'])===$s)>{{ ucfirst($s) }}</option>@endforeach</select>
   <select name="service"><option value="">All Services</option>@foreach($services as $s)<option value="{{ $s }}" @selected($filters['service']===$s)>{{ $s }}</option>@endforeach</select>
   <select name="inspection"><option value="">All Inspection</option>@foreach($inspections as $s)<option value="{{ $s }}" @selected($filters['inspection']===$s)>{{ $s }}</option>@endforeach</select>
   <button type="submit" class="am62-blue">Apply Filters</button>
   <a class="am62-white" href="{{ route('erp.job-cards.index') }}">Reset</a>
  </form>
 </details>
</div>
<script id="AM62_SELECT_ROW">
(function(){
 const root=document.getElementById('am62-admin-job-cards');if(!root)return;
 const rows=Array.from(root.querySelectorAll('[data-am62-row]'));
 const get=id=>document.getElementById('am62-'+id);
 const first=rows.find(row=>row.classList.contains('am62-picked'));
 const top=get('top-return'),button=get('return'),invoiceBtn=get('viewinvoice'),openBtn=get('open'),notice=get('finance-msg');
 function destination(el,href){if(!el)return;el.href=href||'#';if(href){el.removeAttribute('aria-disabled');}else{el.setAttribute('aria-disabled','true');}}
 function select(row){
  if(!row)return;
  rows.forEach(x=>x.classList.toggle('am62-picked',x===row));
  const a=row.dataset;get('title').textContent=a.jc;get('customer').textContent=a.customer;get('vehicle').textContent=a.vehicle;
  get('invoice').textContent=a.invoice?a.invoice+' · ₹'+a.grand:'No issued invoice';
  get('jc-status').textContent=a.status;
  destination(button,a.review);destination(top,a.review);
  destination(invoiceBtn,a.invoicelink);destination(openBtn,a.show);
  notice.hidden=!(a.finance.includes('PAYMENT')||a.finance.includes('PAID'));
 }
 rows.forEach(row=>{
  row.addEventListener('click',event=>{if(event.target.closest('a,button,input,select'))return;select(row);});
  row.addEventListener('keydown',event=>{if(event.key==='Enter'&&event.target===row){event.preventDefault();select(row);}});
 });
 if(first)select(first);
 const search=get('search'),count=get('count');
 if(search)search.addEventListener('input',()=>{
  const query=search.value.trim().toLowerCase(),words=query.split(/\s+/).filter(Boolean);
  let visible=0;
  rows.forEach(row=>{const on=words.every(word=>row.dataset.search.includes(word));row.hidden=!on;if(on)visible++;});
  if(count)count.textContent=visible+' VISIBLE / '+rows.length+' LOADED';
 });
})();
</script>
@endsection
