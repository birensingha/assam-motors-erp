{{-- V645: Admin-only, WIP-only ROT time controls; no customer price modification. --}}
@php
  $rotClockRole=strtolower(trim((string)(auth()->user()?->role??'')));
  $rotClockWip=in_array(strtolower(trim((string)$job->status)),['wip','in-progress','in progress','work in progress'],true);
  $rotClockAllowed=$rotClockRole==='admin' && $rotClockWip && empty($job->invoice_id);
  $savedSeconds=max(0,(int)$rot->total_seconds);
  $rotClockHms=sprintf('%02d:%02d:%02d',intdiv($savedSeconds,3600),intdiv($savedSeconds%3600,60),$savedSeconds%60);
@endphp
@if($rotClockAllowed)
<details class="action-pop" data-v645-rot-time="{{ $rot->id }}">
  <summary class="btn small" style="background:#edf5ff;color:#163d68;border:1px solid #a5c5ed">✎ Edit ROT Time</summary>
  <div class="pop-form" style="min-width:min(360px,85vw);max-width:460px">
    <div style="font-size:12px;font-weight:800;color:#153e63">Admin Time Correction · {{ $rot->rot_code }}</div>
    <p class="muted" style="font-size:11px">Only WIP and no active invoice. Every edit needs a reason and is recorded in ROT History. Customer rate, discount and GST stay unchanged.</p>

    <form method="post" action="{{ route('erp.rot.time.standard',$rot->id) }}" style="display:grid;gap:7px">
      @csrf
      <input type="hidden" name="expected_std_hours" value="{{ number_format((float)$rot->std_hours,2,'.','') }}">
      <label>Standard Hours (operational target)
        <input name="new_std_hours" type="number" min="0.01" max="168" step="0.01" value="{{ number_format((float)$rot->std_hours,2,'.','') }}" required>
      </label>
      <label>Reason for Standard Time Edit *
        <textarea name="reason" minlength="12" maxlength="255" rows="2" required placeholder="Why is the standard time being revised?"></textarea>
      </label>
      <label>Admin Note (optional)
        <textarea name="note" maxlength="1000" rows="2" placeholder="Reference / supervisor observation"></textarea>
      </label>
      <button class="btn small primary" type="submit" onclick="return confirm('Save audited Standard Hours correction? Labour billing totals will NOT be recalculated.')">Save Standard Hours</button>
    </form>

    <hr style="border:0;border-top:1px solid #cfdbe7;margin:12px 0">
    <div style="font-size:12px;font-weight:800;color:#153e63">Actual / Productive Time Correction</div>
    <p class="muted" style="font-size:11px">Current saved productive time: <strong>{{ $rotClockHms }}</strong>. Original Start/Pause/End events and mechanic segments remain in History.</p>
    @if(in_array($rot->status,['Paused','Completed'],true) && $rot->first_start_at)
      <form method="post" action="{{ route('erp.rot.time.actual',$rot->id) }}" style="display:grid;gap:7px">
        @csrf
        <input type="hidden" name="expected_actual_seconds" value="{{ $savedSeconds }}">
        <label>Corrected Actual Time (HH:MM:SS)
          <input name="new_actual_hms" type="text" inputmode="numeric" pattern="[0-9]{1,3}:[0-5][0-9]:[0-5][0-9]" value="{{ $rotClockHms }}" required placeholder="00:45:30">
        </label>
        <label>Reason for Actual Time Correction *
          <textarea name="reason" minlength="12" maxlength="255" rows="2" required placeholder="Explain the wrong clock or recorded duration"></textarea>
        </label>
        <label>Admin Note (optional)
          <textarea name="note" maxlength="1000" rows="2" placeholder="Reference / supporting evidence"></textarea>
        </label>
        <button class="btn small warning-btn" type="submit" onclick="return confirm('Correct saved productive seconds with a permanent Admin audit? Raw mechanic timing events remain unchanged.')">Save Actual Time Correction</button>
      </form>
    @elseif($rot->status==='Running')
      <p style="padding:8px;background:#fff2d9;border-radius:7px;font-size:12px"><strong>ROT is RUNNING.</strong> Pause this ROT first, then reopen Edit ROT Time to correct productive seconds. Live stopwatch will not be reset.</p>
    @elseif($rot->status==='Assigned')
      <p class="muted" style="font-size:12px">Actual Time correction is available after the ROT has started and is Paused or Completed.</p>
    @else
      <p class="muted" style="font-size:12px">Actual Time correction currently unavailable. Review status and ROT History.</p>
    @endif
    <a href="{{ route('erp.rot.history',$rot->id) }}" class="btn small ghost">📜 View Time Correction Audit</a>
  </div>
</details>
@endif
