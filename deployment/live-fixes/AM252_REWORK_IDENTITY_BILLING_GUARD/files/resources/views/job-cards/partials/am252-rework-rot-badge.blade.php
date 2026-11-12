{{-- AM252_REWORK_ROT_IDENTITY_BADGE: real linked ROT and billing metadata, no edits. --}}
@php
  $am252Session = (string)($rot->id ?? $line->rot_session_id ?? '');
  $am252Link = null;
  if($am252Session!=='' && \Illuminate\Support\Facades\Schema::hasTable('am_complaint_rework_links')){
    $am252Link = \Illuminate\Support\Facades\DB::table('am_complaint_rework_links')
      ->where('job_card_id',(int)$job->id)
      ->where(function($q) use ($am252Session){
        $q->where('original_rot_session_id',$am252Session)
          ->orWhere('rework_rot_session_id',$am252Session);
      })
      ->first(['complaint_id','original_rot_session_id','rework_rot_session_id']);
  }
  $am252IsRework = $am252Link && $am252Session===(string)$am252Link->rework_rot_session_id;
  $am252SafeBilling = (int)($line->billing_included??1)===0
    && abs((float)($line->total??0))<0.005
    && abs((float)($line->labour_rate??0))<0.005
    && abs((float)($line->gst_percent??0))<0.005;
@endphp
@if($am252Link)
 @once
 <style id="am252-rot-inline-tag-style">
  .am252-tag{display:inline-flex;align-items:center;gap:5px;border-radius:6px;border:1px solid #b4d5bc;background:#e8f7ec;color:#116039;padding:3px 7px;font-weight:800;font-size:10px;line-height:1.4;margin:3px 4px 3px 0;text-decoration:none}
  .am252-tag.original{border-color:#c6d7e8;background:#eaf2fc;color:#254f83}
  .am252-tag.warning{border-color:#f3bc9a;background:#fff1e7;color:#a33e10}
 </style>
 @endonce
 @if($am252IsRework)
  <a class="am252-tag" href="{{ route('erp.rot-work-complaints.rework.show',$am252Link->complaint_id) }}">↻ REPEAT COMPLAINT REWORK #{{ $am252Link->complaint_id }}</a>
  @if($am252SafeBilling)
   <span class="am252-tag">₹0 • INTERNAL NO CUSTOMER CHARGE</span>
  @else
   <span class="am252-tag warning">BILLING ALERT — REWORK MUST BE NO CHARGE</span>
  @endif
 @else
  <a class="am252-tag original" href="{{ route('erp.rot-work-complaints.show',$am252Link->complaint_id) }}">ORIGINAL WORK • COMPLAINT #{{ $am252Link->complaint_id }}</a>
 @endif
@endif
