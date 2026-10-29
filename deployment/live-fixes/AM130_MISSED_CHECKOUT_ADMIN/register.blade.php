{{-- AM130_MISSED_CHECKOUT_ADMIN_REGISTER: Admin-only; all data is server-validated. --}}
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
  </style>
  <details open>
    <summary>
      <strong>Missed Check-Out Register</strong>
      <span class="am130-number">{{ (int)($am130PendingCount??0) }}</span>
      <span style="font-size:11px;color:#536c84;margin-left:auto">Admin Correction · Audit Required</span>
    </summary>
    <p class="am130-help">Earlier dates with Check-In but no Check-Out. Staff cannot back-date Check-Out and their next Check-In is blocked until all missing dates are resolved. Confirm the employee's actual departure time before correcting; do not use an assumed 11:59 PM. Saved corrections are recorded with Admin identity, before/after and mandatory reason.</p>
    @if($errors->has('am130_reason') || $errors->has('am130_checkout_time') || $errors->has('attendance'))
      <div class="am130-error" role="alert">{{ $errors->first('am130_reason') ?: ($errors->first('am130_checkout_time') ?: $errors->first('attendance')) }}</div>
    @endif
    @if(($am130PendingRows??collect())->isEmpty())
       <div class="am130-empty">No earlier attendance with missing Check-Out. No Admin correction required.</div>
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
      @if((int)$am130PendingCount > $am130PendingRows->count())
       <p class="am130-help">Showing first {{ $am130PendingRows->count() }} of {{ $am130PendingCount }} records. Resolve these and refresh for the remaining cases.</p>
      @endif
    @endif
  </details>
</section>
