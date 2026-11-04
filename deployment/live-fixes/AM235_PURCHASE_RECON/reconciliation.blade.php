{{-- AM235_PURCHASE_RECONCILIATION: read-only JC and STOCK report --}}
@extends('layouts.erp')
@section('title','Parts Purchase Reconciliation · ASSAM MOTORS ERP')
@push('styles')
<style>
.am235-page{--navy:#17385f;--border:#dae4f0;--pale:#f5f8fd;color:#17324f}
.am235-head{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;margin:0 0 14px}
.am235-head h1{margin:0 0 4px;font-weight:800;font-size:22px;color:#16355c}
.am235-head p{margin:0;font-size:13px;color:#576b82}
.am235-actions{display:flex;gap:8px;flex-wrap:wrap}
.am235-actions a{border-radius:8px;text-decoration:none;padding:9px 12px;border:1px solid #c3d5e8;color:#173d6e;font-size:12px;font-weight:750;background:#fff}
.am235-actions a.primary{color:#fff;background:#183d73;border-color:#183d73}
.am235-filters{display:flex;align-items:flex-end;gap:9px;flex-wrap:wrap;background:#fff;border:1px solid var(--border);border-radius:11px;padding:13px;margin-bottom:14px}
.am235-filters label{display:flex;flex-direction:column;gap:4px;font-size:11px;color:#526781;font-weight:650}
.am235-filters input,.am235-filters select{height:35px;min-width:115px;border:1px solid #cad7e5;border-radius:7px;padding:6px 9px;background:#fff;color:#17324f;font-size:12px}
.am235-filters input[name=q]{min-width:min(320px,80vw)}
.am235-filters button{height:35px;border:0;background:#173d73;color:#fff;border-radius:7px;padding:0 15px;cursor:pointer;font-weight:700}
.am235-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:9px;margin:0 0 12px}
.am235-stat{padding:12px;background:#fff;border:1px solid var(--border);border-radius:10px}
.am235-stat strong{display:block;font-size:23px;color:#133c6a;font-variant-numeric:tabular-nums}
.am235-stat span{display:block;font-size:11px;color:#607189;line-height:1.4}
.am235-note{font-size:12px;line-height:1.5;color:#526b85;margin:0 0 11px;padding:8px 11px;background:#eff6fd;border-left:3px solid #3782bc}
.am235-table-scroll{background:#fff;border:1px solid #d8e4f0;border-radius:12px;overflow:auto}
.am235-table{border-collapse:collapse;width:100%;min-width:1030px;color:#17324f}
.am235-table th{background:#153b6d;color:#fff;font-size:11px;text-align:left;padding:11px 9px;white-space:nowrap;position:sticky;top:0;z-index:1}
.am235-table td{font-size:12px;padding:10px 9px;border-bottom:1px solid #e5edf6;vertical-align:top}
.am235-table tbody tr:hover{background:#f5faff}
.am235-table td.num,.am235-table th.num{text-align:right;font-variant-numeric:tabular-nums}
.am235-table .sub{display:block;font-size:10px;color:#657990;line-height:1.5;margin-top:3px}
.am235-table a{color:#175c9f;font-weight:700;text-decoration:none}
.am235-table a:hover{text-decoration:underline}
.am235-status{display:inline-flex;font-size:10px;line-height:1.3;font-weight:750;padding:5px 7px;border-radius:7px;white-space:nowrap}
.am235-status.LEDGER_REVIEW{background:#ffe7e7;color:#a32727}
.am235-status.PENDING_ISSUE,.am235-status.PARTIAL_ISSUE{background:#fff0cf;color:#8b5700}
.am235-status.DUE_BILLING,.am235-status.FITTING_REVIEW{background:#e8f2ff;color:#144f9b}
.am235-status.INVOICE_LINKED,.am235-status.STOCK_MATCHED{background:#e2f6ea;color:#136037}
.am235-footer{padding:13px 4px;font-size:12px}
@media(max-width:700px){.am235-head h1{font-size:19px}.am235-filters label{width:calc(50% - 8px)}.am235-filters label:first-child{width:100%}.am235-filters input,.am235-filters select{width:100%;min-width:0}}
</style>
@endpush
@section('content')
<div class="am235-page">
  <div class="am235-head">
    <div>
      <h1>Parts Purchase Reconciliation</h1>
      <p>STOCK Ledger and Job Card–wise Purchase / Issue / Billing Review</p>
    </div>
    <div class="am235-actions">
      <a href="{{ route('erp.purchases.index') }}">Purchase Register</a>
      <a class="primary" href="{{ route('erp.purchases.create') }}">+ New Parts Purchase</a>
    </div>
  </div>
  <form method="get" class="am235-filters">
    <label>Search JC / Vehicle / Part / Purchase
      <input type="search" name="q" maxlength="100" value="{{ $filters['q'] }}" placeholder="JC-2026-0011 / AS01HC0595 / Part...">
    </label>
    <label>Against
      <select name="against">
        <option value="ALL" @selected($filters['against']==='ALL')>All Purchases</option>
        <option value="JOB" @selected($filters['against']==='JOB')>JOB CARD</option>
        <option value="STOCK" @selected($filters['against']==='STOCK')>STOCK</option>
      </select>
    </label>
    <label>Status
      <select name="state">
        <option value="ALL" @selected($filters['state']==='ALL')>All Statuses</option>
        <option value="LEDGER_REVIEW" @selected($filters['state']==='LEDGER_REVIEW')>Ledger Review</option>
        <option value="PENDING_ISSUE" @selected($filters['state']==='PENDING_ISSUE')>Pending Issue</option>
        <option value="PARTIAL_ISSUE" @selected($filters['state']==='PARTIAL_ISSUE')>Partially Issued</option>
        <option value="DUE_BILLING" @selected($filters['state']==='DUE_BILLING')>Due for Billing</option>
        <option value="FITTING_REVIEW" @selected($filters['state']==='FITTING_REVIEW')>Fitting Review</option>
        <option value="INVOICE_LINKED" @selected($filters['state']==='INVOICE_LINKED')>Invoice Linked</option>
        <option value="STOCK_MATCHED" @selected($filters['state']==='STOCK_MATCHED')>Stock Receipt Matched</option>
      </select>
    </label>
    <label>From <input type="date" name="from" value="{{ $filters['from'] }}"></label>
    <label>To <input type="date" name="to" value="{{ $filters['to'] }}"></label>
    <button type="submit">Apply Filters</button>
  </form>

  <div class="am235-stats">
    <div class="am235-stat"><strong>{{ number_format($group_count) }}</strong><span>Linked JC / Part & STOCK / Part Groups</span></div>
    <div class="am235-stat"><strong>{{ $counts['PENDING_ISSUE']+$counts['PARTIAL_ISSUE'] }}</strong><span>Pending / Partial Parts Issue</span></div>
    <div class="am235-stat"><strong>{{ $counts['DUE_BILLING'] }}</strong><span>Due for Billing</span></div>
    <div class="am235-stat"><strong>{{ $counts['LEDGER_REVIEW'] }}</strong><span>Purchase Receipt / Stock Ledger Difference</span></div>
  </div>
  <p class="am235-note">
    <strong>Quantity method:</strong> Records are grouped by linked JC + Part (or STOCK + Part). Purchased and ledger quantities reflect all-time records for matching groups even when a date filter is used.
    Invoice Linked means the JC has an active invoice; item-wise billed quantity is not independently proven by the current invoice schema.
    @if($limited) The latest 10,000 matching purchase lines were inspected; narrow the filters for complete coverage. @endif
  </p>

  <div class="am235-table-scroll">
    <table class="am235-table">
      <thead>
        <tr>
          <th>Purchase / Vendor</th>
          <th>Against / Job Card</th>
          <th>Part</th>
          <th class="num">Purchased</th>
          <th class="num">Stock In</th>
          <th class="num">Issued</th>
          <th class="num">Fitted</th>
          <th class="num">Pending</th>
          <th>Invoice / Billing</th>
          <th>Reconciliation Status</th>
        </tr>
      </thead>
      <tbody>
      @forelse($entries as $entry)
        <tr>
          <td>
            <a href="{{ route('erp.purchases.show',$entry['purchase_anchor']) }}">{{ $entry['batch_no'] }}</a>
            <span class="sub">{{ $entry['latest_purchase'] }} · {{ $entry['count'] }} purchase line(s)</span>
            <span class="sub">{{ $entry['latest_vendor'] }}</span>
          </td>
          <td>
            @if($entry['job_id'])
              <a href="{{ route('erp.job-cards.show',$entry['job_id']) }}">{{ $entry['job_no'] ?: ('JC #'.$entry['job_id']) }}</a>
              <span class="sub">{{ $entry['vehicle_reg_no'] }}</span>
              <span class="sub">{{ $entry['customer_name'] }}</span>
            @else
              <strong>STOCK</strong>
              <span class="sub">Inventory / Stock Ledger</span>
            @endif
          </td>
          <td>
            <strong>{{ $entry['part_code'] }}</strong>
            <span class="sub">{{ $entry['part_name'] }}</span>
            @if(!$entry['job_id'])<span class="sub">Current stock: {{ number_format($entry['stock_qty'],3) }}</span>@endif
          </td>
          <td class="num">{{ number_format($entry['purchase_qty'],3) }}</td>
          <td class="num">{{ number_format($entry['received_qty'],3) }}</td>
          <td class="num">{{ $entry['job_id'] ? number_format($entry['issued_qty'],3) : '—' }}</td>
          <td class="num">{{ $entry['job_id'] ? number_format($entry['fitted_qty'],3) : '—' }}</td>
          <td class="num">{{ $entry['job_id'] ? number_format($entry['pending_qty'],3) : '—' }}</td>
          <td>
            @if($entry['invoice_id'])
              <a href="{{ route('erp.invoices.show',$entry['invoice_id']) }}">{{ $entry['invoice_no'] }}</a>
              <span class="sub">JC invoice exists — verify item billing</span>
            @elseif($entry['job_id'])
              <span class="sub">No active JC invoice yet</span>
            @else
              <span class="sub">Not applicable to STOCK</span>
            @endif
          </td>
          <td>
            <span class="am235-status {{ $entry['state'] }}">
              @switch($entry['state'])
                @case('LEDGER_REVIEW')Stock Ledger Review @break
                @case('PENDING_ISSUE')Pending Parts Issue @break
                @case('PARTIAL_ISSUE')Partially Issued @break
                @case('DUE_BILLING')Due for Billing @break
                @case('FITTING_REVIEW')Fitting Review @break
                @case('INVOICE_LINKED')Invoice Linked / Verify @break
                @case('STOCK_MATCHED')Stock Receipt Matched @break
              @endswitch
            </span>
            @if(abs($entry['ledger_difference']) > 0.001)
              <span class="sub">Receipt difference {{ number_format($entry['ledger_difference'],3) }}</span>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="10" style="text-align:center;padding:25px;color:#627890">No matching Parts Purchase reconciliation entries.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="am235-footer">{{ $entries->links() }}</div>
</div>
@endsection
