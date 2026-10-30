{{-- AM208_ATTENDANCE_PENDING_FILTERS: extend existing AM130 Admin-only audited register; all correction forms preserved. --}}
<section class="am130-register" id="am130-missed-checkout-register" aria-label="Missed Check-Out Register">
  <style>
  .am130-register{margin:12px 0 18px;border:1px solid #c6d8e9;border-radius:12px;background:#fff;box-shadow:0 3px 12px #17395d0d;overflow:hidden}
  .am130-register>details>summary{display:flex;align-items:center;gap:10px;padding:13px 16px;background:#f3f8fd;cursor:pointer;list-style:none;color:#133c60}
  .am130-register>details>summary::-webkit-details-marker{display:none}
  .am130-register .am130-number{padding:4px 9px;border-radius:15px;background:#ffede2;color:#9c4319;font-weight:850;min-width:32px;text-align:center;font-variant-numeric:tabular-nums}
  .am130-register .am130-help{font-size:12px;color:#536c84;padding:8px 16px;line-height:1.55}
  .am130-register .am130-table-wrap{overflow-x:auto}
  .am130-register table{border-collapse:collapse;width:100%;min-width:900px;font-size:12px}
  .am130-register th{background:#f4f7fa;color:#324e68;position:sticky;top:0;text-align:left;font-weight:750;white-space:nowrap}
  .am130-register th,.am130-register td{padding:9px 11px;border-bottom:1px solid #e2ebf2;vertical-align:middle}
  .am130-register .am130-missing{font-weight:800;color:#ab391b}
  .am130-register .am130-form{display:flex;align-items:end;gap:7px;min-width:385px;flex-wrap:wrap}
  .am130-register .am130-form label{display:flex;flex-direction:column;gap:3px;font-weight:700;color:#344d64;font-size:11px}
  .am130-register .am130-form input{border:1px solid #c8d5e2;border-radius:6px;background:#fff;padding:7px 8px;height:34px;box-sizing:border-box;font-size:12px;min-width:0}
  .am130-register .am130-form input[type=time]{width:120px}
  .am130-register .am130-form input[name=am130_reason]{width:245px}
  .am130-register .am130-form button{height:34px;border:0;border-radius:6px;background:#165c9b;color:#fff;font-size:12px;font-weight:800;padding:0 11px;cursor:pointer}
  .am130-register .am130-form button:hover{background:#0c4275}
  .am130-register .am130-empty{padding:14px 16px;color:#38765b;font-weight:650}
  .am130-register .am130-error{margin:9px 16px;padding:8px 10px;border-left:3px solid #b43f27;background:#fff3ee;color:#982a14;font-size:12px}
  @media(max-width:640px){.am130-register>details>summary{padding:11px}.am130-register .am130-form{min-width:340px}}

  /* AM208: concise, separate filter bar; no interference with the authenticated correction form */
  .am130-register .am208-filter{display:grid;grid-template-columns:minmax(165px,2fr) minmax(150px,1.35fr) repeat(2,minmax(125px,1fr)) auto auto;gap:8px;align-items:end;margin:3px 14px 10px;padding:12px;border:1px solid #dbe6f2;border-radius:9px;background:#f7fafd}
  .am130-register .am208-filter label{display:flex;flex-direction:column;gap:5px;font-size:11px;color:#375472;font-weight:750;min-width:0}
  .am130-register .am208-filter input,.am130-register .am208-filter select{box-sizing:border-box;min-width:0;width:100%;height:35px;background:#fff;border:1px solid #bfcfdf;border-radius:6px;padding:6px 8px;font-size:12px;color:#214366}
  .am130-register .am208-filter button,.am130-register .am208-filter a{height:35px;display:inline-flex;justify-content:center;align-items:center;padding:0 12px;border-radius:6px;box-sizing:border-box;font-weight:800;font-size:11px;text-decoration:none;white-space:nowrap}
  .am130-register .am208-filter button{color:#fff;border:1px solid #195da0;background:#195da0;cursor:pointer}
  .am130-register .am208-filter a{color:#19538a;border:1px solid #bfcfdf;background:#fff}
  .am130-register .am208-filter input:focus-visible,.am130-register .am208-filter select:focus-visible{outline:2px solid #3f83c5;outline-offset:1px}
  .am130-register .am208-result{display:flex;gap:12px;flex-wrap:wrap;align-items:center;padding:2px 16px 10px;color:#425e7b;font-size:11px}
  .am130-register .am208-result strong{color:#183f70}
  .am130-register .am208-today-title{font-weight:800;font-size:12px;color:#234d73;padding:12px 16px 7px;border-top:1px solid #e2ebf2}
  .am130-register .am208-no-in{color:#99651f;font-weight:750}
  .am130-register .am208-help{color:#62788f;font-size:11px;font-weight:400}
  @media(max-width:1100px){.am130-register .am208-filter{grid-template-columns:repeat(3,minmax(0,1fr))}}
  @media(max-width:700px){.am130-register .am208-filter{grid-template-columns:repeat(2,minmax(0,1fr))}.am130-register .am208-filter label:first-child,.am130-register .am208-filter label:nth-child(2){grid-column:1 / -1}}
  </style>
  <details open>
    <summary>
      <strong>Missed Check-Out Register</strong>
      <span class="am130-number">{{ (int)($am130PendingCount??0) }}</span>
      <span style="font-size:11px;color:#536c84;margin-left:auto">Admin Correction · Audit Required</span>
    </summary>
    <p class="am130-help">Earlier dates with Check-In but no Check-Out. Staff cannot back-date Check-Out and their next Check-In is blocked until all missing dates are resolved. Confirm the employee's actual departure time before correcting; do not use an assumed 11:59 PM. Saved corrections are recorded with Admin identity, before/after and mandatory reason.</p>

    <form class="am208-filter" method="get" action="{{ route('erp.attendance') }}" aria-label="Employee attendance pending filters">
      <label>Employee Name / Staff Code
        <input type="search" name="am208_staff" value="{{ $am208Staff??'' }}" maxlength="100" autocomplete="off" placeholder="Type name or staff code">
      </label>
      <label>Pending Type
        <select name="am208_kind">
          <option value="missed_checkout" @selected(($am208Kind??'missed_checkout')==='missed_checkout')>Missing Check-Out</option>
          <option value="blocked_checkin" @selected(($am208Kind??'')==='blocked_checkin')>Check-In Blocked (Past Check-Out)</option>
          <option value="no_checkin_today" @selected(($am208Kind??'')==='no_checkin_today')>No Check-In Today</option>
          <option value="all" @selected(($am208Kind??'')==='all')>All Pending / Not Checked In</option>
        </select>
      </label>
      <label>Date From
        <input type="date" name="am208_from" value="{{ $am208From??'' }}" max="{{ now('Asia/Kolkata')->toDateString() }}">
      </label>
      <label>Date To
        <input type="date" name="am208_to" value="{{ $am208To??'' }}" max="{{ now('Asia/Kolkata')->toDateString() }}">
      </label>
      <button type="submit">Apply Filter</button>
      <a href="{{ route('erp.attendance') }}">Reset</a>
    </form>
    <div class="am208-result">
      <span>Matching missed Check-Out: <strong>{{ $am208CheckoutMatched??0 }}</strong></span>
      <span>All unresolved: <strong>{{ (int)($am130PendingCount??0) }}</strong></span>
      @if(($am208Kind??'')==='no_checkin_today'||($am208Kind??'')==='all')
      <span>Not checked-in today: <strong>{{ $am208NoCheckinCount??0 }}</strong></span>
      @endif
    </div>
    @if($errors->has('am130_reason') || $errors->has('am130_checkout_time') || $errors->has('attendance'))
      <div class="am130-error" role="alert">{{ $errors->first('am130_reason') ?: ($errors->first('am130_checkout_time') ?: $errors->first('attendance')) }}</div>
    @endif
    @if(($am130PendingRows??collect())->isEmpty())
       <div class="am130-empty">No previous-day missing Check-Out entries match the selected employee / date / status filters.</div>
    @else
      <div class="am130-table-wrap">
        <table>
          <thead><tr><th>Attendance Date</th><th>Employee / Staff Code</th><th>Check-In (IST)</th><th>Check-Out</th><th>Admin Correction</th></tr></thead>
          <tbody>
           @foreach($am130PendingRows as $am130Row)
            <tr>
              <td><strong>{{ \Carbon\Carbon::parse($am130Row->date)->format('d-m-Y') }}</strong></td>
              <td><strong>{{ $am130Row->employee_name ?: ('Staff #'.$am130Row->staff_id) }}</strong><div style="color:#678099">{{ $am130Row->staff_code ?: '—' }}{{ $am130Row->department ? ' · '.$am130Row->department : '' }}</div></td>
              <td>{{ $am130Row->check_in ? \Carbon\Carbon::parse($am130Row->check_in)->format('h:i A') : '—' }}</td>
              <td><span class="am130-missing">Missing · Check-In Blocked</span></td>
              <td>
                <form class="am130-form" method="post" action="{{ route('erp.attendance.update',$am130Row->id) }}" onsubmit="return confirm('Have you verified the actual Check-Out time and recorded the correction reason? This action is audited.')">
                  @csrf
                  <input type="hidden" name="am130_resolution" value="1">
                  <label>Actual Check-Out (IST) *
                    <input type="time" name="am130_checkout_time" required>
                  </label>
                  <label>Reason / Evidence *
                    <input type="text" name="am130_reason" minlength="12" maxlength="1000" placeholder="Verified actual departure + why missed" required>
                  </label>
                  <button type="submit">Save & Audit</button>
                </form>
              </td>
            </tr>
           @endforeach
          </tbody>
        </table>
      </div>
      @if((int)($am208CheckoutMatched??0) > $am130PendingRows->count())
       <p class="am130-help">Showing first {{ $am130PendingRows->count() }} of {{ $am208CheckoutMatched }} matching missed Check-Out records. Narrow the employee/date filters to see remaining records. Resolve these and refresh for the remaining cases.</p>
      @endif
    @endif
    @if(($am208Kind??'')==='no_checkin_today'||($am208Kind??'')==='all')
      <div class="am208-today-title">Employees with no recorded Check-In today ({{ now('Asia/Kolkata')->format('d-m-Y') }})
       <span class="am208-help">Informational only — includes employees on approved leave, weekly off or not yet on duty. This is not an Absence declaration.</span>
      </div>
      @if(($am208NoCheckinRows??collect())->isEmpty())
        <p class="am130-help">No employees match today's no-Check-In filter.</p>
      @else
        <div class="am130-table-wrap">
          <table>
            <thead><tr><th>Employee / Staff Code</th><th>Department</th><th>Attendance Date</th><th>Status</th></tr></thead>
            <tbody>
              @foreach($am208NoCheckinRows as $am208Row)
              <tr>
                <td><strong>{{ $am208Row->employee_name ?: ('Staff #'.$am208Row->staff_id) }}</strong>
                  <div style="color:#678099">{{ $am208Row->staff_code ?: '—' }}</div>
                </td>
                <td>{{ $am208Row->department ?: '—' }}</td>
                <td>{{ now('Asia/Kolkata')->format('d-m-Y') }}</td>
                <td><span class="am208-no-in">No Check-In Recorded</span></td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @if(($am208NoCheckinCount??0) > $am208NoCheckinRows->count())
          <p class="am130-help">Showing first {{ $am208NoCheckinRows->count() }} of {{ $am208NoCheckinCount }} employees. Narrow staff search for remaining employees.</p>
        @endif
      @endif
    @endif
  </details>
</section>
