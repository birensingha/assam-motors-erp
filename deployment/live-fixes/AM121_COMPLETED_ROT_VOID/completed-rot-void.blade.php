{{-- AM121: Completed ROT Void is audited FINANCIAL cancellation, not work session deletion. --}}
@php
    $am121Actor=request()->user();
    $am121Admin=$am121Actor && (bool)($am121Actor->active??false)
        && strtolower(trim((string)($am121Actor->role??'')))==='admin';
    $am121Wip=in_array(strtolower(trim((string)($job->status??''))),
        ['wip','in progress','in-progress','work in progress'],true);
    $am121Completed=$rot && strtolower(trim((string)($rot->status??'')))==='completed';
    $am121Voided=\Illuminate\Support\Facades\Schema::hasTable('job_card_change_audits')
        && \Illuminate\Support\Facades\DB::table('job_card_change_audits')
            ->where('job_card_id',$job->id)
            ->where('entity_type','JOB_CARD_LABOUR')
            ->where('entity_id',$line->id)
            ->where('action','COMPLETED_ROT_VOID_AM121')
            ->exists();
@endphp
@if($am121Voided)
  <span class="am121-voided-label" style="display:inline-block;margin:6px 0;padding:5px 8px;border:1px solid #c4b8d9;border-radius:6px;color:#665081;background:#f6f3fb;font-size:11px;font-weight:800">
    VOIDED BILLING — Work history retained
  </span>
@elseif($am121Admin && $am121Wip && $am121Completed && empty($job->invoice_id))
  <details class="am121-void-panel" style="margin:8px 0;padding:8px;border:1px solid #ebc6cb;border-radius:8px;background:#fff9fa">
    <summary style="cursor:pointer;font-size:11px;font-weight:800;color:#9b243b">Void Completed ROT (Admin)</summary>
    <form method="post" action="{{ route('erp.job-cards.labour.void-completed',[$job->id,$line->id]) }}"
      onsubmit="return confirm('Void this COMPLETED ROT billing? The original mechanic, completed work sessions and timing history will remain in the audit. This cannot be undone with this control.');"
      style="margin-top:10px;display:grid;gap:8px;max-width:560px">
      @csrf
      <input type="hidden" name="expected_old_total" value="{{ number_format((float)($line->total??0),2,'.','') }}">
      <label style="font-size:11px;font-weight:750">Void Reason * (12–500 characters)
        <textarea name="reason" minlength="12" maxlength="500" required rows="2" style="width:100%;box-sizing:border-box;border:1px solid #d9b9c0;border-radius:5px;padding:7px" placeholder="Why should this Completed ROT be removed from customer billing?"></textarea>
      </label>
      <label style="font-size:11px;font-weight:750">Admin Note * (5–1000 characters)
        <textarea name="admin_note" minlength="5" maxlength="1000" required rows="2" style="width:100%;box-sizing:border-box;border:1px solid #d9b9c0;border-radius:5px;padding:7px" placeholder="Operational or billing impact, correction reference"></textarea>
      </label>
      <label style="font-size:11px;font-weight:750">Type exactly <strong>VOID {{ strtoupper(trim((string)($line->rot_code??''))) }}</strong> to confirm
        <input type="text" name="confirmation" required maxlength="100" autocomplete="off" spellcheck="false"
          style="display:block;width:100%;box-sizing:border-box;border:1px solid #d9b9c0;border-radius:5px;padding:7px">
      </label>
      <label style="display:flex;gap:7px;align-items:flex-start;font-size:11px;line-height:1.45">
        <input type="checkbox" name="history_ack" value="1" required>
        I understand that original work and mechanic history remains; only the billed labour amount is voided.
      </label>
      <p style="font-size:10px;color:#855565;margin:0">Only WIP and invoice-free Job Cards are eligible. Purchases, money receipts, issued invoices and duplicate voids are blocked by server checks.</p>
      <button type="submit" style="justify-self:start;border:0;border-radius:6px;background:#a52740;color:#fff;padding:8px 13px;font-weight:800;cursor:pointer">Confirm Void Completed ROT</button>
    </form>
  </details>
@endif
