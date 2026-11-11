{{-- V647: one clear, fully visible Admin ROT editor, replacing duplicated legacy conditions. --}}
@php
  $rotClockRole=strtolower(trim((string)(auth()->user()?->role??'')));
  $rotClockStatus=strtolower(trim((string)$job->status));
  $rotClockWip=in_array($rotClockStatus,['wip','in-progress','in progress','work in progress'],true);
  $rotClockFinalStage=in_array($rotClockStatus,['completed','closed','invoiced'],true);
  $rotClockAllowed=$rotClockRole==='admin' && ($rotClockWip || $rotClockFinalStage);
  $rotClockStandardAllowed=$rotClockRole==='admin' && $rotClockWip && empty($job->invoice_id);
  $rotClockSeconds=max(0,(int)$rot->total_seconds);
  $rotClockHms=sprintf('%02d:%02d:%02d',intdiv($rotClockSeconds,3600),intdiv($rotClockSeconds%3600,60),$rotClockSeconds%60);
  $jcOpeningDate=$job->created_at ? \Carbon\CarbonImmutable::parse($job->created_at,'Asia/Kolkata') : null;
  $rotActualStart=$rot->first_start_at ? \Carbon\CarbonImmutable::parse($rot->first_start_at,'Asia/Kolkata') : null;
  $rotActualEnd=$rot->end_time ? \Carbon\CarbonImmutable::parse($rot->end_time,'Asia/Kolkata') : null;
  $elapsedSeconds=$rotActualStart && $rotActualEnd ? max(0,$rotActualEnd->getTimestamp()-$rotActualStart->getTimestamp()) : null;
