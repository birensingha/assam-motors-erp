{{-- FIX65 — Presentation-only NEW ADMIN customer Labour billing. Original JC/ROT state remains authoritative. --}}
<style id="AM65_CUSTOMER_LABOUR_PROTOTYPE">
.am65{border:1px solid #d2def0;border-radius:13px;overflow:hidden;background:#fff;margin:12px 0 15px;color:#18375b}
.am65 *{box-sizing:border-box}
.am65-titlebar{display:flex;gap:12px;justify-content:space-between;align-items:center;padding:14px 16px;background:linear-gradient(105deg,#173d72,#2861a5);color:#fff;flex-wrap:wrap}
.am65-titlebar h3{font-weight:850;color:#fff;margin:0;font-size:16px}
.am65-titlebar p{font-size:11px;margin:4px 0 0;color:#dae9fc;line-height:1.35}
.am65-tag{display:inline-flex;border-radius:30px;align-items:center;gap:6px;padding:6px 10px;background:#e8f0ff;color:#194b98;font-weight:850;font-size:11px}
.am65-titlebar .am65-tag{color:#153d6b;background:#e3f3ff}
.am65-toolbar{display:flex;gap:9px;flex-wrap:wrap;justify-content:space-between;align-items:center;padding:10px 15px;border-bottom:1px solid #e3ecfa;background:#f9fbff}
.am65-search{flex:1;min-width:180px;max-width:350px;padding:9px 10px;border:1px solid #c6d6ea;border-radius:7px;background:white;font:inherit;font-size:12px}
.am65-search:focus{outline:2px solid #84b7fc}
.am65-toolbar-actions{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.am65-button{display:inline-flex;justify-content:center;align-items:center;gap:5px;padding:8px 11px;border:1px solid #bfd2ec;background:#fff;color:#1857a7!important;border-radius:7px;text-decoration:none!important;font-size:11px;font-weight:800;white-space:nowrap;min-height:34px}
.am65-button.green{background:#13774a;border-color:#13774a;color:#fff!important}
.am65-button.orange{background:#fff3e1;border-color:#efd2aa;color:#895316!important}
.am65-table-scroll{overflow:auto;max-width:100%;border-top:0}
.am65-table{border-collapse:separate;border-spacing:0;width:100%;min-width:1100px;font-size:12px;color:#163354}
.am65-table th{background:#e9f1ff;color:#2b568c;font-size:10px;text-transform:uppercase;letter-spacing:.045em;text-align:left;border-bottom:1px solid #d1e1f3;border-right:1px solid #e2ebf7;padding:10px 9px;position:sticky;top:0;z-index:2;white-space:nowrap}
.am65-table th:last-child{border-right:0}
.am65-table th.am65-taxhead{background:#fff3e2;color:#8a5719}
.am65-table td{padding:10px 9px;vertical-align:middle;border-bottom:1px solid #e7edf5;background:#fff}
.am65-table tr:hover td{background:#f6faff}
.am65-table .am65-num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
.am65-table td.am65-number{font-size:11px;color:#7186a1;font-weight:800}
.am65-service{min-width:225px}
.am65-service strong{display:block;font-size:12px;color:#173c70}
.am65-service .am65-desc{font-size:12px;font-weight:750;color:#153757;max-width:360px;white-space:normal;line-height:1.45;margin:3px 0}
.am65-service .am65-meta{display:block;color:#70849e;font-size:11px;line-height:1.45}
.am65-service .am65-meta b{color:#526d8b}
.am65-totalcell{color:#155e48!important;background:#f0f9f4!important;font-weight:900!important;font-size:13px}
.am65-muted{color:#7b889a}
.am65-billstatus{display:inline-flex;padding:4px 7px;font-size:10px;font-weight:850;border-radius:5px;background:#e6f7ed;color:#136843;white-space:nowrap}
.am65-billstatus.off{background:#fff0dd;color:#915d13}
.am65-table tfoot td{background:#f1f6fd!important;color:#153965;font-weight:850;border-top:1px solid #bbd2f0;border-bottom:0}
.am65-table tfoot .am65-final{background:#ddf5e8!important;color:#075838!important;font-size:14px}
.am65-footer{padding:11px 15px;display:flex;gap:8px;justify-content:space-between;align-items:center;flex-wrap:wrap;background:#f9fbff}
.am65-footer small{font-size:11px;color:#6f8197;line-height:1.55;max-width:720px}
.am65-footbadge{background:#fff3e2;color:#8b5819;padding:6px 9px;border-radius:7px;font-size:10px;font-weight:850}
.am65-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:9px;padding:11px 15px;border-bottom:1px solid #e5edfa}
.am65-sum{border:1px solid #dbe6f4;border-radius:9px;background:#fff;padding:10px 12px}
.am65-sum span{font-size:10px;letter-spacing:.04em;font-weight:800;color:#6983a2;text-transform:uppercase;display:block}
.am65-sum b{font-size:17px;color:#1b4e8b;display:block;margin:4px 0 0;white-space:nowrap}
.am65-sum.green{background:#effaf4;border-color:#c7e6d4}
.am65-sum.green b{color:#146f48}
.am65-workshop-toggle{border:1px solid #dae4ef;background:#fbfcff;border-radius:12px;margin:0 0 12px;overflow:hidden}
.am65-workshop-toggle>summary{display:flex;gap:8px;justify-content:space-between;align-items:center;list-style:none;cursor:pointer;padding:13px 16px;color:#194b81;font-weight:850;font-size:13px}
.am65-workshop-toggle>summary::-webkit-details-marker{display:none}
.am65-workshop-toggle>summary::after{content:'▾';color:#3767a0;font-size:18px}
.am65-workshop-toggle[open]>summary{background:#eff5fd;border-bottom:1px solid #d6e3f5}
.am65-workshop-toggle[open]>summary::after{content:'▴'}
.am65-workshop-toggle>summary small{font-size:11px;color:#72839a;font-weight:500}
.am65-workshop-toggle .rot-list{padding:10px!important}
@media(max-width:900px){.am65-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.am65-titlebar h3{font-size:14px}}
@media(max-width:450px){.am65-sum b{font-size:14px}.am65-footer{align-items:stretch}.am65-titlebar{padding:12px}.am65-toolbar-actions{width:100%}.am65-button{flex:1}}
</style>
@php
  $am65ShowEdit=in_array(strtolower(trim((string)$job->status)),['wip','in progress','in-progress','work in progress'],true) && empty($job->invoice_id);
  $am65Billable=$labour->filter(fn($l)=>!isset($l->billing_included)||(int)$l->billing_included===1);
  $am65BillableTaxable=(float)$am65Billable->sum(fn($l)=>(float)($l->total??0));
  $am65BillableGst=(float)$am65Billable->sum(fn($l)=>round((float)($l->total??0)*(float)($l->gst_percent??0)/100,2));
  $am65BillableDiscount=(float)$am65Billable->sum(fn($l)=>(float)($l->discount_amount??0));
  $am65LineCount=$am65Billable->count();
  $am65Excluded=$labour->count()-$am65LineCount;
@endphp
<section class="am65" id="am65-customer-labour">
 <div class="am65-titlebar">
   <div>
     <h3>Labour / ROT — Customer Billing</h3>
     <p>Customer ko charge kiye gaye labour ka item-wise summary · Same Job Card {{ $job->job_no }}</p>
   </div>
   <span class="am65-tag">CUSTOMER BILLING · {{ $am65LineCount }} ITEM(S)</span>
 </div>
 <div class="am65-summary">
   <div class="am65-sum"><span>Labour Items</span><b>{{ $am65LineCount }}</b></div>
   <div class="am65-sum"><span>Labour Taxable</span><b>₹{{ number_format($am65BillableTaxable,2) }}</b></div>
   <div class="am65-sum"><span>GST Preview</span><b>₹{{ number_format($am65BillableGst,2) }}</b></div>
   <div class="am65-sum green"><span>Labour Customer Total</span><b>₹{{ number_format($am65BillableTaxable+$am65BillableGst,2) }}</b></div>
 </div>
 <div class="am65-toolbar">
   <input class="am65-search" type="search" id="am65-search" placeholder="Search ROT code / Labour / Mechanic" aria-label="Search Labour billing lines">
   <div class="am65-toolbar-actions">
     @if($am65ShowEdit)
       <a class="am65-button green" href="{{ route('erp.fix48.job-amounts',$job->id) }}">✎ Edit Labour Rate / Discount / GST</a>
       <a class="am65-button" href="{{ route('erp.job-cards.edit',$job->id) }}">Admin ROT Alteration →</a>
     @elseif(!empty($job->invoice_id))
       <a class="am65-button orange" href="{{ route('erp.job-cards.credit-correction',$job->id) }}">↩ Return to JC to Edit</a>
     @endif
   </div>
 </div>
 <div class="am65-table-scroll">
 <table class="am65-table" id="am65-table">
   <thead><tr>
     <th>SL</th>
     <th>Labour / ROT Service &amp; Details</th>
     <th>Qty</th>
     <th>Std Hrs</th>
     <th>Rate ₹</th>
     <th>Disc %</th>
     <th>Discount ₹</th>
     <th>Taxable ₹</th>
     <th class="am65-taxhead">GST %</th>
     <th class="am65-taxhead">GST ₹</th>
     <th>Customer Total ₹</th>
     <th>Billing</th>
   </tr></thead>
   <tbody>
   @forelse($labour as $line)
     @php
       $am65Rot=$rotByLabour->get($line->id);
       $am65Taxable=(float)($line->total??0);
       $am65Tax=round($am65Taxable*(float)($line->gst_percent??0)/100,2);
       $am65Included=!isset($line->billing_included) || (int)$line->billing_included===1;
     @endphp
     <tr data-am65-line data-am65-search="{{ strtolower(($line->rot_code??'').' '.($line->description??'').' '.($am65Rot->staff_name??($line->mechanic_name??''))) }}">
       <td class="am65-number">{{ $loop->iteration }}</td>
       <td class="am65-service">
         <strong>{{ $line->rot_code?:'LABOUR' }}</strong>
         <div class="am65-desc">{{ $line->description?:'Service Labour' }}</div>
         <span class="am65-meta"><b>Mechanic:</b> {{ $am65Rot->staff_name??($line->mechanic_name?:'Not assigned') }}
           · <b>ROT:</b> {{ $am65Rot->status??($line->execution_status??'Planned') }}</span>
         @if($am65Rot)
           <span class="am65-meta"><b>Actual productive:</b> {{ $fmtSeconds($am65Rot->total_seconds) }}</span>
         @endif
         @if(trim((string)($line->note??''))!=='')
           <span class="am65-meta"><b>Note:</b> {{ $line->note }}</span>
         @endif
       </td>
       <td class="am65-num">1</td>
       <td class="am65-num">{{ number_format((float)($line->standard_hours??0),2) }}</td>
       <td class="am65-num">₹{{ number_format((float)($line->labour_rate??0),2) }}</td>
       <td class="am65-num">{{ number_format((float)($line->discount_percent??0),2) }}%</td>
       <td class="am65-num">₹{{ number_format((float)($line->discount_amount??0),2) }}</td>
       <td class="am65-num">₹{{ number_format($am65Taxable,2) }}</td>
       <td class="am65-num">{{ number_format((float)($line->gst_percent??0),2) }}%</td>
       <td class="am65-num">₹{{ number_format($am65Tax,2) }}</td>
       <td class="am65-num am65-totalcell">₹{{ number_format($am65Taxable+$am65Tax,2) }}</td>
       <td><span class="am65-billstatus {{ $am65Included?'':'off' }}">{{ $am65Included?'INCLUDED':'NOT BILLED' }}</span></td>
     </tr>
   @empty
     <tr><td colspan="12" style="padding:20px;text-align:center;color:#7a8fa9">No Labour / ROT items added.</td></tr>
   @endforelse
   </tbody>
   <tfoot>
    <tr>
      <td colspan="6">Customer Labour — Billing Summary</td>
      <td class="am65-num">₹{{ number_format($am65BillableDiscount,2) }}</td>
      <td class="am65-num">₹{{ number_format($am65BillableTaxable,2) }}</td>
      <td class="am65-num">—</td>
      <td class="am65-num">₹{{ number_format($am65BillableGst,2) }}</td>
      <td class="am65-num am65-final">₹{{ number_format($am65BillableTaxable+$am65BillableGst,2) }}</td>
      <td>{{ $am65LineCount }} billed</td>
    </tr>
   </tfoot>
 </table>
 </div>
 <div class="am65-footer">
   <small>Amounts show saved JC labour taxable values plus an indicative item-wise GST preview. Already-approved original invoice / final invoice ledger remains authoritative. ROT timing and billing are independent controls.</small>
   @if($am65Excluded>0)<span class="am65-footbadge">{{ $am65Excluded }} EXCLUDED FROM BILLING</span>@endif
 </div>
</section>
<script id="AM65_SEARCH_LABOUR">
(()=>{
const input=document.getElementById('am65-search'),table=document.getElementById('am65-table');
if(!input||!table)return;
const lines=[...table.querySelectorAll('[data-am65-line]')];
input.addEventListener('input',()=>{
  const terms=input.value.toLowerCase().trim().split(/\s+/).filter(Boolean);
  lines.forEach(tr=>{tr.hidden=!terms.every(term=>tr.dataset.am65Search.includes(term));});
});
})();
</script>
