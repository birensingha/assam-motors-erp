#!/usr/bin/env python3
"""FIX92: ROT details and audited Delete/Reversal inside Edit Labour modal.
Guarded single-source STAGING view and route patch. No data writes at apply.
"""
from pathlib import Path
import sys,os,tempfile,hashlib
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
 raise SystemExit('Usage: apply.py check|apply ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file()
view=root/'resources/views/job-cards/show.blade.php'
routes=root/'routes/web.php'
ctl=root/'app/Http/Controllers/Web/AdminLabourLineAuditController.php'
payload=Path(__file__).resolve().parent/'AdminLabourLineAuditController.php'
if not payload.is_file():raise SystemExit('FIX92_CONTROLLER_PAYLOAD_MISSING')
s=view.read_text(errors='surrogateescape');r=routes.read_text(errors='surrogateescape')
sha=hashlib.sha256(view.read_bytes()).hexdigest();print('FIX92_SOURCE_SHA',sha)
marker='id="FIX92_LABOUR_MECHANICS_DELETE_DIALOG"'
if marker in s:
 if s.count(marker)==1 and "erp.job-cards.labour.delete-admin" in r and ctl.is_file():
  print('FIX92_ALREADY_APPLIED');sys.exit(0)
 raise SystemExit('FIX92_PARTIAL_PATCH_ABORT')
expected='890e190ee2b2d7d996766ee6d2b78d5c69f9b964e298f53eb349af346e600c32'
if sha!=expected:raise SystemExit('FIX92_STAGING_SOURCE_CHANGED_ABORT')
if ctl.exists():raise SystemExit('FIX92_CONTROLLER_ALREADY_EXISTS_ABORT')
def single(original,revised,reason):
 global s
 n=s.count(original);print('FIX92_VIEW_ANCHOR',reason,n)
 if n!=1:raise SystemExit('FIX92_VIEW_ANCHOR_ERROR_'+reason)
 s=s.replace(original,revised,1)
anchor='data-am90-can-edit="{{ $am90CanEdit ? \'1\' : \'0\' }}">'
extra=r'''data-am90-can-edit="{{ $am90CanEdit ? '1' : '0' }}"
      data-am92-mechanic="{{ $rot->staff_name ?? ($line->mechanic_name ?: 'Not assigned') }}"
      data-am92-staff-code="{{ $rot->staff_code ?? '—' }}"
      data-am92-rot-status="{{ $rot->status ?? 'Unassigned' }}"
      data-am92-execution="{{ $line->execution_status ?? 'Planned' }}"
      data-am92-session-id="{{ $rot->id ?? '—' }}"
      data-am92-std-hours="{{ number_format((float)($line->standard_hours??0),2) }}"
      data-am92-first-start="{{ $rot && $rot->first_start_at ? $rot->first_start_at->format('d-m-Y h:i A') : '—' }}"
      data-am92-end-time="{{ $rot && $rot->end_time ? $rot->end_time->format('d-m-Y h:i A') : '—' }}"
      data-am92-rot-start="{{ $line->rot_start ?: '—' }}"
      data-am92-rot-end="{{ $line->rot_end ?: '—' }}"
      data-am92-productive="{{ $rot ? $fmtSeconds($rot->total_seconds) : '00:00:00' }}"
      data-am92-updated="{{ $rot && $rot->updated_at ? $rot->updated_at->format('d-m-Y h:i A') : '—' }}"
      data-am92-source="{{ $line->source_estimate_item_id ? 'Approved Estimate' : 'Direct / Additional' }}"
      data-am92-locked="{{ $rot || !empty($line->rot_session_id) || !empty($line->mechanic_id) || !empty($line->rot_start) || !empty($line->rot_end) || !empty($line->source_estimate_item_id) || (int)($line->productive_seconds??0)>0 || !in_array(strtolower(trim((string)($line->execution_status??'planned'))),['','planned','pending','not_started','not started'],true) ? '1' : '0' }}"
      data-am92-history-url="{{ $rot ? route('erp.rot.history',$rot->id) : '' }}"
      data-am92-delete-url="{{ route('erp.job-cards.labour.delete-admin',[$job->id,$line->id]) }}"
      data-am92-review-url="{{ route('erp.job-cards.labour.review-admin',[$job->id,$line->id]) }}">'''
single(anchor,extra,'rot_card_data_add')
addon=r'''
{{-- FIX92: mechanical record and audited Labour deletion or reversal request in Edit. --}}
<style id="FIX92_LABOUR_MECHANICS_DELETE_DIALOG">
.am90-dialog .am92-details{
  border:1px solid #bed1e5!important;border-radius:8px!important;overflow:hidden!important;
  margin:7px 0 13px!important;background:#f8fbff!important
}
.am90-dialog .am92-detail-head{display:flex;align-items:center;justify-content:space-between;
  gap:8px;flex-wrap:wrap;background:#e9f3ff;padding:8px 10px;
  border-bottom:1px solid #c9def3;color:#184674;font-weight:800;font-size:12px}
.am90-dialog .am92-detail-head a{font-size:10px;color:#075dad;text-decoration:underline}
.am90-dialog .am92-detail-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:0}
.am90-dialog .am92-detail-cell{padding:8px 10px;border-right:1px solid #e1eaf5;border-bottom:1px solid #e1eaf5;min-width:0}
.am90-dialog .am92-detail-cell small{display:block;color:#617890;font-size:9px;margin-bottom:4px}
.am90-dialog .am92-detail-cell strong{color:#24415d;font-size:11px;font-weight:760;overflow-wrap:anywhere}
.am90-dialog .am92-audit-section{border:1px solid #dfc7b9;background:#fffdfa;
 border-radius:8px!important;margin-top:14px;padding:11px 12px}
.am90-dialog .am92-audit-section label{display:block!important;font-size:11px!important;
 font-weight:800!important;color:#7b4b26;margin-bottom:6px}
.am90-dialog .am92-audit-section textarea{width:100%!important;box-sizing:border-box!important;
 min-height:62px!important;max-height:160px!important;resize:vertical!important;
 padding:8px!important;border-radius:6px!important;border:1px solid #c7ab97!important;
 background:#fff!important;color:#3f5368!important;font-size:11px!important}
.am90-dialog .am92-audit-hint{font-size:10px!important;color:#805f44!important;margin:6px 0}
.am90-dialog .am92-audit-actions{display:flex;gap:8px!important;justify-content:flex-end;
 align-items:center!important;flex-wrap:wrap!important;margin-top:9px}
.am90-dialog button.am92-delete{background:#fff0f0!important;border:1px solid #e9aaaa!important;
 border-radius:6px!important;color:#aa2929!important;font-weight:800!important;font-size:11px!important;
 padding:8px 11px!important;min-height:34px!important;cursor:pointer!important}
.am90-dialog button.am92-review{background:#fff3dc!important;border:1px solid #d9ad60!important;
 border-radius:6px!important;color:#82540b!important;font-weight:800!important;font-size:11px!important;
 padding:8px 11px!important;min-height:34px!important;cursor:pointer!important}
.am90-dialog button.am92-delete:disabled,.am90-dialog button.am92-review:disabled{
 opacity:.45!important;cursor:not-allowed!important}
.am90-dialog .am92-result{font-size:11px;font-weight:700;color:#b02f2f;margin-top:7px}
@media(max-width:750px){.am90-dialog .am92-detail-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
<script>
(function(){
'use strict';
function init(){
 var body=document.getElementById('am90-table-body');
 var list=document.querySelector('.jc-labour-block .rot-list');
 if(!body||!list)return;
 var csrfInput=document.querySelector('#am85-part-form input[name=_token]');
 var csrf=csrfInput?csrfInput.value:(document.querySelector('meta[name=csrf-token]')||{}).content;
 function make(tag,classname,text){
  var x=document.createElement(tag);if(classname)x.className=classname;
  if(text!==undefined)x.textContent=text;return x;
 }
 function addField(parent,label,value){
  var cell=make('div','am92-detail-cell');
  cell.appendChild(make('small',null,label));
  cell.appendChild(make('strong',null,value||'—'));
  parent.appendChild(cell);
 }
 function showError(container,msg){container.textContent=msg;container.setAttribute('role','alert');}
 function toast(msg){
  var old=document.getElementById('am92-status');if(old)old.remove();
  var x=make('div','am87-toast',msg);x.id='am92-status';x.setAttribute('role','status');document.body.appendChild(x);
 }
 async function send(url,info,after,output,buttons){
  buttons.forEach(function(b){b.disabled=true;});
  output.textContent='';
  try{
   var data=new FormData();data.append('_token',csrf||'');
   Object.keys(info).forEach(function(k){data.append(k,info[k]);});
   var res=await fetch(url,{method:'POST',body:data,credentials:'same-origin',cache:'no-store',
     redirect:'follow',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
   if(!res.ok){
     var details='';
     var json=await res.json().catch(function(){return null;});
     if(json&&json.errors)details=Object.values(json.errors).flat().join('; ');
     throw new Error(details||json&&json.message||'Server rejected the request (HTTP '+res.status+').');
   }
   if(new URL(res.url,location.href).origin!==location.origin)
     throw new Error('Unexpected redirect location.');
   var html=await res.text();
   var doc=new DOMParser().parseFromString(html,'text/html');
   var updated=doc.querySelector('.jc-labour-block .rot-list');
   if(!updated)throw new Error('Server confirmation missing. Refresh Job Card before retrying.');
   // The audit action completed; replace only the Labour rows, no full page refresh.
   list.replaceChildren.apply(list,Array.from(updated.children).map(function(child){
      return document.importNode(child,true);
   }));
   var from=doc.querySelector('#am90-table-body'),to=document.getElementById('am90-table-body');
   if(from&&to){to.replaceChildren.apply(to,Array.from(from.children).map(function(child){return document.importNode(child,true);}));}
   var dialog=document.querySelector('dialog.am90-dialog');
   if(dialog&&dialog.open)dialog.close();
   toast(after);
  }catch(err){showError(output,(err&&err.message?err.message:'Change not confirmed')+
             ' Check audit and current Labour row before retry.');}
  finally{buttons.forEach(function(b){if(b.isConnected)b.disabled=false;});}
 }
 function enhance(id){
  var dialog=document.querySelector('dialog.am90-dialog[open]');
  var card=Array.from(list.querySelectorAll('.rot-card[data-am90-labour-id]'))
    .find(function(x){return x.dataset.am90LabourId===String(id);});
  if(!dialog||!card||dialog.querySelector('.am92-details'))return;
  var box=dialog.querySelector('.am87-dialog-content'),form=box&&box.querySelector('form.am82-finance-form');
  if(!box||!form)return;
  var d=card.dataset,details=make('section','am92-details');
  var heading=make('div','am92-detail-head');
  heading.appendChild(make('span',null,'Mechanic / ROT Details & Timing'));
  if(d.am92HistoryUrl){
   var link=make('a',null,'Full ROT History ↗');link.href=d.am92HistoryUrl;
   link.target='_blank';link.rel='noopener noreferrer';heading.appendChild(link);
  }
  details.appendChild(heading);
  var grid=make('div','am92-detail-grid');
  [
   ['ROT Code',d.am90Rot],['Labour Item',d.am90Description],
   ['Mechanic',d.am92Mechanic],['Staff Code',d.am92StaffCode],
   ['ROT Session',d.am92SessionId],['Work Status',d.am92RotStatus],
   ['Execution',d.am92Execution],['Source',d.am92Source],
   ['First Start',d.am92FirstStart],['End / Complete',d.am92EndTime],
   ['Actual ROT Start',d.am92RotStart],['Actual ROT End',d.am92RotEnd],
   ['Productive Time',d.am92Productive],['Standard Hours',d.am92StdHours],
   ['Last Updated',d.am92Updated],['Labour Line ID',String(id)]
  ].forEach(function(pair){addField(grid,pair[0],pair[1]);});
  details.appendChild(grid);
  box.insertBefore(details,form);
  // One reason is used for Edit Billing and Delete/Reversal: keep existing
  // Finance correction reason and mirror its current value into audit action.
  var section=make('section','am92-audit-section');
  var reasonInput=form.querySelector('[name=reason]');
  var label=make('label',null,'Delete / Reversal Reason (required for audit)');
  var reason=make('textarea');reason.required=true;reason.minLength=12;reason.maxLength=500;
  reason.placeholder='Explain why this Labour/ROT must be deleted or reversed (12+ characters)';
  reason.value=reasonInput?reasonInput.value:'';
  section.appendChild(label);section.appendChild(reason);
  var locked=d.am92Locked==='1';
  section.appendChild(make('p','am92-audit-hint',locked?
   'This ROT is assigned, executed or estimate-linked. Its timing and mechanic history cannot be erased. Submit an audited reversal request instead.':
   'Unstarted direct Labour can be deleted by Admin after reason and tax-total verification. This action cannot be undone.'));
  var error=make('div','am92-result');
  var actions=make('div','am92-audit-actions');
  var del=make('button','am92-delete','Delete Labour');
  del.type='button';del.disabled=locked;
  del.title=locked?'Assigned/started ROT: request reversal instead':'Admin-only delete unstarted Labour';
  var review=make('button','am92-review','Request ROT Reversal');
  review.type='button';
  actions.appendChild(del);actions.appendChild(review);
  section.appendChild(error);section.appendChild(actions);
  box.appendChild(section);
  if(reasonInput){
   reason.addEventListener('input',function(){reasonInput.value=reason.value;});
   reasonInput.addEventListener('input',function(){reason.value=reasonInput.value;});
  }
  function auditReason(){
   var value=reason.value.trim()||(reasonInput?reasonInput.value.trim():'');
   if(value.length<12||value.length>500){
    showError(error,'Write an audit reason of 12–500 characters first.');reason.focus();return null;
   }
   return value;
  }
  del.addEventListener('click',function(){
   var value=auditReason();if(value===null)return;
   if(!window.confirm('Permanently delete this UNSTARTED Labour line? Admin audit will be recorded.'))return;
   var expected=form.querySelector('[name=expected_old_total]');
   send(d.am92DeleteUrl,{_method:'DELETE',reason:value,expected_old_total:expected?expected.value:''},
     'Unstarted Labour deleted with Admin audit.',error,[del,review]);
  });
  review.addEventListener('click',function(){
   var value=auditReason();if(value===null)return;
   if(!window.confirm('Request audited ROT reversal? No Labour or time will be deleted until reviewed.'))return;
   send(d.am92ReviewUrl,{reason:value},
       'Admin ROT/Estimate reversal review requested; original Labour remains unchanged.',error,[del,review]);
  });
 }
 // FIX90's existing body listener creates modal synchronously. Our later listener
 // enriches that same dialog; it does NOT replace the proven Finance editor.
 body.addEventListener('click',function(e){
   var button=e.target.closest('button[data-am90-edit]');
   if(button)enhance(button.dataset.am90Edit);
 });
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});
else init();
})();
</script>
'''
s+='\n'+addon
anchor='    // FIX91 Strict Admin-only direct OSL billing; supplier purchase is separate.'
add_routes="""    // FIX92 strict Admin-only Labour delete/reversal; preserve ROT work history.
    \\Illuminate\\Support\\Facades\\Route::delete('/job-cards/{job}/labour/{line}/admin-delete', [\\App\\Http\\Controllers\\Web\\AdminLabourLineAuditController::class,'delete'])->whereNumber('job')->whereNumber('line')->name('erp.job-cards.labour.delete-admin');
    \\Illuminate\\Support\\Facades\\Route::post('/job-cards/{job}/labour/{line}/admin-review', [\\App\\Http\\Controllers\\Web\\AdminLabourLineAuditController::class,'requestReview'])->whereNumber('job')->whereNumber('line')->name('erp.job-cards.labour.review-admin');
"""
if r.count(anchor)!=1:raise SystemExit('FIX92_ROUTE_ANCHOR_MISMATCH')
r=r.replace(anchor,add_routes+anchor,1)
for chk in ['id="FIX91_UNIFIED_OSL_ADMIN"','id="FIX90_LABOUR_EXCEL_BILLING_GRID"',
 'id="FIX92_LABOUR_MECHANICS_DELETE_DIALOG"','data-am92-mechanic',
 "route('erp.fix48.job-line',[$job->id,'labour',$line->id])",
 'OSL Jobs / Outside Services']:
 if chk not in s:raise SystemExit('FIX92_CRITICAL_VIEW_MISSING '+chk)
if s.count(marker)!=1:raise SystemExit('FIX92_BAD_PATCH_MARKER')
if mode=='check':
 print('FIX92_DELETE_MODAL_PREFLIGHT_PASS_NO_WRITES');sys.exit(0)
def atomic(p,new):
 fd,tmp=tempfile.mkstemp(prefix='.fix92_',dir=p.parent)
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as h:h.write(new)
  os.chmod(tmp,(p.stat().st_mode & 0o777) if p.exists() else 0o644)
  os.replace(tmp,p)
 finally:
  if os.path.exists(tmp):os.unlink(tmp)
atomic(ctl,payload.read_text())
atomic(routes,r)
atomic(view,s)
print('FIX92_LABOUR_MECHANICS_ADMIN_DELETE_UI_APPLIED_NO_DB_WRITES')
