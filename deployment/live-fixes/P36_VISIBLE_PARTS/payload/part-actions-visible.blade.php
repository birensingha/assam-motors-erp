{{-- P36_VISIBLE_PARTS_ACTIONS: ABOVE the horizontally scrollable Parts table. --}}
@php
  $p36Active = in_array(strtolower(trim((string)($job->status ?? ''))),['open','in-progress','in progress','wip','work in progress'],true);
@endphp
<div id="p36-parts-actions" class="p36-parts-control">
 <div class="p36-parts-heading">
  <div><strong><span style="font-size:18px">📦</span> Parts Requisition / Issue / Fitted</strong>
   <p>Yahin se Part ka action karein — right-side table scroll karne ki zaroorat nahi. Admin reason aur confirmation mandatory hain.</p>
  </div>
  <span class="p36-chip">STOCK CONTROL</span>
 </div>
 @forelse($parts as $p)
  @php
   $p36Status=strtoupper(trim((string)($p->issue_status ?? 'PLANNED')));
   $p36Writable=$p36Active && !in_array($p36Status,['FITTED','RETURNED','REVERSED'],true);
  @endphp
  <article class="p36-part-item">
   <div class="p36-part-description">
    <strong>{{ $p->part_name }}</strong>
    <p>Part #{{ $p->id }} · {{ $p->part_code }} · Qty {{ number_format((float)$p->qty,3) }}</p>
    <span class="p36-state {{ $p36Status==='FITTED'?'done':($p36Status==='ISSUED'?'issued':'pending') }}">{{ $p36Status ?: 'PLANNED' }}</span>
   </div>
   <div class="p36-part-controls">
    @if(!$p36Active)
     <div class="p36-note">Job Card status locked for issue. Only OPEN / WIP permits controlled issue actions.</div>
    @elseif(in_array($p36Status,['','PLANNED','PENDING','NOT ISSUED','NOT_ISSUED'],true))
     <form method="post" action="{{ route('erp.job-cards.parts.requisition',[$job->id,$p->id]) }}"
       onsubmit="return confirm('Create Parts Requisition? Stock will NOT be deducted.')">
      @csrf
      <label for="p36-reason-{{ $p->id }}">Requisition reason * <span class="p36-small">minimum 8 characters</span></label>
      <textarea id="p36-reason-{{ $p->id }}" name="reason" required minlength="8" maxlength="1000" rows="2" placeholder="Part fitted / required for vehicle repair"></textarea>
      <button type="submit" class="p36-action request">① Create Part Requisition →</button>
     </form>
     <div class="p36-note">Next: authorised Stock Issue → Mark Fitted. Requisition se stock change nahi hoga.</div>
    @elseif($p36Status==='REQUISITIONED')
     <form method="post" action="{{ route('erp.job-cards.parts.issue',[$job->id,$p->id]) }}"
       onsubmit="return confirm('Issue Part from stock? This deducts available stock ONCE. Negative/insufficient stock is blocked.')">
      @csrf
      <label for="p36-reason-{{ $p->id }}">Stock issue reason * <span class="p36-small">minimum 8 characters</span></label>
      <textarea id="p36-reason-{{ $p->id }}" name="reason" required minlength="8" maxlength="1000" rows="2" placeholder="Store approved and part physically handed over"></textarea>
      <button type="submit" class="p36-action issue">② Issue Part from Stock →</button>
     </form>
     <div class="p36-warning">Stock issue server check: negative / insufficient stock means NO issue. Reconcile original purchase and stock history first.</div>
    @elseif($p36Status==='ISSUED')
     <form method="post" action="{{ route('erp.job-cards.parts.fitted',[$job->id,$p->id]) }}"
       onsubmit="return confirm('Confirm this issued Part was physically fitted? Stock will NOT be deducted again.')">
      @csrf
      <label for="p36-reason-{{ $p->id }}">Fitting confirmation * <span class="p36-small">minimum 8 characters</span></label>
      <textarea id="p36-reason-{{ $p->id }}" name="reason" required minlength="8" maxlength="1000" rows="2" placeholder="Fitted and checked by workshop"></textarea>
      <button type="submit" class="p36-action fitted">③ Confirm Part Fitted →</button>
     </form>
     <div class="p36-note">Fitted only after matching recorded Stock Issue; no double deduction.</div>
    @elseif($p36Status==='FITTED')
     <strong class="p36-done">✓ FITTED · Stock issue recorded · No action pending</strong>
    @else
     <div class="p36-note">This status is not eligible for an automatic stock issue. Use authorised Stock/Reversal review.</div>
    @endif
   </div>
  </article>
 @empty
  <div class="p36-note">No Job Card Parts lines have been added yet.</div>
 @endforelse
</div>
<style>
.p36-parts-control{scroll-margin-top:18px;max-width:100%;box-sizing:border-box;border:2px solid #b8d8eb;border-left:5px solid #0877af;border-radius:13px;background:#f5faff;padding:16px;margin:13px 0 18px;box-shadow:0 5px 17px rgba(9,64,108,.08);color:#174168}
.p36-parts-heading{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin:0 0 14px}
.p36-parts-heading strong{font-size:18px;line-height:1.3;color:#093d6e}
.p36-parts-heading p{font-size:12px;color:#526f89;margin:5px 0 0}
.p36-chip{font-weight:850;color:#0e6593;background:#dff3ff;border:1px solid #afd8ec;border-radius:9px;padding:8px 10px;font-size:10px}
.p36-part-item{display:grid;grid-template-columns:minmax(170px,1fr) minmax(290px,2fr);gap:15px;align-items:start;background:#fff;border:1px solid #d7e5f0;border-radius:11px;padding:14px;margin:10px 0}
.p36-part-description strong{font-size:15px;color:#173d5e;display:block}
.p36-part-description p{color:#667f93;font-size:12px;margin:6px 0}
.p36-state{display:inline-block;padding:6px 11px;border-radius:100px;font-size:11px;font-weight:850;background:#fff1d6;color:#956015}
.p36-state.issued{background:#e3f0ff;color:#155a9e}.p36-state.done{background:#dcf5e8;color:#117247}
.p36-part-controls form{display:flex;flex-direction:column;gap:7px}
.p36-part-controls label{font-size:12px;font-weight:800;color:#315574}
.p36-part-controls textarea{min-height:64px;width:100%;box-sizing:border-box;border:1px solid #bbd0e2;border-radius:9px;padding:10px;font-size:13px;resize:vertical;background:white}
.p36-action{border:0;border-radius:10px;min-height:47px;padding:12px 17px;color:white;font-weight:850;font-size:14px;cursor:pointer;text-align:center;background:#166aa5}
.p36-action.issue{background:#0b795f}.p36-action.fitted{background:#165da2}
.p36-action:focus-visible{outline:3px solid #eba52b;outline-offset:3px}
.p36-small{font-size:10px;color:#617d93;font-weight:500}
.p36-note,.p36-warning{padding:9px 10px;border-radius:8px;background:#eff7fc;color:#546f84;font-size:11px;line-height:1.5;margin-top:6px}
.p36-warning{background:#fff5e2;color:#855818;border-left:3px solid #e2a22a}
.p36-done{background:#e6f8ef;color:#156b43;border-radius:9px;padding:11px;display:block}
@media(max-width:720px){.p36-part-item{grid-template-columns:1fr}.p36-action{width:100%}.p36-parts-control{padding:12px}}
</style>
