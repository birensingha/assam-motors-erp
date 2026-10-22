#!/usr/bin/env python3
"""FIX97 one visible Labour/ROT search/select control.
CSS presentation only: the FIX96 dropdown arrow is overlaid INSIDE its input,
not rendered as a second bordered field. Native select, JS input/change,
required validation, POST and AJAX logic are untouched.
"""
from pathlib import Path
import hashlib,sys,os,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
 raise SystemExit('Usage: apply.py check|apply LARAVEL_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file(), 'FIX97_NOT_LARAVEL'
p=root/'resources/views/job-cards/show.blade.php'
s=p.read_text(encoding='utf8',errors='surrogateescape')
marker='id="FIX97_ONE_VISIBLE_ROT_COMBO"'
print('FIX97_ORIGINAL_SHA256',hashlib.sha256(p.read_bytes()).hexdigest())
if marker in s:
 if s.count(marker)==1 and 'am96-arrow' in s:
  print('FIX97_ALREADY_APPLIED');sys.exit(0)
 raise SystemExit('FIX97_PARTIAL_PATCH_ABORT')
for key in ['id="FIX96_SINGLE_ROW_LABOUR_COMBO"',
            'am96-shell','am96-visible-search','am96-arrow',
            'id="am82-rot-select"','id="am82-rot-search"',
            'id="am90-labour-billing"',
            'id="FIX95_ROT_ONE_ROW_NO_BILLING_BOX"',
            'id="FIX93_OSL_MODAL_TOOLS"']:
 if key not in s:raise SystemExit('FIX97_REQUIRED_FUNCTION_MISSING '+key)
style=r'''
{{-- FIX97: one visible ROT searchable combobox, one arrow inside same outline.
 No change to native selection, JS, pricing, required validation or submissions. --}}
<style id="FIX97_ONE_VISIBLE_ROT_COMBO">
/* The FIX96 native select is the DATA source only; it must not make a second box. */
.jc-labour-block .am83-inline-add #am82-rot-select{
 position:absolute!important;left:-9999px!important;top:auto!important;
 width:1px!important;height:1px!important;min-width:1px!important;
 max-width:1px!important;min-height:1px!important;
 padding:0!important;margin:0!important;border:0!important;
 overflow:hidden!important;opacity:0!important;
 pointer-events:none!important;appearance:none!important;
 -webkit-appearance:none!important;box-shadow:none!important
}
/* One boundary owns BOTH text input and arrow (no gap or second outline). */
.jc-labour-block .am83-inline-add .am96-shell{
 display:flex!important;flex-direction:row!important;
 align-items:center!important;position:relative!important;
 width:100%!important;min-width:0!important;height:29px!important;
 gap:0!important;padding:0!important;margin:0!important;
 border:1px solid #9fbedc!important;border-radius:5px!important;
 box-shadow:none!important;background:#fff!important;
 box-sizing:border-box!important;overflow:hidden!important
}
.jc-labour-block .am83-inline-add .am96-shell input.am96-visible-search{
 flex:1 1 auto!important;display:block!important;
 width:100%!important;min-width:0!important;max-width:none!important;
 height:27px!important;min-height:27px!important;
 padding:3px 26px 3px 6px!important;margin:0!important;
 border:0!important;border-radius:4px!important;
 outline:none!important;box-shadow:none!important;
 background:#fff!important;color:#204875!important;
 -webkit-appearance:none!important;appearance:none!important;
 font-size:10px!important;text-overflow:ellipsis!important;
}
/* Overlays the last 24px INSIDE the field, no own box, no border. */
.jc-labour-block .am83-inline-add .am96-shell button.am96-arrow{
 position:absolute!important;top:0!important;right:0!important;left:auto!important;
 z-index:3!important;display:flex!important;justify-content:center!important;
 align-items:center!important;width:24px!important;min-width:24px!important;
 height:27px!important;min-height:27px!important;
 padding:0!important;margin:0!important;gap:0!important;
 border:0!important;border-left:0!important;
 border-radius:0 4px 4px 0!important;background:transparent!important;
 color:#2b5f91!important;box-shadow:none!important;
 font-size:11px!important;line-height:1!important;
 cursor:pointer!important;appearance:none!important;
 -webkit-appearance:none!important
}
.jc-labour-block .am83-inline-add .am96-shell button.am96-arrow:hover,
.jc-labour-block .am83-inline-add .am96-shell button.am96-arrow:focus-visible{
 background:#e9f4ff!important;border:0!important;outline-offset:-2px!important
}
.jc-labour-block .am83-inline-add .am96-shell:focus-within{
 border-color:#167bd0!important;
 box-shadow:0 0 0 2px rgba(14,111,211,.14)!important
}
/* Don't expose the hidden compatibility search bridge. */
.jc-labour-block .am83-inline-add #am82-rot-search{display:none!important}
</style>
'''
s+='\n'+style
if s.count(marker)!=1 or 'am96-visible-search' not in s:
 raise SystemExit('FIX97_RESULT_INVALID')
if mode=='check':
 if os.environ.get('FIX97_PREVIEW_PATH'):
  Path(os.environ['FIX97_PREVIEW_PATH']).write_text(s,encoding='utf8',errors='surrogateescape')
  print('FIX97_TEMP_PREVIEW_ONLY')
 print('FIX97_PREFLIGHT_PASS_NO_WRITES');sys.exit(0)
fd,tmp=tempfile.mkstemp(dir=p.parent,prefix='.fix97_')
try:
 with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
 os.chmod(tmp,p.stat().st_mode&0o777)
 os.replace(tmp,p)
finally:
 if os.path.exists(tmp):os.unlink(tmp)
print('FIX97_ONE_SINGLE_BORDERSHARED_ROT_SEARCH_FIELD_APPLIED_VIEW_ONLY')
