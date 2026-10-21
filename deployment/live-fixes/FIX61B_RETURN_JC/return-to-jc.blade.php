{{-- FIX61B: one-button, ONE reason Return to JC; posted receipt cases fail closed. --}}
@php
  $am61bActor = auth()->user();
  $am61bInvoiced = strtolower(trim((string)($job->status??''))) === 'invoiced' && !empty($job->invoice_id);
  $am61bInvoice = $am61bInvoiced ? \Illuminate\Support\Facades\DB::table('invoices')->where('id',(int)$job->invoice_id)->first() : null;
  $am61bHasMoney = $am61bInvoice && (
    abs((float)($am61bInvoice->paid_amount??0)) > 0.009 ||
    abs((float)($am61bInvoice->paid_total??0)) > 0.009 ||
    \Illuminate\Support\Facades\DB::table('customer_payments')->where('invoice_id',$am61bInvoice->id)->exists() ||
    \Illuminate\Support\Facades\DB::table('customer_payment_allocations')->where('invoice_id',$am61bInvoice->id)->exists());
  $am61bReady = $am61bInvoice && strtolower(trim((string)$am61bInvoice->status)) === 'issued' &&
    !$am61bHasMoney && $am61bActor instanceof \App\Models\ErpAuthUser &&
    $am61bActor->active && $am61bActor->isAdmin();
@endphp
@if($am61bInvoiced && $am61bActor instanceof \App\Models\ErpAuthUser && $am61bActor->active && $am61bActor->isAdmin())
<section id="am61b-return" style="margin:14px 0 16px;border:1px solid #a9c5e2;border-radius:12px;background:#f5f9ff;padding:14px 16px">
  <div style="display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap">
    <div>
      <strong style="color:#174c78">RETURN TO JOB CARD</strong>
      <div style="color:#546577;font-size:13px;margin-top:4px">
        Original {{ $am61bInvoice->invoice_no??'Invoice' }} • ₹{{ number_format((float)($am61bInvoice->grand_total??0),2) }}
      </div>
    </div>
    <a href="{{ route('erp.job-cards.credit-correction',$job->id) }}" style="color:#175c8d;font-size:13px">Original invoice / credit history</a>
  </div>
  @if($am61bReady)
    <details style="margin-top:12px">
      <summary style="cursor:pointer;display:inline-flex;background:#b82d36;color:white;font-weight:800;padding:11px 18px;border-radius:9px;list-style:none">
        ↩ RETURN TO JC
      </summary>
      <form method="post" action="{{ route('erp.job-cards.return-to-jc',$job->id) }}" style="max-width:750px;padding:15px 0 0">
        @csrf
        <label style="display:block;font-size:14px;font-weight:700">Reason for Return to JC *
          <textarea name="reason" minlength="10" maxlength="1000" rows="3" required
            placeholder="What needs to be corrected in this Job Card?"
            style="display:block;margin:7px 0 12px;width:100%;border:1px solid #a4bbd6;border-radius:8px;padding:10px">{{ old('reason') }}</textarea>
        </label>
        <p style="font-size:13px;color:#774d1d;line-height:1.5">
          Confirm will post a <b>Full Credit Note</b> for the unpaid invoice and reopen this Job Card to <b>WIP</b> as one audited transaction. Original invoice stays preserved. GST / e-Invoice statutory reporting must be handled separately.
        </p>
        <input type="hidden" name="confirm_return" value="1">
        <button type="submit" style="background:#134a75;color:white;font-weight:800;border:0;border-radius:8px;padding:12px 18px;cursor:pointer">CONFIRM & RETURN TO JC</button>
      </form>
    </details>
  @elseif($am61bHasMoney)
    <p style="margin:9px 0 0;color:#85421a;font-size:13px">
      <b>Money Receipt / Advance linked — automatic return temporarily locked.</b>
      The receipt must be preserved and its allocation reconciled first. The paid/partial receipt workflow is being implemented separately; no financial reversal will run from this screen yet.
    </p>
  @else
    <p style="margin:9px 0 0;color:#85421a;font-size:13px">
      Automatic Return blocked until linked invoice status and billing are verified by Finance.
    </p>
  @endif
</section>
@endif
