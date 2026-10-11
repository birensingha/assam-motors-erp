{{-- FIX46_WEB_JOBCARD_LIFECYCLE_INVOICE --}}
@php
$amLifeStatus=strtolower(trim((string)$job->status));
$amLifePublic=strtolower(trim((string)$job->customer_public_status));
$amLifeInvoice=\Illuminate\Support\Facades\Schema::hasTable('invoices')
    ? \Illuminate\Support\Facades\DB::table('invoices')->where('job_card_id',$job->id)->whereRaw("LOWER(COALESCE(status,'')) <> 'reversed'")->orderByDesc('id')->first()
    : null;
$amLifeQc=\Illuminate\Support\Facades\Schema::hasTable('workshop_qc_checks')
    ? \Illuminate\Support\Facades\DB::table('workshop_qc_checks')->where('job_card_id',$job->id)->orderByDesc('id')->first()
    : null;
$amLifeRotActive=\Illuminate\Support\Facades\Schema::hasTable('rot_sessions')
    ? (int)\Illuminate\Support\Facades\DB::table('rot_sessions')->where('job_card_id',$job->id)->whereRaw("LOWER(COALESCE(status,'')) IN ('assigned','running','paused')")->count()
    : 0;
$amLifePartPending=(\Illuminate\Support\Facades\Schema::hasTable('job_card_parts') && \Illuminate\Support\Facades\Schema::hasColumn('job_card_parts','issue_status'))
    ? (int)\Illuminate\Support\Facades\DB::table('job_card_parts')->where('job_card_id',$job->id)->whereRaw("LOWER(TRIM(COALESCE(issue_status,''))) IN ('','planned','pending','not issued','not_issued','issued')")->count()
    : 0;
$amLifeLabourPending=(\Illuminate\Support\Facades\Schema::hasTable('job_card_labour') && \Illuminate\Support\Facades\Schema::hasColumn('job_card_labour','execution_status'))
    ? (int)\Illuminate\Support\Facades\DB::table('job_card_labour')->where('job_card_id',$job->id)->whereRaw("UPPER(COALESCE(mechanic_name,'')) <> 'OSL'")->whereRaw("LOWER(COALESCE(execution_status,'')) IN ('','planned','pending','assigned','running','paused')")->count()
    : 0;
$amLifeOslPending=\Illuminate\Support\Facades\Schema::hasTable('workshop_osl_requests')
    ? (int)\Illuminate\Support\Facades\DB::table('workshop_osl_requests')->where('job_card_id',$job->id)->whereRaw("UPPER(COALESCE(status,'')) IN ('REQUESTED','APPROVED','IN_PROGRESS')")->count()
    : 0;

$amLifeStage='OPEN';
if($amLifeStatus==='invoiced' || $job->invoice_id || $amLifeInvoice) $amLifeStage='INVOICED';
elseif($amLifeStatus==='closed') $amLifeStage='CLOSED';
elseif(str_contains($amLifePublic,'ready for delivery')) $amLifeStage='READY_FOR_DELIVERY';
elseif(str_contains($amLifePublic,'quality check passed')) $amLifeStage='QC_PASSED';
elseif(str_contains($amLifePublic,'quality check')) $amLifeStage='QC_PENDING';
elseif(str_contains($amLifePublic,'work complete') || $amLifeStatus==='completed') $amLifeStage='WORK_COMPLETE';
elseif(in_array($amLifeStatus,['in progress','in-progress','work in progress','wip'])) $amLifeStage='WIP';

$amStages=[
 'OPEN'=>'Open',
 'WIP'=>'WIP',
 'WORK_COMPLETE'=>'Work Complete',
 'QC_PENDING'=>'QC',
 'QC_PASSED'=>'QC Passed',
 'READY_FOR_DELIVERY'=>'Ready Delivery',
 'CLOSED'=>'Closed',
 'INVOICED'=>'Invoiced',
];
$amStageKeys=array_keys($amStages);
$amCurrentIndex=array_search($amLifeStage,$amStageKeys,true);
if($amCurrentIndex===false) $amCurrentIndex=0;
@endphp

