@extends('layouts.erp')
@section('title','Manager Vehicle Inspection — Assam Motors')
@section('topbar_title','Vehicle Inspection — Manager')
@section('content')
{{-- P21_MANAGER_INSPECTION_WORK: Assignment separate from deciding technician --}}
<style>
.p21-wrap{max-width:1240px;margin:0 auto;color:#12345c}.p21-head{border-radius:16px;background:linear-gradient(115deg,#0c3562,#126c89);color:#fff;padding:22px;margin:12px 0 17px;border-bottom:5px solid #eab83e}
.p21-head h1{color:#fff;font-size:25px;margin:3px 0 8px}.p21-head p{font-size:13px;color:#d5e6f3;margin:3px 0}
.p21-note{border-radius:10px;border-left:4px solid #c99d28;padding:12px;background:#fff7e2;margin:12px 0;font-size:13px}
.p21-success{border-radius:10px;border-left:4px solid #18936a;padding:12px;background:#e8f8f2;margin:12px 0;font-size:13px}
.p21-error{border-radius:10px;border-left:4px solid #cc443a;padding:12px;background:#fff0ed;margin:12px 0;font-size:13px}
.p21-card{background:#fff;border:1px solid #d7e3ee;border-radius:14px;padding:18px;margin:12px 0;box-shadow:0 4px 13px #113b630b}
.p21-card h2{color:#103660;font-size:19px;margin:0 0 10px}.p21-table{overflow:auto}.p21-table table{border-collapse:collapse;width:100%;min-width:800px}
.p21-table th{background:#123e70;color:#fff;font-size:12px;text-align:left;padding:11px;white-space:nowrap}
.p21-table td{padding:11px;border-bottom:1px solid #e5edf4;font-size:13px;vertical-align:top}
.p21-table tr:nth-child(even){background:#f8fbff}
.p21-badge{display:inline-block;font-size:11px;font-weight:800;padding:5px 9px;background:#fff0cb;color:#955c10;border-radius:14px}
.p21-badge.ok{background:#dbf5e7;color:#157447}
.p21-btn{display:inline-flex;align-items:center;justify-content:center;background:#164f8c;color:#fff!important;border:0;padding:10px 13px;border-radius:9px;text-decoration:none;font-weight:800;font-size:13px;min-height:43px;cursor:pointer}
.p21-btn.success{background:#07765a}.p21-btn:focus-visible{outline:3px solid #e8ba2f;outline-offset:2px}
.p21-form{display:grid;grid-template-columns:minmax(200px,1fr) 2fr auto;gap:10px;align-items:end}
.p21-form input,.p21-form select,.p21-form textarea,.p21-checklist select,.p21-checklist textarea{width:100%;min-height:39px;border:1px solid #c6d6e8;border-radius:8px;padding:8px;margin-top:5px;background:#fff;font-size:13px;box-sizing:border-box}
.p21-form label{font-size:12px;color:#4c6480;font-weight:750}
.p21-checklist{display:grid;gap:8px}.p21-item{display:grid;grid-template-columns:minmax(160px,1fr) 125px minmax(170px,1.5fr);gap:10px;align-items:center;padding:10px 13px;border:1px solid #dbe7f2;background:#f8fbff;border-radius:10px}
.p21-item strong{font-size:13px}.p21-item small{color:#67819e}.p21-item textarea{min-height:42px;margin:0}
.p21-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:16px;flex-wrap:wrap}
.p21-link{font-size:12px;text-decoration:underline;color:#194f82}
@media(max-width:700px){.p21-form{grid-template-columns:1fr}.p21-item{grid-template-columns:1fr 130px}.p21-item textarea{grid-column:1/-1}}
</style>
<div class="p21-wrap">
 <div class="p21-head">
  <div style="font-size:11px;letter-spacing:.1em;font-weight:800">ASSAM MOTORS · WORKSHOP INSPECTION CONTROL</div>
  <h1>Manager Vehicle Inspection</h1>
  <p>Step 1 — Assign Manager → Complete Inspection → Final Diagnosis → Technician Decision → Parts/Labour Estimate</p>
  <p>Your linked Staff Master role: <strong>{{ $myStaff->role ?? 'Not linked' }}</strong></p>
  <a href="{{ route('erp.job-cards.index') }}" style="color:#fff;text-decoration:underline;font-size:12px">← Back to Job Cards</a>
 </div>
 @if(session('success'))<div class="p21-success">{{ session('success') }}</div>@endif
 @if($errors->any())<div class="p21-error"><strong>Unable to proceed:</strong> {{ $errors->first() }}</div>@endif
 <div class="p21-note"><strong>Two separate responsibilities:</strong> Assigned Inspection Manager and Deciding Technician do NOT share an assignment. Completing Inspection unlocks Final Diagnosis, not the Estimate. Only completed Diagnosis + Technician Decision can unlock Parts/Labour Estimate.</div>
 <div class="p21-card">
  <h2>Who is assigned to Vehicle Inspection?</h2>
  <div class="p21-table"><table>
   <thead><tr><th>Job Card / Vehicle</th><th>Inspection status</th><th>Inspection assigned to</th><th>Completed by / time</th><th>Deciding technician</th><th>Action</th></tr></thead>
   <tbody>
   @forelse($records as $jc)
    <tr>
     <td><strong>{{ $jc->job_no }}</strong><div>{{ $jc->vehicle_reg_no ?: '—' }}</div><small>{{ $jc->customer_name }}</small></td>
     <td><span class="p21-badge {{ strtolower((string)$jc->inspection_status)==='completed'?'ok':'' }}">{{ strtoupper($jc->inspection_status ?: 'PENDING') }}</span></td>
     <td>{{ $jc->inspector_name ?: 'NOT ASSIGNED' }} @if($jc->inspector_code)<small>({{ $jc->inspector_code }})</small>@endif
        @if($jc->assigned_at)<div><small>Assigned {{ \Carbon\Carbon::parse($jc->assigned_at)->format('d-m-Y h:i A') }}</small></div>@endif
     </td>
     <td>{{ $jc->completed_by_name ?: '—' }}<div><small>{{ $jc->inspection_submitted_at ? \Carbon\Carbon::parse($jc->inspection_submitted_at)->format('d-m-Y h:i A') : 'Pending' }}</small></div></td>
     <td>{{ $jc->technician ?: 'Not assigned yet' }}</td>
     <td><a class="p21-btn" href="{{ route('erp.inspection-manager.work',$jc->id) }}">{{ strtolower((string)$jc->inspection_status)==='completed'?'View':'Assign / Inspect' }}</a></td>
    </tr>
   @empty
    <tr><td colspan="6">No matching Job Cards.</td></tr>
   @endforelse
   </tbody>
  </table></div>
 </div>
 @if($records->count()===1)
  @php
   $jc=$records->first();
   $inspectPending=strtolower(trim((string)$jc->status))==='open'
     && strtolower(trim((string)$jc->inspection_status))==='pending'
     && strtolower(trim((string)$jc->diagnosis_status))!=='completed';
   $isMyAssignment=(int)($jc->assigned_staff_id??0)>0
     && (int)$jc->assigned_staff_id===(int)$myStaffId
     && strtolower(trim((string)($myStaff->role??'')))==='manager';
   $standardInspectionItems=[
    'Exterior / Body','Engine Bay','Brake System','Tyres / Wheels','Suspension',
    'Lights / Electrical','Battery','Interior / Dashboard','Fluid Levels','Underbody'
   ];
  @endphp
  @if($inspectPending)
   <section class="p21-card">
    <h2>Assign Inspection Manager — {{ $jc->job_no }}</h2>
    <form class="p21-form" method="post" action="{{ route('erp.inspection-manager.assign',$jc->id) }}">
     @csrf
     <label>Active Manager<select name="assigned_staff_id" required>
        <option value="">Choose Manager</option>
        @foreach($managers as $person)
         <option value="{{ $person->id }}" @selected((int)($jc->assigned_staff_id??0)===(int)$person->id)>{{ $person->name }} ({{ $person->staff_code }})</option>
        @endforeach
     </select></label>
     <label>Assignment / reassignment reason<input type="text" name="reason" required minlength="8" maxlength="500" placeholder="Initial inspection / handover reason"></label>
     <button class="p21-btn" type="submit">Assign Manager</button>
    </form>
   </section>
  @endif
  <section class="p21-card">
   <h2>Inspection Work — {{ $jc->job_no }}</h2>
   @if(!$inspectPending)
    <div class="p21-note">Inspection is already completed, Job Card is no longer OPEN, or Diagnosis has started. Checklist is locked to protect history. <a class="p21-link" href="{{ route('erp.job-cards.show',$jc->id) }}">Open Job Card</a></div>
   @elseif(!$isMyAssignment)
    <div class="p21-note">This inspection is not assigned to your linked Manager Staff ID. Assign yourself through the form above or ask Admin to assign it. Only the assigned Manager can submit.</div>
   @else
    <form method="post" action="{{ route('erp.inspection-manager.complete',$jc->id) }}" onsubmit="return confirm('Complete Vehicle Inspection for this Job Card? This cannot be resubmitted automatically.')">
     @csrf
     <div class="p21-checklist">
      @foreach($standardInspectionItems as $ix=>$part)
       <div class="p21-item">
        <div><strong>{{ $ix+1 }}. {{ $part }}</strong><input type="hidden" name="items[{{ $ix }}][item_name]" value="{{ $part }}"></div>
        <label><small>Condition</small><select name="items[{{ $ix }}][status]" required><option value="">Choose...</option><option value="good">Good</option><option value="fair">Fair</option><option value="poor">Poor</option></select></label>
        <label><small>Remarks</small><textarea name="items[{{ $ix }}][remarks]" rows="1" maxlength="1000" placeholder="Observation"></textarea></label>
       </div>
      @endforeach
     </div>
     <div class="p21-foot"><p style="font-size:12px;color:#56728e;margin:0">Final Diagnosis remains blocked until this is saved as Completed.</p><button class="p21-btn success" type="submit">✓ Complete Vehicle Inspection</button></div>
    </form>
   @endif
  </section>
 @endif
</div>
@endsection
