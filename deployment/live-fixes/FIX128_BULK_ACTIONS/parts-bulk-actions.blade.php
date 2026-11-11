{{-- FIX128_PARTS_BULK_SAFE: exactly two stock actions in Parts section head, no row markup change. --}}
@php
 $am128User=auth()->user();
 $am128Admin=$am128User instanceof \App\Models\ErpAuthUser && $am128User->active && $am128User->isAdmin();
 $am128Wip=in_array(strtolower(trim((string)($job->status??''))),['wip','in-progress','in progress','work in progress'],true) && empty($job->invoice_id);
 $am128CanUse=$am128Admin && $am128Wip;
 $am128Planned=$parts->filter(fn($p)=>in_array(strtoupper(trim((string)($p->issue_status??'PLANNED'))),['','PLANNED','PENDING','NOT ISSUED','NOT_ISSUED','REQUISITIONED'],true))->count();
 $am128Issued=$parts->filter(fn($p)=>strtoupper(trim((string)($p->issue_status??'')))==='ISSUED')->count();
 $am128Fitted=$parts->filter(fn($p)=>strtoupper(trim((string)($p->issue_status??'')))==='FITTED')->count();
@endphp
<div class="am128-stock-bar" id="am128-parts-stock-actions" aria-label="Job Card bulk Parts issue and fitted">
 <span class="am128-stock-summary">Planned <b>{{ $am128Planned }}</b> · Issued <b>{{ $am128Issued }}</b> · Fitted <b>{{ $am128Fitted }}</b></span>
 <div class="am128-stock-actions">
  @if($am128CanUse)
   <form method="post" action="{{ route('erp.job-cards.parts.issue-all',$job->id) }}"
     onsubmit="return confirm('Issue ALL planned Job Card Parts from Stock? Every line is checked first. If any stock is missing, NONE is issued.')">
    @csrf
    <input type="hidden" name="reason" value="Admin approved all eligible Job Card Parts for stock issue">
    <button type="submit" class="btn primary" @disabled($am128Planned===0)>Issue All Parts ({{ $am128Planned }})</button>
   </form>
   <form method="post" action="{{ route('erp.job-cards.parts.fitted-all',$job->id) }}"
     onsubmit="return confirm('Confirm ALL issued Job Card Parts are physically FITTED and checked? No second stock deduction.')">
    @csrf
    <input type="hidden" name="reason" value="Admin physically verified all issued Job Card Parts are fitted">
    <button type="submit" class="btn" @disabled($am128Planned>0 || $am128Issued===0)>Mark All Fitted ({{ $am128Issued }})</button>
   </form>
  @else
   <span class="am128-stock-help">Bulk actions: Admin, WIP and pre-invoice only.</span>
  @endif
 </div>
 @if($errors->has('part'))
 <p class="am128-stock-error" role="alert">{{ $errors->first('part') }}</p>
 @endif
</div>
<style>
#am128-parts-stock-actions{display:flex;flex-wrap:wrap;align-items:center;gap:7px 12px;margin:6px 0 0;font-size:11px;max-width:100%}
#am128-parts-stock-actions .am128-stock-summary{white-space:nowrap;color:#345c4a}
#am128-parts-stock-actions .am128-stock-actions{display:flex;flex-wrap:wrap;align-items:center;gap:6px}
#am128-parts-stock-actions .am128-stock-actions form{display:inline;margin:0}
#am128-parts-stock-actions .btn{padding:5px 10px;min-height:29px;font-size:11px;white-space:nowrap}
#am128-parts-stock-actions .btn[disabled]{cursor:not-allowed;opacity:.5}
#am128-parts-stock-actions .am128-stock-error{width:100%;margin:2px 0;font-size:11px;color:#991b1b}
#am128-parts-stock-actions .am128-stock-help{color:#64748b;font-size:10px}
</style>
