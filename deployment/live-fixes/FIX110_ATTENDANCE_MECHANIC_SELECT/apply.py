#!/usr/bin/env python3
"""FIX110: make Attendance status and Change Mechanic dropdown lists visible.

Display-only Blade changes; original <select> elements and their
name/value/options remain the submitted source of truth. No permission,
attendance, personnel, ROT timing, DB or assignment rules are modified.
"""
from pathlib import Path
import sys,os,tempfile,hashlib,re
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
    raise SystemExit('Usage: apply.py check|apply LARAVEL_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
assert (root/'artisan').exists()
files={
 'attendance':root/'resources/views/admin/native/attendance.blade.php',
 'mechanic':root/'resources/views/job-cards/show.blade.php'
}
expected={
 'attendance':'57f522f8ee49df087f959ec32aab92d98eaf7bd8a915fd100cd4e1d098f38c9f',
 'mechanic':'2d115b9312f6a407bc7828e83807acc3aefd6df86debef3ab05d3918a31e1b4a'
}
original={}
marker='FIX110_VISIBLE_ATTENDANCE_AND_MECHANIC_DROPDOWNS'
for name,p in files.items():
    assert p.is_file(),p
    raw=p.read_bytes()
    sha=hashlib.sha256(raw).hexdigest()
    print('FIX110_INPUT_SHA',name,sha)
    original[name]=raw.decode('utf8','surrogateescape')
    if sha!=expected[name]:
        if marker in original[name] and all(marker in q.read_text(errors='surrogateescape')
                                            for q in files.values()):
            print('FIX110_ALREADY_APPLIED');sys.exit(0)
        raise SystemExit('FIX110_SOURCE_CHANGED_STOP_'+name)
def one(s,old,new,key):
    n=s.count(old)
    print('FIX110_ANCHOR',key,n)
    if n!=1:raise SystemExit('FIX110_CHANGED_TEMPLATE_ABORT_'+key)
    return s.replace(old,new,1)
attendance=original['attendance']
for k in ['id="attEditStatus"','Edit Attendance','Save Correction','Reset Attendance']:
    if k not in attendance:raise SystemExit('FIX110_ATTENDANCE_MARKER_MISSING_'+k)
for st in ['present','absent','leave','off']:
    if ('<option value="'+st+'">') not in attendance:
        raise SystemExit('FIX110_STATUS_OPTION_MISSING_'+st)
att_script=r'''
{{-- FIX110_VISIBLE_ATTENDANCE_AND_MECHANIC_DROPDOWNS: attendance status list.
     Native status options are the single source of truth. --}}
<style id="FIX110_ATTENDANCE_STATUS_MENU_STYLE">
.am110-att-combo{position:relative;display:flex;width:100%;height:35px;
 box-sizing:border-box;z-index:5}
.am110-att-combo .am110-att-current{flex:1;width:100%;height:35px;
 display:flex;align-items:center;justify-content:space-between;
 border:1px solid #7babe2;border-radius:6px;background:#fff;
 padding:4px 10px;color:#153c69;cursor:pointer;
 font:12px Arial,sans-serif;text-align:left}
.am110-att-combo .am110-att-list{display:none;position:absolute;top:36px;
 left:0;width:100%;max-height:210px;box-sizing:border-box;
 border:1px solid #7ba9db;background:#fff;border-radius:6px;
 overflow:auto;box-shadow:0 10px 24px rgba(9,38,77,.22);
 z-index:2147483640}
.am110-att-combo .am110-att-list.is-open{display:block}
.am110-att-combo .am110-att-option{display:block;width:100%;
 background:#fff;color:#183c66;cursor:pointer;border:0;
 text-align:left;padding:9px 11px;min-height:34px;
 border-bottom:1px solid #e7edf5;font:12px Arial,sans-serif}
.am110-att-combo .am110-att-option:hover,
.am110-att-combo .am110-att-option:focus,
.am110-att-combo .am110-att-option[aria-selected="true"]{
 background:#e7f2ff;color:#103c71}
.am110-att-combo select.am110-att-source{
 position:absolute!important;opacity:0!important;pointer-events:none!important;
 width:1px!important;height:1px!important;left:0;top:0}
</style>
<script>
(function(){
 'use strict';
 function init(){
   var select=document.getElementById('attEditStatus');
   if(!select || select.dataset.fix110Enhanced==='1')return;
   var optionList=Array.prototype.slice.call(select.options).filter(function(o){
     return o.value && !o.disabled;
   });
   if(!optionList.length)return;
   select.dataset.fix110Enhanced='1';
   var wrap=document.createElement('div');
   wrap.className='am110-att-combo';
   select.parentNode.insertBefore(wrap,select);
   wrap.appendChild(select);select.classList.add('am110-att-source');
   var button=document.createElement('button');button.type='button';
   button.className='am110-att-current';
   button.setAttribute('role','combobox');
   button.setAttribute('aria-haspopup','listbox');
   button.setAttribute('aria-expanded','false');
   button.setAttribute('aria-label','Attendance Status, open options');
   var label=document.createElement('span'),arrow=document.createElement('span');
   arrow.textContent='▼';arrow.style.fontSize='10px';
   button.appendChild(label);button.appendChild(arrow);
   wrap.appendChild(button);
   var menu=document.createElement('div');
   menu.className='am110-att-list';menu.setAttribute('role','listbox');
   menu.id='fix110-att-status-options';
   button.setAttribute('aria-controls',menu.id);
   wrap.appendChild(menu);
   var isOpen=false;
   function sync(){
     var picked=Array.prototype.slice.call(select.options).find(function(o){
       return o.value===select.value;
     });
     label.textContent=picked?picked.textContent.trim():'Select Status';
     Array.prototype.slice.call(menu.children).forEach(function(b){
       b.setAttribute('aria-selected',b.dataset.value===select.value?'true':'false');
     });
   }
   function close(){isOpen=false;menu.classList.remove('is-open');button.setAttribute('aria-expanded','false');}
   function choose(opt){
     select.value=opt.value;
     select.dispatchEvent(new Event('input',{bubbles:true}));
     select.dispatchEvent(new Event('change',{bubbles:true}));
     sync();close();button.focus({preventScroll:true});
   }
   function open(){
     menu.textContent='';
     Array.prototype.slice.call(select.options).filter(function(o){return o.value&&!o.disabled;})
       .forEach(function(o){
         var b=document.createElement('button');b.type='button';b.className='am110-att-option';
         b.dataset.value=o.value;b.textContent=o.textContent.trim();
         b.setAttribute('role','option');b.setAttribute('aria-selected',o.value===select.value?'true':'false');
         b.addEventListener('click',function(e){e.preventDefault();choose(o);});
         menu.appendChild(b);
       });
     isOpen=true;menu.classList.add('is-open');
     button.setAttribute('aria-expanded','true');
     var active=menu.querySelector('[aria-selected="true"]');
     if(active)active.scrollIntoView({block:'nearest'});
   }
   button.addEventListener('click',function(e){e.preventDefault();if(isOpen)close();else open();});
   button.addEventListener('keydown',function(e){
     if(e.key==='Escape'||e.key==='Tab'){close();return;}
     if(e.key==='Enter'||e.key===' '||e.key==='ArrowDown'){
       e.preventDefault();if(!isOpen)open();
       var first=menu.querySelector('[aria-selected="true"]')||menu.firstElementChild;
       if(first)first.focus();
     }
   });
   menu.addEventListener('keydown',function(e){
     if(e.key==='Escape'||e.key==='Tab'){close();button.focus();return;}
     if(e.key==='ArrowDown'||e.key==='ArrowUp'){
       e.preventDefault();
       var arr=Array.prototype.slice.call(menu.querySelectorAll('button'));
       var i=arr.indexOf(document.activeElement);
       if(arr.length)arr[(i+(e.key==='ArrowDown'?1:arr.length-1))%arr.length].focus();
     }
   });
   document.addEventListener('click',function(e){
     if(isOpen&&!wrap.contains(e.target))close();
   });
   select.addEventListener('change',sync);
   var modal=document.getElementById('attEditStatus').closest('dialog');
   if(modal && window.MutationObserver){
      new MutationObserver(function(){if(modal.open){sync();close();}}).observe(modal,{attributes:true,attributeFilter:['open']});
   }
   document.addEventListener('click',function(e){
     if(e.target.closest('[onclick*="openAttendanceEditor"]')){
       requestAnimationFrame(function(){sync();close();});
     }
   },true);
   sync();
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});
 else init();
})();
</script>
'''
# Insert just after the Edit Attendance modal closing dialog, inside the Blade section.
idx=attendance.index('id="attEditStatus"')
end=attendance.find('</dialog>',idx)
if end<0:raise SystemExit('FIX110_ATTENDANCE_EDIT_DIALOG_CLOSING_MISSING')
end+=len('</dialog>')
attendance=attendance[:end]+'\n'+att_script+attendance[end:]
if attendance.count('id="FIX110_ATTENDANCE_STATUS_MENU_STYLE"')!=1:
    raise SystemExit('FIX110_ATTENDANCE_BAD_INJECTION')

mechanic=original['mechanic']
for token in ['id="FIX109_MECHANIC_COMBO"','document.body.appendChild(menu);',
              '.am109-menu{position:fixed;display:none;',
              'function position(){','function close(){','var options=Array.prototype.slice.call(select.options||[])']:
    if token not in mechanic:raise SystemExit('FIX110_OLD_MECHANIC_PICKER_CHANGED_'+token)
# The FIX109 menu was portaled to document.body and positioned fixed. This can
# end up BEHIND a native modal/top layer, producing a blank popup even though
# staff options exist. Keep it in the same modal as the input and arrow.
mechanic=one(mechanic,
             '.am109-menu{position:fixed;display:none;',
             '.am109-menu{position:absolute;top:36px;left:0;width:100%;display:none;',
             'menu_position_css')
mechanic=one(mechanic,'document.body.appendChild(menu);',
             'wrap.appendChild(menu);','menu_inside_modal')
old="""   function position(){
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
   }"""
new="""   function position(){
     // FIX110: Stay inside the Change Mechanic modal's stacking context.
     if(!open)return;
     menu.style.position='absolute';
     menu.style.top='36px';menu.style.bottom='auto';
     menu.style.left='0';menu.style.width='100%';
     menu.style.maxHeight='215px';
   }"""
mechanic=one(mechanic,old,new,'keep_mechanic_menu_inside_modal_layer')
mechanic=one(mechanic,
 """   function render(filter){
     var q=String(filter||'').trim().toLowerCase();""",
 """   function render(filter){
     // Refresh list from live native options, including after DOM updates.
     options=Array.prototype.slice.call(select.options||[]).filter(function(o){return o.value&&!o.disabled;});
     var q=String(filter||'').trim().toLowerCase();""",
 'live_staff_option_refresh')
mechanic=one(mechanic,
 "empty.textContent='No matching mechanic found';",
 "empty.textContent=options.length?'No matching mechanic found':'No eligible mechanics available in this Job Card';",
 'mechanic_empty_reason')
# Keep the popup and its form visible when the list opens.
mechanic=one(mechanic,
 '/* FIX109_MULTI_PLANNED_ROT_AND_SEARCH_MECHANIC: name/code search in Change Mechanic.',
 '/* FIX109_MULTI_PLANNED_ROT_AND_SEARCH_MECHANIC: name/code search in Change Mechanic.',
 'noop') if False else mechanic
mechanic=mechanic.replace('{{-- FIX109_MULTI_PLANNED_ROT_AND_SEARCH_MECHANIC: name/code search in Change Mechanic.',
 '''{{-- FIX110_VISIBLE_ATTENDANCE_AND_MECHANIC_DROPDOWNS: modal-contained dropdown.
     FIX109_MULTI_PLANNED_ROT_AND_SEARCH_MECHANIC: name/code search in Change Mechanic.''',1)
if mechanic.count(marker)!=1:
    raise SystemExit('FIX110_MECHANIC_MARKER_NOT_ONCE')
for k in ['name="staff_id"','form.addEventListener(\'submit\'',
          'select.dispatchEvent(new Event(\'change\'','change-mechanic']:
    if k not in mechanic:raise SystemExit('FIX110_MECHANIC_CRITICAL_MISSING_'+k)
output={'attendance':attendance,'mechanic':mechanic}
if mode=='check':
    for k,v in output.items():
        out=os.environ.get('FIX110_PREVIEW_'+k.upper())
        if out:
            Path(out).write_text(v,encoding='utf8',errors='surrogateescape')
            print('FIX110_CANDIDATE_WRITTEN',k)
    print('FIX110_PREFLIGHT_ONLY_NO_LIVE_WRITES');sys.exit(0)
for k,v in output.items():
    p=files[k]
    fd,temp=tempfile.mkstemp(dir=p.parent,prefix='.fix110_')
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(v)
        os.chmod(temp,p.stat().st_mode&0o777)
        os.replace(temp,p)
    finally:
        if os.path.exists(temp):os.unlink(temp)
    print('FIX110_APPLIED_VIEW',p.relative_to(root))
print('FIX110_STATUS_AND_MECHANIC_POPUPS_FIXED_VIEW_ONLY_NO_DATA_WRITES')
