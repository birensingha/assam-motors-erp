{{-- AM210_LIVE_DUTY_GPS_REPORTING_INTERVAL: existing Live Duty Location page footer control. --}}
<style>
.am210-gps-panel{background:#fff;border:1px solid #dce7ef;border-radius:15px;padding:14px 16px;box-shadow:0 3px 12px #2143640a}
.am210-gps-head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:9px}
.am210-gps-head h2{font-size:15px!important;margin:0!important;color:#1b3755!important;font-weight:800}
.am210-gps-current{font-size:12px;color:#355e83;font-weight:700}
.am210-gps-row{display:flex;align-items:end;gap:10px;flex-wrap:wrap}
.am210-gps-row label{display:grid;gap:5px;min-width:225px;font-size:11px;font-weight:750;color:#627c93}
.am210-gps-row select{min-width:225px;width:100%;border:1px solid #c8d9e7;border-radius:8px;padding:10px 11px;font:inherit;font-size:12px;color:#183c5f;background:#fff}
.am210-gps-row button{min-height:38px;border-radius:8px;border:1px solid #1467b8;background:#1467b8;color:#fff;font-size:12px;font-weight:750;padding:10px 15px;cursor:pointer}
.am210-gps-row button:disabled{opacity:.55;cursor:not-allowed}
.am210-gps-note{font-size:11px;line-height:1.6;color:#647e92;margin:10px 0 0}
.am210-gps-feedback{font-size:11px;font-weight:700;color:#235479;min-width:150px}
.am210-gps-feedback[data-error="1"]{color:#ad3549}
.sl-pill.scheduled{background:#e7f1ff;color:#316ca2}
.sl-dot.scheduled{background:#3c8bbd}
@media(max-width:600px){.am210-gps-row label,.am210-gps-row select{min-width:0;width:100%}.am210-gps-row label{flex:1 1 100%}}
</style>
<section class="am210-gps-panel" aria-label="GPS Reporting Settings" id="am210GpsPanel">
 <div class="am210-gps-head">
  <h2>GPS Reporting Interval <small style="font-size:11px;font-weight:600;color:#74899b">· Admin Control</small></h2>
  <span class="am210-gps-current" id="am210Current">Reading current ERP setting…</span>
 </div>
 <div class="am210-gps-row">
  <label for="am210Interval">Report location every
   <select id="am210Interval" disabled>
    <option value="">Choose reporting interval</option>
    <option value="1">60 Seconds (1 Minute)</option>
    <option value="5">5 Minutes</option>
   </select>
  </label>
  <button type="button" id="am210Save" disabled>Save Reporting Interval</button>
  <span id="am210Feedback" class="am210-gps-feedback" role="status" aria-live="polite"></span>
 </div>
 <p class="am210-gps-note">Global setting for authorized Staff App devices. Changing the interval does not change Check-In/ROT GPS accuracy, geofence, or the 1-second dashboard refresh. Android versions with ERP-controlled reporting support will apply the setting on their next successful sync; older versions retain their built-in interval.</p>
</section>
<script>
(function(){
'use strict';
const endpoint=@json(route('erp.staff-live-location.reporting-interval'));
const csrfToken=@json(csrf_token());
const input=document.getElementById('am210Interval');
const button=document.getElementById('am210Save');
const current=document.getElementById('am210Current');
const feedback=document.getElementById('am210Feedback');
let saved=null,busy=false,dirty=false;
function message(value,error=false) {
 feedback.textContent=value;
 feedback.dataset.error=error?'1':'0';
}
function format(value){return value===1?'60 Seconds (1 Minute)':value+' Minutes';}
function applyCurrent(minutes) {
 if(!Number.isInteger(minutes)||minutes<1||minutes>15)throw new Error('Unexpected ERP GPS interval');
 saved=minutes;
 window.am210ActiveSampleMinutes=minutes;
 window.dispatchEvent(new Event('am210-reporting-interval-changed'));
 current.textContent='Current: '+format(minutes);
 if(!dirty){input.value=[1,5].includes(minutes)?String(minutes):'';}
 input.disabled=false;
 button.disabled=busy||!input.value||Number(input.value)===saved;
}
async function request(method,body=null) {
 const opts={method,credentials:'same-origin',cache:'no-store',
  headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}};
 if(method!=='GET'){
  opts.headers['Content-Type']='application/json';
  opts.headers['X-CSRF-TOKEN']=csrfToken;
  opts.body=JSON.stringify(body);
 }
 const r=await fetch(endpoint,opts);
 const data=await r.json().catch(()=>({}));
 if(!r.ok||data.ok!==true) {
  if(r.status===419)throw new Error('Session expired. ERP page reload karke save kijiye.');
  if(r.status===403)throw new Error('Admin permission required for GPS Settings.');
  throw new Error(data.message||('Settings request failed (HTTP '+r.status+')'));
 }
 return data;
}
async function refresh() {
 if(busy||dirty||document.hidden)return;
 try{
  const data=await request('GET');
  applyCurrent(Number(data.sample_minutes));
  message('');
 }catch(err){
  current.textContent='Unable to read current interval';
  message(err.message||'GPS reporting settings unavailable',true);
 }
}
input.addEventListener('change',()=>{
 dirty=Boolean(saved!==null&&Number(input.value)!==saved);
 button.disabled=busy||!dirty||!input.value;
 message(dirty?'Press Save to change all Staff devices.':'');
});
button.addEventListener('click',async()=>{
 if(busy)return;
 const value=Number(input.value);
 if(![1,5].includes(value)){message('Choose 60 Seconds or 5 Minutes.',true);return;}
 busy=true;button.disabled=true;input.disabled=true;
 message('Saving ERP GPS policy…');
 try{
  const data=await request('POST',{sample_minutes:value});
  dirty=false;
  applyCurrent(Number(data.sample_minutes));
  message('Saved. Supported Android devices will sync this setting.');
 }catch(err){
  message(err.message||'Save failed. No change confirmed.',true);
 }finally{
  busy=false;input.disabled=false;
  button.disabled=!dirty||!input.value;
 }
});
document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh();});
window.setInterval(refresh,60_000);
refresh();
})();
</script>