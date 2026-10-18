{{-- V648 daily ROT register: accurate date-clipped Start/Resume/Pause/Complete events. --}}
@php
  $showDuration=static function($s){
    $s=max(0,(int)$s);
    return sprintf('%02dh %02dm %02ds',intdiv($s,3600),intdiv($s%3600,60),$s%60);
  };
@endphp
<section class="card" style="border-top:3px solid #175b9b;margin-bottom:18px">
  <div class="panel-head"><div>
    <h2>📅 Staff Daily ROT Performance</h2>
    <p class="muted">Ek din ka Check-In → Assigned ROT → Start/Pause/Resume/Complete → Check-Out record (IST).</p>
  </div></div>
  <form method="get" action="{{ route('erp.rot-performance') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin:10px 14px 15px">
    <label style="font-size:12px">Date (IST)
      <input type="date" name="date" value="{{ $reportDate }}" max="{{ now('Asia/Kolkata')->toDateString() }}" required style="display:block;min-height:36px">
    </label>
    <label style="font-size:12px">Staff
      <select name="staff_id" style="display:block;min-width:205px;min-height:36px">
        <option value="">All Staff — Daily Summary</option>
        @foreach($staffOptions as $member)
          <option value="{{ $member->id }}" {{ (int)$selectedStaffId===(int)$member->id?'selected':'' }}>
            {{ $member->name }} ({{ $member->staff_code }})
          </option>
        @endforeach
      </select>
    </label>
    <button type="submit" class="btn primary">🔎 Show Daily Report</button>
    <a href="{{ route('erp.rot-performance') }}" class="btn">Today</a>
  </form>
  @if($dailyDetail)
    <div style="margin:0 14px 10px;padding:10px;background:#edf4ff;border:1px solid #c2d8f0;border-radius:7px">
      <b>{{ $dailyDetail['staff']->name }}</b> · {{ $dailyDetail['staff']->staff_code }}
      · {{ \Carbon\CarbonImmutable::parse($reportDate)->format('d-m-Y') }}
      <div class="muted" style="margin-top:5px">
        Check-In: <b>{{ $dailyDetail['attendance']?->check_in ? \Carbon\CarbonImmutable::parse($dailyDetail['attendance']->check_in,'Asia/Kolkata')->format('h:i:s A'):'Not recorded' }}</b>
        &nbsp; | &nbsp; Check-Out: <b>{{ $dailyDetail['attendance']?->check_out ? \Carbon\CarbonImmutable::parse($dailyDetail['attendance']->check_out,'Asia/Kolkata')->format('h:i:s A'):'Not checked out' }}</b>
      </div>
    </div>
    <div class="dashboard-kpis" style="padding:8px 14px">
      @foreach([
        ['Duty Time',$showDuration($dailyDetail['summary']['duty_seconds'])],
        ['Productive',$showDuration($dailyDetail['summary']['productive_seconds'])],
        ['Pause Duration',$showDuration($dailyDetail['summary']['paused_seconds'])],
        ['Other Duty Time',$showDuration($dailyDetail['summary']['unaccounted_seconds'])],
        ['ROT Count',$dailyDetail['summary']['rot_count']],
        ['Pause / Resume',$dailyDetail['summary']['pause_count'].' / '.$dailyDetail['summary']['resume_count']],
        ['Auto Check-Out Pauses',$dailyDetail['summary']['auto_pause_count']],
        ['Pending',$dailyDetail['summary']['pending_count']],
      ] as $tile)
        <div class="dashboard-kpi"><div><small>{{ $tile[0] }}</small><strong>{{ $tile[1] }}</strong></div></div>
      @endforeach
    </div>
    @foreach($dailyDetail['warnings'] as $warning)
      <div style="margin:9px 14px;padding:9px;border:1px solid #f2d18e;background:#fff8e8;border-radius:6px;color:#8a5e0b">{{ $warning }}</div>
    @endforeach
    <div style="overflow-x:auto;margin:12px 14px">
      <h3 style="font-size:14px;font-weight:800;margin-bottom:9px">1. ROT-wise Daily Production</h3>
      <table class="data-table" style="width:100%;min-width:880px">
        <thead><tr><th>Job Card / ROT</th><th>Vehicle</th><th>Standard Hrs</th><th>Productive Today</th><th>Pause Duration</th><th>Pause / Resume</th><th>Auto Pause</th><th>Current Status</th></tr></thead>
        <tbody>
          @forelse($dailyDetail['rot_rows'] as $r)
            <tr>
              <td><b>{{ $r->rot_code }}</b><div class="muted">{{ $r->rot_desc }}</div><a href="{{ route('erp.job-cards.show',$r->job_card_id??0) }}">{{ $r->job_card_no }}</a></td>
              <td>{{ $r->vehicle_reg_no??'—' }}</td>
              <td>{{ number_format((float)$r->std_hours,2) }}h</td>
              <td><b>{{ $showDuration($r->daily_productive_seconds) }}</b></td>
              <td>{{ $showDuration($r->daily_paused_seconds) }}</td>
              <td>{{ $r->daily_pause_count }} / {{ $r->daily_resume_count }}</td>
              <td>{{ $r->daily_auto_pause_count }}</td>
              <td><span class="badge">{{ $r->status }}</span></td>
            </tr>
          @empty
            <tr><td colspan="8" style="padding:20px;text-align:center">No ROT activity or assignment recorded for this staff on this date.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div style="overflow-x:auto;margin:12px 14px">
      <h3 style="font-size:14px;font-weight:800;margin-bottom:9px">2. Complete Daily Activity Timeline</h3>
      <table class="data-table" style="width:100%;min-width:720px">
        <thead><tr><th>Time (IST)</th><th>Activity</th><th>ROT</th><th>Job Card</th><th>Remarks</th></tr></thead>
        <tbody>
          @forelse($dailyDetail['timeline'] as $event)
            <tr>
              <td>{{ \Carbon\CarbonImmutable::createFromTimestamp($event['at'],'Asia/Kolkata')->format('h:i:s A') }}</td>
              <td><b>{{ $event['action'] }}</b></td>
              <td>{{ $event['rot_code']??'—' }}</td>
              <td>{{ $event['job_no']??'—' }}</td>
              <td>{{ ($event['note']??'')==='AUTO_CHECK_OUT'?'Automatically Paused on Check-Out':(($event['note']??'')==='MANUAL'?'Staff/Admin Paused':'—') }}</td>
            </tr>
          @empty
            <tr><td colspan="5" style="padding:20px;text-align:center">No Check-In, Check-Out, or ROT events for the selected day.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  @else
    <div style="padding:0 14px 14px">
      <h3 style="font-size:14px;font-weight:800">Daily Staff Attendance Summary</h3>
      <p class="muted">Staff name par click karne se poori ROT productive time aur Pause/Resume history milegi.</p>
      <div style="overflow-x:auto"><table class="data-table" style="width:100%;min-width:620px">
        <thead><tr><th>Staff</th><th>Check-In</th><th>Check-Out</th><th>Duty Hours</th><th>Daily ROT Register</th></tr></thead>
        <tbody>
          @forelse($attendanceOverview as $a)
            <tr>
              <td><b>{{ $a->staff_name }}</b><div class="muted">{{ $a->staff_code }}</div></td>
              <td>{{ $a->check_in ? \Carbon\CarbonImmutable::parse($a->check_in,'Asia/Kolkata')->format('h:i:s A'):'—' }}</td>
              <td>{{ $a->check_out ? \Carbon\CarbonImmutable::parse($a->check_out,'Asia/Kolkata')->format('h:i:s A'):'Pending' }}</td>
              <td>{{ number_format((float)$a->worked_hours,2) }}h</td>
              <td><a href="{{ route('erp.rot-performance',['date'=>$reportDate,'staff_id'=>$a->staff_id]) }}" class="btn small">View All ROT Activity</a></td>
            </tr>
          @empty
            <tr><td colspan="5" style="padding:20px;text-align:center">No attendance registered this day. Select an individual staff above to view ROT activity.</td></tr>
          @endforelse
        </tbody>
      </table></div>
    </div>
  @endif
  <p class="muted" style="padding:0 14px 14px;font-size:11px">
    Productive seconds are derived from the day's recorded Start/Resume → Pause/Complete intervals (clipped to attendance and day boundaries).
    Admin corrections and original clock records remain independently available in ROT History. Duty hours include non-ROT time.
    Report is a daily activity ledger, not an invoice.
  </p>
</section>
