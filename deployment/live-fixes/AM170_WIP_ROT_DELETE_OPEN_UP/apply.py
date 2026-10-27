#!/usr/bin/env python3
"""AM170 — WIP Job Card ROT Delete/Reversal popover opens UP, staying visible.

UI-only on resources/views/job-cards/show.blade.php. Does not change permissions,
ROT delete handlers, audit logging, finance calculations or database rows.
Run: python3 apply.py check|apply LARAVEL_ROOT
"""
from pathlib import Path
import datetime, os, shutil, subprocess, sys, tempfile

if len(sys.argv) != 3 or sys.argv[1] not in ("check", "apply"):
    raise SystemExit("Usage: apply.py check|apply LARAVEL_STAGING_ROOT")
mode = sys.argv[1]
root = Path(sys.argv[2]).expanduser().resolve()
view = root / "resources/views/job-cards/show.blade.php"
if not (root / "artisan").is_file() or not view.is_file():
    raise SystemExit("AM170_STOP_LARAVEL_VIEW_MISSING")
original = view.read_text(encoding="utf-8", errors="surrogateescape")
marker = "AM170_WIP_ROT_DELETE_OPEN_UP"
if marker in original:
    print("AM170_ALREADY_APPLIED")
    sys.exit(0)
must_have = [
    "@section('content')",
    ".jc-labour-block",
    ".rot-actions",
    "FIX92_LABOUR_MECHANICS_DELETE_DIALOG",
]
missing = [x for x in must_have if x not in original]
if missing:
    raise SystemExit("AM170_STOP_UNEXPECTED_VIEW_STRUCTURE: " + ",".join(missing))
if original.count("@section('content')") != 1:
    raise SystemExit("AM170_STOP_AMBIGUOUS_CONTENT_SECTION")

addon = r"""
{{-- AM170_WIP_ROT_DELETE_OPEN_UP: Only adjust opened ROT action panels. --}}
<style id="AM170_WIP_ROT_DELETE_OPEN_UP">
/* A fixed-position popover is not clipped by scrolling ROT work cards. */
.am170-rot-delete-popover{
  position:fixed!important;
  box-sizing:border-box!important;
  z-index:2147482000!important;
  display:block!important;
  margin:0!important;
  padding:12px!important;
  background:#fff!important;
  color:#183852!important;
  border:1px solid #a9c2de!important;
  border-radius:9px!important;
  box-shadow:0 14px 38px rgba(20,40,64,.25)!important;
  overflow-x:hidden!important;
  overflow-y:auto!important;
  overscroll-behavior:contain!important;
}
details.am170-rot-delete-parent[open]{
  position:relative!important;
  z-index:2147481900!important;
}
@media(max-width:650px){
  .am170-rot-delete-popover{padding:9px!important;max-width:calc(100vw - 20px)!important}
}
</style>
<script>
(function(){
 'use strict';
 var live=new Set();
 function eligible(details){
   if(!details || details.tagName!=='DETAILS')return false;
   var place=details.closest('.jc-labour-block .rot-card, .jc-labour-block .rot-actions, dialog.am90-dialog');
   if(!place)return false;
   var trigger=details.querySelector(':scope > summary');
   if(!trigger)return false;
   var heading=(trigger.textContent||'').trim().toLowerCase();
   var content=(details.textContent||'').slice(0,3500).toLowerCase();
   return /delete\s*(?:rot|labour)|(?:rot|labour)\s*delet|remove\s*(?:rot|labour)|rot\s*reversal|full\s*rot\s*alteration|rot\s*alteration/.test(heading+' '+content);
 }
 function findPanel(details){
   var summary=details.querySelector(':scope > summary');
   if(!summary)return null;
   var node=summary.nextElementSibling;
   while(node && /^(STYLE|SCRIPT|TEMPLATE)$/.test(node.tagName))node=node.nextElementSibling;
   return node && /^(DIV|FORM|SECTION|ARTICLE|UL)$/.test(node.tagName) ? node : null;
 }
 function clear(details){
   live.delete(details);
   details.classList.remove('am170-rot-delete-parent');
   var panel=details._am170Panel;
   if(panel){
     panel.classList.remove('am170-rot-delete-popover');
     var saved=panel._am170OriginalStyles||{};
     ['top','bottom','left','right','width','max-height'].forEach(function(k){
       panel.style.removeProperty(k);
       if(saved[k] && saved[k].value)panel.style.setProperty(k,saved[k].value,saved[k].priority||'');
     });
     delete panel._am170OriginalStyles;
   }
   delete details._am170Panel;
 }
 function position(details){
   if(!details.isConnected || !details.open || !eligible(details)){
     clear(details);return;
   }
   var summary=details.querySelector(':scope > summary');
   var panel=details._am170Panel || findPanel(details);
   if(!summary||!panel)return;
   details._am170Panel=panel;
   if(!panel._am170OriginalStyles){
     panel._am170OriginalStyles={};
     ['top','bottom','left','right','width','max-height'].forEach(function(k){
       panel._am170OriginalStyles[k]={
         value:panel.style.getPropertyValue(k),
         priority:panel.style.getPropertyPriority(k)
       };
     });
   }
   details.classList.add('am170-rot-delete-parent');
   panel.classList.add('am170-rot-delete-popover');
   var r=summary.getBoundingClientRect();
   var margin=10;
   var viewportW=document.documentElement.clientWidth;
   var viewportH=document.documentElement.clientHeight;
   var width=Math.min(480,Math.max(260,viewportW-2*margin));
   var left=Math.max(margin,Math.min(r.right-width,viewportW-width-margin));
   var natural=Math.max(panel.scrollHeight,160);
   var above=Math.max(0,r.top-2*margin);
   var below=Math.max(0,viewportH-r.bottom-2*margin);
   // Prefer opening upward; only fall downward when almost no space above.
   var up=above>=110 || above>=below;
   var room=Math.max(105,up?above:below);
   var height=Math.min(natural,room,Math.max(105,viewportH-2*margin));
   var top=up ? Math.max(margin,r.top-height-7) :
                 Math.min(viewportH-margin-height,r.bottom+7);
   panel.style.setProperty('width',width+'px','important');
   panel.style.setProperty('left',left+'px','important');
   panel.style.setProperty('right','auto','important');
   panel.style.setProperty('top',top+'px','important');
   panel.style.setProperty('bottom','auto','important');
   panel.style.setProperty('max-height',height+'px','important');
   live.add(details);
 }
 function refresh(){
   live.forEach(function(d){position(d);});
 }
 // Capturing listener also works for non-bubbling details toggle events.
 document.addEventListener('toggle',function(event){
   var d=event.target;
   if(!(d instanceof HTMLDetailsElement))return;
   if(d.open && eligible(d)){
     position(d);
     requestAnimationFrame(function(){if(d.open)position(d);});
   }else if(live.has(d))clear(d);
 },true);
 document.addEventListener('click',function(event){
   var summary=event.target.closest && event.target.closest('summary');
   if(!summary || !summary.parentElement || summary.parentElement.tagName!=='DETAILS')return;
   var d=summary.parentElement;
   if(eligible(d))requestAnimationFrame(function(){if(d.open)position(d);});
 },true);
 // Keep the upward popup aligned when the page scrolls or screen changes.
 window.addEventListener('resize',refresh,{passive:true});
 document.addEventListener('scroll',refresh,true);
})();
</script>
"""
new = original.replace("@section('content')", "@section('content')\n"+addon, 1)
if new.count('id="AM170_WIP_ROT_DELETE_OPEN_UP"') != 1:
    raise SystemExit("AM170_STOP_OUTPUT_VALIDATION")
