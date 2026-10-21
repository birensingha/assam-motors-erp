#!/usr/bin/env python3
"""FIX82B: DOM-ready initialization for moved Parts/Labour typeahead and pricing.
UI JavaScript only; ensure dynamically rendered sections exist before binding.
"""
from pathlib import Path
import sys,os,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
 raise SystemExit('Usage: apply.py check|apply ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
assert (root/'artisan').exists()
p=root/'resources/views/job-cards/show.blade.php'
s=p.read_bytes().decode('utf8','surrogateescape')
if 'FIX82_JC_SINGLE_ENTRY_PANEL' not in s:raise SystemExit('FIX82B_NEEDS_FIX82_FIRST')
if 'FIX82B_DOM_READY_SEARCH_GST' in s:
 print('FIX82B_ALREADY_APPLIED');sys.exit(0)
start=s.index('<style id="FIX82_JC_SINGLE_ENTRY_PANEL">')
beg=s.index("<script>\n(function(){\n  'use strict';\n  const money",start)
end=s.index("})();\n</script>",beg)+len("})();\n</script>")
code=s[beg:end]
oldStart="(function(){\n  'use strict';\n  const money"
newStart="(function(){\n  'use strict';\n  // FIX82B_DOM_READY_SEARCH_GST: bind controls after new Parts and Labour sections render.\n  const am82Ready = function(){\n  const money"
if code.count(oldStart)!=1:raise SystemExit('FIX82B_START_UNKNOWN')
if code.count("})();\n</script>")!=1:raise SystemExit('FIX82B_END_UNKNOWN')
code=code.replace(oldStart,newStart,1)
code=code.replace("})();\n</script>",
 """  };
  if(document.readyState==='loading'){
    document.addEventListener('DOMContentLoaded',am82Ready,{once:true});
  }else{
    am82Ready();
  }
})();
</script>""",1)
if code.count("FIX82B_DOM_READY_SEARCH_GST")!=1:raise SystemExit('FIX82B_MARKER_INVALID')
s=s[:beg]+code+s[end:]
if s.count('class="am82-finance-form"')!=1 or s.count('id="am82-rot-select"')!=1:
 raise SystemExit('FIX82B_NEW_CONTROLS_MISSING')
print('FIX82B_DOM_READY_PATCH_PREFLIGHT_PASS')
if mode=='check':sys.exit(0)
fd,tmp=tempfile.mkstemp(prefix='.fix82b-',dir=p.parent)
try:
 with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
 os.chmod(tmp,p.stat().st_mode&0o777)
 os.replace(tmp,p)
finally:
 if os.path.exists(tmp):os.unlink(tmp)
print('FIX82B_DOM_READY_SCRIPT_APPLIED')
