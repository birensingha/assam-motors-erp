/* FIX58 ASSAM MOTORS STAFF GPS DASHBOARD — read-only native WebView UI.
   Real server readings ONLY. Does not change background location, duty, ROT or permissions.
   Patch is for an ORIGINAL-certificate-signed build, not a live remote hotfix.
*/
(function(){
'use strict';
const host=document.getElementById('view-owner-locations');
const template=document.getElementById('amGps6320Template');
if(!host||!template||document.getElementById('amGps6320'))return;
const container=document.createElement('div');
container.id='amGps6320';
container.appendChild(template.content.cloneNode(true));
host.insertBefore(container,host.firstChild);
const $=id=>container.querySelector('#'+id);
const state={rows:[],selected:null,fetching:false,follow:true,solo:false,workshop:false,
 mapKey:'',offsetMs:0,serverClock:false,connected:false,lastSync:0,settings:{},ticker:0};
const media=window.matchMedia('(max-width:780px)');
function visible(){
 return media.matches&&document.body.classList.contains('owner-mode') &&
  (host.classList.contains('active') || getComputedStyle(host).display!=='none');
}
function put(id,value){const node=$(id);if(node)node.textContent=String(value);}
function number(value){
 if(value==null||String(value).trim()==='')return null;
 const n=Number(value);return Number.isFinite(n)?n:null;
}
function instant(raw){
 if(raw===null||raw===undefined||raw==='')return null;
 let s=String(raw).trim().replace(' ','T');
 if(!s)return null;
 if(/^\d{10,13}$/.test(s))return Number(s)*(s.length===10?1000:1);
 if(!/(Z|[+-]\d{2}:\d{2})$/i.test(s))s+='+05:30';
 const n=Date.parse(s);return Number.isFinite(n)?n:null;
}
function now(){return Date.now()+(state.serverClock?state.offsetMs:0);}
function age(row){
 const point=row?.point,ts=instant(point?.recorded_at);
 if(ts===null)return null;
 const n=now()-ts;return n < -15000?null:Math.max(0,n);
}
function formatAge(ms){
 if(ms===null||!Number.isFinite(ms))return 'Unknown';
 const s=Math.floor(ms/1000);
 if(s<60)return s+'s ago';if(s<3600)return Math.floor(s/60)+'m ago';
 if(s<86400)return Math.floor(s/3600)+'h '+Math.floor((s%3600)/60000)+'m ago';
 return Math.floor(s/86400)+'d ago';
}
function hasPoint(p){
 const a=number(p?.latitude),b=number(p?.longitude);
 return a!==null&&b!==null&&Math.abs(a)<=90&&Math.abs(b)<=180&&!(a===0&&b===0);
}
function kind(row){
 const p=row?.point;if(!hasPoint(p))return 'none';
 const ms=age(row);if(ms===null)return 'stale';
 const accuracy=number(p.accuracy_m);
 const ceiling=Math.max(10,Math.min(100,number(state.settings.reporting_accuracy_m)??100));
 const quality=String(p.quality||'').toUpperCase();
 if(accuracy===null||accuracy>ceiling||quality==='WEAK_ACCURACY'||quality==='MOCK_LOCATION')return 'weak';
 if(ms<=120000)return 'fresh';
 return ms<=600000?'delayed':'stale';
}
function status(k){return ({fresh:'FRESH GPS',delayed:'DELAYED',stale:'STALE',weak:'WEAK GPS',none:'NO GPS'})[k]||'NO GPS';}
function badge(el,k){if(!el)return;el.className='amg-badge'+(k==='fresh'?'':k==='weak'?' weak':' stale');el.textContent=status(k);}
function checkIn(r){return !!r.staff?.attendance?.check_in&&!r.staff?.attendance?.check_out;}
function selected(){return state.rows.find(r=>r.id===state.selected)||null;}
function letters(s){return String(s||'?').trim().split(/\s+/).slice(0,2).map(x=>x[0]||'').join('').toUpperCase()||'?';}
function merge(staff,locations){
 const map=new Map();
 for(const p of staff||[]){
  const id=Number(p.id);if(!Number.isSafeInteger(id)||id<=0)continue;
  map.set(id,{id,staff:p,point:p.location||null});
 }
 for(const p of locations||[]){
  const id=Number(p.staff_id);if(!map.has(id))continue;
  const r=map.get(id),old=instant(r.point?.recorded_at)??-Infinity;
  const newer=instant(p.recorded_at)??-Infinity;
  if(!r.point||newer>=old)r.point=p;
 }
 return Array.from(map.values()).sort((a,b)=>String(a.staff.name||'').localeCompare(String(b.staff.name||'')));
}
function showDetails(){
 const row=selected();
 if(!row){
  put('amgName','Select staff');put('amgAvatar','—');put('amgSelectedInfo','No location selected');
  put('amgFixAge','—');put('amgAccuracy','—');put('amgQuality','—');put('amgAttendanceText','—');
  badge($('amgSelectedBadge'),'none');return;
 }
 const p=row.point,k=kind(row),accuracy=number(p?.accuracy_m);
 put('amgName',(row.staff.name||'Staff')+' · '+(row.staff.staff_code||''));
 put('amgAvatar',letters(row.staff.name));
 put('amgSelectedInfo','Last fix '+formatAge(age(row))+' · '+(accuracy!==null?'GPS ±'+accuracy.toFixed(1)+'m':'accuracy unknown'));
 put('amgFixAge',formatAge(age(row)));
 put('amgAccuracy',accuracy===null?'Unknown':'±'+accuracy.toFixed(1)+' m');
 put('amgQuality',status(k));put('amgAttendanceText',checkIn(row)?'Checked In':'Not Checked In');
 badge($('amgSelectedBadge'),k);
 const msg=!hasPoint(p)?'No valid reported coordinate.':
  (k==='fresh'?'Last VERIFIED GPS fix. ':'Last known position; not verified live. ')+
  'Accuracy '+(accuracy===null?'unknown':'±'+accuracy.toFixed(1)+' m')+
  ' · Recorded '+formatAge(age(row))+'.';
 put('amgMapNote',msg+' GPS marker is a reported observation, not continuous tracking.');
}
function mapUrl(lat,lon){
 const delta=.005,box=[lon-delta,lat-delta,lon+delta,lat+delta].map(v=>v.toFixed(6)).join(',');
 return 'https://www.openstreetmap.org/export/embed.html?bbox='+encodeURIComponent(box)+
  '&layer=mapnik&marker='+encodeURIComponent(lat.toFixed(6)+','+lon.toFixed(6));
}
function drawMap(force){
 const row=selected(),p=row?.point;
 let lat=null,lon=null;
 if(state.workshop && number(state.settings.latitude)!==null && number(state.settings.longitude)!==null){
  lat=Number(state.settings.latitude);lon=Number(state.settings.longitude);
 } else if(hasPoint(p)){lat=Number(p.latitude);lon=Number(p.longitude);}
 const map=$('amgMap');
 if(lat===null||lon===null){
  if(state.mapKey==='empty'&&!force)return;
  const e=document.createElement('div');e.className='amg-map-empty';e.textContent='No valid GPS point. Map location is not estimated.';
  map.replaceChildren(e);state.mapKey='empty';return;
 }
 const key=(state.workshop?'workshop':'staff')+':'+lat.toFixed(5)+':'+lon.toFixed(5);
 if(state.mapKey===key&&!force)return;
 const frame=document.createElement('iframe');
 frame.title=(state.workshop?'Workshop':'Reported staff')+' location on OpenStreetMap';
 frame.src=mapUrl(lat,lon);frame.loading='lazy';frame.referrerPolicy='no-referrer';
 map.replaceChildren(frame);state.mapKey=key;
}
function filtered(){
 const q=($('amgSearch').value||'').trim().toLowerCase(),gps=$('amgPresence').value,att=$('amgAttendance').value;
 return state.rows.filter(r=>{
  if(state.solo&&r.id!==state.selected)return false;
  if(q&&!((r.staff.name||'')+' '+(r.staff.staff_code||'')).toLowerCase().includes(q))return false;
  const k=kind(r);
  if(gps==='fresh'&&k!=='fresh')return false;
  if(gps==='none'&&k!=='none')return false;
  if(gps==='stale'&&!['weak','delayed','stale'].includes(k))return false;
  if(att==='in'&&!checkIn(r))return false;
  if(att==='out'&&checkIn(r))return false;
  return true;
 });
}
function staffList(){
 const parent=$('amgList');parent.replaceChildren();
 const rows=filtered();
 if(!rows.length){
  const n=document.createElement('div');n.className='amg-empty';n.textContent='No matching staff';
  parent.appendChild(n);return;
 }
 for(const r of rows.slice(0,250)){
  const button=document.createElement('button');button.type='button';button.className='amg-staff';
  button.setAttribute('aria-selected',r.id===state.selected?'true':'false');
  const avatar=document.createElement('span');avatar.className='amg-avatar';avatar.textContent=letters(r.staff.name);
  const middle=document.createElement('div');middle.className='amg-person-content';
  const h=document.createElement('strong');h.textContent=r.staff.name||'Staff';
  const sub=document.createElement('small');
  const accuracy=number(r.point?.accuracy_m);
  sub.textContent=(r.staff.staff_code||'')+' · '+(checkIn(r)?'CHECKED IN':'NOT CHECKED IN')+
    ' · '+formatAge(age(r))+(accuracy!==null?' · ±'+accuracy.toFixed(1)+'m':'');
  middle.append(h,sub);const chip=document.createElement('span');badge(chip,kind(r));
  button.append(avatar,middle,chip);
  button.addEventListener('click',()=>{state.selected=r.id;state.workshop=false;render();});
  parent.appendChild(button);
 }
}
function render(){
 const rows=state.rows,kinds=rows.map(kind);
 put('amgTotal',rows.length);put('amgFresh',kinds.filter(k=>k==='fresh').length);
 put('amgStale',kinds.filter(k=>['weak','delayed','stale'].includes(k)).length);
 put('amgChecked',rows.filter(checkIn).length);
 put('amgRot',rows.filter(r=>String(r.staff?.active_rot?.status||'').toLowerCase()==='running').length);
 put('amgUpdated',state.lastSync?'Updated '+formatAge(now()-state.lastSync):'Not synced');
 $('amgWorkshop').disabled=number(state.settings.latitude)===null||number(state.settings.longitude)===null;
 $('amgSolo').textContent=state.solo?'Show all':'Focus one';
 showDetails();staffList();if(state.follow||!state.mapKey)drawMap(false);
}
async function refresh(){
 if(!visible()||state.fetching)return;
 if(typeof window.AMNative?.nativeRequest!=='function'){
  $('amgError').style.display='block';put('amgError','Native ERP API bridge unavailable');return;
 }
 state.fetching=true;$('amgRefresh').disabled=true;
 try{
  const [staffReq,locReq]=await Promise.allSettled([
   window.AMNative.nativeRequest('GET','/admin/mobile/staff'),
   window.AMNative.nativeRequest('GET','/admin/mobile/staff-locations?latest=1')
  ]);
  if(!visible())return;
  if(staffReq.status!=='fulfilled'&&locReq.status!=='fulfilled')throw new Error('ERP GPS feeds are temporarily unavailable.');
  const staff=staffReq.status==='fulfilled'&&Array.isArray(staffReq.value?.staff)
    ?staffReq.value.staff:state.rows.map(x=>x.staff);
  const points=locReq.status==='fulfilled'&&Array.isArray(locReq.value?.locations)
    ?locReq.value.locations:state.rows.filter(x=>x.point).map(x=>Object.assign({staff_id:x.id},x.point));
  if(locReq.status==='fulfilled'){
   const sourceTime=instant(locReq.value.server_time);
   if(sourceTime!==null){state.serverClock=true;state.offsetMs=sourceTime-Date.now();}
   state.settings=locReq.value.settings||state.settings;
  }
  state.connected=staffReq.status==='fulfilled'&&locReq.status==='fulfilled';
  if(state.connected){state.lastSync=now();$('amgError').style.display='none';}
  else {
   put('amgError','One GPS source failed; shown values may be incomplete.');$('amgError').style.display='block';
  }
  state.rows=merge(staff,points);
  if(state.selected===null||!state.rows.some(x=>x.id===state.selected))
   state.selected=state.rows.find(x=>kind(x)==='fresh')?.id??state.rows[0]?.id??null;
  render();
 }catch(error){
  state.connected=false;put('amgError',(error.message||'GPS connection unavailable')+' · Last received samples shown.');
  $('amgError').style.display='block';render();
 }finally{state.fetching=false;$('amgRefresh').disabled=false;}
}
function tick(){
 if(!visible())return;
 try{
  put('amgClock',new Intl.DateTimeFormat('en-IN',{
   timeZone:'Asia/Kolkata',hour:'2-digit',minute:'2-digit',second:'2-digit'
  }).format(new Date(now()))+' IST');
 }catch(_){put('amgClock','IST');}
 put('amgServerAge',state.lastSync?'Feed '+formatAge(now()-state.lastSync):'Waiting for ERP');
 badge($('amgLive'),state.connected&&state.rows.some(r=>kind(r)==='fresh')?'fresh':'stale');
 if(state.rows.length){showDetails();if(++state.ticker%5===0)staffList();}
}
$('amgSearch').addEventListener('input',staffList);
$('amgPresence').addEventListener('change',staffList);
$('amgAttendance').addEventListener('change',staffList);
$('amgRefresh').addEventListener('click',refresh);
$('amgFocus').addEventListener('click',()=>{state.workshop=false;drawMap(true);});
$('amgWorkshop').addEventListener('click',()=>{state.workshop=true;drawMap(true);});
$('amgFollow').addEventListener('click',()=>{
 state.follow=!state.follow;$('amgFollow').textContent=state.follow?'◎ Follow ON':'◎ Follow OFF';
 $('amgFollow').setAttribute('aria-pressed',String(state.follow));
 if(state.follow){state.workshop=false;drawMap(true);}
});
$('amgSolo').addEventListener('click',()=>{state.solo=!state.solo;render();});
$('amgAll').addEventListener('click',()=>{
 state.solo=false;$('amgSearch').value='';$('amgPresence').value='all';
 $('amgAttendance').value='all';render();
});
function activate(){
 const ok=visible();
 host.classList.toggle('am-gps6320-active',ok);
 if(ok&&(!state.lastSync||now()-state.lastSync>25000))refresh();
}
const obs=new MutationObserver(activate);
obs.observe(host,{attributes:true,attributeFilter:['class','style']});
obs.observe(document.body,{attributes:true,attributeFilter:['class']});
if(media.addEventListener)media.addEventListener('change',activate);
else if(media.addListener)media.addListener(activate);
setInterval(()=>{activate();tick();},1000);
setInterval(()=>{if(visible())refresh();},30000);
activate();
})();
