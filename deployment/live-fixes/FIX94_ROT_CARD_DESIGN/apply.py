#!/usr/bin/env python3
"""FIX94: screenshot-matched compact Mechanic & ROT work card on STAGING.
Blade VIEW ONLY; existing RotLiveClock, backend ROT transitions, mechanic
assignment, billing editor, OSL table and audit controls are preserved.
"""
from pathlib import Path
import sys,hashlib,tempfile,os
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
 raise SystemExit('Usage: apply.py check|apply LARAVEL_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file()
p=root/'resources/views/job-cards/show.blade.php'
raw=p.read_bytes();actual=hashlib.sha256(raw).hexdigest()
print('FIX94_SOURCE_SHA256',actual)
marker='id="FIX94_COMPACT_MECHANIC_ROT_CARD"'
s=raw.decode('utf8','surrogateescape')
if marker in s:
 if s.count(marker)==1:
  print('FIX94_ALREADY_APPLIED');sys.exit(0)
 raise SystemExit('FIX94_PARTIALLY_APPLIED_STOP')
expected='552bbc082a61e4d3fb3f6634e0182616493aa1657e81ba4fef707e39263be3fc'
if actual!=expected:raise SystemExit('FIX94_SOURCE_CHANGED_STOP')
for key in ['class="rot-list"','rot-card rot-','class="rot-main"','class="rot-meta"',
   'class="rot-status-block"','class="rot-actions"','dashboard.partials.rot-live-clock',
   "route('erp.rot.start'","route('erp.rot.pause'","route('erp.rot.complete'",
   "route('erp.rot.resume'","route('erp.rot.change-mechanic'",
   "route('erp.rot.history'","job-cards.rot-time-editor",
   'id="FIX92_LABOUR_MECHANICS_DELETE_DIALOG"',
   'id="FIX93_OSL_MODAL_TOOLS"']:
 if key not in s:raise SystemExit('FIX94_MISSING_CRITICAL_ANCHOR '+key)
cssjs=r'''
{{-- FIX94 user-designed compact ROT control card; presentation only.
     Live timing originates from RotLiveClock::snapshot and still updates every
     second; no counter reinitialisation, no changed ROT/work endpoints. --}}
<style id="FIX94_COMPACT_MECHANIC_ROT_CARD">
/* Outer card mimics supplied reference: white body, fine gray border, green left rail. */
.jc-labour-block .am83-workspace .rot-list{padding:0!important;margin:0!important;overflow:visible!important}
.jc-labour-block .am83-workspace .rot-card{
 position:relative!important;background:#fff!important;
 border:1px solid #cfe0df!important;border-left:4px solid #2a9a76!important;
 border-radius:8px!important;box-shadow:none!important;
 padding:8px 10px 7px!important;margin:7px 0 9px!important;
 min-height:0!important;overflow:visible!important;
 font-family:inherit!important
}
.jc-labour-block .am83-workspace .rot-card.rot-paused{border-left-color:#d79d31!important}
.jc-labour-block .am83-workspace .rot-card.rot-completed{border-left-color:#3876b4!important}
.jc-labour-block .am83-workspace .rot-card.rot-unassigned{border-left-color:#879cae!important}
.jc-labour-block .rot-card .rot-main{
 display:flex!important;align-items:flex-start!important;justify-content:space-between!important;
 flex-wrap:nowrap!important;gap:12px!important;margin:0!important;
 padding:0 0 6px!important;border-bottom:1px solid #dfe8ef!important
}
.jc-labour-block .rot-card .rot-main>div:first-child{flex:1 1 auto!important;min-width:0!important;line-height:1.24!important}
.jc-labour-block .rot-card .rot-code{
 color:#315e82!important;letter-spacing:.055em!important;font-size:9px!important;
 font-weight:850!important;line-height:1.4!important;margin:0 0 3px!important
}
.jc-labour-block .rot-card .am83-source{
 display:inline-block!important;padding:2px 5px!important;margin-left:6px!important;
 vertical-align:middle!important;line-height:1.2!important;letter-spacing:.02em!important;
 border-radius:3px!important;font-size:8px!important;font-weight:850!important
}
.jc-labour-block .rot-card .am83-source.estimate{background:#dcf7e9!important;color:#147555!important}
.jc-labour-block .rot-card .am83-source.extra{background:#e8f1ff!important;color:#185a97!important}
.jc-labour-block .rot-card .rot-main h3{
 display:inline!important;margin:0 7px 0 0!important;padding:0!important;border:0!important;
 font-size:13px!important;font-weight:850!important;color:#152c4a!important;
 line-height:1.4!important;letter-spacing:0!important
}
.jc-labour-block .rot-card .rot-main .muted{
 display:inline!important;font-size:10px!important;font-weight:500!important;
 color:#52718e!important;line-height:1.45!important
}
.jc-labour-block .rot-card .rot-status-block{
 flex:0 0 auto!important;display:grid!important;grid-template-columns:auto auto!important;
 column-gap:8px!important;row-gap:3px!important;align-items:center!important;
 justify-items:end!important;text-align:right!important;min-width:125px!important;
 background:transparent!important;border:0!important;padding:1px 0!important
}
.jc-labour-block .rot-card .rot-status-block .rot-status{
 grid-column:1/-1!important;display:inline-block!important;justify-self:end!important;
 width:auto!important;background:#e5f7ec!important;color:#12613d!important;
 border-radius:18px!important;font-size:9px!important;font-weight:900!important;
 padding:3px 9px!important;line-height:1.1!important;border:0!important
}
.jc-labour-block .rot-card .rot-status-block .rot-status.paused{background:#fff1d9!important;color:#93621e!important}
.jc-labour-block .rot-card .rot-status-block .rot-status.completed{background:#e2f0ff!important;color:#225b9c!important}
.jc-labour-block .rot-card .rot-status-block .rot-status.unassigned{background:#edf1f5!important;color:#54677d!important}
.jc-labour-block .rot-card .rot-status-block strong{
 font-size:11px!important;color:#113754!important;font-weight:850!important;white-space:nowrap!important
}
.jc-labour-block .rot-card .rot-status-block small{
 font-size:9px!important;color:#4c6a84!important;font-weight:550!important;white-space:nowrap!important
}
.jc-labour-block .rot-card .rot-meta{
 display:flex!important;align-items:center!important;gap:7px!important;flex-wrap:wrap!important;
 margin:0!important;padding:6px 0 4px!important;background:transparent!important;
 color:#476b8c!important;font-size:10px!important;line-height:1.4!important;
 border:0!important;min-height:0!important
}
.jc-labour-block .rot-card .rot-meta b{color:#335979!important;font-weight:700!important}
.jc-labour-block .rot-card .rot-meta .mono{font-size:9px!important;color:#506c89!important;overflow-wrap:anywhere}
/* Clock: screenshot positions blue progress, live HH:MM:SS and remaining in one compact strip. */
.jc-labour-block .rot-card .am-rot-live{
 margin:1px 0 5px!important;padding:5px 9px 4px!important;
 border:0!important;border-radius:4px!important;
 background:#eff5fa!important;color:#194e7e!important;
 display:flex!important;flex-direction:column!important;gap:0!important
}
.jc-labour-block .rot-card .am-rot-live-head{
 display:flex!important;align-items:center!important;justify-content:space-between!important;
 gap:8px!important;line-height:1.25!important;margin:0!important;
 font-size:10px!important;font-weight:700!important
}
.jc-labour-block .rot-card .am-rot-live-head>span{font-size:10px!important;color:#215880!important;letter-spacing:.01em!important}
.jc-labour-block .rot-card .am-rot-live-head>strong{
 font-size:16px!important;line-height:1.1!important;color:#07589a!important;
 font-weight:900!important;font-variant-numeric:tabular-nums!important
}
.jc-labour-block .rot-card .am-rot-live-track{
 height:5px!important;flex:0 0 5px!important;max-height:5px!important;
 background:#d9e4ed!important;border-radius:2px!important;overflow:hidden!important;
 margin:5px 0 4px!important
}
.jc-labour-block .rot-card .am-rot-live-track i{
 height:100%!important;background:#0879e6!important;
 border-radius:0!important;display:block!important;max-width:100%!important;
 transition:width .5s linear!important
}
.jc-labour-block .rot-card .am-rot-live .am94-clock-foot{
 display:flex!important;gap:6px!important;align-items:center!important;
 flex-wrap:wrap!important;font-size:9px!important;line-height:1.28!important;
 min-width:0!important;color:#3b6387!important
}
.jc-labour-block .rot-card .am94-clock-foot [data-am-percent]{white-space:nowrap!important;color:#155c93!important}
.jc-labour-block .rot-card .am94-clock-foot .am-rot-live-details{
 display:flex!important;gap:7px!important;align-items:center!important;flex:1 1 auto!important;
 margin:0!important;min-width:0!important;flex-wrap:wrap!important;font-size:9px!important;
 color:#3e6282!important
}
.jc-labour-block .rot-card .am94-clock-foot .am-rot-live-details>span{white-space:normal!important}
.jc-labour-block .rot-card .am94-clock-foot [data-am-balance]{
 margin-left:auto!important;white-space:nowrap!important;color:#0d547f!important;
 font-weight:850!important;font-variant-numeric:tabular-nums!important
}
.jc-labour-block .rot-card .am-rot-live.is-over{background:#fff0ee!important;color:#a53a2b!important}
.jc-labour-block .rot-card .am-rot-live.is-over .am-rot-live-track i{background:#d63232!important}
.jc-labour-block .rot-card .am-rot-live.is-near{background:#fff8e9!important;color:#866118!important}
.jc-labour-block .rot-card .am-rot-live.is-near .am-rot-live-track i{background:#e9a11e!important}
/* Keep original secure ROT actions, only streamline their buttons. */
.jc-labour-block .rot-card .rot-actions{
 display:flex!important;align-items:center!important;gap:6px!important;
 flex-wrap:wrap!important;margin:3px 0 0!important;padding:0!important;
 border:0!important;background:transparent!important;overflow:visible!important;min-height:0!important
}
.jc-labour-block .rot-card .rot-actions>form,
.jc-labour-block .rot-card .rot-actions>details{
 margin:0!important;min-height:0!important;max-width:100%!important
}
.jc-labour-block .rot-card .rot-actions .btn,
.jc-labour-block .rot-card .rot-actions summary.btn{
 padding:5px 9px!important;min-height:28px!important;
 border-radius:5px!important;line-height:1.3!important;
 font-size:10px!important;font-weight:650!important;
 white-space:nowrap!important;box-shadow:none!important
}
.jc-labour-block .rot-card .rot-actions .warning-btn{background:#fff2d8!important;border:1px solid #edca8b!important;color:#8a5512!important}
.jc-labour-block .rot-card .rot-actions .success-btn{background:#e8f8ef!important;border:1px solid #9dd6b5!important;color:#14704b!important}
.jc-labour-block .rot-card .rot-actions .btn:not(.warning-btn):not(.success-btn):not(.danger):not(.primary){
 background:#f5f9fd!important;border:1px solid #a8c5df!important;color:#225782!important
}
.jc-labour-block .rot-card .rot-actions details.action-pop[open]{
 position:relative!important;z-index:1200!important
}
.jc-labour-block .rot-card .rot-actions details.action-pop .pop-form{
 z-index:1201!important;max-width:min(410px,calc(100vw - 35px))!important
}
/* Admin time-correction advanced section remains accessible, not overlaid/hidden. */
.jc-labour-block .rot-card .am-v647-time-editor[open]{position:relative!important;z-index:80!important}
.jc-labour-block .rot-card .am-v647-time-editor[open]>div{
 max-height:none!important;overflow:visible!important
}
@media(max-width:720px){
 .jc-labour-block .am83-workspace .rot-card{padding:8px 8px!important}
 .jc-labour-block .rot-card .rot-main h3{font-size:12px!important}
 .jc-labour-block .rot-card .rot-main .muted{display:block!important;font-size:9px!important;margin-top:2px!important}
 .jc-labour-block .rot-card .rot-status-block{min-width:99px!important}
 .jc-labour-block .rot-card .rot-meta{font-size:9px!important}
 .jc-labour-block .rot-card .am-rot-live-head>strong{font-size:14px!important}
 .jc-labour-block .rot-card .am94-clock-foot .am-rot-live-details{flex:1 1 100%!important;order:3!important}
 .jc-labour-block .rot-card .am94-clock-foot [data-am-balance]{margin-left:auto!important}
}
@media(prefers-reduced-motion:reduce){
 .jc-labour-block .rot-card .am-rot-live-track i{transition:none!important}
}
</style>
<script>
(function(){
 'use strict';
 // Re-group the SAME live-clock elements into compact screenshot order.
 // Keep every [data-am-*] node and existing v639 second-by-second timer unchanged.
 function decorate(){
  document.querySelectorAll('.jc-labour-block .rot-card .am-rot-live').forEach(function(clock){
   if(clock.dataset.am94Styled==='1')return;
   var metrics=clock.querySelector('.am-rot-live-metrics'),
       details=clock.querySelector('.am-rot-live-details'),
       percent=clock.querySelector('[data-am-percent]'),
       balance=clock.querySelector('[data-am-balance]');
   if(!metrics||!details||!percent||!balance)return;
   var footer=document.createElement('div');
   footer.className='am94-clock-foot';
   // Snapshot DOM is moved, not copied, to preserve live timer bindings.
   footer.appendChild(percent);
   footer.appendChild(details);
   footer.appendChild(balance);
   metrics.replaceWith(footer);
   clock.dataset.am94Styled='1';
  });
 }
 var started=false;
 function init(){
  if(started)return;started=true;
  decorate();
  var list=document.querySelector('.jc-labour-block .rot-list');
  if(!list)return;
  new MutationObserver(function(records){
   if(records.some(function(x){return x.target===list && x.addedNodes.length>0;}))
     decorate();
  }).observe(list,{childList:true});
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});
 else init();
})();
</script>
'''
s+='\n'+cssjs
if s.count(marker)!=1 or s.count('data-am94Styled')<2:raise SystemExit('FIX94_OUTPUT_VALIDATION_FAILED')
if mode=='check':
 if os.environ.get('FIX94_PREVIEW_PATH'):
  Path(os.environ['FIX94_PREVIEW_PATH']).write_text(s,encoding='utf8',errors='surrogateescape')
  print('FIX94_CANDIDATE_WRITTEN_TEMP_ONLY')
 print('FIX94_PREFLIGHT_PASS_NO_WRITES');sys.exit(0)
fd,tmp=tempfile.mkstemp(prefix='.fix94_',dir=p.parent)
try:
 with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as fh:fh.write(s)
 os.chmod(tmp,p.stat().st_mode&0o777)
 os.replace(tmp,p)
finally:
 if os.path.exists(tmp):os.unlink(tmp)
print('FIX94_COMPACT_ROT_CARD_VIEW_ONLY_APPLIED')