@endphp
@if($rotClockAllowed)
<details class="am-v647-time-editor" data-v647-session="{{ $rot->id }}" style="position:static!important;flex:1 1 100%;width:100%;order:30;margin-top:8px;overflow:visible!important">
  <summary class="btn small" style="display:inline-block;cursor:pointer;background:#e9f2fd;color:#184c7a;border:1px solid #b6d1ed">✎ Edit ROT Start / End / Time</summary>
  <div style="position:static!important;display:block!important;overflow:visible!important;height:auto!important;max-height:none!important;min-width:0;width:100%;max-width:720px;margin-top:9px;border:1px solid #bfd4ee;border-radius:8px;padding:12px;background:#f8fbff">
    <div style="font-size:13px;font-weight:800;color:#133c63">ROT Time Correction · {{ $rot->rot_code }}</div>
    <p style="font-size:11px;color:#51697e;margin:6px 0 12px">
      Admin-only. Separate: <b>Start/End DateTime</b>, <b>Standard Hours</b>, and <b>Productive Time</b>. Reason compulsory. Invoice amount, GST, Rate and original clock events are never silently changed.
    </p>

    <section style="border:1px solid #c7d9ea;border-radius:7px;padding:10px;background:#fff;margin-bottom:12px">
      <div style="font-weight:800;color:#154d7d;font-size:12px">1. Correct Start & End Date / Time</div>
      <div style="font-size:11px;margin:5px 0;color:#5e7184">
        <b>JC Opening:</b> {{ $jcOpeningDate ? $jcOpeningDate->format('d-m-Y h:i:s A') : 'Not available' }} (IST)
      </div>
      @if($rot->status==='Completed' && $rotActualStart && $rotActualEnd && $jcOpeningDate)
        <form method="post" action="{{ route('erp.rot.time.dates',$rot->id) }}" style="display:grid;gap:9px">
          @csrf
          <input type="hidden" name="expected_start" value="{{ $rotActualStart->format('Y-m-d H:i:s') }}">
          <input type="hidden" name="expected_end" value="{{ $rotActualEnd->format('Y-m-d H:i:s') }}">
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(205px,1fr));gap:9px">
            <label style="font-size:11px;font-weight:700">ROT Start Date & Time (IST)
              <input type="datetime-local" name="start_datetime" step="1"
                min="{{ $jcOpeningDate->format('Y-m-d\TH:i:s') }}"
                value="{{ $rotActualStart->format('Y-m-d\TH:i:s') }}" required
                style="width:100%;display:block;margin-top:5px;padding:8px;border:1px solid #bbcde0;border-radius:6px">
            </label>
            <label style="font-size:11px;font-weight:700">ROT End Date & Time (IST)
              <input type="datetime-local" name="end_datetime" step="1"
                value="{{ $rotActualEnd->format('Y-m-d\TH:i:s') }}" required
                style="width:100%;display:block;margin-top:5px;padding:8px;border:1px solid #bbcde0;border-radius:6px">
            </label>
          </div>
          <div style="font-size:11px;color:#1d648d">Elapsed TAT: <b>{{ $elapsedSeconds===null?'—':sprintf('%02d:%02d:%02d',intdiv($elapsedSeconds,3600),intdiv($elapsedSeconds%3600,60),$elapsedSeconds%60) }}</b> · Productive Time: <b>{{ $rotClockHms }}</b> (unchanged by date correction)</div>
          <label style="font-size:11px;font-weight:700">Modification Reason (required)
            <textarea name="reason" rows="2" minlength="12" maxlength="255" required placeholder="Why were the recorded Start / End timestamps incorrect?" style="display:block;width:100%;margin-top:5px;padding:8px;border:1px solid #bbcde0;border-radius:6px"></textarea>
          </label>
          <label style="font-size:11px;font-weight:700">Admin Note (optional)
            <textarea name="note" rows="2" maxlength="1000" placeholder="Supporting observation / reference" style="display:block;width:100%;margin-top:5px;padding:8px;border:1px solid #bbcde0;border-radius:6px"></textarea>
          </label>
          <div style="font-size:11px;padding:8px;border:1px solid #f3cb84;border-radius:5px;background:#fff5df;color:#905c1b">
            ROT Start cannot be before JC opening. ROT End must be after Start and cannot be in the future. After invoicing, End cannot be later than invoice issue time.
          </div>
          <button class="btn small primary" type="submit" onclick="return confirm('Save audited ROT Start & End timestamps? Original events, productive seconds, and invoices stay unchanged.')">✓ Save Start & End Date/Time</button>
        </form>
      @else
        <p style="font-size:11px;padding:8px;background:#fff1dc;border-radius:6px">
          Start/End history editing is available after this ROT is <b>Completed</b>. Running/Paused ROT must first be completed normally.
        </p>
      @endif
    </section>

    @if($rotClockStandardAllowed)
      <section style="border-top:1px solid #d2e1ef;padding-top:11px">
        <div style="font-weight:800;color:#154d7d;font-size:12px">2. Edit Standard Hours (WIP only)</div>
        <form method="post" action="{{ route('erp.rot.time.standard',$rot->id) }}" style="display:grid;gap:7px;margin-top:8px">
          @csrf
          <input type="hidden" name="expected_std_hours" value="{{ number_format((float)$rot->std_hours,2,'.','') }}">
          <label style="font-size:11px">New Standard Hours
            <input name="new_std_hours" type="number" min="0.01" max="168" step="0.01" value="{{ number_format((float)$rot->std_hours,2,'.','') }}" required style="display:block;width:100%;padding:7px">
          </label>
          <label style="font-size:11px">Reason (required)
            <textarea name="reason" minlength="12" maxlength="255" rows="2" required style="display:block;width:100%;padding:7px"></textarea>
          </label>
          <button class="btn small primary" type="submit">Save Standard Hours</button>
        </form>
      </section>
    @else
      <p style="font-size:11px;padding:8px;background:#fff3e4;border-radius:6px">
        Standard Hours / billing quantity locked outside uninvoiced WIP. Start/End historical TAT correction above does not require invoice reversal.
      </p>
    @endif

    <section style="border-top:1px solid #d2e1ef;padding-top:11px;margin-top:12px">
      <div style="font-weight:800;color:#154d7d;font-size:12px">3. Actual / Productive Time Correction</div>
      <p style="font-size:11px;color:#5a7085;margin:6px 0">Saved productive work: <b>{{ $rotClockHms }}</b>. Correct separately; Start/End adjustment does not change it.</p>
      @if(in_array($rot->status,['Paused','Completed'],true) && $rot->first_start_at && ($rotClockWip || $rot->status==='Completed'))
        <form method="post" action="{{ route('erp.rot.time.actual',$rot->id) }}" style="display:grid;gap:7px">
          @csrf
          <input type="hidden" name="expected_actual_seconds" value="{{ $rotClockSeconds }}">
          <label style="font-size:11px">Corrected Productive Time (HH:MM:SS)
            <input name="new_actual_hms" type="text" inputmode="numeric" pattern="[0-9]{1,3}:[0-5][0-9]:[0-5][0-9]" value="{{ $rotClockHms }}" required style="display:block;width:100%;padding:7px">
          </label>
          <label style="font-size:11px">Reason (required)
            <textarea name="reason" minlength="12" maxlength="255" rows="2" required style="display:block;width:100%;padding:7px"></textarea>
          </label>
          <button class="btn small" type="submit" onclick="return confirm('Save audited Productive Time Correction?')">Save Productive Time</button>
        </form>
      @else
        <p style="font-size:11px;color:#815618">Pause/Complete the ROT before correcting saved productive seconds.</p>
      @endif
    </section>
    <a class="btn small ghost" href="{{ route('erp.rot.history',$rot->id) }}" style="display:inline-block;margin-top:12px">📜 History & Admin Audit</a>
  </div>
</details>
@endif
