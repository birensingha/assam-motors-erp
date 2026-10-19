@extends('layouts.erp')
@section('title','Job Card Billing Correction — Assam Motors')
@section('content')
<style>
.am56-wrap{max-width:1100px;margin:0 auto}.am56-header{background:#103d6a;color:#fff;border-radius:13px;padding:22px;margin:14px 0}
.am56-header h1{color:#fff;margin:0}.am56-card{background:#fff;border:1px solid #d5e1eb;border-radius:12px;padding:17px;margin:14px 0}
.am56-card h2{color:#12456c;margin:0 0 10px;font-size:18px}.am56-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.am56-meta{color:#536f83;font-size:13px}.am56-input{width:100%;border:1px solid #b9cede;border-radius:7px;padding:10px}
.am56-btn{display:inline-block;border:0;background:#155384;color:white;border-radius:7px;padding:10px 14px;text-decoration:none;font-weight:700;cursor:pointer}
.am56-muted{background:#f2f6fa;border-left:4px solid #87a4bb;padding:12px}.am56-alert{background:#fff3d8;border-left:4px solid #e6a02c;padding:12px}
.am56-badge{font-size:11px;border-radius:30px;background:#e7edf2;padding:4px 10px;color:#315774}
.am56-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}@media(max-width:750px){.am56-grid{grid-template-columns:1fr}}
</style>
<div class="am56-wrap">
  <header class="am56-header">
    <h1>Simple Job Card Correction</h1>
    <p>{{ $card->job_no }} · {{ $card->vehicle_reg_no }} · {{ $card->customer_name }}</p>
    <div class="am56-row"><strong>Job Card: {{ strtoupper($card->status) }}</strong>
      <a class="am56-btn" style="background:#fff;color:#174c77" href="{{ route('erp.job-cards.show',$card->id) }}">← Back to Job Card</a>
    </div>
  </header>
  @if($errors->any())<div class="am56-card am56-alert"><strong>Action blocked — no transaction posted:</strong>@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>@endif
  @if(session('success'))<div class="am56-card">{{ session('success') }}</div>@endif

  @php
   $am56Active=$invoices->first(fn($i)=>strtolower((string)$i->status)!=='reversed');
   $am56Status=strtolower(trim((string)$card->status));
  @endphp
  <section class="am56-card" id="am56-credit">
    <h2>1. Invoice Return through Credit Note</h2>
    @if($am56Active)
      @php
        $am56PaymentLinked=((float)($am56Active->paid_amount??0)>0.009)||((float)($am56Active->paid_total??0)>0.009)||
          \Illuminate\Support\Facades\DB::table('customer_payment_allocations')->where('invoice_id',$am56Active->id)->exists()||
          \Illuminate\Support\Facades\DB::table('customer_payments')->where('invoice_id',$am56Active->id)->exists();
      @endphp
      <p><strong>{{ $am56Active->invoice_no }}</strong> · ₹{{ number_format((float)$am56Active->grand_total,2) }} · {{ strtoupper($am56Active->status) }}</p>
      <p class="am56-alert">Original invoice is never deleted. Credit Note creates a balancing ledger credit and preserves an immutable invoice line snapshot. Payment-linked invoices require Finance settlement first. GST/e-Invoice portal reporting is NOT automatic.</p>
      @if($am56PaymentLinked)
        <div class="am56-alert"><strong>Payment linked — Credit Note posting locked.</strong> Reconcile payment allocation / refund / adjustment through Finance first. Original receipt and ledger history are preserved.</div>
      @else
      <form method="post" action="{{ route('erp.job-cards.credit-note.post',[$card->id,$am56Active->id]) }}"
          onsubmit="return confirm('Post full Credit Note against issued invoice? This is an accounting transaction.');">
        @csrf
        <div class="am56-grid">
          <label>Credit Note Date<input class="am56-input" type="date" name="credit_date" value="{{ now('Asia/Kolkata')->toDateString() }}" required max="{{ now('Asia/Kolkata')->toDateString() }}"></label>
          <label>Correction Reason (minimum 10 characters)
            <textarea class="am56-input" name="reason" minlength="10" maxlength="1000" rows="3" required placeholder="What was wrong in the invoice?"></textarea>
          </label>
        </div>
        <label style="display:block;margin:12px 0"><input type="checkbox" name="confirmed_finance" value="1" required>
          I checked customer payment/GST/e-Invoice reporting status and will complete any required statutory credit-note reporting separately.
        </label>
        <button class="am56-btn" type="submit">Issue Full Credit Note (Unpaid Invoice Only)</button>
      </form>
      @endif
    @else
      <div class="am56-muted">No active issued invoice linked to this Job Card.</div>
    @endif
    @foreach($notes as $cn)
      <div style="border-top:1px solid #d9e4ed;margin-top:12px;padding-top:12px">
        <strong>{{ $cn->credit_note_no }}</strong> · ₹{{ number_format((float)$cn->credit_amount,2) }} · Posted
        <a class="am56-btn" href="{{ route('erp.invoice-credit-note.print',$cn->id) }}" target="_blank" rel="noopener">Print Credit Note</a>
      </div>
    @endforeach
  </section>

  <section class="am56-card" id="am56-reopen">
    <h2>2. Reopen Closed Job Card → WIP</h2>
    @if($am56Status==='closed' && !$am56Active)
      <form method="post" action="{{ route('erp.job-cards.lifecycle.reopen',$card->id) }}">
        @csrf
        <label>Reason for Reopening<textarea class="am56-input" name="reason" rows="2" minlength="5" maxlength="1000" required placeholder="Example: Wrong ROT discount; revise final invoice"></textarea></label>
        <button class="am56-btn" type="submit">Reopen JC to WIP</button>
      </form>
    @elseif($am56Status==='invoiced')
      <p class="am56-muted">Credit Note must be posted in Step 1 before Job Card can reopen.</p>
    @elseif(in_array($am56Status,['wip','in-progress','in progress','work in progress'],true))
      <p><strong>Job Card WIP — editing unlocked.</strong> Original inspection, stock and ROT audit histories remain available.</p>
      <a class="am56-btn" href="{{ route('erp.job-cards.show',$card->id) }}">Open Editable Job Card</a>
    @else
      <p class="am56-muted">Current status: {{ $card->status }}. Existing lifecycle checks still apply.</p>
    @endif
  </section>

  <section class="am56-card" id="am56-rot">
    <h2>3. Reopen Completed ROT / Edit Labour and Parts</h2>
    <div class="am56-row">
      <a class="am56-btn" href="{{ route('erp.fix48.job-amounts',$card->id) }}">Edit ROT Rate / Discount / GST</a>
      <a class="am56-btn" href="{{ route('erp.job-cards.show',$card->id) }}#jc55-osl">Edit Parts / ROT / OSL on Job Card</a>
    </div>
    @foreach($sessions as $rot)
      <div style="padding:11px 0;border-bottom:1px solid #e1e8f1" class="am56-row">
        <div style="flex:1">
          <strong>{{ $rot->rot_code }} — {{ $rot->rot_desc }}</strong>
          <div class="am56-meta">Mechanic: {{ $rot->staff_name }} · <span class="am56-badge">{{ $rot->status }}</span>
            · First Start: {{ $rot->first_start_at ?: 'Not started' }}
            · End: {{ $rot->end_time ?: '—' }}</div>
        </div>
        @if(strtolower((string)$rot->status)==='completed' && in_array($am56Status,['wip','in-progress','in progress','work in progress'],true) && !$am56Active)
        <details><summary class="am56-btn">Reopen ROT</summary>
          <form method="post" action="{{ route('erp.rot.reopen',$rot->id) }}" style="margin:9px 0;width:min(370px,90vw)">
            @csrf
            <label>Reason<input class="am56-input" name="reason" minlength="5" required></label>
            <label>Admin Note<textarea class="am56-input" name="note" rows="2" required></textarea></label>
            <button class="am56-btn" type="submit">Reopen Completed ROT</button>
          </form></details>
        @endif
      </div>
    @endforeach
    <p class="am56-meta">ROT Reopen preserves the historical first-start time and productive seconds; it clears the completion time and returns ROT to Paused. Staff Check-In and single-active-ROT restrictions still apply.</p>
  </section>
</div>
@endsection
