@extends('layouts.erp')
@section('title','Billing Correction / Return to JC — ASSAM MOTORS')
@section('topbar_title','NEW ADMIN PANEL · Billing Correction')
@section('content')
<style id="AM63_BILLING_PROTOTYPE_V1">
.am63{max-width:1450px;margin:0 auto 35px;color:#183456;font-family:inherit}
.am63 *{box-sizing:border-box}
.am63-head{display:flex;justify-content:space-between;gap:13px;align-items:center;margin-bottom:16px;flex-wrap:wrap}
.am63-eyebrow{color:#607f9e;text-transform:uppercase;letter-spacing:1px;font-size:10px;font-weight:850}
.am63-head h1{font-size:23px;line-height:1.2;margin:4px 0 5px;color:#102a50;font-weight:850}
.am63-sub{color:#6e819a;font-size:12px}
.am63-actions{display:flex;flex-wrap:wrap;align-items:center;gap:7px}
.am63-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:10px 13px;border-radius:8px;border:1px solid #c9d9ec;background:white;color:#22517f;text-decoration:none!important;cursor:pointer;min-height:39px;font-size:12px;font-weight:850;line-height:1.2}
.am63-btn:hover{background:#eef5ff}
.am63-btn.primary{color:#fff;background:#1e62b8;border-color:#1e62b8}
.am63-btn.red{color:#fff;background:#db2d44;border-color:#db2d44}
.am63-btn.red:hover{background:#bc1d35}
.am63-btn[disabled]{cursor:not-allowed;background:#b3bac6!important;border-color:#b3bac6!important;color:#fff!important}
.am63-panel{border-radius:12px;border:1px solid #d6e2f0;background:#fff;box-shadow:0 2px 5px rgba(19,46,87,.035);margin-bottom:14px;overflow:hidden}
.am63-panelheader{padding:15px 17px;border-bottom:1px solid #e1e8f1;display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap}
.am63-panelheader h2{color:#14385f;font-size:15px;margin:0;font-weight:850}
.am63-content{padding:16px 17px}
.am63-banner{background:linear-gradient(110deg,#15365d,#1c4c85);color:white;padding:18px 21px;border-radius:13px;margin-bottom:15px;display:flex;align-items:center;justify-content:space-between;gap:17px;flex-wrap:wrap}
.am63-banner h2{color:#fff;font-size:19px;margin:3px 0 6px}
.am63-banner .caption{font-size:12px;color:#d9e7f8}
.am63-status{display:inline-flex;align-items:center;border-radius:30px;padding:5px 10px;color:#2855a6;background:#ebf1ff;font-size:10px;font-weight:850;text-transform:uppercase;letter-spacing:.03em}
.am63-status.warn{color:#855300;background:#fff1d6}
.am63-status.ok{color:#157347;background:#e4f7eb}
.am63-statgrid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:9px;margin-bottom:14px}
.am63-stat{border-radius:10px;border:1px solid #d9e6f4;background:#fff;padding:13px 14px;min-width:0}
.am63-stat label{display:block;font-weight:850;color:#6e819e;text-transform:uppercase;font-size:10px;letter-spacing:.055em}
.am63-stat b{display:block;font-size:18px;color:#183e75;margin:6px 0 3px;overflow-wrap:anywhere}
.am63-stat small{color:#71849c;font-size:11px}
.am63-layout{display:grid;grid-template-columns:minmax(0,1.8fr) minmax(290px,.9fr);gap:14px;align-items:start}
.am63-info{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.am63-info label{display:block;color:#7286a0;font-size:10px;letter-spacing:.035em;text-transform:uppercase;margin-bottom:5px;font-weight:750}
.am63-info strong{color:#17365d;font-size:12px;line-height:1.4;overflow-wrap:anywhere}
.am63-invoicebox{border-radius:9px;background:#f3f7fe;border:1px solid #dae6f6;padding:11px 13px;display:flex;flex-wrap:wrap;gap:10px;justify-content:space-between;margin-top:14px}
.am63-money{font-variant-numeric:tabular-nums}
.am63-callout{padding:12px 13px;background:#fff8eb;border:1px solid #f0d9ad;border-radius:9px;font-size:12px;color:#785321;line-height:1.6;margin:12px 0}
.am63-callout.ok{background:#effaf2;border-color:#cce6d2;color:#246441}
.am63-callout.info{background:#f1f6fd;border-color:#cdddf3;color:#1e5187}
.am63-strong{font-weight:850}
.am63-cta{background:#f8fbff;padding:15px;border-radius:10px;border:1px solid #dce6f5}
.am63-cta p{font-size:12px;line-height:1.55;color:#647990;margin:6px 0 11px}
.am63-steps{margin:0;padding:0;list-style:none}
.am63-steps li{display:flex;gap:10px;align-items:flex-start;padding:12px 0;border-bottom:1px solid #e4edf6}
.am63-steps li:last-child{border-bottom:0}
.am63-stepnumber{display:flex;justify-content:center;align-items:center;width:29px;height:29px;flex:0 0 29px;border-radius:50%;background:#e7f0ff;color:#215bbb;font-weight:850;font-size:13px}
.am63-stepinfo b{display:block;font-size:12px;color:#173859;margin:0 0 2px}
.am63-stepinfo small{color:#6f8095;font-size:11px;line-height:1.5}
.am63-tablewrap{overflow:auto}
.am63-table{width:100%;border-collapse:collapse;min-width:440px;font-size:12px}
.am63-table th{text-align:left;background:#f0f5fd;color:#486589;font-size:10px;text-transform:uppercase;padding:12px}
.am63-table td{padding:11px 12px;border-top:1px solid #edf1f6;vertical-align:top}
.am63-link{color:#225db6;font-weight:750;text-decoration:none}
.am63-link:hover{text-decoration:underline}
.am63-empty{font-size:12px;color:#72849a;padding:15px}
.am63-rotitem{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 0;border-bottom:1px solid #edf2fa;flex-wrap:wrap}
.am63-rotitem:last-child{border-bottom:0}
.am63-rotitem strong{display:block;font-size:12px;color:#153961}
.am63-rotitem small{display:block;color:#71839a;margin-top:3px;line-height:1.45}
.am63-rotform{width:100%;background:#f5f8fd;border:1px solid #dce7f5;padding:12px;border-radius:8px;margin-top:7px;display:grid;gap:9px}
.am63-rotform input,.am63-rotform textarea{width:100%;border:1px solid #bdcde1;border-radius:6px;padding:9px;font:inherit}
.am63-rotform label{font-size:11px;font-weight:750}
.am63-page-alert{padding:13px 16px;background:#fff4de;border:1px solid #f2dca9;border-radius:11px;margin-bottom:12px;color:#83581d;font-size:12px}
.am63-page-alert p{margin:5px 0 0}
.am63-page-alert.success{background:#eefaf2;border-color:#c9e7d1;color:#1f7347}
.am63-backlink{margin-top:10px;display:inline-flex}
.am63-shade{position:fixed;inset:0;z-index:2147482000;background:rgba(13,29,51,.72);display:flex;align-items:center;justify-content:center;padding:15px}
.am63-shade[hidden]{display:none!important}
.am63-dialog{width:min(620px,100%);max-height:94vh;overflow:auto;border:1px solid #d7e4f1;border-radius:16px;padding:20px;background:#fff;box-shadow:0 15px 75px #071b2a66}
.am63-dialoghead{display:flex;justify-content:space-between;gap:10px;align-items:center}
.am63-dialog h2{margin:0;font-size:20px;color:#12365e}
.am63-dialog p{font-size:12px;color:#62768f;line-height:1.55}
.am63-dialog label{font-size:12px;color:#274260;font-weight:800;display:block;margin-bottom:6px}
.am63-dialog textarea,.am63-dialog input[type=date]{width:100%;padding:11px;border-radius:8px;border:1px solid #b7cee6;background:#fff;color:#18365c;font:inherit;font-size:13px}
.am63-dialog textarea:focus,.am63-dialog input:focus{outline:2px solid #5993e5}
.am63-fieldgrid{display:grid;grid-template-columns:1fr 2fr;gap:10px;margin:13px 0}
.am63-consent{display:flex;gap:10px;align-items:flex-start;margin:10px 0 16px;font-size:12px;line-height:1.5;color:#4d647e}
.am63-consent input{margin-top:3px}
.am63-close{padding:5px 11px;border:1px solid #d3dfed;border-radius:7px;background:#f4f8fe;color:#284b72;font-size:21px;cursor:pointer}
.am63-small{font-size:11px;color:#70839c}
@media(max-width:1050px){.am63-layout{grid-template-columns:1fr}.am63-statgrid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:650px){.am63-head h1{font-size:19px}.am63-statgrid{grid-template-columns:repeat(2,minmax(0,1fr))}.am63-info{grid-template-columns:1fr 1fr}.am63-fieldgrid{grid-template-columns:1fr}.am63-banner{padding:16px}.am63-banner .am63-btn{width:100%}}
@media(max-width:420px){.am63-info{grid-template-columns:1fr}}
</style>
@php
  $am63Active=$invoices->first(fn($inv)=>strtolower(trim((string)$inv->status))!=='reversed');
  $am63Original=$am63Active?:$invoices->first();
  $am63JobStatus=strtolower(trim((string)$card->status));
  $am63InvoiceStatus=$am63Active?strtolower(trim((string)$am63Active->status)):'';
  $am63Gross=(float)($am63Original->grand_total??0);
  $am63Paid=(float)($am63Original->paid_amount??0);
  $am63Due=(float)($am63Original->balance_due??max(0,$am63Gross-$am63Paid));
  $am63HasReceipt=$am63Active && (
     abs((float)($am63Active->paid_amount??0))>0.009||
     abs((float)($am63Active->paid_total??0))>0.009||
     \Illuminate\Support\Facades\DB::table('customer_payments')->where('invoice_id',$am63Active->id)->exists()||
     \Illuminate\Support\Facades\DB::table('customer_payment_allocations')->where('invoice_id',$am63Active->id)->exists());
  $am63CanReturn=$am63Active && $am63JobStatus==='invoiced' &&
      $am63InvoiceStatus==='issued' && !$am63HasReceipt &&
      (int)($card->invoice_id??0)===(int)$am63Active->id &&
      !$notes->contains(fn($n)=>(int)$n->invoice_id===(int)$am63Active->id);
  $am63Wip=in_array($am63JobStatus,['wip','in-progress','in progress','work in progress'],true);
  $am63CanReopen=$am63JobStatus==='closed' && !$am63Active && $notes->isNotEmpty();
@endphp
<div class="am63" id="am63-billing">
 <header class="am63-head">
  <div>
   <div class="am63-eyebrow">Assam Motors / New Admin Panel / Job Card</div>
   <h1>Billing Correction · Return to JC</h1>
   <div class="am63-sub">One correction workspace · Original invoices and receipts remain preserved</div>
  </div>
  <div class="am63-actions">
   <a class="am63-btn" href="{{ route('erp.job-cards.index') }}">← Job Card Register</a>
   <a class="am63-btn" href="{{ route('erp.job-cards.show',$card->id) }}">Open Job Card</a>
  </div>
 </header>

 @if($errors->any())
 <div class="am63-page-alert" role="alert">
  <b>Action blocked — no transaction confirmed on this screen.</b>
  @foreach($errors->all() as $err)<p>{{ $err }}</p>@endforeach
 </div>
 @endif
 @if(session('success'))
 <div class="am63-page-alert success" role="status">{{ session('success') }}</div>
 @endif

 <section class="am63-banner">
  <div>
   <div class="am63-eyebrow" style="color:#bfd2ef">JOB CARD · FINANCIAL CORRECTION</div>
   <h2>{{ $card->job_no }} — {{ $card->vehicle_reg_no }}</h2>
   <div class="caption">{{ $card->customer_name }} · JC Status: {{ strtoupper((string)$card->status) }}</div>
  </div>
  <div class="am63-actions">
    @if($am63CanReturn)
     <button type="button" class="am63-btn red" id="am63-start-return">↩ RETURN TO JC</button>
    @elseif($am63HasReceipt)
     <button type="button" class="am63-btn red" disabled title="Payment linked — Finance reconciliation required">↩ RETURN TO JC · FINANCE HOLD</button>
    @elseif($am63Wip)
     <a href="{{ route('erp.job-cards.show',$card->id) }}" class="am63-btn primary">OPEN EDITABLE JC →</a>
    @else
     <a href="#am63-finance" class="am63-btn" style="background:#fff;color:#194271">VIEW FINANCIAL STATUS ↓</a>
    @endif
  </div>
 </section>

 <div class="am63-statgrid">
   <div class="am63-stat"><label>Original Invoice</label><b style="font-size:15px">{{ $am63Original->invoice_no??'Not Issued' }}</b><small>Original reference is never overwritten</small></div>
   <div class="am63-stat"><label>Invoice Amount</label><b class="am63-money">₹{{ number_format($am63Gross,2) }}</b><small>Stored original invoice total</small></div>
   <div class="am63-stat"><label>Amount Received</label><b class="am63-money">₹{{ number_format($am63Paid,2) }}</b><small>As recorded against original invoice</small></div>
   <div class="am63-stat"><label>Invoice Balance</label><b class="am63-money">₹{{ number_format($am63Due,2) }}</b><small>Stored balance on original invoice</small></div>
 </div>

 <div class="am63-layout">
  <div>
   <section class="am63-panel" id="am63-finance">
     <div class="am63-panelheader">
       <h2>Original Invoice & Finance Status</h2>
       <span class="am63-status {{ $am63HasReceipt?'warn':'' }}">{{ $am63Active?strtoupper((string)$am63Active->status):($am63Original?'FULLY CREDITED / HISTORICAL':'NO ACTIVE INVOICE') }}</span>
     </div>
     <div class="am63-content">
       <div class="am63-info">
         <div><label>Job Card</label><strong>{{ $card->job_no }}</strong></div>
         <div><label>Customer</label><strong>{{ $card->customer_name }}</strong></div>
         <div><label>Vehicle</label><strong>{{ $card->vehicle_reg_no }}</strong></div>
         <div><label>Original Invoice No</label><strong>{{ $am63Original->invoice_no??'—' }}</strong></div>
         <div><label>Invoice Status</label><strong>{{ $am63Original?strtoupper((string)$am63Original->status):'NOT ISSUED' }}</strong></div>
         <div><label>Job Card Status</label><strong>{{ strtoupper((string)$card->status) }}</strong></div>
       </div>
       @if($am63Original)
        <div class="am63-invoicebox">
         <div><div class="am63-small">Original amount (tax included)</div><strong>₹{{ number_format($am63Gross,2) }}</strong></div>
         <div><div class="am63-small">Received</div><strong>₹{{ number_format($am63Paid,2) }}</strong></div>
         <div><div class="am63-small">Outstanding</div><strong>₹{{ number_format($am63Due,2) }}</strong></div>
         <div><a class="am63-link" href="{{ route('erp.invoices.show',$am63Original->id) }}">View Original Invoice →</a></div>
        </div>
       @endif

       @if($am63HasReceipt)
       <div class="am63-callout">
        <b>FINANCE HOLD — Money Receipt linked</b>
        <div>Original receipt, its payment reference and customer ledger must be preserved. This page will not issue a Full Credit Note or reopen JC until the receipt allocation is reconciled. Paid/part-paid Return workflow is still pending.</div>
       </div>
       <div class="am63-actions">
          <a class="am63-btn" href="{{ route('erp.money-receipts.index') }}">Money Receipt Register →</a>
          <a class="am63-btn" href="{{ route('erp.job-cards.show',$card->id) }}">View JC Details</a>
       </div>
       @elseif($am63CanReturn)
        <div class="am63-callout info">
         <b>Ready for audited Return to JC</b>
         <div>Full Credit Note and WIP reopening will be processed together in one transaction. Original invoice stays stored. New corrected invoice must get a NEW invoice number.</div>
        </div>
        <button class="am63-btn red" type="button" data-am63-open>↩ RETURN TO JC — ONE ACTION</button>
       @elseif($am63CanReopen)
        <div class="am63-callout ok"><b>Credit Note already posted.</b> Original invoice is preserved. The Job Card is currently CLOSED and can be reopened with a reason.</div>
        <form action="{{ route('erp.job-cards.lifecycle.reopen',$card->id) }}" method="post" style="display:grid;gap:9px;max-width:620px">
         @csrf
         <label style="font-size:12px;font-weight:800">Reason for Reopening *</label>
         <textarea name="reason" required minlength="5" maxlength="1000" rows="2" style="border:1px solid #bacfe4;padding:10px;border-radius:8px;font:inherit" placeholder="Reason for WIP correction">{{ old('reason') }}</textarea>
         <div><button class="am63-btn primary" type="submit">Reopen Existing JC to WIP</button></div>
        </form>
       @elseif($am63Wip)
        <div class="am63-callout ok"><b>Job Card WIP — editing enabled.</b> Make required changes to Parts, Labour/ROT and OSL, then complete the ordinary QC/invoicing workflow.</div>
        <div class="am63-actions">
          <a class="am63-btn primary" href="{{ route('erp.job-cards.show',$card->id) }}">Open Editable Job Card</a>
          <a class="am63-btn" href="{{ route('erp.fix48.job-amounts',$card->id) }}">Labour Amounts / Discount</a>
        </div>
       @else
        <div class="am63-callout">Current status does not meet the single-step Return conditions. Only an INVOICED JC linked to a financially verified, unpaid ISSUED invoice may use automatic Return.</div>
       @endif
     </div>
   </section>

   <section class="am63-panel" id="am63-credit-history">
     <div class="am63-panelheader">
        <h2>Credit Note & Correction History</h2><span class="am63-status">{{ $notes->count() }} CREDIT NOTE(S)</span>
     </div>
     @if($notes->isNotEmpty())
       <div class="am63-tablewrap">
        <table class="am63-table">
         <thead><tr><th>Credit Note</th><th>Against Invoice</th><th>Date</th><th>Credit Amount</th><th>Status</th><th>Document</th></tr></thead>
         <tbody>
          @foreach($notes as $cn)
          @php $am63MatchedInvoice=$invoices->first(fn($i)=>(int)$i->id===(int)$cn->invoice_id); @endphp
          <tr>
            <td><b>{{ $cn->credit_note_no }}</b></td>
            <td>{{ $am63MatchedInvoice->invoice_no??'Original invoice' }}</td>
            <td>{{ $cn->credit_date??'—' }}</td>
            <td class="am63-money">₹{{ number_format((float)$cn->credit_amount,2) }}</td>
            <td><span class="am63-status ok">{{ strtoupper((string)$cn->status) }}</span></td>
            <td><a class="am63-link" target="_blank" rel="noopener" href="{{ route('erp.invoice-credit-note.print',$cn->id) }}">Print / View →</a></td>
          </tr>
          @endforeach
         </tbody>
        </table>
       </div>
     @else
       <div class="am63-empty">No Credit Note issued against this Job Card yet. No financial reversal has been assumed.</div>
     @endif
   </section>

   <section class="am63-panel" id="am63-labour">
    <div class="am63-panelheader">
      <h2>Labour / ROT Correction</h2><span class="am63-status">{{ $sessions->count() }} SESSION(S)</span>
    </div>
    <div class="am63-content">
     @if(!$am63Wip)
      <div class="am63-callout info">ROT/Part alterations remain locked until JC is officially returned to WIP. Completed ROTs must use the Admin reason-wise field unlock; original staff logs are not replaced.</div>
     @else
      <div class="am63-actions" style="margin-bottom:8px">
        <a class="am63-btn primary" href="{{ route('erp.job-cards.show',$card->id) }}">Edit Parts / Labour / OSL</a>
        <a class="am63-btn" href="{{ route('erp.fix48.job-amounts',$card->id) }}">Rate / Discount / GST Correction</a>
      </div>
     @endif
     @forelse($sessions as $rot)
      <div class="am63-rotitem">
       <div style="flex:1;min-width:190px">
        <strong>{{ $rot->rot_code }} — {{ $rot->rot_desc }}</strong>
        <small>Mechanic: {{ $rot->staff_name?:'—' }} · Status: {{ $rot->status }} · Started: {{ $rot->first_start_at?:'—' }} · Finished: {{ $rot->end_time?:'—' }}</small>
       </div>
       @if($am63Wip && !$am63Active && strtolower(trim((string)$rot->status))==='completed')
        <details style="width:min(360px,100%)">
         <summary class="am63-btn">Admin Reopen ROT</summary>
         <form class="am63-rotform" method="post" action="{{ route('erp.rot.reopen',$rot->id) }}">
          @csrf
          <label>Reason * <input name="reason" minlength="5" required placeholder="Why reopen this ROT?"></label>
          <label>Admin Note * <textarea rows="2" name="note" required placeholder="Audited correction details"></textarea></label>
          <button class="am63-btn primary" type="submit">Reopen ROT with Audit</button>
         </form>
        </details>
       @endif
      </div>
     @empty
      <div class="am63-empty">No ROT sessions associated with this JC.</div>
     @endforelse
    </div>
   </section>
  </div>

  <aside>
    <section class="am63-panel">
      <div class="am63-panelheader"><h2>Correction Workflow</h2><span class="am63-status">SAME JC NO</span></div>
      <div class="am63-content">
        <ol class="am63-steps">
         <li><span class="am63-stepnumber">1</span><span class="am63-stepinfo"><b>↩ Return to JC</b><small>Admin reason + confirmation; full CN for eligible unpaid invoice, without deleting original</small></span></li>
         <li><span class="am63-stepnumber">2</span><span class="am63-stepinfo"><b>Correct Job Card</b><small>WIP: edit Parts, Labour/ROT, OSL with controlled alterations</small></span></li>
         <li><span class="am63-stepnumber">3</span><span class="am63-stepinfo"><b>Review & Approve</b><small>Verify corrected JC total, GST, QC and delivery status</small></span></li>
         <li><span class="am63-stepnumber">4</span><span class="am63-stepinfo"><b>New Corrected Invoice</b><small>Issue a new number, preserving original invoice and credit history</small></span></li>
        </ol>
        <a class="am63-btn primary am63-backlink" href="{{ route('erp.job-cards.show',$card->id) }}">Open JC Control Centre →</a>
      </div>
    </section>
    <section class="am63-panel">
      <div class="am63-panelheader"><h2>Finance & Audit Controls</h2></div>
      <div class="am63-content">
       <div class="am63-cta">
        <b style="font-size:12px">Original records remain intact</b>
        <p>Original invoice number, old receipt, Credit Note and customer statement must be traceable. Finance requires independent checks for split/multiple receipts and Part-Paid invoices.</p>
        <a class="am63-btn" href="{{ route('erp.money-receipts.index') }}">Money Receipt Register →</a>
       </div>
       <p class="am63-small" style="line-height:1.6;margin:10px 0 0">This screen cannot itself adjust a partially paid receipt. GST/e-invoice reporting obligations must be checked by authorised Finance staff.</p>
      </div>
    </section>
  </aside>
 </div>
</div>

@if($am63CanReturn)
<div class="am63-shade" id="am63-return-modal" hidden>
  <div class="am63-dialog" role="dialog" aria-modal="true" aria-labelledby="am63-modal-title">
    <div class="am63-dialoghead">
      <h2 id="am63-modal-title">↩ RETURN TO JC — Confirm</h2>
      <button type="button" class="am63-close" id="am63-modal-close" aria-label="Close confirmation">×</button>
    </div>
    <p>Single audited operation: Full Credit Note against the original <b>{{ $am63Active->invoice_no }}</b> and reopen <b>{{ $card->job_no }}</b> to WIP. Invoice history will be preserved.</p>
    <div class="am63-callout info">Original Invoice: <b>{{ $am63Active->invoice_no }}</b> · ₹{{ number_format((float)$am63Active->grand_total,2) }} · Status: {{ strtoupper((string)$am63Active->status) }} · No linked receipt detected</div>
    <form id="am63-confirm-form" method="post" action="{{ route('erp.job-cards.return-to-jc.unpaid',[$card->id,$am63Active->id]) }}">
      @csrf
      <div class="am63-fieldgrid">
       <div>
        <label for="am63-credit-date">Credit Note Date</label>
        <input id="am63-credit-date" type="date" name="credit_date" required max="{{ now('Asia/Kolkata')->toDateString() }}" value="{{ old('credit_date',now('Asia/Kolkata')->toDateString()) }}">
       </div>
       <div>
        <label for="am63-reason">Why are you returning this JC? *</label>
        <textarea name="reason" id="am63-reason" minlength="10" maxlength="1000" rows="3" required placeholder="e.g. Correct labour discount and republish the invoice">{{ old('reason') }}</textarea>
       </div>
      </div>
      <label class="am63-consent">
       <input type="checkbox" name="confirmed_finance" value="1" required>
       <span>I verified the invoice has no receipt/payment allocation. I understand this issues a Full Credit Note and restores the same JC to WIP. Any statutory GST/e-invoice reporting must be handled separately.</span>
      </label>
      <div class="am63-actions">
        <button type="submit" class="am63-btn red" id="am63-commit-return">CONFIRM & RETURN TO JC</button>
        <button type="button" class="am63-btn" id="am63-cancel">Cancel</button>
      </div>
    </form>
  </div>
</div>
<script id="AM63_RETURN_MODAL">
(function(){
 const modal=document.getElementById('am63-return-modal');
 if(!modal)return;
 const triggers=[document.getElementById('am63-start-return'),...document.querySelectorAll('[data-am63-open]')].filter(Boolean);
 const closeButton=document.getElementById('am63-modal-close');
 const cancel=document.getElementById('am63-cancel');
 const reason=document.getElementById('am63-reason');
 const form=document.getElementById('am63-confirm-form');
 const submit=document.getElementById('am63-commit-return');
 let lastTrigger=null;
 function open(trigger){lastTrigger=trigger;modal.hidden=false;if(reason)reason.focus();}
 function close(){modal.hidden=true;if(lastTrigger)lastTrigger.focus();}
 triggers.forEach(el=>el.addEventListener('click',()=>open(el)));
 closeButton.addEventListener('click',close);
 cancel.addEventListener('click',close);
 modal.addEventListener('click',e=>{if(e.target===modal)close();});
 document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!modal.hidden)close();});
 form.addEventListener('submit',()=>{setTimeout(()=>{submit.disabled=true;submit.textContent='PROCESSING — DO NOT REPEAT';},0);});
})();
</script>
@endif
@endsection
