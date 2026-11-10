{{-- AM242: Staff portal self-visible complaint history only. Never use request-supplied staff IDs. --}}
@php
  $am242Actor=auth()->user();
  $am242StaffId=($am242Actor && method_exists($am242Actor,'isStaff') && $am242Actor->isStaff())
      ? (int)($am242Actor->staff_id??0) : 0;
  $am242Own=collect();
  $am242Receiving=collect();
  if ($am242StaffId>0 && \Illuminate\Support\Facades\Schema::hasTable('am_rot_work_complaints')) {
      $am242Own=\Illuminate\Support\Facades\DB::table('am_rot_work_complaints')
          ->where('staff_id',$am242StaffId)->orderByDesc('id')->limit(30)->get();
      // Receiving mechanic entries appear only after a real AM241 rework link exists.
      if (\Illuminate\Support\Facades\Schema::hasTable('am_complaint_rework_links')) {
          $am242Receiving=\Illuminate\Support\Facades\DB::table('am_complaint_rework_links as l')
              ->join('am_rot_work_complaints as c','c.id','=','l.complaint_id')
              ->where('l.assigned_staff_id',$am242StaffId)
              ->orderByDesc('l.id')->limit(30)
              ->get(['l.id','l.job_card_id','l.original_rot_session_id',
                  'l.rework_rot_session_id','l.instructions','l.status',
                  'c.id as complaint_id','c.description','c.outcome']);
      }
  }
@endphp
@if($am242StaffId>0 && ($am242Own->isNotEmpty() || $am242Receiving->isNotEmpty()))
<section class="staff-panel am242-complaints" id="am242-my-complaints" style="border:1px solid #d5dfea;border-left:5px solid #c17c26;border-radius:9px;padding:12px 14px;margin:8px 0;background:#fff">
  <div class="panel-head" style="display:flex;align-items:center;justify-content:space-between;gap:10px">
    <h2 style="font-size:15px;margin:0">⚠ My Work Complaints & Rework</h2>
    <strong style="font-size:12px;background:#fff0db;padding:4px 8px;border-radius:13px">{{ $am242Own->count() }} original complaint(s)</strong>
  </div>
  <p style="font-size:11px;color:#60748a;margin:5px 0 9px">
    Reported complaint does not automatically mean a confirmed workmanship fault. Your original Completed ROT stays in history.
  </p>
  @if($am242Own->isNotEmpty())
  <h3 style="font-size:13px;margin:9px 0">Complaints Against My Completed Work</h3>
  @foreach($am242Own as $am242C)
    <details style="border:1px solid #dbe5ed;border-radius:7px;margin:7px 0;padding:8px 10px">
      <summary style="cursor:pointer;font-weight:700;font-size:12px">
       Complaint #{{ $am242C->id }} · JC #{{ $am242C->job_card_id }} · {{ $am242C->outcome==='WORKMANSHIP_CONFIRMED'?'Confirmed Workmanship':($am242C->outcome ? str_replace('_',' ',$am242C->outcome) : 'Under Review') }}
      </summary>
      <p style="font-size:11px"><b>Original ROT:</b> {{ $am242C->rot_session_id }} · <b>Reported:</b> {{ $am242C->reported_at }}</p>
      <p style="font-size:12px;white-space:pre-wrap"><b>Complaint:</b> {{ $am242C->description }}</p>
      @if($am242C->investigation_notes)
      <p style="font-size:12px;white-space:pre-wrap"><b>Supervisor Findings:</b> {{ $am242C->investigation_notes }}</p>
      @endif
      <p style="font-size:12px;white-space:pre-wrap"><b>Employee Response on Record:</b> {{ $am242C->employee_response ?: 'Not yet recorded' }}</p>
      <p style="font-size:11px;color:#60748a">If explanation is incorrect or needs an amendment, contact Admin/HR. This panel cannot alter investigation records.</p>
    </details>
  @endforeach
  @endif
  @if($am242Receiving->isNotEmpty())
    <h3 style="font-size:13px;margin:12px 0 8px">Complaint Rework Assigned to Me</h3>
    @foreach($am242Receiving as $am242R)
      <details style="border:1px solid #c9dfec;border-left:3px solid #2674a8;border-radius:7px;margin:7px 0;padding:8px 10px">
        <summary style="font-weight:700;cursor:pointer;font-size:12px">
          Rework ROT {{ $am242R->rework_rot_session_id }} · JC #{{ $am242R->job_card_id }} · {{ $am242R->status }}
        </summary>
        <p style="font-size:12px"><b>Original complaint #{{ $am242R->complaint_id }}</b> · ROT {{ $am242R->original_rot_session_id }}</p>
        <p style="font-size:12px;white-space:pre-wrap"><b>Complaint:</b> {{ $am242R->description }}</p>
        <p style="font-size:12px;white-space:pre-wrap"><b>Rework Instructions:</b> {{ $am242R->instructions }}</p>
        <p style="font-size:11px;color:#60748a">Use your existing assigned ROT card and normal Check-In, Start, Pause and Complete controls to record work. Assignment does not make you responsible for the original fault.</p>
      </details>
    @endforeach
  @endif
</section>
@endif
