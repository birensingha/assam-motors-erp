{{-- P33_JOB_PART_REQUISITION_ISSUE_FITTED --}}
@php($p33State=strtoupper(trim((string)($p->issue_status??'PLANNED'))))
@if(in_array(strtolower(trim((string)$job->status)),['open','in-progress','in progress','wip','work in progress'],true))
  @if(in_array($p33State,['','PLANNED','PENDING','NOT ISSUED','NOT_ISSUED'],true))
   <details class="action-pop" style="margin:5px 0">
    <summary class="btn small" style="background:#eef7ff;color:#124e7a">📋 Requisition</summary>
    <form method="post" action="{{ route('erp.job-cards.parts.requisition',[$job->id,$p->id]) }}" class="pop-form"
       onsubmit="return confirm('Record a Parts Requisition? No stock will be deducted yet.')">
     @csrf
     <p style="font-size:11px">Request the Store to issue this Part. Stock unchanged.</p>
     <label>Requisition Reason *<textarea name="reason" rows="2" required minlength="8" maxlength="1000" placeholder="Part required for repair, quantity and reason"></textarea></label>
     <button type="submit" class="btn primary small">Create Requisition →</button>
    </form>
   </details>
  @elseif($p33State==='REQUISITIONED')
   <details class="action-pop" style="margin:5px 0">
    <summary class="btn small" style="background:#e0f4eb;color:#106443">📦 Issue from Stock</summary>
    <form method="post" action="{{ route('erp.job-cards.parts.issue',[$job->id,$p->id]) }}" class="pop-form"
      onsubmit="return confirm('This will DEDUCT this Part quantity from Parts Master stock exactly once. Continue?')">
     @csrf
     <p style="font-size:11px">Only WIP Job Cards. Insufficient or negative stock is blocked. Reconcile purchase/stock first.</p>
     <label>Issue Reason *<textarea name="reason" rows="2" required minlength="8" maxlength="1000" placeholder="Stock verified and part handed over to technician"></textarea></label>
     <button type="submit" class="btn primary small">Confirm Stock Issue →</button>
    </form>
   </details>
  @elseif($p33State==='ISSUED')
   <details class="action-pop" style="margin:5px 0">
    <summary class="btn small" style="background:#e5f2ff;color:#124e80">🔧 Mark Fitted</summary>
    <form method="post" action="{{ route('erp.job-cards.parts.fitted',[$job->id,$p->id]) }}" class="pop-form"
      onsubmit="return confirm('Confirm Part is physically fitted to the vehicle? This will NOT deduct stock a second time.')">
     @csrf
     <p style="font-size:11px">Fitted means installation verified; the matching stock-out movement must already exist.</p>
     <label>Fitting Confirmation Reason *<textarea name="reason" rows="2" required minlength="8" maxlength="1000" placeholder="Fitted to vehicle and confirmed by workshop"></textarea></label>
     <button type="submit" class="btn primary small">Confirm Fitted →</button>
    </form>
   </details>
  @elseif($p33State==='FITTED')
   <span class="badge" style="background:#e5f6ed;color:#14653b">✓ Fitted · Stock issued</span>
  @endif
@endif
