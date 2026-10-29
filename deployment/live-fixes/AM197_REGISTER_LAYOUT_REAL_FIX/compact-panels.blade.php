{{-- AM197: real structural replacement of FIX62 selected-JC preview and totals/filters.
     All existing route references, record bindings, JS target IDs and finance guard
     data remain unchanged. No separate/supplementary submit forms are added. --}}
<style id="AM197_REGISTER_REAL_LAYOUT">
#am62-admin-job-cards #am62-selected.am197-preview{
 display:block!important;box-sizing:border-box!important;padding:9px 12px!important;
 margin:8px 0!important;border:1px solid #d3e2f3!important;border-radius:10px!important;
 background:#fff!important;max-height:none!important;height:auto!important;
}
#am62-admin-job-cards #am62-selected .am197-preview-top{
 display:flex!important;justify-content:space-between!important;align-items:center!important;
 flex-wrap:wrap!important;gap:8px 12px!important;padding:0!important;margin:0!important;
}
#am62-admin-job-cards #am62-selected .am197-preview-title{
 display:flex!important;gap:9px!important;align-items:center!important;min-width:0!important;
}
#am62-admin-job-cards #am62-selected .am197-preview-title h2{
 margin:0!important;font-size:13px!important;line-height:1.35!important;
 font-weight:850!important;color:#153356!important;overflow-wrap:anywhere!important;
}
#am62-admin-job-cards #am62-selected .am197-actions{
 display:flex!important;flex-wrap:wrap!important;gap:5px!important;
 align-items:center!important;justify-content:flex-start!important;margin:0!important;
}
#am62-admin-job-cards #am62-selected .am197-actions .am62-white,
#am62-admin-job-cards #am62-selected .am197-actions .am62-danger{
 display:inline-flex!important;min-height:29px!important;height:29px!important;
 align-items:center!important;justify-content:center!important;
 font-size:10px!important;line-height:1.1!important;font-weight:800!important;
 padding:5px 9px!important;margin:0!important;border-radius:6px!important;
 text-decoration:none!important;white-space:nowrap!important;
}
#am62-admin-job-cards #am62-selected .am197-data{
 display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;
 align-items:center!important;gap:7px 14px!important;
 margin:7px 0 0!important;padding:7px 0 0!important;
 border-top:1px solid #e5edf7!important;
}
#am62-admin-job-cards #am62-selected .am197-data div{min-width:0!important}
#am62-admin-job-cards #am62-selected .am197-data small{
 display:block!important;margin:0 0 2px!important;font-size:9px!important;
 line-height:1.2!important;font-weight:650!important;color:#697e97!important;
 text-transform:uppercase!important;letter-spacing:.025em!important;
}
#am62-admin-job-cards #am62-selected .am197-data strong{
 display:block!important;margin:0!important;font-size:11px!important;
 line-height:1.4!important;font-weight:800!important;color:#153356!important;
 overflow-wrap:anywhere!important;
}
#am62-admin-job-cards #am62-selected .am197-finance-warning{
 display:block!important;margin:7px 0 0!important;padding:6px 9px!important;
 border:1px solid #f2d69f!important;border-radius:6px!important;background:#fff8e9!important;
 color:#80541e!important;font-size:10px!important;line-height:1.4!important;
}
#am62-admin-job-cards #am62-selected .am197-finance-warning[hidden]{
 display:none!important;
}
#am62-admin-job-cards #am62-selected .am197-finance-rules{
 display:block!important;margin:5px 0 0!important;padding:0!important;
 border:0!important;background:transparent!important;font-size:10px!important;
}
#am62-admin-job-cards #am62-selected .am197-finance-rules > summary{
 display:list-item!important;cursor:pointer!important;color:#35638e!important;
 font-size:10px!important;font-weight:650!important;padding:0!important;
 background:transparent!important;border:0!important;
}
#am62-admin-job-cards #am62-selected .am197-finance-rules p{
 margin:5px 0!important;color:#5e6f85!important;font-size:10px!important;line-height:1.4!important;
}
#am62-admin-job-cards #am197-filter-card.am197-filter-card{
 display:block!important;margin:8px 0!important;padding:8px 12px!important;
 height:auto!important;max-height:none!important;border:1px solid #d3e2f3!important;
 border-radius:10px!important;background:#fff!important;
}
#am62-admin-job-cards #am197-filter-card .am197-stats{
 display:grid!important;grid-template-columns:repeat(5,minmax(0,1fr))!important;
 gap:6px!important;margin:0 0 7px!important;padding:0!important;
}
#am62-admin-job-cards #am197-filter-card .am197-stat{
 display:flex!important;align-items:center!important;justify-content:space-between!important;
 gap:6px!important;min-width:0!important;height:36px!important;
 padding:6px 9px!important;margin:0!important;background:#f4f8ff!important;
 border:1px solid #e3ebf6!important;border-radius:6px!important;
 color:#2d4f79!important;font-size:9px!important;font-weight:750!important;
 text-transform:uppercase!important;
}
#am62-admin-job-cards #am197-filter-card .am197-stat b{
 flex:0 0 auto!important;display:inline!important;margin:0!important;
 color:#0e3f7e!important;font-size:16px!important;line-height:1!important;font-weight:850!important;
}
#am62-admin-job-cards #am197-filter-card .am197-filter-panel{
 display:block!important;margin:0!important;padding:0!important;border:0!important;background:transparent!important;
}
#am62-admin-job-cards #am197-filter-card .am197-filter-panel > summary{
 display:list-item!important;cursor:pointer!important;line-height:1.3!important;
 margin:1px 0 6px!important;font-size:11px!important;font-weight:800!important;
 color:#214b7f!important;border:0!important;background:transparent!important;
}
#am62-admin-job-cards #am197-filter-card .am197-filter-form{
 display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr)) auto auto!important;
 gap:6px!important;align-items:center!important;justify-content:stretch!important;
 margin:0!important;padding:0!important;width:100%!important;
}
#am62-admin-job-cards #am197-filter-card .am197-filter-form select{
 display:block!important;box-sizing:border-box!important;min-width:0!important;
 max-width:none!important;width:100%!important;height:33px!important;
 min-height:33px!important;padding:4px 8px!important;margin:0!important;
 color:#315276!important;border:1px solid #c8d9ef!important;border-radius:6px!important;
 background:#fff!important;font-size:11px!important;font-weight:500!important;
}
#am62-admin-job-cards #am197-filter-card .am197-filter-form button,
#am62-admin-job-cards #am197-filter-card .am197-filter-form a{
 display:inline-flex!important;box-sizing:border-box!important;align-items:center!important;
 justify-content:center!important;min-width:0!important;max-width:none!important;
 width:auto!important;height:33px!important;min-height:33px!important;
 margin:0!important;padding:6px 10px!important;border-radius:6px!important;
 font-size:10px!important;font-weight:750!important;text-decoration:none!important;
 white-space:nowrap!important;
}
@media(max-width:780px){
 #am62-admin-job-cards #am62-selected .am197-preview-top{align-items:flex-start!important;}
 #am62-admin-job-cards #am62-selected .am197-data{grid-template-columns:repeat(2,minmax(0,1fr))!important;}
 #am62-admin-job-cards #am62-selected .am197-data>div:last-child{grid-column:1 / -1!important;}
 #am62-admin-job-cards #am197-filter-card .am197-filter-form{
  grid-template-columns:repeat(3,minmax(0,1fr))!important;
 }
 #am62-admin-job-cards #am197-filter-card .am197-filter-form button,
 #am62-admin-job-cards #am197-filter-card .am197-filter-form a{width:100%!important;}
}
@media(max-width:570px){
 #am62-admin-job-cards #am197-filter-card .am197-stats{
  grid-template-columns:repeat(2,minmax(0,1fr))!important;
 }
 #am62-admin-job-cards #am197-filter-card .am197-filter-form{
  grid-template-columns:minmax(0,1fr) minmax(0,1fr)!important;
 }
 #am62-admin-job-cards #am197-filter-card .am197-filter-form select{
  grid-column:1 / -1!important;
 }
 #am62-admin-job-cards #am62-selected .am197-actions{gap:5px!important;}
}
</style>

