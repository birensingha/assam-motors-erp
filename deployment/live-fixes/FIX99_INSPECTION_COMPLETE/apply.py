#!/usr/bin/env python3
"""FIX99 STAGING inspection validation and silent-submit UX.

- All 30 official points required, existing access/assignment/JC guards retained
- Fair/Poor observations required server side with actionable 422 error
- Staff web form highlights first incomplete item and shows completion error
- No DB migrations, no inspection submissions/record writes from patch
- Does NOT alter Android APK client source (not present here)
"""
from pathlib import Path
import sys,hashlib,os,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
 raise SystemExit('Usage: apply.py check|apply LARAVEL_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file()
backend=root/'app/Http/Controllers/Api/V1/StaffExtraController.php'
view=root/'resources/views/staff/assigned-inspection.blade.php'
original={p:p.read_bytes() for p in [backend,view]}
hashes={
 backend:'fb59ec78d1f90956ac4c650a0c4feeb134cd87a9579fc5cb2f98b82ee090b7ad',
 view:'7b8ac5cc108bc37a42507bfa4ded7e9c26c3ca91e896fe268fd82d91b7236fab',
}
marker='FIX99_INSPECTION_REMARKS_AND_SUBMIT_FEEDBACK'
if all(marker in original[p].decode('utf8','surrogateescape') for p in [backend,view]):
 print('FIX99_ALREADY_APPLIED');sys.exit(0)
for p in [backend,view]:
 actual=hashlib.sha256(original[p]).hexdigest()
 print('FIX99_FILE_SHA',p.relative_to(root),actual)
 if hashes[p]!=actual:raise SystemExit('FIX99_SOURCE_CHANGED_ABORT '+str(p))
b=original[backend].decode('utf8','surrogateescape')
v=original[view].decode('utf8','surrogateescape')
anchor="        DB::transaction(function() use($job,$staffId,$data){"
if b.count(anchor)!=1:raise SystemExit('FIX99_TRANSACTION_ANCHOR_MISMATCH')
b=b.replace(anchor,r'''        // FIX99_INSPECTION_REMARKS_AND_SUBMIT_FEEDBACK: enforce the field rule
        // already displayed in mobile inspection checklist. Do NOT commit partial
        // or invalid inspections; return a useful field-level 422 instead.
        $missingRemarks=[];
        foreach(array_values($data['items']) as $index=>$item){
            $condition=strtolower(trim((string)($item['status']??'')));
            if(in_array($condition,['fair','poor'],true)
               && trim((string)($item['remarks']??''))===''){
                $missingRemarks[]=sprintf(
                    '%d. %s', $index+1,
                    (string)($item['item_name']??'Inspection point')
                );
            }
        }
        if($missingRemarks){
            throw \Illuminate\Validation\ValidationException::withMessages([
                'items'=>'Remarks are required for Fair/Poor at: '.
                  implode('; ',$missingRemarks).
                  '. Please enter observations and submit again.'
            ]);
        }
'''+anchor,1)
form='<form method="post" action="{{ route(\'staff.assigned-inspection.complete\',$card[\'id\']) }}" onsubmit="return confirm(\'Complete this Vehicle Inspection? Saved inspection cannot be overwritten.\')">'
if v.count(form)!=1:raise SystemExit('FIX99_STAFF_FORM_ANCHOR_CHANGED')
v=v.replace(form,form.replace('<form ', '<form id="am99-inspection-form" ')+
''' 
     <div id="am99-validation-alert" role="alert" aria-live="assertive" tabindex="-1" hidden
       style="margin:12px 0;padding:13px;border:1px solid #dd9d9b;border-left:4px solid #b92c2c;border-radius:9px;background:#fff1f0;color:#87342e;font-size:13px;font-weight:bold"></div>''',1)
# Preserve Form, status inputs, remarks and original endpoint.
markup='     <button type="submit" class="p29-btn">✓ Complete Vehicle Inspection</button>'
if v.count(markup)!=1:raise SystemExit('FIX99_BUTTON_MISSING')
v=v.replace(markup,'''     <button type="submit" class="p29-btn" id="am99-complete-button">✓ Complete Vehicle Inspection</button>
     <p id="am99-submit-progress" aria-live="polite" hidden style="color:#14546d;font-weight:700;font-size:12px;margin:9px 0">Submitting inspection to ERP…</p>''',1)
before='</body></html>'
if v.count(before)!=1:raise SystemExit('FIX99_TEMPLATE_CLOSING_CHANGED')
js=r'''
{{-- FIX99_INSPECTION_REMARKS_AND_SUBMIT_FEEDBACK: same checklist validation
     used by Android; readable errors instead of a silent disabled submit.
     Original post action, CSRF, audited API transaction retained. --}}
<script>
(function(){
 'use strict';
 function init(){
   var form=document.getElementById('am99-inspection-form');
   var button=document.getElementById('am99-complete-button');
   var banner=document.getElementById('am99-validation-alert');
   var progress=document.getElementById('am99-submit-progress');
   if(!form||!button||!banner)return;
   var rows=Array.from(form.querySelectorAll('.p29-check'));
   var isBusy=false;
   function condition(row){return row.querySelector('select[name$="[status]"]');}
   function remark(row){return row.querySelector('textarea[name$="[remarks]"]');}
   function highlight(row,invalid){
     row.style.borderLeft=invalid?'4px solid #bd292d':'';
     row.style.background=invalid?'#fff2f0':'';
     row.style.borderRadius=invalid?'8px':'';
   }
   function clearMessage(){
     banner.hidden=true;banner.textContent='';
   }
   function validate(){
     var missing=[];
     var first=null;
     rows.forEach(function(row,i){
       var c=condition(row),r=remark(row);
       if(!c||!r)return;
       var status=c.value.toLowerCase();
       var needs=status==='fair'||status==='poor';
       var invalid=!status||(needs&&!r.value.trim());
       r.required=needs;
       highlight(row,invalid);
       if(invalid){
         var label=row.querySelector('strong');
         var title=label?label.textContent.trim():'Point '+(i+1);
         missing.push(title+(!status?' — select Condition':' — remarks required for '+status));
         if(!first)first=(!status?c:r);
       }
     });
     if(missing.length){
       banner.hidden=false;
       banner.textContent='Inspection not completed. Please correct '+missing.length+' item(s): '+missing.join('; ');
       banner.scrollIntoView({block:'start',behavior:'smooth'});
       if(first)first.focus({preventScroll:true});
       if(progress)progress.hidden=true;
       return false;
     }
     clearMessage();return true;
   }
   rows.forEach(function(row){
     var c=condition(row),r=remark(row);
     if(c)c.addEventListener('change',function(){
       if(r)r.required=c.value==='fair'||c.value==='poor';
       highlight(row,false);clearMessage();
     });
     if(r)r.addEventListener('input',function(){highlight(row,false);clearMessage();});
   });
   // Capturing the button click is important: browsers may prevent the submit
   // event entirely when one of the required selects/remarks is invalid.
   button.addEventListener('click',function(e){
     if(isBusy){e.preventDefault();return;}
     if(!validate()){e.preventDefault();e.stopImmediatePropagation();}
   },true);
   form.addEventListener('submit',function(e){
     if(isBusy){e.preventDefault();return;}
     if(!validate()){e.preventDefault();e.stopImmediatePropagation();return;}
     // The existing inline onsubmit confirm still owns user confirmation.
     // Avoid disabling the button before that confirm completes.
   },true);
   // On confirmed POST the page navigates normally. Display backend errors
   // after redirect through the preexisting Laravel $errors flash block.
   form.addEventListener('submit',function(e){
     if(e.defaultPrevented)return;
     isBusy=true;
     if(progress)progress.hidden=false;
     button.textContent='Submitting Inspection…';
   },false);
   window.addEventListener('pageshow',function(){
     isBusy=false;button.disabled=false;button.textContent='✓ Complete Vehicle Inspection';
     if(progress)progress.hidden=true;
   });
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});
 else init();
})();
</script>
'''
# Original inline confirmation executes in target phase before bubble handlers;
# don't show submitted indicator until confirmation confirmed. Existing
# confirmation returns false on cancel; use form.submit flag in bubble listener
# only when event.defaultPrevented false.
v=v.replace(before,js+before,1)
for k in ['function saveInspection','VehicleInspectionChecklist::valid','job_card_inspection_assignments',
          "'inspection_status'=>'completed'"]:
 if k not in b:raise SystemExit('FIX99_API_CRITICAL_GUARD_MISSING '+k)
for k in ['id="am99-inspection-form"','id="am99-complete-button"',
          'role="alert"','Please correct','[status]','[remarks]']:
 if k not in v:raise SystemExit('FIX99_VIEW_CRITICAL_MISSING '+k)
if mode=='check':
 if os.environ.get('FIX99_PREVIEW_BACKEND'):
  Path(os.environ['FIX99_PREVIEW_BACKEND']).write_text(b,encoding='utf8',errors='surrogateescape')
 if os.environ.get('FIX99_PREVIEW_VIEW'):
  Path(os.environ['FIX99_PREVIEW_VIEW']).write_text(v,encoding='utf8',errors='surrogateescape')
 print('FIX99_INSPECTION_VALIDATION_AND_FEEDBACK_PREFLIGHT_PASS_NO_WRITES')
 sys.exit(0)
def atomic(p,new):
 fd,tmp=tempfile.mkstemp(dir=p.parent,prefix='.fix99_')
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(new)
  os.chmod(tmp,p.stat().st_mode&0o777)
  os.replace(tmp,p)
 finally:
  if os.path.exists(tmp):os.unlink(tmp)
atomic(backend,b);atomic(view,v)
print('FIX99_STAFF_WEB_AND_API_INSPECTION_FEEDBACK_APPLIED_NO_DB_WRITES')
