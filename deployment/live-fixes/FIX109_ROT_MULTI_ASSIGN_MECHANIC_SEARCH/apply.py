#!/usr/bin/env python3
"""FIX109 STAGING: multiple planned ROT assignments + searchable Change Mechanic.

- Existing Check-In requirement remains.
- Assign a mechanic to several job cards, even if other jobs are planned.
- Start/Resume must still block if another ROT is RUNNING.
- Change Mechanic uses existing staff_id select as form source of truth.
- No DB changes, staff history edits, timers, payroll or stock modifications.
"""
import sys,re,os,hashlib,tempfile,subprocess
from pathlib import Path
if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply"):
    raise SystemExit("Usage: apply.py check|apply STAGING_LARAVEL_ROOT")
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
assert (root/"artisan").is_file(),"FIX109_NOT_LARAVEL_ROOT"
files={
 "assign":root/"app/Services/RotAssignmentService.php",
 "work":root/"app/Services/RotWorkflowService.php",
 "view":root/"resources/views/job-cards/show.blade.php"
}
source={}
for k,p in files.items():
    assert p.is_file(),"FIX109_MISSING_"+str(p)
    source[k]=p.read_text(encoding="utf8",errors="surrogateescape")
    print("FIX109_INPUT_SHA",k,hashlib.sha256(p.read_bytes()).hexdigest())
marker="FIX109_MULTI_PLANNED_ROT_AND_SEARCH_MECHANIC"
if all(marker in source[k] for k in files):
    print("FIX109_ALREADY_APPLIED");sys.exit(0)
if any(marker in source[k] for k in files):
    raise SystemExit("FIX109_PARTIAL_PATCH_ABORT")
def replace_once(s,old,new,name):
    n=s.count(old);print("FIX109_ANCHOR",name,n)
    if n!=1:raise SystemExit("FIX109_SAFE_STOP_"+name)
    return s.replace(old,new,1)

# Old FIX_ROT_V2 explicitly blocks Assigned OR Running for ANOTHER planned ROT.
a=source["assign"]
old="""            $busy = RotSession::query()->where('staff_id', $staffId)
                ->whereIn('status', ['Assigned','Running'])->lockForUpdate()->exists();"""
new="""            // FIX109_MULTI_PLANNED_ROT_AND_SEARCH_MECHANIC: assignment is planning.
            // Do not reserve a mechanic for a future ROT. Start and Resume are
            // the only activities that require an exclusive Running time slot.
            // Existing Check-In eligibility logic in this method is unchanged.
            $busy = false;"""
a=replace_once(a,old,new,"advance_assignment_conflict_guard")
assert "Staff already has an Assigned or Running ROT." in a
if "assertStaffCheckedIn" not in a and "check" not in a.lower():
    raise SystemExit("FIX109_CHECKIN_BASELINE_MISSING")

# Reassignment and Start/Resume validators may currently count Assigned as
# busy: count ONLY Running for activity exclusivity. Do not relax the actual
# Running blocker. The three method boundaries originate in deployed ROT V2.
w=source["work"]
methods=[
 ("public function reopenResetEndTime(", "public function changeMechanic(", "reopen_guard"),
 ("public function changeMechanic(", "public function history(", "change_mechanic_guard"),
 ("private function assertNoOtherRunningRot(", "private function ensureCurrentSegment(", "start_resume_running_guard")
]
for begin,finish,label in methods:
    start=w.find(begin)
    end=w.find(finish,start+len(begin))
    if start<0 or end<0:raise SystemExit("FIX109_MISSING_METHOD_"+label)
    part=w[start:end]
    original="->whereIn('status', ['Assigned','Running'])"
    # FIX109 does not modify a second, unexpected query.
    if part.count(original)!=1:raise SystemExit("FIX109_CHANGED_GUARD_"+label)
    part=part.replace(original,"->where('status', 'Running')",1)
    w=w[:start]+part+w[end:]
w=replace_once(w,
    "class RotWorkflowService",
    "// FIX109_MULTI_PLANNED_ROT_AND_SEARCH_MECHANIC: only Running ROT conflicts at Start/Resume.\nclass RotWorkflowService",
    "workflow_marker")
# We purposely DO NOT alter Start / Resume's check-in enforcement or clock.
for code,txt in [("assign",a),("work",w)]:
    result=subprocess.run(["php","-l"],input=txt,text=True,capture_output=True)
    if result.returncode:
        raise SystemExit("FIX109_PHP_SYNTAX_FAIL_"+code+": "+result.stderr[-300:])

# Use a searchable combobox with original select retained, not replaced.
# It works in existing Change Mechanic modal and forms created later.
v=source["view"]
if "route('erp.rot.change-mechanic'" not in v:
    raise SystemExit("FIX109_VIEW_MISSING_CHANGE_MECHANIC_FORM")
