{{-- V649: read-only advanced ROT performance register. --}}
@php
$fmtTime = static fn($seconds)=>sprintf('%02d:%02d:%02d', intdiv(max(0,(int)$seconds),3600),intdiv(max(0,(int)$seconds)%3600,60),max(0,(int)$seconds)%60);
@endphp
<section class="card" style="margin-bottom:16px;border-top:4px solid #145e9c">
  <div class="panel-head" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;justify-content:space-between">
    <div>
      <h2>📊 Advanced Staff / ROT Performance Register</h2>
      <p class="muted">Five filters · Job Card-wise drilldown · All Pause/Resume reasons · Vehicle In & actual time analysis</p>
    </div>
    <span class="badge active-badge">Read-only Audit Report</span>
  </div>
  <form method="get" action="{{ route('erp.rot-performance') }}" style="padding:14px;background:#eef5fc;border-bottom:1px solid #c9dbe9">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(175px,1fr));gap:9px">
      <label style="font-size:11px;font-weight:800;color:#30536f">1. Staff Name
        <select name="staff_id" style="width:100%;display:block;margin-top:5px;min-height:38px;padding:7px">
          <option value="">All Staff</option>
          @foreach($staffOptions as $member)
            <option value="{{ $member->id }}" {{ (int)($advancedRotReport['filters']['staff_id']??0)===(int)$member->id?'selected':'' }}>
              {{ $member->name }} ({{ $member->staff_code }})
            </option>
          @endforeach
        </select>
      </label>
      <label style="font-size:11px;font-weight:800;color:#30536f">2. ROT / Labour
        <select name="rot_code" style="width:100%;display:block;margin-top:5px;min-height:38px;padding:7px">
          <option value="">All ROT</option>
          @foreach($advancedRotCodes as $code)
            <option value="{{ $code->rot_code }}" {{ ($advancedRotReport['filters']['rot_code']??'')===$code->rot_code?'selected':'' }}>
              {{ $code->rot_code }} — {{ $code->rot_desc }}
            </option>
          @endforeach
        </select>
      </label>
      <label style="font-size:11px;font-weight:800;color:#30536f">3. From Date
        <input name="from" type="date" value="{{ $advancedRotReport['filters']['from']??'' }}"
          style="width:100%;display:block;margin-top:5px;min-height:38px;padding:7px">
      </label>
      <label style="font-size:11px;font-weight:800;color:#30536f">To Date
        <input name="to" type="date" max="{{ now('Asia/Kolkata')->toDateString() }}"
          value="{{ $advancedRotReport['filters']['to']??'' }}"
          style="width:100%;display:block;margin-top:5px;min-height:38px;padding:7px">
      </label>
      <label style="font-size:11px;font-weight:800;color:#30536f">4. Vehicle No
        <input type="search" name="vehicle" value="{{ $advancedRotReport['filters']['vehicle']??'' }}"
          placeholder="AS01..." style="width:100%;display:block;margin-top:5px;min-height:38px;padding:7px">
      </label>
      <label style="font-size:11px;font-weight:800;color:#30536f">5. Job Card No
        <input type="search" name="job_card" value="{{ $advancedRotReport['filters']['job_card']??'' }}"
          placeholder="JC-2026..." style="width:100%;display:block;margin-top:5px;min-height:38px;padding:7px">
      </label>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-top:12px">
      <span class="muted" style="font-size:11px">Leave dates blank for all recorded history. From–To includes overlapping multi-day ROT. All times IST.</span>
      <div style="display:flex;gap:8px">
        <a class="btn small" href="{{ route('erp.rot-performance') }}">↻ Clear Filters</a>
        <button type="submit" class="btn primary">🔎 Search Report</button>
      </div>
    </div>
  </form>

  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:8px;padding:12px 14px">
    @foreach([
      ['ROT Matched',$advancedRotReport['totals']['rot_count']],
      ['Total Standard',$advancedRotReport['totals']['standard_formatted']],
      ['Saved Actual',$advancedRotReport['totals']['actual_formatted']],
      ['Period Working',$advancedRotReport['totals']['period_formatted']],
      ['Over Standard',$advancedRotReport['totals']['over_standard_formatted']],
      ['Non-Productive Elapsed',$advancedRotReport['totals']['nonproductive_formatted']],
      ['Pause / Resume',$advancedRotReport['totals']['pause_count'].' / '.$advancedRotReport['totals']['resume_count']],
    ] as $kpi)
      <div style="padding:12px;background:#f5f9fd;border:1px solid #d6e3ee;border-radius:8px">
        <div style="font-size:10px;color:#55718b;font-weight:700">{{ $kpi[0] }}</div>
        <div style="font-weight:900;font-size:16px;color:#154a78;white-space:nowrap;margin-top:5px">{{ $kpi[1] }}</div>
      </div>
    @endforeach
  </div>
  @if($advancedRotReport['limited'])
    <p style="padding:10px;margin:8px 13px;background:#fff5e5;border:1px solid #edc986;border-radius:5px;color:#996012;font-size:12px">
      First 150 ROT shown. Please narrow Staff / Date / JC / Vehicle filters to retrieve all matching lines.
    </p>
  @endif
  <div style="margin:0 14px 13px;padding:10px;background:#eaf4ff;border:1px solid #c0d8ef;border-radius:6px;font-size:11px;color:#285876">
    <b>Reading the time figures:</b> Standard = allotted work; Saved Actual = total productive time on that ROT;
    Period Working = event-recorded work between selected From–To dates; Over Standard = Actual exceeding target.
    Non-Productive Elapsed = Start-to-End minus productive seconds. It includes overnight/off-duty/waiting and cannot automatically be called mechanic waste.
  </div>

  <div style="padding:3px 14px 13px">
    <h3 style="font-size:14px;color:#1a496e;font-weight:900;margin:5px 0 10px">Job Card-wise ROT Register</h3>
    @forelse($advancedRotReport['groups'] as $group)
      <details {{ ($advancedRotReport['filters']['job_card']??'')!==''?'open':'' }}
        style="border:1px solid #cbd9e8;border-radius:9px;margin-bottom:12px;background:#fff;overflow:hidden">
        <summary style="cursor:pointer;padding:12px 13px;background:#e9f2fd;color:#20486d;font-size:12px;font-weight:800;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
          <span>▸ {{ $group['job_no'] }}</span>
          <span style="color:#356b95">🚗 {{ $group['vehicle'] }}</span>
          <span style="font-weight:500;color:#54718a">{{ count($group['rots']) }} ROT</span>
          <span class="badge">{{ $group['jc_status'] }}</span>
          <span style="margin-left:auto;font-size:10px">Click to Expand All ROT</span>
        </summary>
        <div style="padding:10px 12px;border-bottom:1px solid #d4e2ed;background:#fafcff;display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:8px;font-size:11px">
          <div><b>Vehicle In:</b><div>{{ $group['vehicle_in'] }}</div></div>
          <div><b>JC Opening:</b><div>{{ $group['jc_opened'] }}</div></div>
          <div><b>JC Closed:</b><div>{{ $group['jc_closed'] }}</div></div>
          <div><b>Vehicle Out:</b><div>{{ $group['vehicle_out'] }} <span class="muted">(No gate-out timestamp in ERP)</span></div></div>
        </div>
        <div style="overflow-x:auto">
          <table class="data-table" style="width:100%;min-width:1110px;font-size:11px">
            <thead><tr>
              <th>ROT / Staff</th><th>Standard</th><th>Actual</th><th>Selected Period</th>
              <th>Elapsed TAT</th><th>Non-Productive</th><th>Over Std</th><th>Pause / Resume</th>
              <th>Days Since Start</th><th>Status</th>
            </tr></thead>
            <tbody>
              @foreach($group['rots'] as $r)
                <tr>
                  <td style="min-width:170px"><b>{{ $r['rot_code'] }}</b><div>{{ $r['rot_desc'] }}</div><div class="muted">{{ $r['staff']?:'Unassigned' }}</div></td>
                  <td>{{ $r['std'] }}</td><td><b>{{ $r['actual'] }}</b></td>
                  <td>{{ $r['period'] }}</td><td>{{ $r['elapsed'] }}</td>
                  <td style="color:#98671b">{{ $r['nonproductive'] }}</td>
                  <td style="color:{{ $r['over_standard_seconds']>0?'#b03934':'#178351' }}"><b>{{ $r['over_standard'] }}</b></td>
                  <td>{{ $r['pause_count'] }} / {{ $r['resume_count'] }}</td>
                  <td>{{ $r['days_since_start'] }} day(s)</td>
                  <td>{{ $r['current_status'] }}</td>
                </tr>
                <tr><td colspan="10" style="padding:7px 12px;background:#f9fbfe">
                  <details {{ ($advancedRotReport['filters']['job_card']??'')!==''?'open':'' }}
                    style="border:1px solid #d9e5f0;border-radius:7px;background:white">
                    <summary style="cursor:pointer;padding:9px 11px;color:#175e9b;font-weight:800">
                      ▼ Full ROT History: {{ $r['rot_code'] }} | Start {{ $r['start'] }} | End {{ $r['end'] }}
                    </summary>
                    <div style="padding:12px">
                      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:13px">
                        <span class="badge">Pause: {{ $r['pause_count'] }}</span>
                        <span class="badge">Resume: {{ $r['resume_count'] }}</span>
                        <span class="badge">Cumulative Pause: {{ $r['pause_duration'] }}</span>
                        <span class="badge">Staff Segment Records: {{ count($r['staff_segments']) }}</span>
                      </div>
                      <h4 style="font-size:12px;font-weight:900;color:#254d70">A. Event-by-Event Timeline (All Days)</h4>
                      <div style="overflow-x:auto"><table class="data-table" style="width:100%;min-width:620px">
                        <thead><tr><th>Date & Time IST</th><th>Event</th><th>Staff / Actor</th><th>Pause Reason</th><th>Notes</th></tr></thead>
                        <tbody>
                          @forelse($r['history'] as $h)
                            <tr><td>{{ $h['time'] }}</td><td><b>{{ $h['action'] }}</b></td><td>{{ $h['staff_name'] }}</td><td>{{ $h['reason']?:'—' }}</td><td>{{ $h['note']?:'—' }}</td></tr>
                          @empty<tr><td colspan="5">No recorded ROT event history.</td></tr>@endforelse
                        </tbody>
                      </table></div>
                      <h4 style="font-size:12px;font-weight:900;color:#254d70;margin:14px 0 8px">B. All Pause Reasons / Durations</h4>
                      <div style="overflow-x:auto"><table class="data-table" style="width:100%;min-width:650px">
                        <thead><tr><th>Paused at (IST)</th><th>Resumed at (IST)</th><th>Duration</th><th>Reason</th><th>Remark</th></tr></thead>
                        <tbody>
                          @forelse($r['pause_records'] as $p)
                            <tr><td>{{ $p['time'] }}</td><td>{{ $p['resumed_at'] }}</td><td>{{ $p['duration'] }}</td><td><b>{{ $p['reason'] }}</b></td><td>{{ $p['note']?:'—' }}</td></tr>
                          @empty<tr><td colspan="5">No Pause reasons saved for this ROT.</td></tr>@endforelse
                        </tbody>
                      </table></div>
                      <h4 style="font-size:12px;font-weight:900;color:#254d70;margin:14px 0 8px">C. Mechanic Assignment & Productive Segments</h4>
                      <div style="overflow-x:auto"><table class="data-table" style="width:100%;min-width:630px">
                        <thead><tr><th>Staff</th><th>Assigned</th><th>First Started</th><th>Ended</th><th>Segment Productive</th><th>Reason</th></tr></thead>
                        <tbody>
                          @forelse($r['staff_segments'] as $segment)
                            <tr><td>{{ $segment['name'] }}<div class="muted">{{ $segment['code'] }}</div></td>
                              <td>{{ $segment['assigned_at'] }}</td><td>{{ $segment['first_started_at'] }}</td>
                              <td>{{ $segment['ended_at'] }}</td><td><b>{{ $segment['productive'] }}</b></td><td>{{ $segment['reason']?:'—' }}</td></tr>
                          @empty<tr><td colspan="6">Mechanic segments not recorded for this ROT.</td></tr>@endforelse
                        </tbody>
                      </table></div>
                      <a class="btn small" href="{{ route('erp.rot.history',$r['id']) }}" style="display:inline-block;margin-top:12px">
                        Open Original ROT & Admin Correction Audit
                      </a>
                    </div>
                  </details>
                </td></tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <div style="padding:9px 12px;background:#f5f9fe;text-align:right">
          <a class="btn small" href="{{ route('erp.job-cards.show',$group['job_card_id']) }}">Open Job Card →</a>
        </div>
      </details>
    @empty
      <div style="padding:24px;text-align:center;background:#f9fbfe;border:1px dashed #bdcfdf;border-radius:7px">
        No ROT matches the selected filters. Try clearing From/To or selecting a different Staff/JC/Vehicle.
      </div>
    @endforelse
  </div>
  <div style="padding:11px 14px;border-top:1px solid #d8e4ee;background:#f8fbff">
    <h3 style="font-size:14px;font-weight:900;color:#204e73;margin-bottom:7px">Check-In / Check-Out Raw Attendance Records</h3>
    <p class="muted" style="font-size:11px;margin-bottom:7px">Each saved attendance row for staff associated with the matched ROTs. Check-In/Out is a duty record, not Vehicle In/Out.</p>
    <div style="max-height:420px;overflow:auto">
      <table class="data-table" style="width:100%;min-width:690px">
        <thead><tr><th>Staff</th><th>Attendance Date</th><th>Check-In IST</th><th>Check-Out IST</th><th>Saved Duty Hours</th></tr></thead>
        <tbody>
          @forelse($advancedRotReport['attendance'] as $a)
            <tr><td><b>{{ $a['staff'] }}</b> ({{ $a['code'] }})</td>
              <td>{{ $a['date'] }}</td><td>{{ $a['checkin'] }}</td><td>{{ $a['checkout'] }}</td>
              <td>{{ number_format((float)$a['duty_hours'],2) }}h</td></tr>
          @empty<tr><td colspan="5">No Attendance record for matched staff/date.</td></tr>@endforelse
        </tbody>
      </table>
    </div>
  </div>
</section>