<style id="FIX46_WEB_LIFECYCLE_UI">
.am-life-card{border-top:4px solid #0b315b;background:linear-gradient(180deg,#fff,#f8fbfd)}
.am-life-steps{display:grid;grid-template-columns:repeat(8,minmax(110px,1fr));gap:6px;overflow:auto;padding-bottom:4px}
.am-life-step{border:1px solid #d9e4ee;border-radius:10px;padding:10px;background:#fff;min-width:110px}
.am-life-step span{display:block;font-size:9px;color:#6b7f93;text-transform:uppercase;font-weight:800}
.am-life-step strong{display:block;margin-top:4px;color:#264968;font-size:11px}
.am-life-step.done{background:#edf8f1;border-color:#9bd0aa}.am-life-step.done strong{color:#176b37}
.am-life-step.current{background:#eaf2ff;border-color:#79a6e8;box-shadow:0 0 0 2px rgba(37,99,235,.08)}.am-life-step.current strong{color:#154d9d}
.am-life-grid{display:grid;grid-template-columns:1.2fr .8fr;gap:14px;margin-top:14px}
.am-life-box{border:1px solid #dbe5ee;border-radius:12px;background:#fff;padding:13px}
.am-life-box h3{margin:0 0 5px;color:#173d60;font-size:14px}.am-life-box p{margin:0;color:#667b8f;font-size:11px}
.am-life-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.am-life-actions details{position:relative}.am-life-actions summary{list-style:none}.am-life-actions summary::-webkit-details-marker{display:none}
.am-life-form{margin-top:8px;display:grid;gap:8px;min-width:270px;padding:10px;border:1px solid #dbe5ee;border-radius:10px;background:#fff}
.am-life-form label{font-size:10px;font-weight:800;color:#526a82}.am-life-form input,.am-life-form textarea,.am-life-form select{width:100%;margin-top:4px;border:1px solid #cfdbe6;border-radius:8px;padding:8px}
.am-life-badges{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}.am-life-chip{border-radius:999px;padding:6px 9px;font-size:9px;font-weight:800;background:#edf2f7;color:#425a70}.am-life-chip.warn{background:#fff4dd;color:#8b5a00}.am-life-chip.ok{background:#eaf8ee;color:#176b37}
.am-life-note{font-size:10px;color:#6e8193;margin-top:10px}
@media(max-width:900px){.am-life-grid{grid-template-columns:1fr}.am-life-steps{grid-template-columns:repeat(8,130px)}}
</style>

<div class="card section-gap am-life-card">
  <div class="section-head">
    <div>
      <h2 class="section-title">Workshop Lifecycle & Billing</h2>
      <p class="muted">OPEN → WIP → WORK COMPLETE → QC → READY FOR DELIVERY → CLOSED → INVOICED</p>
    </div>
    @if($amLifeInvoice)
      <div class="actions">
        <a class="btn primary" href="{{ route('erp.invoices.show',$amLifeInvoice->id) }}">View Invoice {{ $amLifeInvoice->invoice_no }}</a>
        <a class="btn" target="_blank" href="{{ route('erp.invoices.print',$amLifeInvoice->id) }}">Print / PDF</a>
      </div>
    @endif
  </div>

  <div class="am-life-steps">
    @foreach($amStages as $key=>$label)
      @php($idx=array_search($key,$amStageKeys,true))
      <div class="am-life-step {{ $idx<$amCurrentIndex?'done':'' }} {{ $key===$amLifeStage?'current':'' }}">
        <span>Step {{ $idx+1 }}</span><strong>{{ $label }}</strong>
      </div>
    @endforeach
  </div>

  <div class="am-life-grid">
    <div class="am-life-box">
      <h3>Current Stage: {{ str_replace('_',' ',$amLifeStage) }}</h3>
      <p>{{ $job->customer_public_status }}</p>

      <div class="am-life-badges">
        <span class="am-life-chip {{ $amLifeRotActive?'warn':'ok' }}">ROT Pending: {{ $amLifeRotActive }}</span>
        <span class="am-life-chip {{ $amLifeLabourPending?'warn':'ok' }}">Labour Pending: {{ $amLifeLabourPending }}</span>
        <span class="am-life-chip {{ $amLifePartPending?'warn':'ok' }}">Parts Pending: {{ $amLifePartPending }}</span>
        <span class="am-life-chip {{ $amLifeOslPending?'warn':'ok' }}">OSL Pending: {{ $amLifeOslPending }}</span>
        <span class="am-life-chip {{ ($amLifeQc && strtoupper((string)$amLifeQc->result)==='PASS')?'ok':'warn' }}">Latest QC: {{ $amLifeQc ? strtoupper((string)$amLifeQc->result) : 'NONE' }}</span>
      </div>

      <div class="am-life-actions">
        @if($amLifeStage==='OPEN' && $diagnosisReady)
          <details><summary class="btn primary">▶ Start WIP</summary>
            <form method="post" action="{{ route('erp.job-cards.lifecycle.wip',$job->id) }}" class="am-life-form">
              @csrf<label>Reason *<textarea name="reason" required rows="2" placeholder="Work released / direct work start"></textarea></label>
              <button class="btn primary">Confirm WIP</button>
            </form>
          </details>
        @endif

        @if($amLifeStage==='WIP')
          <details><summary class="btn success-btn">✓ Work Complete</summary>
            <form method="post" action="{{ route('erp.job-cards.lifecycle.work-complete',$job->id) }}" class="am-life-form">
              @csrf<label>Reason *<textarea name="reason" required rows="2" placeholder="All assigned work completed"></textarea></label>
              <button class="btn success-btn">Mark Work Complete</button>
            </form>
          </details>
        @endif

        @if($amLifeStage==='WORK_COMPLETE')
          <details><summary class="btn primary">🔍 Start Quality Check</summary>
            <form method="post" action="{{ route('erp.job-cards.lifecycle.qc-pending',$job->id) }}" class="am-life-form">
              @csrf
              <label>QC Staff<select name="assigned_staff_id"><option value="">Not assigned</option>@foreach($staff as $s)<option value="{{ $s->id }}">{{ $s->name }} ({{ $s->staff_code }})</option>@endforeach</select></label>
              <label>QC Note<textarea name="notes" rows="2"></textarea></label>
              <label>Reason *<textarea name="reason" required rows="2">Work complete; vehicle sent for QC.</textarea></label>
              <button class="btn primary">Start QC</button>
            </form>
          </details>
        @endif

        @if($amLifeStage==='QC_PENDING')
          <details><summary class="btn success-btn">✓ QC PASS</summary>
            <form method="post" action="{{ route('erp.job-cards.lifecycle.qc-result',$job->id) }}" class="am-life-form">
              @csrf<input type="hidden" name="result" value="PASS">
              <label>QC Staff<select name="assigned_staff_id"><option value="">Not assigned</option>@foreach($staff as $s)<option value="{{ $s->id }}">{{ $s->name }} ({{ $s->staff_code }})</option>@endforeach</select></label>
              <label>QC Note<textarea name="notes" rows="2"></textarea></label>
              <label>Reason *<textarea name="reason" required rows="2">Quality Check passed.</textarea></label>
              <button class="btn success-btn">Confirm QC PASS</button>
            </form>
          </details>

          <details><summary class="btn danger">✕ QC FAIL / Rework</summary>
            <form method="post" action="{{ route('erp.job-cards.lifecycle.qc-result',$job->id) }}" class="am-life-form">
              @csrf<input type="hidden" name="result" value="FAIL">
              <label>Responsible Staff<select name="assigned_staff_id"><option value="">Not assigned</option>@foreach($staff as $s)<option value="{{ $s->id }}">{{ $s->name }} ({{ $s->staff_code }})</option>@endforeach</select></label>
              <label>Failed Item / Complaint<textarea name="failed_item" rows="2" required></textarea></label>
              <label>QC Note<textarea name="notes" rows="2"></textarea></label>
              <label>Reason *<textarea name="reason" required rows="2">QC failed; sent back for rework.</textarea></label>
              <button class="btn danger">Send to Rework</button>
            </form>
          </details>
        @endif

        @if($amLifeStage==='QC_PASSED')
          <details><summary class="btn success-btn">🚗 Ready for Delivery</summary>
            <form method="post" action="{{ route('erp.job-cards.lifecycle.ready',$job->id) }}" class="am-life-form">
              @csrf<label>Reason *<textarea name="reason" required rows="2">QC passed; vehicle ready for customer delivery.</textarea></label>
              <button class="btn success-btn">Mark Ready for Delivery</button>
            </form>
          </details>
        @endif

        @if($amLifeStage==='READY_FOR_DELIVERY')
          <details><summary class="btn primary">🔒 Close Job Card</summary>
            <form method="post" action="{{ route('erp.job-cards.lifecycle.close',$job->id) }}" class="am-life-form">
              @csrf<label>Reason *<textarea name="reason" required rows="2">Ready for delivery confirmed; close Job Card.</textarea></label>
              <button class="btn primary">Close Job Card</button>
            </form>
          </details>
        @endif

        @if($amLifeStage==='CLOSED' && !$amLifeInvoice)
          <details><summary class="btn primary">🧾 Convert to Invoice</summary>
            <form method="post" action="{{ route('erp.job-cards.invoice.create',$job->id) }}" class="am-life-form">
              @csrf
              <label>Invoice Date *<input type="date" name="invoice_date" value="{{ now('Asia/Kolkata')->toDateString() }}" max="{{ now('Asia/Kolkata')->toDateString() }}" required></label>
              <label>Reason *<textarea name="reason" required rows="2">Closed Job Card converted to customer invoice.</textarea></label>
              <button class="btn primary">Create Invoice + Post Ledger</button>
            </form>
          </details>

          <details><summary class="btn warning-btn">↻ Reopen Closed Job Card</summary>
            <form method="post" action="{{ route('erp.job-cards.lifecycle.reopen',$job->id) }}" class="am-life-form">
              @csrf<label>Reason *<textarea name="reason" required rows="2" placeholder="Why is this closed Job Card being reopened?"></textarea></label>
              <button class="btn warning-btn">Reopen to WIP</button>
            </form>
          </details>
        @endif
      </div>

      @if($amLifeStage==='WIP' && ($amLifeRotActive || $amLifeLabourPending || $amLifePartPending || $amLifeOslPending))
        <div class="am-life-note">Work Complete button server-side blocked rahega jab tak pending ROT/Labour/Parts/OSL clear nahi hote.</div>
      @endif
    </div>

    <div class="am-life-box">
      <h3>Billing Gate</h3>
      @if($amLifeStage==='INVOICED' && $amLifeInvoice)
        <p><strong>{{ $amLifeInvoice->invoice_no }}</strong> created. Job Card is locked from direct editing.</p>
        <div class="am-life-badges">
          <span class="am-life-chip">Invoice Total ₹{{ number_format((float)$amLifeInvoice->grand_total,2) }}</span>
          <span class="am-life-chip {{ (float)($amLifeInvoice->balance_due??$amLifeInvoice->balance_total)>0?'warn':'ok' }}">Balance ₹{{ number_format((float)($amLifeInvoice->balance_due??$amLifeInvoice->balance_total),2) }}</span>
        </div>
      @elseif($amLifeStage==='CLOSED')
        <p>Job Card closed hai. Ab Invoice conversion available hai. Invoice create hote hi Customer Ledger me DEBIT post hoga.</p>
      @else
        <p>Invoice tabhi create hoga jab QC PASS → Ready for Delivery → Job Card Close complete ho.</p>
      @endif
    </div>
  </div>
</div>
