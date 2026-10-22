#!/usr/bin/env python3
"""FIX91 STAGING: OSL rows in unified Labour grid; Admin-only edit/delete.
Fixes actual route + guard, no DB migration, no stock/estimate changes.
"""
from pathlib import Path
import hashlib,sys,os,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
 raise SystemExit('Usage: apply.py check|apply ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file()
view=root/'resources/views/job-cards/show.blade.php'
routes=root/'routes/web.php'
financial=root/'app/Http/Controllers/Web/AdminWorkshopControlPageController.php'
controller=root/'app/Http/Controllers/Web/AdminJobCardOslLineController.php'
payload=Path(__file__).resolve().parent/'AdminJobCardOslLineController.php'
assert payload.is_file()
sources={p:p.read_text(errors='surrogateescape') for p in [view,routes,financial]}
s=sources[view];r=sources[routes];f=sources[financial]
print('FIX91_VIEW_SHA',hashlib.sha256(view.read_bytes()).hexdigest())
marker='id="FIX91_UNIFIED_OSL_ADMIN"'
if marker in s:
 if s.count(marker)==1 and 'erp.job-cards.osl.admin-save' in r and 'FIX91_ADMIN_ONLY_FINANCE' in f:
  print('FIX91_ALREADY_APPLIED');sys.exit(0)
 raise SystemExit('FIX91_INCOMPLETE_ABORT')
expected='ae36b72866c294d6f23773570a47c56839d41b5e828a90e5595f47dbc5b87484'
if hashlib.sha256(view.read_bytes()).hexdigest()!=expected:
 raise SystemExit('FIX91_CONCURRENT_VIEW_CHANGE_ABORT')
def one(a,b,label):
 global s
 n=s.count(a);print('FIX91_VIEW_ANCHOR',label,n)
 if n!=1: raise SystemExit('FIX91_ANCHOR_MISMATCH_'+label)
 s=s.replace(a,b,1)
# Derive authorization from server-authenticated ERP role; never trust browser flags.
one('<div class="card section-gap jc-purchase-block jc-labour-block">',
    '''<div class="card section-gap jc-purchase-block jc-labour-block">
  @php($am91Admin=strtolower(trim((string)(auth()->user()->role??'')))==='admin')
  @php($am91CanWrite=in_array(strtolower(trim((string)$job->status)),['wip','in progress','in-progress','work in progress'],true) && empty($job->invoice_id))
''','role_before_entry')
one('    @php($am90CanEdit=in_array(strtolower(trim((string)$job->status)),[\'wip\',\'in progress\',\'in-progress\',\'work in progress\'],true) && empty($job->invoice_id))',
    '    @php($am90CanEdit=$am91CanWrite && $am91Admin)','admin_gate_table')
one('  <p class="muted">Approved Estimate se transferred ROTs niche dikhenge. Extra ROT ke liye yahi search use karein; Add ke baad isi row mein mechanic assign aur work Start/Pause/Complete karein.</p>',
    '  <p class="muted">Approved Estimate se transferred ROTs niche hain. Extra ROT aur OSL isi workspace se add karein.</p>',
    'shorten_entry_note')
one('        <div class="actions"><button class="btn primary">＋ Add Labour / ROT</button></div>',
    '''        <div class="actions am91-add-actions">
           <button class="btn primary">＋ Add Labour / ROT</button>
           @if($am91Admin)
           <button type="button" class="btn am91-osl-add" id="am91-add-osl">+ Add OSL</button>
           @else
           <button type="button" class="btn am91-osl-add" id="am91-need-admin">+ Add OSL · Admin Required</button>
           @endif
        </div>''','osl_button_same_row')
one('<div class="am90-billing-head"><strong>Labour Billing Register</strong><small>Std Hrs locked · Edit requires Correction Reason</small></div>',
    '<div class="am90-billing-head"><strong>Labour + OSL Billing Register</strong><small>Billing changes &amp; OSL deletion require Admin authorization and reason</small></div>',
    'billing_header')
# Table rows need OSL including both direct billing and estimate OSL.
one('          @forelse($am83RotLabour as $line)',
    '''          @forelse($labour as $line)
            @php($am91IsOsl=strtoupper(trim((string)($line->mechanic_name??'')))==='OSL' || strtoupper(trim((string)($line->rot_code??'')))==='OSL')''',
    'all_billing_lines')
one('            <tr data-am90-row="{{ $line->id }}">',
    '''            <tr data-am90-row="{{ $line->id }}" @if($am91IsOsl) data-am91-osl-id="{{ $line->id }}"
              data-am91-description="{{ $line->description?:'Outside Service' }}"
              data-am91-qty="{{ number_format((float)($line->standard_hours??1),2,'.','') }}"
              data-am91-rate="{{ number_format((float)($line->labour_rate??0),2,'.','') }}"
              data-am91-discount="{{ number_format((float)($line->discount_percent??0),2,'.','') }}"
              data-am91-gst="{{ number_format((float)($line->gst_percent??0),2,'.','') }}"
              data-am91-total="{{ number_format((float)($line->total??0),2,'.','') }}"
              data-am91-estimate="{{ !empty($line->source_estimate_item_id)?'1':'0' }}"
              @endif>''',
    'osl_dataset_rows')
one('<td class="am90-labour-name">{{ $line->description ?: \'Labour\' }}</td>',
    '<td class="am90-labour-name">@if($am91IsOsl)<span class="am91-osl-tag">OSL</span>@endif {{ $line->description ?: \'Labour\' }}</td>',
    'osl_label')
one('''               @if($am90CanEdit)<button class="am90-edit" type="button" data-am90-edit="{{ $line->id }}">Edit</button>
               @else <span class="am90-locked" title="Edit allowed during Work In Progress without an active invoice">Locked</span>@endif''',
    '''               @if($am91CanWrite && !$am91Admin)
                 <button class="am91-permission" type="button">Admin Permission</button>
               @elseif($am90CanEdit && $am91IsOsl)
                 <button class="am90-edit" type="button" data-am91-osl-edit="{{ $line->id }}">Edit</button>
               @elseif($am90CanEdit)
                 <button class="am90-edit" type="button" data-am90-edit="{{ $line->id }}">Edit</button>
               @else <span class="am90-locked" title="Locked outside WIP or with active invoice">Locked</span>@endif''',
    'row_edit_permission')
# Existing operational ROT panel remains; only remove duplicate OSL billed/planned UI
# from the separate purchase panel (supplier purchase table itself is unchanged).
one('      @if($am83OslLabour->isNotEmpty())\n        <div class="am83-osl-jc-lines">',
    '      @if(false && $am83OslLabour->isNotEmpty())\n        <div class="am83-osl-jc-lines">',
    'avoid_duplicate_osl_billing_display')
# FIX90 JS rebuilding ROT rows after AJAX must preserve OSL rows.
one("    body.replaceChildren();\n    if(!cards.length){",
    "    var oslRows=Array.from(body.querySelectorAll('tr[data-am91-osl-id]'));\n    body.replaceChildren();\n    if(!cards.length){\n      if(oslRows.length){oslRows.forEach(function(row){body.appendChild(row);});return;}",
    'preserve_osl_ajax')
one('      row.appendChild(action);body.appendChild(row);\n    });\n  }',
    '''      row.appendChild(action);body.appendChild(row);
    });
    oslRows.forEach(function(row){body.appendChild(row);});
    // Adjust display serial numbers in the merged table after scoped AJAX.
    Array.from(body.querySelectorAll('tr:not(.am90-empty-row)')).forEach(function(row,i){
      if(row.cells[0])row.cells[0].textContent=String(i+1);
    });
  }''',
    'sync_merged_labour_osl')
# AJAX replacement runs in FIX87; refetch of normal ROT edits should not lose OSL.
ui=r'''
{{-- FIX91: OSL rows in Labour Register, plus strict Admin permission feedback. --}}
<style id="FIX91_UNIFIED_OSL_ADMIN">
.am91-add-actions{display:flex!important;align-items:center!important;justify-content:flex-end!important;
 gap:7px!important;flex-wrap:wrap!important}
.am91-osl-add{background:#f59e0b!important;color:#422509!important;border:1px solid #d28706!important;
 font-size:11px!important;min-height:33px!important;cursor:pointer!important;font-weight:800!important}
.jc-labour-block .am91-osl-tag{background:#ffedd5;color:#904900;font-size:9px;font-weight:900;
 padding:2px 4px;border-radius:4px;margin-right:4px;white-space:nowrap}
.jc-labour-block .am91-permission{border:1px solid #d4a455!important;background:#fff8e7!important;
 color:#79510b!important;font-size:9px!important;border-radius:4px!important;
 padding:4px 3px!important;cursor:pointer!important}
dialog.am91-osl-dialog{position:fixed!important;inset:0!important;margin:auto!important;
 box-sizing:border-box!important;width:min(820px,calc(100vw - 20px))!important;
 max-height:calc(100vh - 30px)!important;overflow:auto!important;
 background:white!important;border:1px solid #9fb9d8!important;border-radius:11px!important;
 box-shadow:0 15px 55px #132d5166!important;padding:0!important;z-index:2147483000!important}
dialog.am91-osl-dialog::backdrop{background:rgba(13,39,67,.60)}
.am91-dialog-head{padding:12px 15px;background:#f2f7ff;border-bottom:1px solid #bed3e8;
 display:flex;justify-content:space-between;align-items:center;color:#173d68;font-size:14px}
.am91-dialog-head button{background:white;border:1px solid #bbcada;border-radius:5px;font-size:18px;
 cursor:pointer;width:33px;height:31px}
.am91-osl-inner{padding:15px}
.am91-note{font-size:11px;color:#5c758e;margin:0 0 10px}
.am91-osl-form{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px!important}
.am91-osl-form label{font-size:11px!important;font-weight:750!important;color:#2b5279!important;
 display:grid;gap:4px}
.am91-osl-form label.wide{grid-column:span 3}
.am91-osl-form input,.am91-osl-form textarea{box-sizing:border-box;width:100%!important;
 min-height:32px!important;padding:5px 7px!important;border:1px solid #aac4df!important;
 border-radius:5px!important;background:white!important;color:#183d61!important;font-size:12px!important}
.am91-osl-form textarea{min-height:58px!important}
.am91-form-actions{grid-column:span 3;display:flex;justify-content:space-between;align-items:center;
 gap:8px;flex-wrap:wrap}
.am91-form-actions button{min-height:33px;padding:6px 12px;border:0;border-radius:6px;cursor:pointer;
 background:#0d3762;color:white;font-size:11px;font-weight:800}
.am91-form-actions button.am91-delete{background:#b42323}
.am91-form-actions button:disabled{opacity:.5;cursor:wait}
.am91-osl-error{color:#a12424;font-size:11px;margin:8px 0;font-weight:650}
@media(max-width:600px){.am91-osl-form{grid-template-columns:repeat(2,minmax(0,1fr))}
.am91-osl-form label.wide,.am91-form-actions{grid-column:span 2}}
</style>
<script>
(function(){
'use strict';
function init(){
 var btn=document.getElementById('am91-add-osl');
 var body=document.getElementById('am90-table-body');
 if(!body)return;
 var baseUrl=@json(route('erp.job-cards.osl.admin-save',$job->id));
 var csrf=@json(csrf_token());
 var deleteTemplate=@json(route('erp.job-cards.osl.admin-delete',[$job->id,999999]));
 var isAdmin=@json($am91Admin);
 var modal=null,busy=false;
 function message(text){
   var old=document.getElementById('am91-msg');if(old)old.remove();
   var panel=document.createElement('div');panel.id='am91-msg';
   panel.className='am87-toast';panel.textContent=text;
   document.body.appendChild(panel);
 }
 function close(){if(modal){var d=modal;modal=null;if(d.open)d.close();d.remove();}}
 function makeInput(name,title,value,type,wide,required){
   var label=document.createElement('label');if(wide)label.className='wide';
   label.textContent=title;var field;
   if(type==='textarea')field=document.createElement('textarea');
   else {field=document.createElement('input');field.type=type||'text';}
   field.name=name;field.value=value===undefined?'':value;
   if(type==='number'){field.min='0';field.step='0.01';}
   if(required)field.required=true;
   label.appendChild(field);return label;
 }
 function show(row){
   if(!isAdmin){message('Admin permission required. Please request an authorized Admin to perform this change.');return;}
   close();var editing=Boolean(row),id=editing?row.dataset.am91OslId:null;
   var d=document.createElement('dialog');d.className='am91-osl-dialog';
   var head=document.createElement('div');head.className='am91-dialog-head';
   var title=document.createElement('strong');title.textContent=editing?'Edit OSL Billing':'Add OSL Billing';
   var x=document.createElement('button');x.type='button';x.textContent='×';
   x.setAttribute('aria-label','Close OSL Editor');x.addEventListener('click',close);
   head.appendChild(title);head.appendChild(x);
   var inner=document.createElement('div');inner.className='am91-osl-inner';
   var note=document.createElement('p');note.className='am91-note';
   note.textContent='Admin-only • Customer OSL billing only. Supplier OSL Purchase is recorded separately.';
   inner.appendChild(note);
   var form=document.createElement('form');form.className='am91-osl-form';
   form.method='post';form.action=baseUrl+(editing?'/'+id:'');
   var token=document.createElement('input');token.type='hidden';token.name='_token';token.value=csrf;form.appendChild(token);
   form.appendChild(makeInput('description','OSL Service',editing?row.dataset.am91Description:'','text',true,true));
   form.appendChild(makeInput('quantity','Qty / Std Units',editing?row.dataset.am91Qty:'1','number',false,true));
   form.appendChild(makeInput('rate','Rate ₹',editing?row.dataset.am91Rate:'','number',false,true));
   form.appendChild(makeInput('discount_percent','Discount %',editing?row.dataset.am91Discount:'0','number',false,true));
   form.appendChild(makeInput('gst_percent','GST %',editing?row.dataset.am91Gst:'18','number',false,true));
   form.appendChild(makeInput('note','Work Note','','text',false,false));
   form.appendChild(makeInput('reason','Correction / Approval Reason',editing?'':'','textarea',true,editing));
   if(editing){
     var original=document.createElement('input');original.type='hidden';original.name='expected_old_total';
     original.value=row.dataset.am91Total;form.appendChild(original);
   }
   var errors=document.createElement('div');errors.className='am91-osl-error';errors.style.gridColumn='1/-1';
   errors.setAttribute('role','alert');
   var actions=document.createElement('div');actions.className='am91-form-actions';
   var remove=document.createElement('button');remove.type='button';remove.className='am91-delete';
   remove.textContent='Delete OSL';remove.hidden=!editing||row.dataset.am91Estimate==='1';
   var save=document.createElement('button');save.type='submit';save.textContent=editing?'Save OSL Changes':'Add OSL';
   actions.appendChild(remove);actions.appendChild(save);
   form.appendChild(errors);form.appendChild(actions);inner.appendChild(form);
   d.appendChild(head);d.appendChild(inner);document.body.appendChild(d);modal=d;
   d.addEventListener('cancel',function(e){e.preventDefault();close();});
   d.addEventListener('click',function(e){if(e.target===d)close();});
   function clear(){errors.textContent='';}
   async function send(url,bodyData,isDelete){
     if(busy)return;busy=true;save.disabled=true;remove.disabled=true;clear();
     try{
       var res=await fetch(url,{method:'POST',body:bodyData,credentials:'same-origin',
           headers:{'Accept':'text/html'},redirect:'follow',cache:'no-store'});
       if(!res.ok)throw new Error('Server refused: HTTP '+res.status);
       if(new URL(res.url,location.href).origin!==location.origin)throw new Error('Unexpected redirect.');
       var doc=new DOMParser().parseFromString(await res.text(),'text/html');
       var fresh=doc.querySelector('#am90-table-body');
       if(!fresh)throw new Error('Server did not return the Job Card. Check login and permission.');
       var fault=doc.querySelector('.alert-danger,.alert.error,.validation-errors,.invalid-feedback');
       if(fault&&fault.textContent.trim())throw new Error(fault.textContent.trim().slice(0,250));
       var prior=body.querySelectorAll('tr[data-am91-osl-id]').length;
       var after=fresh.querySelectorAll('tr[data-am91-osl-id]').length;
       if((!editing&&!isDelete&&after<=prior)||(isDelete&&after>=prior))
          throw new Error('OSL change could not be confirmed. Check existing row before retry.');
       body.replaceChildren.apply(body,Array.from(fresh.children).map(function(node){return document.importNode(node,true);}));
       var old=document.querySelector('.jc-labour-block .am83-workflow-strip span');
       var updated=doc.querySelector('.jc-labour-block .am83-workflow-strip span');
       if(old&&updated)old.textContent=updated.textContent;
       close();message('OSL billing '+(isDelete?'deleted':'saved')+' with Admin audit.');
     }catch(ex){errors.textContent=(ex&&ex.message?ex.message:'OSL operation not confirmed')+
          ' No retry until the row has been checked.';}
     finally{busy=false;save.disabled=false;remove.disabled=false;}
   }
   form.addEventListener('submit',function(e){e.preventDefault();
      if(!form.reportValidity())return;
      var vals=new FormData(form);
      if(Number(vals.get('discount_percent'))>100||Number(vals.get('gst_percent'))>100||
         Number(vals.get('quantity'))<=0){errors.textContent='Check quantity, discount and GST percentages.';return;}
      send(form.action,vals,false);
   });
   remove.addEventListener('click',function(){
      var reason=form.elements.namedItem('reason');
      if(!reason||reason.value.trim().length<5){errors.textContent='Write a deletion reason of at least five characters.';reason&&reason.focus();return;}
      if(!window.confirm('Delete this DIRECT OSL billing line? Admin audit and safeguards will apply.'))return;
      var fd=new FormData();fd.append('_token',csrf);fd.append('_method','DELETE');fd.append('reason',reason.value);
      send(deleteTemplate.replace('999999',String(id)),fd,true);
   });
   d.showModal();
 }
 if(btn)btn.addEventListener('click',function(){show(null);});
 body.addEventListener('click',function(e){
   var b=e.target.closest('[data-am91-osl-edit]');
   if(b){var row=b.closest('tr[data-am91-osl-id]');if(row)show(row);}
   var permission=e.target.closest('.am91-permission');
   if(permission)message('Admin permission required for Edit/Delete. Please contact an ERP Admin.');
 });
 var none=document.getElementById('am91-need-admin');
 if(none)none.addEventListener('click',function(){message('Only an authorized ERP Admin can add OSL billing.');});
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});
else init();
})();
</script>
'''
# Blade @json in JS: validated in Laravel compile/render, browser JS extraction is
# tested by replacing Blade bindings with quoted JS placeholders during node --check.
s+='\n'+ui
# Keep all existing endpoint guards + legacy OSL purchase panel.
for required in ['id="FIX90_LABOUR_EXCEL_BILLING_GRID"','id="FIX91_UNIFIED_OSL_ADMIN"',
 'data-am91-osl-id','OSL Jobs / Outside Services',
 "route('erp.fix48.job-line',[$job->id,'labour',$line->id])"]:
 if required not in s:raise SystemExit('FIX91_REQUIRED_VIEW_MISSING_'+required)
# New routes within preexisting ERP auth + Admin middleware group.
anchor="    // FIX48_WORKSHOP_DIRECT_CONTROLS — inside the existing protected ERP Admin route group."
routes_add="""    // FIX91 Strict Admin-only direct OSL billing; supplier purchase is separate.
    \\Illuminate\\Support\\Facades\\Route::post('/job-cards/{job}/osl-billing', [\\App\\Http\\Controllers\\Web\\AdminJobCardOslLineController::class,'save'])->whereNumber('job')->name('erp.job-cards.osl.admin-save');
    \\Illuminate\\Support\\Facades\\Route::post('/job-cards/{job}/osl-billing/{line}', [\\App\\Http\\Controllers\\Web\\AdminJobCardOslLineController::class,'save'])->whereNumber('job')->whereNumber('line')->name('erp.job-cards.osl.admin-update');
    \\Illuminate\\Support\\Facades\\Route::delete('/job-cards/{job}/osl-billing/{line}', [\\App\\Http\\Controllers\\Web\\AdminJobCardOslLineController::class,'delete'])->whereNumber('job')->whereNumber('line')->name('erp.job-cards.osl.admin-delete');
"""
if r.count(anchor)!=1:raise SystemExit('FIX91_ROUTE_ANCHOR_MISSING')
r=r.replace(anchor,routes_add+anchor,1)
# Tighten ONLY Labour billing to Admin; existing parts edit roles remain untouched.
fn="public function updateJobLine(Request $request, int $job, string $kind, int $line): RedirectResponse\n    {\n        $user = $this->admin($request);"
guard="public function updateJobLine(Request $request, int $job, string $kind, int $line): RedirectResponse\n    {\n        $user = $this->admin($request);\n        // FIX91_ADMIN_ONLY_FINANCE: Labour rate/discount/GST edits need actual Admin role.\n        if($kind==='labour') abort_unless(strtolower(trim((string)$user->role))==='admin',403,'Admin permission required for Labour edit/delete.');"
if f.count(fn)!=1:raise SystemExit('FIX91_FINANCE_CONTROLLER_ANCHOR_MISSING')
f=f.replace(fn,guard,1)
if controller.exists():raise SystemExit('FIX91_CONTROLLER_EXISTS_CONCURRENT_CHANGE')
if mode=='check':
 print('FIX91_OSL_ADMIN_PREFLIGHT_PASS_NO_WRITES')
 sys.exit(0)
def atomic(p,content):
 fd,tmp=tempfile.mkstemp(dir=p.parent,prefix='.fix91_')
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as fh:fh.write(content)
  if p.exists():os.chmod(tmp,p.stat().st_mode&0o777)
  else:os.chmod(tmp,0o644)
  os.replace(tmp,p)
 finally:
  if os.path.exists(tmp):os.unlink(tmp)
# Dispatcher supplies backups + rollback; only write after full preflight.
atomic(controller,payload.read_text())
atomic(routes,r)
atomic(financial,f)
atomic(view,s)
print('FIX91_ADMIN_GUARD_OSL_ROUTES_UNIFIED_TABLE_APPLIED')
