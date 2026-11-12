{{-- AM250_REWORK_JC_PROGRESS — read-only; native ROT controls remain authoritative. --}}
@php
    $am250Rows=collect();
    if (!empty($job->id)
        && \Illuminate\Support\Facades\Schema::hasTable('am_complaint_rework_links')
        && \Illuminate\Support\Facades\Schema::hasTable('am_rot_work_complaints')
        && \Illuminate\Support\Facades\Schema::hasTable('rot_sessions')
        && \Illuminate\Support\Facades\Schema::hasTable('staff')) {
        $am250Rows=\Illuminate\Support\Facades\DB::table('am_complaint_rework_links as l')
          ->join('am_rot_work_complaints as c','c.id','=','l.complaint_id')
          ->leftJoin('staff as s','s.id','=','l.assigned_staff_id')
          ->leftJoin('rot_sessions as rs','rs.id','=','l.rework_rot_session_id')
          ->where('l.job_card_id',(int)$job->id)
          ->orderByDesc('l.id')
          ->get([
              'l.complaint_id','l.assigned_staff_id',
              'l.assigned_at','l.instructions','l.status as assignment_status',
              'l.rework_rot_session_id','s.name as receiving_staff_name',
              's.staff_code as receiving_staff_code',
              'c.description as complaint_description','c.outcome as complaint_outcome',
              'rs.status as actual_rot_status'
          ]);
    }
@endphp
@if($am250Rows->isNotEmpty())
<style id="AM250_REWORK_JC_PROGRESS">
.am250-wrap{background:#fff;border:1px solid #cedfe9;border-left:4px solid #2673a9;border-radius:10px;padding:14px 16px;margin:14px 0}
.am250-head{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.am250-head h2{margin:0;font-size:15px;color:#163a61}
.am250-meta{margin:5px 0 12px;color:#66798b;font-size:11px}
.am250-item{padding:11px 0;border-top:1px solid #e6edf3}
.am250-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:9px;margin:9px 0}
.am250-cell{background:#f4f8fc;border:1px solid #e3ebf3;padding:9px 10px;border-radius:7px}
.am250-cell small{display:block;font-size:10px;color:#6d7c8b;margin-bottom:3px}
.am250-cell strong{display:block;font-size:12px;overflow-wrap:anywhere;color:#1d3f60}
.am250-notice{background:#fff8e8;border:1px solid #efd2a3;border-radius:7px;padding:8px 11px;font-size:11px;color:#704c18}
.am250-actions{display:flex;gap:13px;flex-wrap:wrap;margin-top:9px}
.am250-actions a{font-size:12px;color:#155f9a;font-weight:750;text-decoration:none}
.am250-repair{margin:7px 0 0;color:#50677a;font-size:11px;white-space:pre-wrap}
@media(max-width:850px){.am250-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:480px){.am250-grid{grid-template-columns:1fr}}
</style>
<section class="am250-wrap" id="am250-complaint-rework" aria-label="Complaint rework progress">
 <div class="am250-head"><h2>🔧 Complaint Rework Progress</h2>
  <span style="font-size:11px;color:#577184">{{ $am250Rows->count() }} complaint assignment(s)</span>
 </div>
 <p class="am250-meta">This is a separate corrective-work record. The original Completed ROT stays untouched. Customer billing is not changed by this panel.</p>
 @foreach($am250Rows as $am250)
 @php
  $am250RotState=strtoupper(trim((string)($am250->actual_rot_status??'')));
  $am250HasLinkedRot=trim((string)($am250->rework_rot_session_id??''))!=='';
  $am250Progress= !$am250HasLinkedRot ? 'ROT PENDING' :
    ($am250RotState==='' ? 'LINKED ROT NOT FOUND' :
    (in_array($am250RotState,['COMPLETED','COMPLETE'],true) ? 'ROT COMPLETED — QC PENDING' :
    ($am250RotState==='RUNNING' ? 'WORK RUNNING' :
    ($am250RotState==='PAUSED' ? 'WORK PAUSED' :
    ($am250RotState==='ASSIGNED' ? 'READY TO START' : 'ROT '.$am250RotState)))));
 @endphp
 <article class="am250-item">
  <div class="am250-grid">
   <div class="am250-cell"><small>Complaint</small><strong>#{{ $am250->complaint_id }}</strong></div>
   <div class="am250-cell"><small>Rework Mechanic</small><strong>{{ $am250->receiving_staff_name ?: ('Staff #'.$am250->assigned_staff_id) }} ({{ $am250->receiving_staff_code ?: '—' }})</strong></div>
   <div class="am250-cell"><small>Work Progress</small><strong>{{ $am250Progress }}</strong></div>
   <div class="am250-cell"><small>Separate Rework ROT</small><strong>{{ $am250->rework_rot_session_id ?: 'Not linked yet' }}</strong></div>
  </div>
  @if(!$am250HasLinkedRot)
   <p class="am250-notice"><strong>Action needed:</strong> The mechanic is assigned, but no separate native ROT is linked. No Start/Pause/Complete timer or actual productive hours are tracked for this complaint yet.</p>
  @elseif(!$am250RotState)
   <p class="am250-notice">Linked ROT was not found. Recheck the complaint link; progress cannot be inferred.</p>
  @elseif(in_array($am250RotState,['COMPLETED','COMPLETE'],true))
   <p class="am250-notice">ROT completion records work-time completion only. Actual rectification and independent QC verification must still be recorded separately.</p>
  @else
   <p class="am250-meta">ROT status is read from the existing workshop timer; refresh this Job Card to see the latest saved status.</p>
  @endif
  <p class="am250-repair"><b>Instructions:</b> {{ $am250->instructions }}</p>
  <div class="am250-actions">
    <a href="{{ route('erp.rot-work-complaints.rework.show',$am250->complaint_id) }}">View / Link Complaint Rework ROT</a>
    <a href="{{ route('erp.rot-work-complaints.show',$am250->complaint_id) }}">Complaint Investigation</a>
  </div>
 </article>
 @endforeach
</section>
@endif
