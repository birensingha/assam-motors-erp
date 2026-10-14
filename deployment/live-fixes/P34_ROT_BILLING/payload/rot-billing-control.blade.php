{{-- P34_ROT_BILLING_CONTROL: Work/Time is independent of customer billing policy. --}}
@php
  $p34Included=(int)($line->billing_included??1)===1;
  $p34CanEdit=!in_array(strtolower(trim((string)$job->status)),['closed','invoiced','cancelled'],true)
      && !(int)($job->invoice_id??0);
@endphp
<div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin:10px 0;padding:11px 12px;background:{{ $p34Included?'#eef8ff':'#fff5e5' }};border:1px solid #cddfed;border-radius:10px">
 <div><strong style="color:#204d6b">Customer Billing:
  <span style="color:{{ $p34Included?'#177b4f':'#a76b0c' }}">{{ $p34Included?'BILLABLE':'NO CHARGE' }}</span></strong>
  <div class="muted" style="font-size:11px;margin-top:4px">
    Original ROT/Labour charge: ₹{{ number_format((float)$line->total,2) }}.
    {{ $p34Included?'Included in JC and final Invoice.':'Excluded from customer Invoice; mechanic time and completion preserved.' }}
  </div>
  @if(!$p34Included && !empty($line->billing_reason))
    <small>Admin reason recorded in Job Card audit.</small>
  @endif
 </div>
 @if($p34CanEdit)
  <details class="action-pop">
   <summary class="btn small {{ $p34Included?'warning-btn':'primary' }}">
     {{ $p34Included?'Set No Charge / Internal':'Make Billable' }}
   </summary>
   <form method="post" class="pop-form"
      action="{{ route('erp.job-cards.labour.billing',[$job->id,$line->id]) }}"
      onsubmit="return confirm('Update customer billing policy and recalculate Job Card totals? ROT time records will not change.')">
    @csrf
    <input type="hidden" name="billing_included" value="{{ $p34Included?'0':'1' }}">
    <p class="muted" style="font-size:11px">ROT Start/Stop and technician productivity remain unchanged. This action changes customer charges and requires balanced GST/totals.</p>
    <label>Billing Change Reason *<textarea name="reason" minlength="8" maxlength="1000" required rows="2" placeholder="Warranty / goodwill / internal work / approved charge change"></textarea></label>
    <button class="btn primary small" type="submit">Confirm {{ $p34Included?'No Charge':'Billable' }}</button>
   </form>
  </details>
 @endif
</div>