<section class="am62-card am197-preview" id="am62-selected">
  <div class="am197-preview-top">
    <div class="am197-preview-title">
      <h2>Job Card — <span id="am62-title">{{ $am62Initial->job_no??'Select a record' }}</span></h2>
      <span class="am62-chip" id="am62-jc-status">{{ strtoupper((string)($am62Initial->status??'—')) }}</span>
    </div>
    <div class="am197-actions">
      <a class="am62-white" id="am62-open" href="{{ $am62Initial ? route('erp.job-cards.show',$am62Initial->id) : '#' }}">▣ Open Job Card</a>
      <a class="am62-white" id="am62-viewinvoice" href="{{ $am62InitialInv ? route('erp.invoices.show',$am62InitialInv->id) : '#' }}" @if(!$am62InitialInv) aria-disabled="true" @endif>▣ Original Invoice</a>
      <a class="am62-danger" data-am62-launch id="am62-return" href="{{ $am62Invoiced ? route('erp.job-cards.credit-correction',$am62Invoiced->id) : '#' }}" @if(!$am62Invoiced) aria-disabled="true" @endif>↩ RETURN TO JC</a>
      <a class="am62-white" href="{{ route('erp.money-receipts.index') }}">Money Receipts</a>
    </div>
  </div>
  <div class="am197-data">
    <div><small>Customer</small><strong id="am62-customer">{{ $am62Initial->customer_name??'—' }}</strong></div>
    <div><small>Vehicle</small><strong id="am62-vehicle">{{ $am62Initial?trim(($am62Initial->vehicle_make??'').' '.($am62Initial->vehicle_model??'')).' · '.$am62Initial->vehicle_reg_no:'—' }}</strong></div>
    <div><small>Original Invoice</small><strong id="am62-invoice">{{ $am62InitialInv ? $am62InitialInv->invoice_no.' · ₹'.number_format((float)$am62InitialInv->grand_total,2) : 'No issued invoice' }}</strong></div>
  </div>
  <div class="am197-finance-warning" id="am62-finance-msg" @if(!$am62Initial||!$am62Initial->am62_payment_linked) hidden @endif>
    Payment linked: original receipt must be preserved. Automated Return to JC is locked until Finance allocation reconciliation. No payment will be reversed by opening this panel.
  </div>
  <details class="am197-finance-rules">
    <summary>Return to JC — financial rules</summary>
    <p>Return opens a reason/confirmation screen. Only fully unpaid, validated invoices currently support one-step posting. Paid/partial receipt corrections remain locked.</p>
  </details>
