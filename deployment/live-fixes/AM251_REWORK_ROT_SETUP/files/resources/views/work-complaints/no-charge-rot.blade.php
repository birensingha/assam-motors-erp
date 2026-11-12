{{-- AM251_REWORK_ROT_CREATE: admin-confirmed, explicit native nonbillable corrective ROT. --}}
@if($link && empty($link->rework_rot_session_id) && $candidateRots->isEmpty())
@php
$am251Masters=\Illuminate\Support\Facades\DB::table('labour_master')
 ->whereRaw("LOWER(TRIM(COALESCE(active,'Yes'))) IN ('yes','active','1')")
 ->where('standard_hours','>',0)
 ->orderBy('rot_code')
 ->limit(4000)
 ->get(['id','rot_code','rot_description','standard_hours']);
@endphp
<div class="item" style="background:#eef8fd;border:1px solid #b6daef;margin:12px 0;padding:14px" id="am251-create-rework">
 <h3 style="margin:0 0 6px;color:#124773;font-size:15px">Option A — Create & Link No-Charge Rework ROT</h3>
 <p style="font-size:12px;margin:5px 0">
  Assign the native ROT to <strong>{{ $receiver->name ?? ('Staff #'.$link->assigned_staff_id) }}</strong>
  for Complaint #{{ $case->id }}. The Job Card will record work time but
  <strong>customer labour rate, total and GST stay ₹0</strong> and billing remains excluded.
 </p>
 <p class="muted" style="font-size:11px">
  This will create <strong>one</strong> new ROT only when you submit.
  Existing staff Check-In is required by the native ROT service. If the mechanic has
  not checked in, nothing will be created.
 </p>
 @if($am251Masters->isEmpty())
 <div class="alert">No active Labour Master with positive standard time found. Create/enable a suitable ROT Master first.</div>
 @else
 <form method="post" action="{{ route('erp.rot-work-complaints.rework.create-rot',$case->id) }}">
  @csrf
  <label for="am251-filter">Search Labour / ROT Master</label>
  <input id="am251-filter" type="search" placeholder="Type ROT code or repair description"
         oninput="(function(v){var s=document.getElementById('am251-master');for(var o of s.options){if(!o.value)continue;o.hidden=!o.text.toLowerCase().includes(v.toLowerCase());}})(this.value)">
  <label for="am251-master">Corrective Labour / ROT *</label>
  <select id="am251-master" name="labour_master_id" required>
   <option value="">Select repair ROT from master...</option>
   @foreach($am251Masters as $m)
   <option value="{{ $m->id }}" @selected((string)old('labour_master_id')===(string)$m->id)>
    {{ $m->rot_code }} — {{ $m->rot_description }} ({{ $m->standard_hours }} std hrs)
   </option>
   @endforeach
  </select>
  <label for="am251-reason">Reason / Work Order *</label>
  <textarea id="am251-reason" name="reason" rows="2" minlength="12" maxlength="1000" required
    placeholder="Complaint corrective work, no customer charge">{{ old('reason',$link->instructions) }}</textarea>
  <label style="font-size:12px;font-weight:600;display:flex;gap:8px;align-items:flex-start">
   <input type="checkbox" name="confirm_internal_no_charge" value="1"
    style="width:auto;margin-top:3px" required>
   I confirm this is internal corrective work at no customer charge. Original Completed ROT stays unchanged.
  </label>
  <button type="submit" onclick="return confirm('Create one new INTERNAL NO-CHARGE ROT and link it to this complaint? No customer billing will be added.')">
   Create & Link No-Charge ROT
  </button>
 </form>
 @endif
</div>
@endif
