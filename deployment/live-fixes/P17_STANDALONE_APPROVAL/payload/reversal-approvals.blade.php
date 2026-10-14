@extends('layouts.erp')
@section('title','Admin Approval Desk — ASSAM MOTORS')
@section('content')
{{-- P17_STANDALONE_APPROVAL_DESK — approval decisions only; no automatic financial/stock reversal --}}
<style>
.p17-wrap{max-width:1150px;margin:0 auto;padding:0 10px 30px}
.p17-hero{background:linear-gradient(115deg,#092b50,#075778);color:#fff;border-bottom:5px solid #eebc3c;padding:23px;border-radius:15px;margin:14px 0}
.p17-hero h1{color:#fff;margin:5px 0;font-size:27px}
.p17-muted{font-size:13px;color:#60788a}
.p17-hero .p17-muted{color:#cae3f4}
.p17-bar{display:flex;align-items:center;flex-wrap:wrap;gap:11px;margin:12px 0}
.p17-link{display:inline-block;color:#175378;border:1px solid #a6c4d7;text-decoration:none;padding:9px 12px;border-radius:9px;background:#fff;font-weight:700}
.p17-card{background:#fff;border:1px solid #d7e3eb;border-radius:13px;padding:17px;margin:14px 0}
.p17-card h2{color:#103b5b;margin:0 0 9px;font-size:20px}
.p17-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:9px}
.p17-metric{padding:12px;border:1px solid #dbe5ed;background:#f4f8fb;border-radius:10px}
.p17-metric b{display:block;font-size:23px;color:#163956}
.p17-warning{border-left:4px solid #e69a21;background:#fff9eb;padding:13px;border-radius:8px;margin:12px 0;font-size:13px}
.p17-info{border-left:4px solid #377dad;background:#eff6fc;padding:12px;border-radius:8px;margin:10px 0}
.p17-queue{display:grid;gap:13px}
.p17-request{border:1px solid #dbe4ee;border-radius:12px;padding:15px;background:#fbfcfd}
.p17-request h3{font-size:17px;color:#113c5d;margin:0 0 7px}
.p17-pill{display:inline-block;border-radius:18px;padding:4px 9px;font-weight:800;font-size:11px;background:#ecf0f4;color:#436077}
.p17-pill.p17-wait{background:#ffedd0;color:#96570b}
.p17-pill.p17-approved{background:#eeeaff;color:#56448a}
.p17-pill.p17-rejected{background:#f6e3e3;color:#9b2424}
.p17-form{display:grid;grid-template-columns:repeat(2,minmax(230px,1fr));gap:12px;margin-top:12px;align-items:end}
.p17-form label{display:block;font-size:13px;font-weight:700;color:#314e64}
.p17-form input,.p17-form select,.p17-form textarea{display:block;width:100%;padding:10px;margin-top:5px;box-sizing:border-box;border:1px solid #bfcfdb;border-radius:8px;min-height:41px;background:#fff}
.p17-wide{grid-column:1 / -1}
.p17-submit{border:0;border-radius:9px;padding:11px 14px;background:#10547c;color:#fff;font-weight:700;cursor:pointer}
.p17-error{border-left:5px solid #c62828;color:#8e1c1c;background:#fff2f2;padding:12px;border-radius:9px}
.p17-success{border-left:5px solid #23955a;color:#0c6e43;background:#eefbf3;padding:12px;border-radius:9px}
@media(max-width:650px){.p17-form{grid-template-columns:1fr}.p17-hero{padding:15px}}
</style>
<div class="p17-wrap">
 <div class="p17-hero">
  <div class="p17-muted">ASSAM MOTORS · WORKSHOP ADMIN CONTROL</div>
  <h1>Admin Approval Desk</h1>
  <p>{{ $card->job_no }} · {{ $card->vehicle_reg_no }} · {{ $card->customer_name }}</p>
  <div class="p17-bar"><span class="p17-pill">{{ strtoupper((string)$card->status) }}</span>
   <a class="p17-link" href="{{ route('erp.job-cards.reversal',$card->id) }}">← Back to Reversal Centre</a>
   <a class="p17-link" href="{{ route('erp.job-cards.show',$card->id) }}">Job Card Summary</a>
  </div>
 </div>
 @if($errors->any())
 <div class="p17-error"><strong>Decision refused. No change made.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
 @endif
 @if(session('success'))<div class="p17-success">{{ session('success') }}</div>@endif
 @php
 $p17Waiting=$reversalReviews->where('status','REQUESTED')->count();
 $p17Approved=$reversalReviews->where('status','APPROVED')->count();
 $p17Rejected=$reversalReviews->where('status','REJECTED')->count();
 @endphp
 <section class="p17-card">
  <h2>Approval queue</h2>
  <p class="p17-muted">Only an active Assam Motors Owner/Admin may approve or reject a review. Previous requests remain in the audit log, even when only the latest request per scope is shown here.</p>
  <div class="p17-summary">
   <div class="p17-metric"><span class="p17-muted">Awaiting decision</span><b>{{ $p17Waiting }}</b></div>
   <div class="p17-metric"><span class="p17-muted">Approved for reconciliation</span><b>{{ $p17Approved }}</b></div>
   <div class="p17-metric"><span class="p17-muted">Rejected</span><b>{{ $p17Rejected }}</b></div>
   <div class="p17-metric"><span class="p17-muted">All current review scopes</span><b>{{ $reversalReviews->count() }}</b></div>
  </div>
  <div class="p17-warning"><strong>Approval is not reversal.</strong> An approved request authorizes further reconciliation. The Estimate, ROT time history, stock movements, vendor obligations, invoices and ledgers remain unchanged until a separate controlled reversal is executed.</div>
 </section>
 <section class="p17-card">
  <h2>Review requests and decisions</h2>
  <div class="p17-queue">
   @forelse($reversalReviews as $review)
    <article id="p17-review-{{ $review['id'] }}" class="p17-request">
     <div class="p17-bar" style="justify-content:space-between">
       <h3>{{ $review['name'] }} · Request #{{ $review['id'] }}</h3>
       <span class="p17-pill {{ $review['status']==='REQUESTED' ? 'p17-wait' : ($review['status']==='APPROVED' ? 'p17-approved' : ($review['status']==='REJECTED' ? 'p17-rejected' : '')) }}">
       {{ $review['status']==='REQUESTED' ? 'AWAITING ADMIN' : ($review['status']==='APPROVED' ? 'APPROVED — REVERSAL PENDING' : $review['status']) }}
       </span>
     </div>
     <p class="p17-muted">Requested on {{ $review['requested_at'] }}</p>
     <p><strong>Request reason:</strong> {{ $review['reason'] }}</p>
     @if($review['status']==='REQUESTED')
      <form class="p17-form" method="post" action="{{ route('erp.job-cards.reversal.review-decision',[$card->id,$review['id']]) }}" onsubmit="return confirm('Save this Admin decision? It will not reverse any Estimate, stock, purchase or financial record.')">
       @csrf
       <label>Confirm exact Request ID<input name="confirm_review_id" required type="number" min="1" placeholder="{{ $review['id'] }}"></label>
       <label>Decision<select name="decision" required><option value="">Select a decision</option><option value="APPROVE">Approve for Reconciliation</option><option value="REJECT">Reject Review</option></select></label>
       <label class="p17-wide">Decision reason (minimum 12 characters)<textarea rows="2" name="reason" required minlength="12" maxlength="500"></textarea></label>
       <label class="p17-wide" style="font-weight:400"><input type="checkbox" name="acknowledge_no_reversal" value="1" required style="display:inline;width:auto;min-height:auto"> I understand this decision does not execute an Estimate, stock, vendor or financial reversal.</label>
       <div class="p17-wide"><button type="submit" class="p17-submit">Save Admin Approval Decision</button></div>
      </form>
     @elseif($review['status']==='APPROVED')
      <div class="p17-info"><strong>Approved for reconciliation.</strong> Actual work/financial reversal remains pending. Decision reason: {{ $review['decision_reason'] }}</div>
     @elseif($review['status']==='ALREADY_CANCELLED')
      <div class="p17-info">Job Card is already CANCELLED. No second cancellation approval is needed.</div>
     @else
      <div class="p17-info"><strong>Review rejected.</strong> Decision reason: {{ $review['decision_reason'] }}</div>
     @endif
    </article>
   @empty
    <div class="p17-info">There are no reversal review requests for this Job Card.</div>
   @endforelse
  </div>
 </section>
 <p class="p17-muted">This page records approval decisions with an immutable audit trail. To check which of the 5 reversal steps are actually complete, return to the Job Card Reversal Centre.</p>
</div>
@endsection
