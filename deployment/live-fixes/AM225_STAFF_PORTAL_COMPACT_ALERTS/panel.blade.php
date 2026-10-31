
{{-- AM225_STAFF_PORTAL_COMPACT_ALERTS: grouped pending work, no suppression or deletion. --}}
<section class="staff-panel am225-notifications" id="am225-notifications" aria-label="Mandatory notification history">
  <details class="am225-all-alerts">
    <summary class="am225-alert-title">
      <span>🔔 Mandatory Notifications</span>
      <span class="am225-alert-total">{{ count($data['reminders']??[]) }} pending</span>
      <span class="am225-alert-expand">View / Hide details</span>
    </summary>
    <p class="am225-alert-note">
      Real duty/attendance action complete hone par hi alert resolve hoga.
      Historical alerts aur escalation records delete nahi kiye gaye hain.
    </p>
    @if(count($data['reminders']??[])===0)
      <p class="empty">No pending mandatory notifications.</p>
    @else
      @php
        $am225Grouped=collect($data['reminders'])
          ->groupBy(fn($r)=>strtoupper((string)($r['type']??'OTHER')))
          ->sortKeys();
        $am225RotIds=collect($data['rots']??[])->pluck('id')
          ->map(fn($id)=>(string)$id)->all();
      @endphp
      <div class="am225-alert-groups">
        @foreach($am225Grouped as $am225Type=>$am225Items)
          <details class="am225-alert-group" @if($am225Type==='CHECKOUT_PENDING' && $am225Items->count()<=2) open @endif>
            <summary>
              <strong>{{ str_replace('_',' ',(string)$am225Type) }}</strong>
              <span class="am225-alert-count">{{ $am225Items->count() }}</span>
              <span class="am225-toggle-label">View records</span>
            </summary>
            <div class="am225-alert-items">
              @foreach($am225Items as $r)
                @php
                  $am225CurrentType=strtoupper((string)($r['type']??''));
                  $am225Target=(string)($r['target_id']??'');
                  $am225IsRot=in_array($am225CurrentType,
                     ['ROT_NOT_STARTED','ROT_PAUSED_TOO_LONG','ROT_PAUSED'],true);
                  $am225RotPresent=$am225IsRot && $am225Target!==''
                     && in_array($am225Target,$am225RotIds,true);
                  $am225Attendance=in_array($am225CurrentType,
                     ['CHECKIN_MISSED','CHECKOUT_PENDING','CHECKOUT_MISSED'],true);
                  $am225Permission=$am225CurrentType==='NOTIFICATION_DISABLED';
                @endphp
                <article class="am225-alert-record {{ !empty($r['escalated'])?'am225-escalated':'' }}">
                  <div>
                    <b>{{ str_replace('_',' ',(string)($r['type']??'ALERT')) }}</b>
                    <p>{{ $r['message']??'Work action pending' }}</p>
                  </div>
                  <div class="am225-alert-actions">
                    @if($am225Attendance)
                      <a href="#am225-attendance">Open Attendance</a>
                    @elseif($am225Permission && !$notificationCompliance['compliant'])
                      <a href="#am225-permission">Permission Help</a>
                    @elseif($am225RotPresent)
                      <a href="#rot-{{ $am225Target }}">Open ROT</a>
                    @elseif($am225IsRot)
                      <span class="am225-no-action">ROT not in current list · Contact Admin</span>
                    @elseif($am225Permission)
                      <span class="am225-no-action">Refresh permission status / Contact Admin</span>
                    @else
                      <span class="am225-no-action">View duty status or contact Admin</span>
                    @endif
                  </div>
                </article>
              @endforeach
            </div>
          </details>
        @endforeach
      </div>
    @endif
  </details>
</section>