# Compile the modified view and check PHP syntax before any filesystem mutation.
with tempfile.TemporaryDirectory(prefix="am170_check_") as temp:
    folder=Path(temp)
    sample=folder/"show.blade.php"
    sample.write_text(new,encoding="utf-8",errors="surrogateescape")
    validator=folder/"validate.php"
    validator.write_text("""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$blade=$app->make('blade.compiler');
file_put_contents($argv[3],$blade->compileString(file_get_contents($argv[2])));
""")
    compiled=folder/"compiled.php"
    result=subprocess.run(["php",str(validator),str(root),str(sample),str(compiled)],
                          text=True,capture_output=True)
    if result.returncode!=0 or not compiled.is_file():
        raise SystemExit("AM170_STOP_BLADE_COMPILE: "+result.stderr[-1400:])
    result=subprocess.run(["php","-l",str(compiled)],text=True,capture_output=True)
    if result.returncode!=0:
        raise SystemExit("AM170_STOP_PHP_LINT: "+(result.stdout+result.stderr)[-1400:])
    print("AM170_BLADE_COMPILE_AND_PHP_LINT_PASS")
if mode=="check":
    print("AM170_PREFLIGHT_PASS_NO_WRITES")
    sys.exit(0)
backup=view.with_name(view.name+".AM170_"+datetime.datetime.now().strftime("%Y%m%d_%H%M%S")+".bak")
shutil.copy2(view,backup)
fd,name=tempfile.mkstemp(prefix=".am170_",dir=view.parent)
try:
    with os.fdopen(fd,"w",encoding="utf-8",errors="surrogateescape") as f:
        f.write(new)
    os.chmod(name,view.stat().st_mode & 0o777)
    os.replace(name,view)
finally:
    if os.path.exists(name):os.unlink(name)
print("AM170_UI_ONLY_APPLIED")
print("AM170_BACKUP",backup)
print("AM170_NEXT: php artisan view:clear and test assigned WIP ROT Delete dropdown")
