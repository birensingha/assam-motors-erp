{{-- AM210_LIVE_DUTY_GPS_REPORTING_INTERVAL: customizable control BELOW Live Duty Location. --}}
<style>
.am210-gps-panel{background:#fff;border:1px solid #dce7ef;border-radius:15px;padding:14px 16px;box-shadow:0 3px 12px #2143640a}
.am210-gps-head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:9px}
.am210-gps-head h2{font-size:15px!important;margin:0!important;color:#1b3755!important;font-weight:800}
.am210-gps-current{font-size:12px;color:#355e83;font-weight:700}
.am210-gps-row{display:flex;align-items:end;gap:10px;flex-wrap:wrap}
.am210-gps-row label{display:grid;gap:5px;font-size:11px;font-weight:750;color:#627c93}
.am210-gps-row input,.am210-gps-row select{border:1px solid #c8d9e7;border-radius:8px;padding:10px 11px;font:inherit;font-size:12px;color:#183c5f;background:#fff;min-height:39px}
.am210-gps-row input{width:145px}.am210-gps-row select{width:135px}
.am210-gps-row button{min-height:39px;border-radius:8px;border:1px solid #1467b8;background:#1467b8;color:#fff;font-size:12px;font-weight:750;padding:10px 15px;cursor:pointer}
.am210-gps-row button:disabled{opacity:.55;cursor:not-allowed}
.am210-gps-note{font-size:11px;line-height:1.6;color:#647e92;margin:10px 0 0}
.am210-gps-feedback{font-size:11px;font-weight:700;color:#235479;min-width:150px}
.am210-gps-feedback[data-error="1"]{color:#ad3549}
.sl-pill.scheduled{background:#e7f1ff;color:#316ca2}
.sl-dot.scheduled{background:#3c8bbd}
@media(max-width:600px){.am210-gps-row label{flex:1 1 100%}.am210-gps-row input,.am210-gps-row select{width:100%}}
</style>
<section class="am210-gps-panel" aria-label="GPS Reporting Settings" id="am210GpsPanel">
 <div class="am210-gps-head">
  <h2>GPS Reporting Interval <small style="font-size:11px;font-weight:600;color:#74899b">· Admin Control</small></h2>
  <span class="am210-gps-current" id="am210Current">Reading ERP setting…</span>
 </div>
 <div class="am210-gps-row">
  <label for="am210IntervalNumber">Report GPS every
   <input id="am210IntervalNumber" type="number" min="1" max="900" step="1" inputmode="numeric" placeholder="e.g. 90" disabled>
  </label>
  <label for="am210IntervalUnit">Time unit
   <select id="am210IntervalUnit" disabled>
    <option value="seconds">Seconds</option>
    <option value="minutes">Minutes</option>
   </select>
  </label>
  <button type="button" id="am210Save" disabled>Save Reporting Interval</button>
  <span id="am210Feedback" class="am210-gps-feedback" role="status" aria-live="polite"></span>
 </div>
 <p class="am210-gps-note">Enter your own timing (examples: 45 seconds, 90 seconds, 2 minutes or 7 minutes). Allowed range: 30 seconds to 15 minutes. This is a global Staff App GPS reporting target; phone/network restrictions can delay delivery. Check-In, geofence and ROT authorization rules are unaffected. Existing App versions without this feature still use their built-in reporting interval.</p>
</section>
<script>
(function(){
'use strict';
const endpoint=@json(route('erp.staff-live-location.reporting-interval'));
const csrfToken=@json(csrf_token());
const input=document.getElementById('am210IntervalNumber');
const unit=document.getElementById('am210IntervalUnit');
const button=document.getElementById('am210Save');
const current=document.getElementById('am210Current');
const feedback=document.getElementById('am210Feedback');
let saved=null,busy=false,dirty=false;
function message(value,error=false) {
 feedback.textContent=value;
 feedback.dataset.error=error?'1':'0';
}
function format(seconds) {
 if(seconds%60===0)return (seconds/60)+' Minute'+(seconds===60?'':'s');
 return seconds+' Seconds';
}
function getSeconds() {
 const n=Number(input.value);
 if(!Number.isSafeInteger(n)||n<=0)return null;
 const seconds=n*(unit.value==='minutes'?60:1);
 return Number.isSafeInteger(seconds)&&seconds>=30&&seconds<=900?seconds:null;
}
function updateButton() {
 const seconds=getSeconds();
 dirty=saved!==null && seconds!==saved;
 button.disabled=busy||!dirty||seconds===null;
 if(dirty && seconds===null)message('Enter a valid interval: 30–900 seconds (up to 15 minutes).',true);
 else if(dirty)message('Press Save to apply this timing.');
 else message('');
}
function applyCurrent(seconds) {
 if(!Number.isSafeInteger(seconds)||seconds<30||seconds>900)throw new Error('Unexpected ERP GPS interval');
 saved=seconds;
 window.am210ActiveSampleSeconds=seconds;
 window.dispatchEvent(new Event('am210-reporting-interval-changed'));
 current.textContent='Current: '+format(seconds);
 if(!dirty){
   if(seconds%60===0){unit.value='minutes';input.value=String(seconds/60);}
   else {unit.value='seconds';input.value=String(seconds);}
 }
 input.disabled=false;unit.disabled=false;
 updateButton();
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
 if(!r.ok||data.ok!==true){
  if(r.status===419)throw new Error('Session expired. ERP page reload karke save kijiye.');
  if(r.status===403)throw new Error('Admin permission required for GPS Settings.');
  throw new Error(data.message||('Settings request failed (HTTP '+r.status+')'));
 }
 return data;
}
async function refresh() {
 if(busy||dirty||document.hidden)return;
 try {
  const data=await request('GET');
  applyCurrent(Number(data.interval_seconds));
 }catch(err){
  current.textContent='Unable to read current interval';
  message(err.message||'GPS reporting settings unavailable',true);
 }
}
input.addEventListener('input',updateButton);
unit.addEventListener('change',updateButton);
button.addEventListener('click',async()=>{
 if(busy)return;
 const seconds=getSeconds();
 if(seconds===null){message('Choose any value from 30 to 900 seconds.',true);return;}
 busy=true;button.disabled=true;input.disabled=true;unit.disabled=true;
 message('Saving ERP GPS policy…');
 try{
  const data=await request('POST',{interval_seconds:seconds});
  dirty=false;
  applyCurrent(Number(data.interval_seconds));
  message('Saved. Updated Android devices will sync this timing.');
 }catch(err){
  message(err.message||'Save failed; no change confirmed.',true);
 }finally{
  busy=false;input.disabled=false;unit.disabled=false;
  updateButton();
 }
});
document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh();});
window.setInterval(refresh,60_000);
refresh();
})();
</script>