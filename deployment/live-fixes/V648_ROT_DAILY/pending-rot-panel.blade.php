{{-- V648: clear separate Admin dashboard pending ROT list; no changes to RTC billing. --}}
<section class="card" style="margin-top:15px;border-top:3px solid #e7a332">
  <div class="panel-head" style="display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap">
    <div><h2>⏳ Pending ROT — Workshop Follow-up</h2>
      <p class="muted">Assigned aur Paused work alag. Check-Out par automatically paused ROT yahin milega; next shift mein manual Resume.</p></div>
    <a class="btn small" href="{{ route('erp.rot-performance') }}">Daily ROT Report →</a>
  </div>
  <div style="display:flex;gap:9px;flex-wrap:wrap;margin:10px 12px">
    <span class="badge">Assigned: {{ $pendingCounts['assigned']??0 }}</span>
    <span class="badge">Paused: {{ $pendingCounts['paused']??0 }}</span>
    <span class="badge active-badge">Running: {{ $pendingCounts['running']??0 }}</span>
  </div>
  <div style="overflow-x:auto;margin:0 12px 14px">
    <table class="data-table" style="width:100%;min-width:720px">
      <thead><tr><th>ROT / Job</th><th>Staff</th><th>Vehicle</th><th>Current Status</th><th>Last Update (IST)</th><th>Action</th></tr></thead>
      <tbody>
      @forelse($pendingRots as $p)
        <tr>
          <td><b>{{ $p->rot_code }}</b><div class="muted">{{ $p->rot_desc }} · {{ $p->job_card_no }}</div></td>
          <td>{{ $p->staff_name?:'Unassigned' }}<div class="muted">{{ $p->staff_code }}</div></td>
          <td>{{ $p->vehicle_reg_no?:'—' }}</td>
          <td><span class="badge">{{ $p->status }}</span>
            @if(!empty($p->invoice_id) || in_array(strtolower((string)($p->jc_status??'')),['closed','invoiced'],true))
              <div style="color:#a24b2e;font-size:11px">JC closed/invoiced — supervisor review</div>
            @endif
          </td>
          <td>{{ $p->updated_at ? \Carbon\CarbonImmutable::parse($p->updated_at,'Asia/Kolkata')->format('d-m h:i A'):'—' }}</td>
          <td><a class="btn small" href="{{ route('erp.job-cards.show',$p->job_card_id) }}">Open JC</a></td>
        </tr>
      @empty
        <tr><td colspan="6" style="text-align:center;padding:17px">No Pending ROT. Sab Assigned/Paused work clear hai.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</section>