widget=r'''
{{-- FIX109_MULTI_PLANNED_ROT_AND_SEARCH_MECHANIC: name/code search in Change Mechanic.
     The original staff_id <select> stays in the form and submits the staff ID. --}}
<style id="FIX109_MECHANIC_COMBO">
.am109-mechanic-picker{display:flex;align-items:center;position:relative;
 width:100%;min-width:0;height:35px;border:1px solid #80a9da;
 background:#fff;border-radius:6px;box-sizing:border-box}
.am109-mechanic-picker:focus-within{border-color:#176fc9;
 box-shadow:0 0 0 2px rgba(23,111,201,.12)}
.am109-mechanic-picker input{flex:1!important;min-width:0!important;
 width:100%!important;height:33px!important;min-height:33px!important;
 padding:5px 7px!important;border:0!important;outline:0!important;
 background:transparent!important;box-shadow:none!important;font:12px Arial,sans-serif!important}
.am109-mechanic-picker button{flex:0 0 29px;width:29px;height:32px;
 background:#eef5ff;color:#194775;border:0;border-left:1px solid #d9e5f4;
 border-radius:0 5px 5px 0;cursor:pointer;font-size:11px}
.am109-mechanic-picker select.am109-source{
 display:none!important;visibility:hidden!important;position:absolute!important;
 width:0!important;height:0!important;pointer-events:none!important}
.am109-menu{position:fixed;display:none;box-sizing:border-box;
 max-height:235px;overflow-y:auto;overscroll-behavior:contain;
 background:#fff;border:1px solid #91b3dc;border-radius:6px;
 box-shadow:0 9px 25px rgba(18,50,95,.24);z-index:2147483640}
.am109-menu.is-open{display:block}
.am109-menu .am109-opt{display:block;width:100%;box-sizing:border-box;
 text-align:left;min-height:31px;cursor:pointer;border:0;
 border-bottom:1px solid #eaf0f7;padding:8px 10px;
 background:#fff;color:#143a64;font:12px Arial,sans-serif}
.am109-menu .am109-opt:hover,.am109-menu .am109-opt.is-highlighted{
 background:#e9f3ff;color:#08477d}
.am109-menu .am109-empty{padding:10px;color:#78859a;font-size:12px}
</style>
<script>
(function(){
 'use strict';
 var activePicker=null, counter=0;
 function closeActive(){if(activePicker)activePicker.close();}
 function enhance(select){
   if(!select || select.dataset.am109Enhanced==='1')return;
   if(!select.closest('form[action*="change-mechanic"]'))return;
   select.dataset.am109Enhanced='1';
   var form=select.closest('form');
   var options=Array.prototype.slice.call(select.options||[]).filter(function(o){
     return o.value && !o.disabled;
   });
   var wrap=document.createElement('div');
   wrap.className='am109-mechanic-picker';
   select.parentNode.insertBefore(wrap,select);
   wrap.appendChild(select);
   select.classList.add('am109-source');
   select.setAttribute('aria-hidden','true');
   select.tabIndex=-1;
   select.required=false;
   var input=document.createElement('input');
   input.type='text';input.required=true;input.autocomplete='off';
   input.placeholder='Search mechanic name / Staff Code';
   input.setAttribute('role','combobox');
   input.setAttribute('aria-autocomplete','list');
   input.setAttribute('aria-expanded','false');
   input.setAttribute('aria-label','New Mechanic: search staff by name or code');
   var menu=document.createElement('div');
   menu.id='am109-menu-'+(++counter);menu.className='am109-menu';
   menu.setAttribute('role','listbox');
   input.setAttribute('aria-controls',menu.id);
   var arrow=document.createElement('button');
   arrow.type='button';arrow.textContent='▼';arrow.title='Show all mechanics';
   arrow.setAttribute('aria-label','Open mechanic dropdown');
   wrap.insertBefore(input,select);wrap.appendChild(arrow);
   document.body.appendChild(menu);
   var shown=[],index=-1,open=false;
   var chosen=options.find(function(o){return o.value===select.value;});
   if(chosen)input.value=chosen.textContent.trim();
   function position(){
     if(!open)return;
     var r=wrap.getBoundingClientRect();
     var width=Math.max(190,Math.min(Math.max(r.width,290),window.innerWidth-20));
     var left=Math.max(8,Math.min(r.left,window.innerWidth-width-8));
     var below=window.innerHeight-r.bottom;
     menu.style.width=width+'px';
     menu.style.left=left+'px';
     if(below>=165||below>=r.top){
       menu.style.top=Math.max(8,r.bottom+3)+'px';
       menu.style.bottom='auto';
       menu.style.maxHeight=Math.max(70,Math.min(235,below-12))+'px';
     }else{
       menu.style.top='auto';
       menu.style.bottom=Math.max(8,window.innerHeight-r.top+3)+'px';
       menu.style.maxHeight=Math.max(70,Math.min(235,r.top-12))+'px';
     }
   }
   function close(){
     open=false;menu.classList.remove('is-open');
     input.setAttribute('aria-expanded','false');
     if(activePicker===api)activePicker=null;
     index=-1;
   }
   function choose(o){
     select.value=o.value;
     input.value=o.textContent.trim();
     input.setCustomValidity('');
     select.dispatchEvent(new Event('change',{bubbles:true}));
     close();
   }
   function render(filter){
     var q=String(filter||'').trim().toLowerCase();
     shown=options.filter(function(o){return !q||o.textContent.toLowerCase().indexOf(q)>=0;});
     menu.textContent='';
     if(!shown.length){
       var empty=document.createElement('div');
       empty.className='am109-empty';empty.textContent='No matching mechanic found';
       menu.appendChild(empty);index=-1;return;
     }
     if(index>=shown.length)index=0;
     shown.forEach(function(o,i){
       var node=document.createElement('button');
       node.type='button';node.className='am109-opt'+(i===index?' is-highlighted':'');
       node.setAttribute('role','option');
       node.textContent=o.textContent.trim();
       node.addEventListener('pointerdown',function(e){e.preventDefault();choose(o);});
       node.addEventListener('click',function(e){e.preventDefault();choose(o);});
       menu.appendChild(node);
     });
   }
   function show(all){
     if(activePicker&&activePicker!==api)activePicker.close();
     activePicker=api;open=true;index=-1;
     render(all?'':input.value);
     menu.classList.add('is-open');input.setAttribute('aria-expanded','true');position();
   }
   var api={close:close,position:position};
   input.addEventListener('input',function(){
     // Typed free text is NEVER accepted as a mechanic ID.
     select.value='';input.setCustomValidity('');
     show(false);
   });
   input.addEventListener('keydown',function(e){
     if(e.key==='Tab'||e.key==='Escape'){close();return;}
     if(e.key==='ArrowDown'||e.key==='ArrowUp'){
       e.preventDefault();if(!open)show(true);
       if(shown.length){index=e.key==='ArrowDown'
         ?(index+1+shown.length)%shown.length
         :(index-1+shown.length)%shown.length;render(input.value);
         menu.querySelectorAll('.am109-opt')[index]?.scrollIntoView({block:'nearest'});}
     }
     if(e.key==='Enter'&&open){
       e.preventDefault();
       if(shown.length)choose(shown[Math.max(0,index)]);
     }
   });
   input.addEventListener('focus',function(){input.setCustomValidity('');});
   arrow.addEventListener('click',function(e){
     e.preventDefault();e.stopPropagation();
     if(open)close();else{input.focus();show(true);}
   });
   document.addEventListener('pointerdown',function(e){
     if(open && !wrap.contains(e.target) && !menu.contains(e.target))close();
   });
   form.addEventListener('submit',function(e){
     if(!select.value){
       e.preventDefault();e.stopImmediatePropagation();
       input.setCustomValidity('Select a mechanic from the dropdown list.');
       input.reportValidity();input.focus({preventScroll:true});show(true);
     }else input.setCustomValidity('');
   },true);
   select.addEventListener('change',function(){
     var o=options.find(function(x){return x.value===select.value;});
     if(o){input.value=o.textContent.trim();input.setCustomValidity('');}
   });
   window.addEventListener('resize',position,{passive:true});
   window.addEventListener('scroll',position,{passive:true,capture:true});
 }
 function scan(){
   document.querySelectorAll('form[action*="change-mechanic"] select[name="staff_id"]')
     .forEach(enhance);
 }
 function ready(){
   scan();
   if(window.MutationObserver){
     var observer=new MutationObserver(scan);
     observer.observe(document.body,{subtree:true,childList:true});
   }
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',ready,{once:true});
 else ready();
})();
</script>
'''
v+='\n'+widget
if v.count(marker)!=1:raise SystemExit("FIX109_VIEW_MARKER_NOT_ONCE")
if "whereIn('status', ['Assigned','Running'])" in w[w.find("private function assertNoOtherRunningRot("):w.find("private function ensureCurrentSegment(")]:
    raise SystemExit("FIX109_RUNNING_GUARD_UNCHANGED")
updated={"assign":a,"work":w,"view":v}
if mode=="check":
    for k,txt in updated.items():
        env=os.environ.get("FIX109_PREVIEW_"+k.upper())
        if env:Path(env).write_text(txt,encoding="utf8",errors="surrogateescape")
    print("FIX109_PREFLIGHT_PHP_AND_BINDINGS_PASS_NO_WRITES");sys.exit(0)
for k,txt in updated.items():
    p=files[k];fd,tmp=tempfile.mkstemp(prefix=".fix109_",dir=p.parent)
    try:
        with os.fdopen(fd,"w",encoding="utf8",errors="surrogateescape") as f:f.write(txt)
        os.chmod(tmp,p.stat().st_mode&0o777)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
    print("FIX109_APPLIED",p.relative_to(root))
print("FIX109_MULTIPLE_ASSIGNED_ALLOWED_RUNNING_EXCLUSIVE_AND_SEARCHABLE_CHANGE_MECHANIC")
