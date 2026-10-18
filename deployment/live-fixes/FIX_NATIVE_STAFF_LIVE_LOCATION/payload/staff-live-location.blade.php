@extends('layouts.erp')
@section('title','Staff Live Location — Assam Motors')
@section('topbar_title','Staff Live Location')
@section('content')
<style>
:root{--sl-navy:#112745;--sl-blue:#1464b8;--sl-mint:#15996c;--sl-amber:#e7a238;--sl-rose:#d4575c;--sl-line:#dce7ef;--sl-ink:#21364d}
.sl-shell{font-family:Inter,ui-sans-serif,system-ui,'Segoe UI',sans-serif;color:var(--sl-ink);display:grid;gap:16px}
.sl-hero{border-radius:20px;background:linear-gradient(118deg,#122b50 5%,#175fa5 70%,#1877bb);color:white;padding:20px 23px;display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;box-shadow:0 12px 28px #114c8b28}
.sl-hero h1{color:white!important;font-size:clamp(23px,2.4vw,34px);margin:2px 0 7px;letter-spacing:-.7px;font-weight:800}
.sl-hero p{color:#d9ebfb;font-size:13px;line-height:1.6;margin:0;max-width:650px}
.sl-overline{letter-spacing:2px;font-weight:800;font-size:10px;color:#a9e3ff}
.sl-live-clock{background:#ffffff16;border:1px solid #ffffff47;border-radius:14px;padding:11px 17px;text-align:right}
.sl-live-clock strong{font-size:21px;font-weight:800;letter-spacing:.3px;color:white;display:block}
.sl-live-clock small{font-size:11px;color:#d5eaff;display:block}
.sl-live-dot{display:inline-block;width:9px;height:9px;background:#63efbc;border-radius:50%;box-shadow:0 0 0 5px #6ef3bc2c;margin-right:9px;animation:sl-pulse 2s infinite}
@keyframes sl-pulse{50%{box-shadow:0 0 0 9px #6ef3bc16}}
.sl-metrics{display:grid;grid-template-columns:repeat(5,minmax(120px,1fr));gap:11px}
.sl-metric{background:white;border:1px solid var(--sl-line);border-radius:15px;padding:14px 17px;box-shadow:0 3px 14px #2143640a;min-height:95px}
.sl-metric .sl-label{font-size:11px;font-weight:700;letter-spacing:.35px;color:#617b92;display:block}
.sl-metric strong{font-weight:850;font-size:31px;line-height:1.25;letter-spacing:-.8px;display:block;color:#183b63;margin-top:3px}
.sl-metric .sl-sub{font-size:11px;color:#8b9bad}
.sl-body{display:grid;grid-template-columns:minmax(305px,350px) minmax(350px,1fr);gap:14px;min-height:610px}
.sl-panel{background:white;border:1px solid var(--sl-line);border-radius:18px;overflow:hidden;box-shadow:0 4px 18px #17466d0b}
.sl-staff-panel{display:flex;flex-direction:column;min-height:630px}
.sl-panel-head{padding:17px 18px 13px;border-bottom:1px solid #e6eef4;display:grid;gap:10px}
.sl-panel-title{display:flex;align-items:center;justify-content:space-between;gap:12px}
.sl-panel-title h2{font-size:15px!important;color:#1b3755!important;letter-spacing:-.2px;font-weight:800;margin:0!important}
.sl-panel-title small{font-size:11px;color:#7793a8}
.sl-input,.sl-select{width:100%;border:1px solid #d9e5ee;border-radius:10px;padding:10px 12px;font:inherit;font-size:12px;outline:0;color:#264057;background:#fbfdff}
.sl-input:focus,.sl-select:focus{border-color:#1b84d3;box-shadow:0 0 0 3px #1688e51a}
.sl-two{display:flex;gap:8px}
.sl-two>*{min-width:0;flex:1}
.sl-staff-list{overflow:auto;flex:1;max-height:585px}
.sl-staff{width:100%;text-align:left;display:grid;grid-template-columns:36px minmax(0,1fr) auto;gap:11px;align-items:center;border:0;border-bottom:1px solid #f0f3f7;padding:13px 15px;background:white;cursor:pointer;color:inherit;font:inherit}
.sl-staff:hover{background:#f3f9ff}.sl-staff.selected{background:#eaf5ff;border-left:4px solid #1d70c8;padding-left:11px}
.sl-avatar{width:36px;height:36px;border-radius:11px;background:#deedfa;color:#1c649e;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px}
.sl-staff-name{font-size:12.5px;font-weight:800;line-height:1.35;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sl-staff-caption{font-size:10px;color:#6d8298;line-height:1.4;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sl-pill{display:inline-flex;align-items:center;gap:5px;border-radius:30px;padding:5px 8px;font-size:10px;font-weight:850;text-transform:uppercase;white-space:nowrap}
.sl-pill.live{background:#def8ed;color:#117b51}
.sl-pill.delayed{background:#fff0d0;color:#93620c}
.sl-pill.stale{background:#ffe8df;color:#ac4b29}
.sl-pill.offline,.sl-pill.none{background:#ebeff4;color:#718096}
.sl-pill::before{content:'';display:block;width:6px;height:6px;background:currentColor;border-radius:50%}
.sl-map-wrap{position:relative;min-height:610px;background:#e8f0ee;display:flex;flex-direction:column}
.sl-map-head{height:auto;min-height:49px;background:white;display:flex;align-items:center;justify-content:space-between;gap:9px;padding:10px 14px;flex-wrap:wrap;border-bottom:1px solid var(--sl-line)}
.sl-map-head strong{font-size:13px}
.sl-map-head small{display:block;color:#8395a5;font-size:10px}
.sl-map-head .sl-tools{display:flex;gap:7px;align-items:center}
.sl-btn{appearance:none;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;gap:5px;border:1px solid #d5e3ef;border-radius:9px;background:#f8fbff;color:#235479;padding:9px 11px;font-weight:750;font-size:11px;cursor:pointer;font-family:inherit;white-space:nowrap}
.sl-btn:hover{border-color:#4da4db;background:#eaf6ff}
.sl-btn.primary{background:#1467b8;color:#fff;border-color:#1467b8}.sl-btn.primary:hover{background:#0d559d}
.sl-map{flex:1;min-height:555px;width:100%;z-index:1}
.sl-map-unavailable{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);padding:12px 15px;border-radius:10px;background:white;box-shadow:0 3px 15px #0003;z-index:2;color:#47586a;font-size:12px;text-align:center}
.sl-map-unavailable[hidden]{display:none!important}
.sl-dot{border:2px solid #fff;width:20px;height:20px;border-radius:50%;box-shadow:0 1px 8px #24365077;background:#8293a3}
.sl-dot.live{background:#09ad71;box-shadow:0 0 0 5px #23c58b33,0 1px 8px #24365077}
.sl-dot.delayed{background:#efaa35}.sl-dot.stale{background:#d96c4a}.sl-dot.offline{background:#8a99a5}
.sl-detail{background:white;border:1px solid var(--sl-line);border-radius:18px;overflow:hidden;box-shadow:0 4px 18px #17466d0b}
.sl-detail-header{padding:16px 19px;border-bottom:1px solid #eaf0f5;display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}
.sl-detail-header h2{font-size:15px!important;font-weight:800;color:#1d3653;margin:0!important}
.sl-detail-body{padding:14px 18px;display:grid;grid-template-columns:repeat(6,minmax(95px,1fr));gap:12px}
.sl-detail-cell{min-width:0}.sl-detail-cell small{font-size:10px;display:block;color:#7890a2;margin-bottom:4px}.sl-detail-cell strong{font-size:12px;font-weight:850;color:#243f5a;overflow-wrap:anywhere}
.sl-detail-actions{border-top:1px solid #eaf0f5;padding:11px 18px;display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap}
.sl-foot{font-size:11px;color:#74899b;line-height:1.7;background:#f5f9fc;border:1px solid var(--sl-line);padding:12px 15px;border-radius:11px}
.sl-explain{font-size:11px;color:#778a9c}
.sl-error{color:#b72c3d!important}
.sl-warning{color:#bb6b11!important}
.sl-smallcaps{font-weight:800;text-transform:uppercase;letter-spacing:1px}
.sl-legend{display:flex;gap:10px;flex-wrap:wrap;font-size:10px;color:#698092}.sl-legend b{font-size:13px}
.sl-history{display:flex;gap:7px;align-items:center;flex-wrap:wrap}
.sl-history .sl-input{width:140px}
.sl-empty{color:#8093a6;text-align:center;font-size:12px;padding:25px}

/* AM_COMPACT_MOBILE_SOLO_TRACK — each staff has a private-focus monitoring view */
.sl-focus{display:none;background:linear-gradient(120deg,#eaf5ff 0%,#fff 80%);border:1px solid #c9e2f2;border-radius:16px;padding:12px 14px;gap:10px;box-shadow:0 5px 18px #104c7514}
.sl-shell.solo .sl-focus{display:grid}
.sl-focus-top{display:flex;gap:9px;align-items:center;justify-content:space-between;flex-wrap:wrap}
.sl-focus-identity{display:flex;gap:9px;align-items:center;min-width:0;flex:1}
.sl-focus-avatar{height:38px;width:38px;border-radius:12px;background:#cde9ff;color:#0e5b9c;font-size:12px;font-weight:850;display:grid;place-items:center;flex-shrink:0}
.sl-focus-identity strong{display:block;color:#153b60;font-size:14px;line-height:1.3;font-weight:850;max-width:37vw;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sl-focus-identity small{display:block;color:#68869a;font-size:10px}
.sl-focus-actions{display:flex;gap:7px;align-items:center}
.sl-back{background:#1565a6!important;border-color:#1565a6!important;color:#fff!important}
.sl-focus-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:7px}
.sl-focus-stat{border:1px solid #e1edf5;border-radius:11px;background:#fff;padding:9px 11px;min-width:0}
.sl-focus-stat small{font-size:10px;color:#71899f;display:block;letter-spacing:.2px;font-weight:700}
.sl-focus-stat strong{font-size:16px;color:#173c60;font-weight:850;display:block;margin-top:2px;overflow-wrap:anywhere}
.sl-focus-notice{font-size:10.5px;color:#718b9e;line-height:1.45}
.sl-list-hint{font-size:11px;background:#eef8ff;border:1px solid #d9e9f6;border-radius:10px;padding:8px 10px;color:#587c99}
.sl-staff{min-height:65px}
.sl-btn:focus-visible,.sl-staff:focus-visible{outline:3px solid #63b2ed;outline-offset:2px}
.sl-shell.solo .sl-metrics{display:none}
.sl-shell.solo .sl-body{grid-template-columns:1fr;min-height:0}
.sl-shell.solo .sl-staff-panel{display:none}
.sl-shell.solo .sl-map-wrap{min-height:480px}
.sl-shell.solo .sl-map{min-height:420px}
.sl-shell:not(.solo) .sl-detail{display:none}
.sl-shell.solo .sl-detail{display:block}
@media(max-width:850px){
 .sl-shell{gap:9px}
 .sl-hero{padding:11px 12px;border-radius:13px;gap:6px;box-shadow:none}
 .sl-hero h1{font-size:19px;margin:2px 0}
 .sl-hero p{display:none}
 .sl-overline{font-size:8px;letter-spacing:1px}
 .sl-live-clock{padding:6px 8px;border-radius:10px}
 .sl-live-clock strong{font-size:14px}
 .sl-live-clock small{font-size:9px}
 .sl-metrics{display:flex!important;overflow-x:auto;gap:6px;padding:0 1px 3px;scroll-snap-type:x proximity;grid-template-columns:none!important}
 .sl-shell.solo .sl-metrics{display:none!important}
 .sl-metric{min-width:108px;min-height:56px;padding:8px 10px;border-radius:10px;scroll-snap-align:start}
 .sl-metric strong{font-size:21px;line-height:1.1}
 .sl-metric .sl-label{font-size:9px}
 .sl-metric .sl-sub{display:none}
 .sl-body{display:grid;grid-template-columns:minmax(0,1fr);min-height:0;gap:8px}
 .sl-panel,.sl-detail{border-radius:14px}
 .sl-staff-panel{min-height:0}
 .sl-panel-head{padding:11px 12px;gap:7px}
 .sl-staff-list{max-height:min(58dvh,500px)}
 .sl-staff{padding:9px 12px;min-height:63px}
 .sl-map-wrap,.sl-shell:not(.solo) .sl-map-wrap{display:none}
 .sl-shell.solo .sl-map-wrap{display:flex;min-height:350px}
 .sl-shell.solo .sl-map{height:min(50dvh,430px);min-height:270px;flex:0 0 auto}
 .sl-map-head{padding:8px 10px}
 .sl-map-head .sl-tools{gap:5px}
 .sl-map-head .sl-btn{padding:7px 9px;font-size:10px}
 .sl-detail-body{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;padding:12px}
 .sl-detail-header{padding:10px 12px;gap:8px}
 .sl-detail-actions{padding:10px 12px;gap:8px}
 .sl-foot{font-size:10px;padding:9px 11px}
 .sl-history .sl-input{width:120px;padding:8px}
 .sl-focus{padding:10px;border-radius:13px;gap:7px}
 .sl-focus-identity strong{font-size:13px}
 .sl-focus-stats{gap:6px}
 .sl-focus-stat{padding:7px 8px;border-radius:9px}
 .sl-focus-stat strong{font-size:15px}
 .sl-focus-stat small{font-size:9px}
 .sl-back{font-size:10px!important;padding:7px!important}
}
@media(max-width:440px){
 .sl-focus-avatar{width:32px;height:32px;border-radius:9px}
 .sl-focus-identity strong{max-width:32vw}
 .sl-focus-actions{gap:4px}
 .sl-focus-actions .sl-btn{font-size:10px}
}

@media(max-width:1190px){.sl-metrics{grid-template-columns:repeat(3,1fr)}.sl-body{grid-template-columns:minmax(250px,300px) minmax(280px,1fr)}.sl-detail-body{grid-template-columns:repeat(3,1fr)}}
@media(max-width:850px){.sl-body{grid-template-columns:1fr}.sl-staff-panel{min-height:0}.sl-staff-list{max-height:240px}.sl-map-wrap,.sl-map{min-height:430px}.sl-map{height:440px}.sl-metrics{grid-template-columns:repeat(2,1fr)}.sl-detail-body{grid-template-columns:repeat(2,1fr)}}
@media(max-width:450px){.sl-metrics{grid-template-columns:repeat(2,1fr)}.sl-hero{padding:16px}.sl-metric{padding:12px}.sl-detail-body{gap:10px}.sl-two{flex-wrap:wrap}.sl-map-head{align-items:start}}
@media(prefers-reduced-motion:reduce){.sl-live-dot{animation:none}}
</style>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="anonymous">
<div class="sl-shell" id="amStaffLive">
 <div class="sl-hero">
   <div><div class="sl-overline">ASSAM MOTORS · WORKFORCE INTELLIGENCE</div>
     <h1>📍 Staff Live Location</h1>
     <p>Modern duty monitoring · Verified location freshness · Staff attendance and ROT overview. Latest GPS updates are shown when phones actually report them.</p>
   </div>
   <div class="sl-live-clock"><small><span class="sl-live-dot"></span>LIVE DASHBOARD · IST</small><strong id="slClock">--:--:--</strong><small id="slConnection">Connecting securely…</small></div>
 </div>

 <section class="sl-focus" id="slFocus" aria-live="polite">
  <div class="sl-focus-top">
   <div class="sl-focus-identity"><div class="sl-focus-avatar" id="slFocusAvatar">—</div><div><strong id="slFocusName">Choose staff</strong><small id="slFocusCode">Individual tracking</small></div></div>
   <div class="sl-focus-actions"><span class="sl-pill none" id="slFocusBadge">NO GPS</span><button type="button" class="sl-btn sl-back" id="slAllStaff">← All Staff</button></div>
  </div>
  <div class="sl-focus-stats">
   <div class="sl-focus-stat"><small>GPS FIX AGE</small><strong id="slFocusAge">—</strong></div>
   <div class="sl-focus-stat"><small>ACCURACY</small><strong id="slFocusAccuracy">—</strong></div>
   <div class="sl-focus-stat"><small>SERVER SYNC AGE</small><strong id="slFocusSync">—</strong></div>
  </div>
  <div class="sl-focus-notice" id="slFocusNotice">Latest reported position; no simulated movement</div>
 </section>
 <div class="sl-metrics">
   <div class="sl-metric"><span class="sl-label">TOTAL ACTIVE STAFF</span><strong id="slTotal">—</strong><span class="sl-sub">Staff Master</span></div>
   <div class="sl-metric"><span class="sl-label">FRESH GPS</span><strong id="slLive">—</strong><span class="sl-sub">Latest sample ≤2 min</span></div>
   <div class="sl-metric"><span class="sl-label">DELAYED / STALE</span><strong id="slStale">—</strong><span class="sl-sub">Last fix needs attention</span></div>
   <div class="sl-metric"><span class="sl-label">CHECKED IN</span><strong id="slDuty">—</strong><span class="sl-sub">Attendance today</span></div>
   <div class="sl-metric"><span class="sl-label">ROT ACTIVE</span><strong id="slRot">—</strong><span class="sl-sub">Assigned · Running · Paused</span></div>
 </div>
 <div class="sl-body">
  <section class="sl-panel sl-staff-panel">
   <div class="sl-panel-head">
    <div class="sl-panel-title"><h2>Staff Location Register</h2><small id="slUpdated">Waiting for feed</small></div>
    <div class="sl-list-hint">Choose any staff — only their location will appear on the tracking screen</div>
    <input class="sl-input" id="slSearch" type="search" autocomplete="off" placeholder="🔎 Search staff name / staff code" aria-label="Search staff">
    <div class="sl-two"><select class="sl-select" id="slFilter" aria-label="GPS status filter"><option value="all">All staff</option><option value="live">Live</option><option value="delayed">Delayed</option><option value="stale">Stale</option><option value="offline">Offline / No GPS</option></select><select class="sl-select" id="slDutyFilter" aria-label="Attendance filter"><option value="all">All attendance</option><option value="in">Checked In</option><option value="out">Not Checked In</option></select></div>
   </div>
   <div class="sl-staff-list" id="slList"><div class="sl-empty">Connecting to secure Staff GPS feed…</div></div>
  </section>
  <section class="sl-panel sl-map-wrap">
    <div class="sl-map-head"><div><strong id="slMapTitle">Real Location Map</strong><small id="slMapSubtitle">GPS positions, not simulated movements</small></div><div class="sl-tools"><button class="sl-btn" type="button" id="slFollow" aria-pressed="true" hidden>◎ Follow ON</button><button class="sl-btn" type="button" id="slWorkshop">⌖ Workshop</button><button class="sl-btn primary" type="button" id="slRefresh">↻ Refresh</button></div></div>
    <div id="slMap" class="sl-map" role="region" aria-label="Staff location map"></div>
    <div id="slMapUnavailable" class="sl-map-unavailable" hidden>Map tiles unavailable. Staff locations and GPS age remain available in the register.</div>
  </section>
 </div>
 <section class="sl-detail">
   <div class="sl-detail-header"><h2 id="slSelectedName">Select a staff member to inspect duty location</h2><span class="sl-pill none" id="slSelectedStatus">NO SELECTION</span></div>
   <div class="sl-detail-body">
     <div class="sl-detail-cell"><small>Location Last Recorded</small><strong id="slRecorded">—</strong></div>
     <div class="sl-detail-cell"><small>Server Received</small><strong id="slReceived">—</strong></div>
     <div class="sl-detail-cell"><small>Accuracy / Provider</small><strong id="slAccuracy">—</strong></div>
     <div class="sl-detail-cell"><small>Geo-fence Confidence</small><strong id="slGeofence">—</strong></div>
     <div class="sl-detail-cell"><small>Attendance Today</small><strong id="slAttendance">—</strong></div>
     <div class="sl-detail-cell"><small>Active ROT / JC</small><strong id="slRotSelected">—</strong></div>
   </div>
   <div class="sl-detail-actions">
     <div class="sl-history"><label for="slHistoryDate" class="sl-explain">History (IST)</label><input id="slHistoryDate" class="sl-input" type="date"><button type="button" class="sl-btn" id="slHistoryButton">View Route</button><button type="button" class="sl-btn" id="slClearRoute">Clear Route</button><span class="sl-explain" id="slHistoryStatus"></span></div>
     <a id="slGoogleMaps" class="sl-btn" href="#" target="_blank" rel="noopener noreferrer" hidden>↗ Open Last Position in Maps</a>
   </div>
 </section>
 <div class="sl-foot"><strong>Important:</strong> Dashboard UI and server changes are checked every second while visible. Android GPS reports at its actual OS/network cadence; a new coordinate is <strong>never invented</strong> for each second. <strong>LIVE</strong> means a genuine GPS fix within 2 minutes; <strong>DELAYED</strong> 2–7 minutes; <strong>STALE</strong> 7–30 minutes; <strong>OFFLINE</strong> over 30 minutes. GPS reporting tolerance (<strong id="slReportingLimit">100 m</strong>) is separate from strict Check-In tolerance (<strong id="slCheckinLimit">30 m</strong>). Stale and approximate locations cannot be treated as verified real-time attendance. Access restricted to authenticated ERP Administrators.</div>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin="anonymous"></script>
<script>
(function(){
'use strict';
const ENDPOINT = @json(route('erp.staff-live-location.feed'));
const HISTORY = @json(route('erp.staff-live-location.history'));
const initialSettings = @json($mapSettings);
const $ = id => document.getElementById(id);
const people = new Map(), points = new Map(), attendance = new Map(), rot = new Map();
const pins = new Map();
let cursor = 0, selected = null, map = null, workshopCircle = null, route = null, accuracyRing = null;
let soloMode=false, followSelected=true, lastFollowedFixId=null;
let settings = initialSettings, lastMeta = 0, lastOk = 0, polling = false, requestErrors = 0, hasFit = false;
let refreshLoop = null;
const ageMs=p => p && p.recorded_at ? Math.max(0,Date.now()-Date.parse(p.recorded_at)) : Infinity;
const fmtDate=x=>!x?'—':new Date(x).toLocaleString('en-IN',{timeZone:'Asia/Kolkata',hour:'2-digit',minute:'2-digit',second:'2-digit',day:'2-digit',month:'short'});
const fmtAge=ms=>!Number.isFinite(ms)?'Never':ms<60000?Math.round(ms/1000)+'s ago':ms<3600000?Math.round(ms/60000)+'m ago':Math.round(ms/3600000)+'h ago';
const quality=p=>{
  if(!p)return 'none';
  const age=ageMs(p);
  if(age>1800000)return 'offline';
  if(age>420000)return 'stale';
  if(age>120000)return 'delayed';
  return 'live';
};
const gpsAccuracy=p=>p&&Number.isFinite(p.accuracy_m)?p.accuracy_m:null;
const mapPoint=p=>p&&Number.isFinite(p.lat)&&Number.isFinite(p.lng)&&Math.abs(p.lat)<=90&&Math.abs(p.lng)<=180;
const safeDateParts=()=>{
  const parts=new Intl.DateTimeFormat('en-GB',{timeZone:'Asia/Kolkata',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date());
  const by=Object.fromEntries(parts.map(p=>[p.type,p.value]));
  return by.year+'-'+by.month+'-'+by.day;
};
const abbreviation=name=>(name||'?').trim().split(/\s+/).slice(0,2).map(x=>x[0]||'').join('').toUpperCase();
function geofence(p){
 if(!p||!mapPoint(p))return 'NO GPS DATA';
 if(quality(p)!=='live')return 'LAST KNOWN ONLY';
 const radius=Number(settings.radius_m)||100;
 const accuracy=gpsAccuracy(p), distance=p.distance_m;
 if(accuracy===null || distance===null)return 'UNVERIFIED';
 if(accuracy>Number(settings.checkin_accuracy_m??30))return 'APPROX · NOT CHECK-IN VERIFIED';
 if(distance+accuracy<=radius)return 'VERIFIED INSIDE';
 if(distance-accuracy>radius)return 'VERIFIED OUTSIDE';
 return 'BOUNDARY UNCERTAIN';
}
function setText(id,v){const e=$(id);if(e)e.textContent=String(v??'—');}
function badge(el,status){el.className='sl-pill '+status;el.textContent=status==='none'?'NO GPS DATA':status.toUpperCase();}
function animateClock(){
 setText('slClock',new Date().toLocaleTimeString('en-IN',{timeZone:'Asia/Kolkata',hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true}));
 const all=Array.from(people.values()), labels=all.map(s=>quality(points.get(s.id)));
 setText('slTotal',all.length);
 setText('slLive',labels.filter(x=>x==='live').length);
 setText('slStale',labels.filter(x=>x==='delayed'||x==='stale').length);
 setText('slDuty',Array.from(attendance.values()).filter(x=>x.checked_in).length);
 setText('slRot',Array.from(rot.values()).filter(x=>x&&['Assigned','Running','Paused'].includes(x.status)).length);
 setText('slReportingLimit',(settings.reporting_accuracy_m??100)+' m');
 setText('slCheckinLimit',(settings.checkin_accuracy_m??30)+' m');
 for(const row of $('slList').querySelectorAll('[data-staff-id]')){
   const id=Number(row.dataset.staffId),p=points.get(id),q=quality(p),st=row.querySelector('.sl-pill'),age=row.querySelector('.sl-time');
   if(st)badge(st,q);
   if(age)age.textContent=p?fmtAge(ageMs(p))+' · '+(gpsAccuracy(p)??'—')+' m accuracy':'No position received';
 }
 if(soloMode && selected!==null)drawSelected();
 for(const [id,marker] of pins)marker.setIcon(markerIcon(quality(points.get(id))));
 if(lastOk)setText('slUpdated','Feed '+fmtAge(Date.now()-lastOk));
}
function staffSorted(){
 const text=$('slSearch').value.trim().toLowerCase(),mode=$('slFilter').value,duty=$('slDutyFilter').value;
 return Array.from(people.values()).filter(s=>{
   const match=(s.name+' '+s.code+' '+s.department).toLowerCase().includes(text);
   const q=quality(points.get(s.id));
   const state=attendance.get(s.id)?.checked_in===true;
   return match && (mode==='all'||q===mode||(mode==='offline'&&q==='none'))
     && (duty==='all'||(duty==='in'?state:!state));
 }).sort((a,b)=>{
   const priority={live:0,delayed:1,stale:2,offline:3,none:4};
   const q=priority[quality(points.get(a.id))]-priority[quality(points.get(b.id))];
   return q||a.name.localeCompare(b.name);
 });
}
function drawStaff(){
 const list=$('slList');list.replaceChildren();
 const all=staffSorted();
 if(!all.length){const e=document.createElement('div');e.className='sl-empty';e.textContent='No staff match these filters.';list.append(e);return;}
 for(const s of all){
   const p=points.get(s.id),q=quality(p);
   const b=document.createElement('button');b.type='button';b.className='sl-staff'+(selected===s.id?' selected':'');b.dataset.staffId=s.id;
   const avatar=document.createElement('span');avatar.className='sl-avatar';avatar.textContent=abbreviation(s.name);
   const copy=document.createElement('span');
   const name=document.createElement('span');name.className='sl-staff-name';name.textContent=s.name;
   const detail=document.createElement('small');detail.className='sl-staff-caption';detail.textContent=s.code+' · '+(attendance.get(s.id)?.checked_in?'CHECK-IN':'NOT CHECKED IN');
   const age=document.createElement('small');age.className='sl-staff-caption sl-time';age.textContent=p?fmtAge(ageMs(p))+' · '+(gpsAccuracy(p)??'—')+' m accuracy':'No position received';
   copy.append(name,detail,age);
   const pill=document.createElement('span');badge(pill,q);
   b.append(avatar,copy,pill);b.addEventListener('click',()=>selectStaff(s.id,true));list.append(b);
 }
}
function markerIcon(status){
 const e='<div class="sl-dot '+(status==='none'?'offline':status)+'"></div>';
 return window.L?L.divIcon({html:e,className:'',iconSize:[20,20],iconAnchor:[10,10]}):null;
}
function initMap(){
 if(typeof window.L==='undefined'){
   $('slMapUnavailable').hidden=false;
   setText('slMapSubtitle','Location data works even if map tiles are blocked');
   return;
 }
 try{
  const initial=(Number.isFinite(settings.latitude)&&Number.isFinite(settings.longitude))
     ? [settings.latitude,settings.longitude]:[22.9734,78.6569];
  map=L.map('slMap',{zoomControl:true,preferCanvas:true}).setView(initial,settings.latitude!==null?15:5);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{
    maxZoom:19,attribution:'© OpenStreetMap contributors',crossOrigin:true
  }).addTo(map);
  if(Number.isFinite(settings.latitude)&&Number.isFinite(settings.longitude))paintWorkshop();
  map.whenReady(()=>setTimeout(()=>map.invalidateSize(),200));
 }catch(e){$('slMapUnavailable').hidden=false;setText('slMapSubtitle','Map initialization unavailable');}
}
function paintWorkshop(){
 if(!map || !Number.isFinite(settings.latitude) || !Number.isFinite(settings.longitude))return;
 if(workshopCircle)map.removeLayer(workshopCircle);
 workshopCircle=L.circle([settings.latitude,settings.longitude],{
   radius:Number(settings.radius_m)||100,color:'#246bb5',weight:2,opacity:.8,
   fillColor:'#3c9ce2',fillOpacity:.09
 }).addTo(map).bindPopup('Assam Motors Workshop · '+settings.radius_m+' m boundary');
}
function renderMap(){
 if(!map)return;
 const known=new Set();
 for(const s of people.values()){
  // In solo mode do NOT display any other staff map pin or tooltip.
  if(soloMode && selected!==s.id)continue;
  const p=points.get(s.id);if(!mapPoint(p))continue;
  known.add(s.id);
  const q=quality(p);
  let mark=pins.get(s.id);
  if(!mark){
    mark=L.marker([p.lat,p.lng],{icon:markerIcon(q)}).addTo(map);
    mark.on('click',()=>selectStaff(s.id,false));
    pins.set(s.id,mark);
  }else mark.setLatLng([p.lat,p.lng]).setIcon(markerIcon(q));
  const title=document.createElement('div');title.textContent=s.name+' ('+s.code+') · '+q.toUpperCase()+' · '+fmtAge(ageMs(p));
  mark.unbindTooltip();mark.bindTooltip(title.textContent,{direction:'top',sticky:true});
 }
 for(const [id,m] of pins)if(!known.has(id)){map.removeLayer(m);pins.delete(id);}
 if(accuracyRing){map.removeLayer(accuracyRing);accuracyRing=null;}
 if(soloMode && selected!==null){
  const p=points.get(selected), accuracy=gpsAccuracy(p);
  // Show last reported accuracy uncertainty; this is NOT a Check-In approval.
  if(mapPoint(p) && accuracy!==null && accuracy>0 && accuracy<=1000){
   accuracyRing=L.circle([p.lat,p.lng],{radius:accuracy,color:quality(p)==='live'?'#1683bf':'#d58648',
    weight:1.5,dashArray:'5 5',fillColor:'#369edc',fillOpacity:.08,interactive:false}).addTo(map);
  }
  if(mapPoint(p) && followSelected && lastFollowedFixId!==p.id){
   lastFollowedFixId=p.id;map.panTo([p.lat,p.lng],{animate:true,duration:.45});
  }
 }
 if(!hasFit && known.size){
   const p=selected!==null?points.get(selected):points.get(known.values().next().value);
   if(mapPoint(p)){map.setView([p.lat,p.lng],15);hasFit=true;}
 }
}
function drawSelected(){
 const s=people.get(selected),p=points.get(selected);
 if(!s){setText('slSelectedName','Select a staff member to inspect duty location');return;}
 setText('slSelectedName',s.name+' · '+s.code);
 setText('slFocusName',s.name);
 setText('slFocusCode',s.code+' · '+(s.department||'Staff'));
 setText('slFocusAvatar',abbreviation(s.name));
 badge($('slSelectedStatus'),quality(p));
 badge($('slFocusBadge'),quality(p));
 setText('slFocusAge',p?fmtAge(ageMs(p)):'Never');
 setText('slFocusSync',p?fmtAge(ageMs({recorded_at:p.received_at})):'Never');
 setText('slFocusAccuracy',p&&gpsAccuracy(p)!==null?gpsAccuracy(p)+' m':'No fix');
 setText('slMapTitle',s.name+' · '+s.code);
 setText('slMapSubtitle',p?'Last location '+fmtAge(ageMs(p))+' · GPS ±'+(gpsAccuracy(p)??'—')+' m':'No coordinate received');
 setText('slFocusNotice',quality(p)==='live'
  ? 'Fresh reported GPS fix · accuracy circle shown; strict Check-In is verified separately'
  : 'LAST KNOWN POSITION ONLY · do not assume staff is still at this coordinate');
 setText('slRecorded',p?fmtDate(p.recorded_at)+' · '+fmtAge(ageMs(p)):'No coordinate');
 setText('slReceived',p?fmtDate(p.received_at):'Never');
 setText('slAccuracy',p?(gpsAccuracy(p)??'—')+' m · '+(p.provider||'Unknown'):'No GPS');
 setText('slGeofence',geofence(p));
 const a=attendance.get(selected);
 setText('slAttendance',a?.checked_in?'CHECKED IN · '+fmtDate(a.check_in):a?'NOT CHECKED IN':'No Check-In today');
 const r=rot.get(selected);
 setText('slRotSelected',r?r.rot_code+' · '+r.status+' · '+r.job_card_no:'No active ROT');
 const link=$('slGoogleMaps');
 if(mapPoint(p)){
  link.hidden=false;
  link.href='https://www.google.com/maps?q='+encodeURIComponent(p.lat+','+p.lng);
  link.title='Open latest reported coordinate · '+fmtAge(ageMs(p));
 }else{link.hidden=true;link.removeAttribute('href');}
}
function selectStaff(id,focus){
 if(!people.has(id))return;
 if(selected!==id)clearHistory();
 selected=id;soloMode=true;followSelected=true;lastFollowedFixId=null;hasFit=false;
 $('amStaffLive').classList.add('solo');
 $('slFollow').hidden=false;$('slFollow').setAttribute('aria-pressed','true');
 $('slFollow').textContent='◎ Follow ON';
 drawStaff();drawSelected();renderMap();
 const p=points.get(id);
 if(map)requestAnimationFrame(()=>setTimeout(()=>{
   map.invalidateSize();
   if(mapPoint(p))map.setView([p.lat,p.lng],16,{animate:false});
 },140));
 if(window.matchMedia('(max-width:850px)').matches)
  $('slFocus').scrollIntoView({behavior:'smooth',block:'start'});
}
function showAllStaff(){
 soloMode=false;selected=null;followSelected=false;lastFollowedFixId=null;hasFit=false;
 clearHistory();$('amStaffLive').classList.remove('solo');$('slFollow').hidden=true;
 setText('slMapTitle','Real Location Map');
 setText('slMapSubtitle','GPS positions, not simulated movements');
 drawStaff();renderMap();
 if(map)requestAnimationFrame(()=>setTimeout(()=>map.invalidateSize(),120));
 if(window.matchMedia('(max-width:850px)').matches)$('slList').scrollIntoView({behavior:'smooth',block:'nearest'});
}
function ingestPoint(p){
 if(!p || !Number.isInteger(p.staff_id) || p.staff_id<1)return false;
 const old=points.get(p.staff_id);
 // Never move the selected marker backwards when an offline backlog replays.
 if(old && Date.parse(p.recorded_at||'') < Date.parse(old.recorded_at||''))return false;
 if(old && p.id<=old.id && p.recorded_at===old.recorded_at)return false;
 points.set(p.staff_id,p);return true;
}
async function poll(force=false){
 if(polling || (!force && document.hidden))return;
 polling=true;
 try{
  const meta=cursor===0||Date.now()-lastMeta>15000;
  const url=new URL(ENDPOINT,location.href);
  url.searchParams.set('after',String(cursor));
  if(meta)url.searchParams.set('meta','1');
  const response=await fetch(url.toString(),{credentials:'same-origin',cache:'no-store',
    headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},signal:AbortSignal.timeout(12000)});
  if(response.status===401||response.status===403||response.redirected){
    setText('slConnection','ERP login required');$('slConnection').classList.add('sl-error');
    if(response.redirected)throw new Error('ERP session expired — please login again.');
    throw new Error('ERP administrator permission required.');
  }
  if(!response.ok)throw new Error('Live feed HTTP '+response.status);
  const payload=await response.json();
  if(!payload.ok)throw new Error('Live feed invalid response');
  if(payload.initial)points.clear();
  cursor=Number(payload.cursor)||0;
  settings=payload.settings||settings;
  let changed=false;
  if(Array.isArray(payload.staff)){
   people.clear();
   for(const s of payload.staff)people.set(s.id,s);
   attendance.clear();rot.clear();
   for(const [id,a] of Object.entries(payload.attendance||{}))attendance.set(Number(id),a);
   for(const [id,r] of Object.entries(payload.rot||{}))rot.set(Number(id),r);
   lastMeta=Date.now();changed=true;
  }
  for(const p of payload.points||[])changed=ingestPoint(p)||changed;
  lastOk=Date.now();requestErrors=0;
  setText('slConnection','Connected · 1s data checks');
  $('slConnection').classList.remove('sl-error','sl-warning');
  paintWorkshop();
  if(changed){
   if(soloMode && selected!==null && !people.has(selected))showAllStaff();
   drawStaff();renderMap();if(soloMode)drawSelected();
  }
 }catch(err){
  requestErrors++;
  const message=err?.message||'Unknown network error';
  setText('slConnection',String(message).slice(0,120));
  $('slConnection').classList.add('sl-warning');
  if(requestErrors===1)console.warn('Staff Live Location feed:',message);
 }finally{polling=false;}
}
async function showHistory(){
 if(selected===null){setText('slHistoryStatus','Select a staff first');return;}
 const date=$('slHistoryDate').value||safeDateParts();
 $('slHistoryButton').disabled=true;setText('slHistoryStatus','Loading route…');
 try{
  const url=new URL(HISTORY,location.href);
  url.searchParams.set('staff_id',selected);url.searchParams.set('date',date);
  const response=await fetch(url.toString(),{credentials:'same-origin',cache:'no-store',headers:{'Accept':'application/json'}});
  if(!response.ok)throw new Error('History HTTP '+response.status);
  const data=await response.json();
  if(!map)throw new Error('Map unavailable; history can be seen in the ERP Location register.');
  if(route)map.removeLayer(route);
  const points=(data.points||[]).filter(mapPoint).map(p=>[p.lat,p.lng]);
  if(points.length){
    route=L.polyline(points,{color:'#1078ca',weight:4,opacity:.82}).addTo(map);
    map.fitBounds(route.getBounds(),{padding:[32,32],maxZoom:17});
  }
  setText('slHistoryStatus',points.length+' route fixes'+(data.limited?' · showing last 300':'')+' · '+date);
 }catch(err){setText('slHistoryStatus',err.message);}finally{$('slHistoryButton').disabled=false;}
}
function clearHistory(){if(route&&map)map.removeLayer(route);route=null;setText('slHistoryStatus','Route cleared');}
function start(){
 $('slHistoryDate').value=safeDateParts();
 $('slSearch').addEventListener('input',drawStaff);
 $('slFilter').addEventListener('change',drawStaff);
 $('slDutyFilter').addEventListener('change',drawStaff);
 $('slRefresh').addEventListener('click',()=>poll(true));
 $('slAllStaff').addEventListener('click',showAllStaff);
 $('slFollow').addEventListener('click',()=>{
  if(!soloMode)return;
  followSelected=!followSelected;
  $('slFollow').textContent=followSelected?'◎ Follow ON':'◉ Follow OFF';
  $('slFollow').setAttribute('aria-pressed',String(followSelected));
  if(followSelected){lastFollowedFixId=null;renderMap();}
 });
 document.addEventListener('keydown',event=>{
  if(event.key==='Escape' && soloMode && document.activeElement?.tagName!=='INPUT')showAllStaff();
 });
 $('slWorkshop').addEventListener('click',()=>{if(map&&Number.isFinite(settings.latitude)&&Number.isFinite(settings.longitude))map.setView([settings.latitude,settings.longitude],17);});
 $('slHistoryButton').addEventListener('click',showHistory);
 $('slClearRoute').addEventListener('click',clearHistory);
 document.addEventListener('visibilitychange',()=>{if(!document.hidden){if(map)map.invalidateSize();poll(true);}});
 initMap();animateClock();poll(true);
 setInterval(animateClock,1000);
 // 1s incremental primary-key poll while dashboard is visible; no fabricated GPS.
 refreshLoop=setInterval(()=>poll(),1000);
}
start();
})();
</script>
@endsection
