@extends('layouts.erp')
@section('title','GRN Stock-In Verification · ASSAM MOTORS')
@section('content')
{{-- AM265_GRN_FULL_EDIT: audited Vendor, Job Card, Challan/date and item correction history. --}}
<style>
.am261{max-width:1460px;margin:0 auto;color:#163e5d;font:12px Inter,"Segoe UI",Arial,sans-serif;padding-bottom:28px}
.am261 *{box-sizing:border-box}.am261 h1{font-size:23px;margin:5px 0;color:#173d59}
.am261 h2{font-size:16px;margin:4px 0 13px;color:#1d5474}
.am261 .panel{background:#fff;border:1px solid #d7e5ee;border-radius:12px;padding:17px;margin:12px 0}
.am261 .details{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:11px}
.am261 .small{color:#758b9d;font-size:10px;font-weight:800;line-height:1.6}
.am261 .value{color:#173f5c;font-size:13px;font-weight:900;overflow-wrap:anywhere;margin-top:4px}
.am261 .actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:13px 0}
.am261 .btn{padding:10px 13px;min-height:38px;border:1px solid #c6d9e6;border-radius:8px;color:#1b557b;background:#f4f9fc;text-decoration:none;font-size:11px;font-weight:850;display:inline-flex;align-items:center}
.am261 .btn.main{background:#0b766c;border-color:#0b766c;color:white}
.am261 .status{border-radius:8px;padding:13px;margin:12px 0;line-height:1.7}
.am261 .status.good{color:#166950;border:1px solid #bce0d0;background:#effaf4}
.am261 .status.bad{color:#a04242;border:1px solid #e9c2c2;background:#fff4f2}
.am261 .tablewrap{border:1px solid #dce8ee;border-radius:10px;overflow:auto}
.am261 table{border-collapse:collapse;width:100%;min-width:980px}
.am261 th{background:#173f68;color:#fff;font-size:10px;text-align:left;padding:11px;white-space:nowrap}
.am261 td{font-size:11px;border-bottom:1px solid #eaf0f5;padding:11px}
.am261 tbody tr:nth-child(even){background:#f8fbfd}
.am261 .num{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
.am261 .ok{color:#0c8068;font-weight:850}.am261 .bad{color:#b54747;font-weight:850}
.am261 .note{color:#778c9e;font-size:11px;line-height:1.6;margin-top:13px}
@media(max-width:800px){.am261 .details{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:510px){.am261 .details{grid-template-columns:1fr 1fr}.am261 h1{font-size:19px}}
</style>
<div class="am261">
  <small style="color:#0e8275;font-weight:900;letter-spacing:.12em">ASSAM MOTORS · GOODS RECEIPT VERIFICATION</small>
  <h1>Confirmed GRN — Stock-In & Item Ledger</h1>
  @if(session('success'))<div class="status good" role="status">{{ session('success') }}</div>@endif
  <section class="panel">
    <div class="details">
      <div><div class="small">GRN Number</div><div class="value">{{ $receipt->grn_no }}</div></div>
      <div><div class="small">Vendor</div><div class="value">{{ $receipt->vendor_name }}</div></div>
      <div><div class="small">Vendor Challan</div><div class="value">{{ $receipt->challan_no }}</div></div>
      <div><div class="small">Date Physically Received</div><div class="value">{{ $receipt->receipt_date }}</div></div>
      <div><div class="small">Purchase Order</div><div class="value">{{ $receipt->po_no?:'Urgent / No PO' }}</div></div>
      <div><div class="small">Against Job Card</div><div class="value">{{ $receipt->job_no?:'STOCK' }}</div></div>
      <div><div class="small">Vehicle</div><div class="value">{{ $receipt->vehicle_reg_no?:'—' }}</div></div>
      <div><div class="small">Vendor Purchase Bill</div><div class="value">Match Pending Quantity in Procurement Register</div></div>
    </div>
  </section>
  @if($allVerified)
    <div class="status good"><strong>GRN Stock-In Verified:</strong> Every received Part quantity reconciles to the original <code>GRN_RECEIPT_IN</code> plus any audited <code>GRN_EDIT_IN / GRN_EDIT_OUT</code> correction movements. The Item Transaction Ledger retains all events. This does not verify the previously unknown opening stock.</div>
  @else
    <div class="status bad"><strong>Stock Movement Mismatch:</strong> At least one receipt line has no matching inventory movement, has a duplicate movement, or its quantity differs. Do not enter a second GRN for this challan. Investigate the receipt and inventory movement audit before Parts issue.</div>
  @endif
  @if($hasUnverifiedOpenings)
    <div class="status" style="color:#7e571f;border:1px solid #eed3a3;background:#fff8e8"><strong>Opening Stock Unverified — Reconciliation Pending:</strong> One or more received Parts had a blank/null opening balance. Stock-In was posted from <strong>physical received quantity using a provisional zero baseline</strong>, and an AM262 exception was recorded with the GRN. This is <strong>not a confirmed historical zero opening</strong>. Please verify/reconcile older stock separately; do not submit a duplicate GRN for this challan.</div>
  @endif
  <section class="panel">
    <div class="actions"><a class="btn main" href="{{ route('erp.procurement.grn.edit',['grn'=>$receipt->id]) }}">Edit Complete GRN · Vendor / JC / Challan / Dates / Items</a></div>
    <h2>Received Item Details — Open Individual Transaction Ledger</h2>
    <div class="tablewrap"><table>
      <thead><tr><th>Part Code / Name</th><th class="num">Received Qty</th><th class="num">Actual Stock-In Movement</th><th class="num">Current ERP Stock</th><th>Movement Status</th><th>Prior Opening</th><th>Item Transaction Ledger</th></tr></thead>
      <tbody>
      @forelse($lines as $line)
      <tr>
        <td><strong>{{ $line->part_name }}</strong><div class="small">{{ $line->part_code }} / {{ $line->unit }}</div></td>
        <td class="num">{{ number_format((float)$line->qty_received,3) }}</td>
        <td class="num">{{ number_format((float)$line->confirmed_stock_in,3) }}</td>
        <td class="num">{{ $line->stock_qty===null?'Not recorded':number_format((float)$line->stock_qty,3) }}</td>
        <td>@if($line->movement_verified)<span class="ok">GRN Verified · {{ $line->movement_count }} movement(s)</span>
            @else<span class="bad">Check movement ({{ $line->movement_count }})</span>@endif</td>
        <td>@if($line->opening_unverified)<span class="bad">NOT RECORDED</span>
          <div class="small">Provisional opening 0; reconciliation pending</div>
          @else<span class="ok">Existing recorded balance used</span>@endif</td>
        <td><a class="btn" href="{{ route('erp.masters.item-ledger',['type'=>'PART','id'=>$line->part_master_id,'q'=>$line->part_code]) }}">View Item Ledger</a></td>
      </tr>
      @empty
      <tr><td colspan="7" style="text-align:center;padding:22px">No received Part lines recorded in this GRN.</td></tr>
      @endforelse
      </tbody>
    </table></div>
    <p class="note">Item Ledger shows a saved GRN under <strong>STOCK · GRN_RECEIPT_IN</strong> and, when corrected, separate <strong>STOCK · GRN_EDIT_IN / GRN_EDIT_OUT</strong> events using the same GRN Number as reference. If you see only a Purchase Order number but no GRN number here, stock has NOT been received. Purchase Bill matching later will not create a second Stock-In movement. Entries with unverified opening retain the AM262 audit exception so the original unknown opening is never mistaken for verified zero.</p>
  </section>
  @if($receipt->job_card_id)
    <section class="panel">
      <h2>Linked Job Card — Issue All Parts Stock Readiness</h2>
      @if($shortages->isEmpty())
        <div class="status good"><strong>All planned Parts have sufficient recorded stock at this moment.</strong> This is a stock-only check; Job Card WIP status and any pending movement guards are still validated at Issue All Parts. Parts are NOT auto-issued by GRN.</div>
      @else
        <div class="status bad"><strong>Issue All Parts will remain blocked</strong> until the following item master balances are resolved. A successful GRN receipt does not erase earlier negative issues.</div>
        <div class="tablewrap"><table>
          <thead><tr><th>Part</th><th class="num">JC Required</th><th class="num">Current ERP Stock</th><th class="num">Shortage</th></tr></thead>
          <tbody>
          @foreach($shortages as $item)
            <tr>
              <td><strong>{{ $item->part_name }}</strong></td>
              <td class="num">{{ number_format($item->required,3) }}</td>
              <td class="num">{{ $item->available===null?'NOT RECORDED':number_format($item->available,3) }}</td>
              <td class="num"><span class="bad">{{ number_format($item->shortage,3) }}</span></td>
            </tr>
          @endforeach
          </tbody>
        </table></div>
        <p class="note">Receive additional Parts only if physically delivered, or reconcile older negative/unrecorded stock with auditable evidence. Do not create a duplicate challan or falsify received quantity to bypass stock control.</p>
      @endif
      <a class="btn" href="{{ route('erp.job-cards.show',$receipt->job_card_id) }}">Open Job Card {{ $receipt->job_no }}</a>
    </section>
  @endif
  <section class="panel">
    <h2>GRN Item Correction History (Audit)</h2>
    @if($edits->isEmpty())
       <p class="note">No corrections recorded. The original GRN movements remain unchanged.</p>
    @else
       <div class="tablewrap"><table>
         <thead><tr><th>Correction</th><th>Part / Action</th><th class="num">Old Qty</th><th class="num">New Qty</th><th class="num">Change</th><th class="num">Old/ New Cost</th><th>Actor / Reason</th></tr></thead>
         <tbody>@foreach($edits as $edit)
           <tr>
             <td><strong>{{ $edit->edit_no }}</strong><small style="display:block">{{ $edit->created_at }}</small></td>
             <td>{{ $edit->part_name }}<small style="display:block">{{ $edit->action }}</small></td>
             <td class="num">{{ number_format((float)$edit->old_received_qty,3) }}</td>
             <td class="num">{{ number_format((float)$edit->new_received_qty,3) }}</td>
             <td class="num">{{ number_format((float)$edit->qty_delta,3) }}</td>
             <td class="num">{{ number_format((float)$edit->old_provisional_cost,2) }} → {{ number_format((float)$edit->new_provisional_cost,2) }}</td>
             <td>{{ $edit->actor }}<small style="display:block">{{ $edit->reason }}</small></td>
           </tr>
         @endforeach</tbody>
       </table></div>
    @endif
  </section>

  <section class="panel">
    <h2>Complete GRN Header Correction History</h2>
    @if($headerEdits->isEmpty())
      <p class="note">No Vendor / Job Card / Challan / date / remarks corrections recorded.</p>
    @else
      <div class="tablewrap"><table>
        <thead><tr><th>Changed On</th><th>Edited Fields</th><th>Previous GRN Header</th><th>Updated GRN Header</th><th>Actor & Reason</th></tr></thead>
        <tbody>
        @foreach($headerEdits as $entry)
          @php
             $before=json_decode($entry->before_json,true)??[];
             $after=json_decode($entry->after_json,true)??[];
             $changed=array_filter(explode(',',(string)$entry->changed_fields));
          @endphp
          <tr>
            <td><strong>{{ $entry->edit_no }}</strong><div class="small">{{ $entry->created_at }}</div></td>
            <td>@foreach($changed as $key)
              <div class="small" style="font-weight:800">{{ str_replace('_',' ',ucwords($key,'_')) }}</div>
            @endforeach</td>
            <td>@foreach($changed as $key)<div class="small"><strong>{{ $key }}:</strong>
                {{ $before[$key]===null?'NONE':(string)($before[$key]??'') }}</div>@endforeach</td>
            <td>@foreach($changed as $key)<div class="small"><strong>{{ $key }}:</strong>
                {{ $after[$key]===null?'NONE':(string)($after[$key]??'') }}</div>@endforeach</td>
            <td><strong>{{ $entry->actor }}</strong><div class="small">{{ $entry->reason }}</div></td>
          </tr>
        @endforeach
        </tbody></table></div>
      <p class="note">Audit values for Vendor and Job Card are the original Master IDs. The GRN details above show the currently linked Vendor name and Job Card number. Original stock received quantities and GRN identity remain traceable.</p>
    @endif
  </section>
  <div class="actions">
    <a class="btn main" href="{{ route('erp.procurement.index') }}">Procurement / Challan Register</a>
    <a class="btn" href="{{ route('erp.procurement.receive') }}">Receive Another Challan</a>
    <a class="btn" href="{{ route('erp.procurement.bill.create',['vendor_id'=>$receipt->vendor_id]) }}">Match Vendor Purchase Bill</a>
    @if($receipt->job_card_id)<a class="btn" href="{{ route('erp.job-cards.show',$receipt->job_card_id) }}">Open Linked Job Card</a>@endif
  </div>
</div>
@endsection