</section>

<section class="am62-card am197-filter-card" id="am197-filter-card">
  <div class="am197-stats">
    <div class="am197-stat">Total JC <b>{{ $summary['total'] }}</b></div>
    <div class="am197-stat">Open <b>{{ $summary['open'] }}</b></div>
    <div class="am197-stat">WIP <b>{{ $summary['in_progress'] }}</b></div>
    <div class="am197-stat">Completed/Closed <b>{{ $summary['completed_closed'] }}</b></div>
    <div class="am197-stat">Invoiced <b>{{ $summary['invoiced'] }}</b></div>
  </div>
  <details class="am197-filter-panel" open>
    <summary>Filter Job Cards — Status / Service / Inspection</summary>
    <form class="am197-filter-form" method="get" action="{{ route('erp.job-cards.index') }}">
      <input type="hidden" name="q" value="{{ $filters['q'] }}">
      <select name="status" aria-label="Filter status">
        <option value="">All Status</option>
        @foreach(['open','in-progress','completed','closed','invoiced','cancelled'] as $s)
          <option value="{{ $s }}" @selected(strtolower($filters['status'])===$s)>{{ ucfirst($s) }}</option>
        @endforeach
      </select>
      <select name="service" aria-label="Filter service">
        <option value="">All Services</option>
        @foreach($services as $s)
          <option value="{{ $s }}" @selected($filters['service']===$s)>{{ $s }}</option>
        @endforeach
      </select>
      <select name="inspection" aria-label="Filter inspection">
        <option value="">All Inspection</option>
        @foreach($inspections as $s)
          <option value="{{ $s }}" @selected($filters['inspection']===$s)>{{ $s }}</option>
        @endforeach
      </select>
      <button type="submit" class="am62-blue">Apply Filters</button>
      <a class="am62-white" href="{{ route('erp.job-cards.index') }}">Reset</a>
    </form>
  </details>
</section>
