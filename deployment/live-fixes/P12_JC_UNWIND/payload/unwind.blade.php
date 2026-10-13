@extends('layouts.erp')
@section('title','Job Card Reversal Centre — ASSAM MOTORS')
@section('content')
<style>
.jcu-wrap{max-width:1180px;margin:0 auto}.jcu-head{background:linear-gradient(115deg,#092c4e,#06587a);color:#fff;border-radius:18px;padding:24px;margin-bottom:20px;border-bottom:5px solid #ffcc3c}
.jcu-head h1{color:#fff;margin:0 0 5px}.jcu-head p{color:#d9eafa;margin:0}.jcu-card{background:#fff;border:1px solid #d7e1eb;border-radius:15px;padding:18px;margin:14px 0;box-shadow:0 3px 10px #102f4d08}
.jcu-card h2{font-size:21px;margin:0 0 10px;color:#093859}.jcu-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px}
.jcu-box{background:#f4f8fb;border:1px solid #dce7ef;padding:13px;border-radius:12px}.jcu-box b{display:block;color:#103b5d}
.jcu-note{padding:13px;border-radius:11px;background:#fff8e7;border-left:4px solid #eb9804;margin:10px 0}
.jcu-form{display:flex;gap:10px;flex-wrap:wrap;align-items:end}.jcu-form label{flex:1;min-width:180px;font-size:13px;font-weight:700}
.jcu-form input,.jcu-form select,.jcu-form textarea{width:100%;padding:10px;margin-top:5px;border:1px solid #cad8e4;border-radius:9px;min-height:42px}
.jcu-action{border:0;border-radius:9px;background:#0b4f78;color:#fff;padding:11px 16px;cursor:pointer;font-weight:700}
.jcu-danger{background:#bd242e}.jcu-btn{display:inline-block;border:1px solid #bfd4e2;border-radius:9px;padding:10px 14px;color:#0b4161;text-decoration:none}
.jcu-list{display:grid;gap:9px}.jcu-item{padding:12px;background:#f8fbfd;border-radius:9px;border:1px solid #e3ebf1}
.jcu-small{font-size:12px;color:#587080}.jcu-status{background:#e7f0f8;color:#174360;border-radius:100px;padding:6px 12px;font-size:12px;font-weight:800}
@media(max-width:600px){.jcu-head{padding:17px}.jcu-card{padding:13px}.jcu-form>*{width:100%}}
</style>
<div class="jcu-wrap">
 <div class="jcu-head">
  <div class="jcu-small" style="color:#bbdcf2">ASSAM MOTORS · WORKSHOP CONTROL CENTRE</div>
  <h1>↶ Job Card Reversal Centre</h1>
  <p>{{ $card->job_no }} · {{ $card->vehicle_reg_no }} · {{ $card->customer_name }}</p>
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:13px"><span class="jcu-status">{{ strtoupper($card->status) }}</span><a class="jcu-btn" style="color:#fff;border-color:#8dbcd4" href="{{ route('erp.job-cards.show',$card->id) }}">← Job Card Summary</a></div>
 </div>
 @if($errors->any())<div class="jcu-card" style="border-left:4px solid #c62828;color:#991b1b"><strong>Action refused. Nothing was changed:</strong><ul>@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul></div>@endif
 @if(session('success'))<div class="jcu-card" style="border-left:4px solid #16a34a">{{ session('success') }}</div>@endif
 <section class="jcu-card">
  <h2>Linked records &amp; cancellation safety</h2>
  <div class="jcu-grid">
   @foreach([
     'Labour / ROT lines'=>$counts['job_card_labour'],
     'ROT sessions'=>$counts['rot_sessions'],
     'Allotted Job Parts'=>$counts['job_card_parts'],
     'Stock movements'=>$counts['inventory_movements'],
     'Estimate versions'=>$counts['job_estimates'],
     'Parts / OSL purchases'=>$counts['workshop_purchases'],
     'Invoices'=>$counts['invoices'],
     'Customer payments'=>$counts['customer_payments'],
     'Ledger entries'=>$counts['customer_ledger']
   ] as $title=>$n)
    <div class="jcu-box"><span class="jcu-small">{{ $title }}</span><b style="font-size:24px">{{ $n }}</b></div>
   @endforeach
  </div>
  <div class="jcu-note">History is not erased. Completed ROT, inspection, approved estimates, stock-in purchases and financial records are retained. Cancelling the Job Card does not void a supplier bill or change stock balance. Financial-linked cards require accounting reversal first.</div>
 </section>
 <section class="jcu-card">
  <h2>1 · Labour / ROT removal</h2>
  <p class="jcu-small">Only never-started, unlinked, planned Labour can be deleted. A completed ROT is kept permanently for productivity/TAT history, even if the Job Card is cancelled.</p>
  <div class="jcu-list">
   @forelse($labour as $line)
    <div class="jcu-item">
     <strong>{{ $line->rot_code ?: 'Labour #'.$line->id }} · {{ $line->description }}</strong>
     <div class="jcu-small">Execution: {{ $line->execution_status ?: 'Pending' }} · Productive seconds: {{ $line->productive_seconds ?: 0 }}</div>
     @php($locked = !empty($line->rot_start) || !empty($line->rot_end) || (int)($line->productive_seconds??0)>0 || !empty($line->source_estimate_item_id) || !in_array(strtolower(trim((string)($line->execution_status??'planned'))),['','planned','pending','not_started','not started'],true))
     @if($locked)
       <div class="jcu-note">ROT/Estimate linked: removal blocked. Use Cancel or request reversal review.</div>
     @else
       <form method="post" action="{{ route('erp.job-cards.reversal.labour',[$card->id,$line->id]) }}" class="jcu-form" onsubmit="return confirm('Delete only if this Labour has never started?')">
        @csrf @method('DELETE')
        <label>Reason (12+ characters)<input name="reason" required minlength="12" maxlength="500"></label>
        <button class="jcu-action jcu-danger">Delete Unstarted Labour</button>
       </form>
     @endif
    </div>
   @empty <div class="jcu-item">No Labour lines on this Job Card.</div>@endforelse
  </div>
 </section>
 <section class="jcu-card">
  <h2>2 · Allotted Parts / Stock Return</h2>
  <p class="jcu-small">ERP ke existing Stock-Safe Part Delete ka action yahan diya gaya hai. Issued Part ko return karne par matching movement se stock audit hoga. Purchase Stock-In is action se delete nahi hoga.</p>
  <div class="jcu-list">
   @forelse($parts as $part)
   <div class="jcu-item">
    <strong>{{ $part->part_name }} · {{ $part->part_code }}</strong>
    <div class="jcu-small">Quantity: {{ $part->qty }} · Issue: {{ $part->issue_status }}</div>
    @if(!in_array(strtolower((string)$card->status),['closed','invoiced','cancelled'],true))
     <form class="jcu-form" method="post" action="{{ route('erp.job-cards.parts.delete',[$card->id,$part->id]) }}" onsubmit="return confirm('Reverse this Part allocation/stock as applicable?')">
      @csrf @method('DELETE')
      <label>Return/Remove reason<input name="reason" required minlength="12" maxlength="1000"></label>
      <button class="jcu-action">Reverse / Remove Part</button>
     </form>
    @else <div class="jcu-note">Locked Job Card; reverse through authorized stock/cancellation review.</div> @endif
   </div>
   @empty <div class="jcu-item">No active Part allocation remains. Historical Inventory Movement records remain available for audit.</div>@endforelse
  </div>
 </section>
 <section class="jcu-card">
  <h2>3 · Estimate Reversal</h2>
  <div class="jcu-list">
   @forelse($estimates as $estimate)
   <div class="jcu-item">
    <strong>{{ $estimate->estimate_no }} · Version {{ $estimate->version_no }} — {{ $estimate->status }}</strong>
    <div class="jcu-small">Released to Job Card: {{ $estimate->converted_to_job_at ? 'YES — audit review required' : 'NO' }}</div>
    <a class="jcu-btn" href="{{ route('erp.estimates.show',$estimate->id) }}">View Estimate</a>
   </div>
   @empty <div class="jcu-item">No Estimate attached.</div>@endforelse
  </div>
  <div class="jcu-note">Approved/released Estimate linked to completed ROT cannot simply be marked Draft or removed. Submit a controlled reversal request below; it records the reason without modifying accounting or work history.</div>
  <form method="post" action="{{ route('erp.job-cards.reversal.request',$card->id) }}" class="jcu-form">
   @csrf <input type="hidden" name="scope" value="ESTIMATE">
   <label>Estimate reversal request reason<input name="reason" required minlength="12" maxlength="500"></label>
   <button class="jcu-action">Request Estimate Reversal Review</button>
  </form>
 </section>
 <section class="jcu-card" style="border-top:5px solid #e25b22">
  <h2>4 · Cancel &amp; archive the Job Card safely</h2>
  <div class="jcu-note"><strong>No data loss:</strong> Cancel keeps all inspection photos, completed ROT/TAT, estimates, purchase bills and inventory movements. Purchase obligations stay in Vendor Ledger; it does NOT refund or automatically reverse a supplier purchase. Cancelled status removes JC from active WIP.</div>
  <form method="post" action="{{ route('erp.job-cards.reversal.cancel',$card->id) }}" class="jcu-form" onsubmit="return confirm('Cancel this Job Card? All work, stock and accounting history will stay recorded. No financial reversal is automatic.')">
   @csrf
   <label>Type exact Job Card Number<input name="confirm_job_no" placeholder="{{ $card->job_no }}" required autocomplete="off" maxlength="80"></label>
   <label>Cancellation reason (12+ characters)<textarea name="reason" minlength="12" required maxlength="500" rows="2"></textarea></label>
   <label style="width:100%;font-weight:400"><input type="checkbox" name="acknowledge_purchase" value="1" required style="width:auto;min-height:auto"> I understand linked purchases/vendor liabilities and completed ROT history remain unchanged.</label>
   <button class="jcu-action jcu-danger" type="submit">Cancel / Archive Job Card →</button>
  </form>
  <p class="jcu-small">Backend blocks invoices, payments, unreversed stock issues, and running/paused ROT. Failed checks leave the Job Card untouched.</p>
 </section>
 <section class="jcu-card">
  <h2>5 · Full reversal / cancellation review (any status)</h2>
  <p>Closed or invoiced records may require credit notes, receipt reversal, vendor returns and additional authorization. The review request is available for every Job Card status; it never erases data.</p>
  <form method="post" action="{{ route('erp.job-cards.reversal.request',$card->id) }}" class="jcu-form">
   @csrf
   <label>Review type<select name="scope" required>
    <option value="CANCEL">Full Job Card Cancellation</option><option value="ROT">Completed ROT Review</option>
    <option value="PARTS">Parts / Stock Review</option><option value="FINANCE">Invoice / Payment / Purchase Review</option>
   </select></label>
   <label>Reason<input name="reason" required minlength="12" maxlength="500"></label>
   <button class="jcu-action">Register Audited Review</button>
  </form>
  <p class="jcu-small">Cancellation requests recorded: {{ $requested }}. Existing strict-empty Delete button remains separate and cannot remove inspection/ROT/financial history.</p>
 </section>
</div>
@endsection
