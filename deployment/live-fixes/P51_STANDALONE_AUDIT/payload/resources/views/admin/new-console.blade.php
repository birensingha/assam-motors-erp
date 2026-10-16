@extends('layouts.new-admin')
@section('title','NEW Admin Overview — Assam Motors')
@section('topbar_title','Administrative Control Centre')
@push('head')
<style>
.am-new-hero{background:linear-gradient(115deg,#112b4b,#1d5f9b);color:white;border:1px solid #366b99;border-radius:18px;padding:26px 29px;display:flex;align-items:center;justify-content:space-between;gap:15px;flex-wrap:wrap;box-shadow:0 9px 30px #1d5f9b20;margin-bottom:19px}
.am-new-hero h1{font-size:clamp(21px,2.4vw,29px);letter-spacing:-.035em;line-height:1.15;margin:4px 0 8px}
.am-new-hero p{margin:0;color:#d4e6f7;font-size:13px}
.am-new-eyebrow{font-size:10px;letter-spacing:.15em;font-weight:870;color:#a7d7ff}
.am-new-button{display:inline-flex;align-items:center;justify-content:center;background:#fff;color:#15528a;font-weight:840;font-size:12px;border-radius:10px;padding:11px 15px;text-decoration:none;min-height:43px}
.am-new-overline{font-size:11px;letter-spacing:.1em;color:#6484a5;font-weight:870;margin:20px 0 10px}
.am-new-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:13px}
.am-new-metric{background:white;border:1px solid #dce6f1;border-radius:14px;padding:19px;box-shadow:0 5px 20px #0f345408;min-width:0;position:relative;overflow:hidden}
.am-new-metric:before{content:"";position:absolute;top:0;left:0;height:100%;width:4px;background:#2462aa}
.am-new-metric:nth-child(3n+2):before{background:#0e9368}.am-new-metric:nth-child(3n+3):before{background:#e8a32e}
.am-new-metric small{display:block;color:#637b91;font-size:11px;font-weight:780;letter-spacing:.02em}
.am-new-metric strong{display:block;font-size:clamp(22px,2.4vw,30px);font-weight:850;color:#1c3d64;margin-top:6px;font-variant-numeric:tabular-nums}
.am-new-panels{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:13px}
.am-new-link{display:flex;justify-content:space-between;align-items:center;gap:10px;min-height:64px;padding:14px 17px;border:1px solid #dce6f1;border-radius:13px;text-decoration:none;background:white;color:#1b4168;font-weight:760;box-shadow:0 4px 16px #133e6108}
.am-new-link:hover{background:#eaf3ff;border-color:#aac9ed}
.am-new-link span:last-child{font-size:19px;color:#3179bb}
.am-new-info{background:#edf5ff;border:1px solid #c9dff7;border-radius:12px;padding:14px;color:#315c81;font-size:12px;line-height:1.6;margin-top:19px}
@media(max-width:1100px){.am-new-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.am-new-panels{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:600px){.am-new-hero{padding:19px}.am-new-panels{grid-template-columns:1fr}.am-new-metric{padding:14px}.am-new-metric strong{font-size:24px}}
</style>
@endpush
@section('content')
<section class="am-new-hero">
 <div>
   <div class="am-new-eyebrow">MANAGEMENT · ADMINISTRATIVE PORTAL</div>
   <h1>ASSAM MOTORS — NEW Admin</h1>
   <p>Standalone administrative workspace on STAGING. Protected by ERP Admin permission.</p>
 </div>
 <a class="am-new-button" href="{{ route('erp.dashboard') }}">Open Workshop ERP →</a>
</section>
<div class="am-new-overline">LIVE STAGING RECORD COUNTS · READ ONLY</div>
<section class="am-new-stats" aria-label="Staging record summary">
 @foreach($stats as $label=>$count)
   <article class="am-new-metric"><small>{{ $label }}</small><strong>{{ $count===null?'—':number_format((int)$count) }}</strong></article>
 @endforeach
</section>
<div class="am-new-overline">ADMINISTRATIVE FUNCTIONS</div>
<section class="am-new-panels" aria-label="Administrative shortcuts">
 <a class="am-new-link" href="{{ route('erp.attendance') }}"><span>Staff Attendance & Control</span><span>↗</span></a>
 <a class="am-new-link" href="{{ route('erp.staff-notifications.index') }}"><span>Staff Notifications</span><span>↗</span></a>
 <a class="am-new-link" href="{{ route('erp.configuration') }}"><span>System Configuration</span><span>↗</span></a>
 <a class="am-new-link" href="{{ route('erp.masters.parts') }}"><span>Parts Master Management</span><span>↗</span></a>
 <a class="am-new-link" href="{{ route('erp.vendors.index') }}"><span>Vendor Master</span><span>↗</span></a>
 <a class="am-new-link" href="{{ route('erp.fix49.payment-register') }}"><span>Financial Voucher Review</span><span>↗</span></a>
</section>
<div class="am-new-info">Admin Console and Workshop ERP now have separate navigation layouts. This is Phase 1 of Legacy independence. Business records are unchanged; Legacy API replacement and historical database reconciliation remain separate migration steps.</div>
@endsection
