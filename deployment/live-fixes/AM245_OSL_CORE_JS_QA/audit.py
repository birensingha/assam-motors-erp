#!/usr/bin/env python3
"""AM245 read-only syntax/structural test of rendered OSL master constructor JS."""
from pathlib import Path
import subprocess,tempfile,sys,re
if len(sys.argv)==3 and sys.argv[1]=='--blade':
 v=Path(sys.argv[2]).read_text('utf8','replace')
else:
 root=Path(sys.argv[1]).resolve()
 if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
  raise SystemExit('AM245_STOP_NOT_STAGING')
 v=(root/'resources/views/job-cards/show.blade.php').read_text('utf8','replace')
start='<script id="am146-osl-calculations">'
if v.count(start)!=1:raise SystemExit('AM245_STOP_EXPECTED_SCRIPT_COUNT')
source=v.split(start,1)[1].split('</script>',1)[0]
for old,new in [
 ('const masterItems=@json($am146Masters);','const masterItems=[];'),
 ("const createUrl=@json(route('erp.osl-masters.quick-create'));","const createUrl='/fake-test-path';")
]:
 if source.count(old)!=1:raise SystemExit('AM245_STOP_EXPECTED_JS_DIRECTIVE')
 source=source.replace(old,new,1)
for token in ['AM244_OSL_CORE_SEARCH','masterRow.append(combo,oldLabel,add,feedback)',
 'const search=document.createElement(\'input\')',
 "search.type='search'",
 'searchLabel.appendChild(search)',
 'select.value=option.value',
 "description.value=option.dataset.name||''",
 'new MutationObserver',
 'form.addEventListener(\'submit\'',
 "add.addEventListener('click'"]:
 if token not in source:raise SystemExit('AM245_STOP_MISSING_CORE_TOKEN_'+token)
if '@json(' in source or '@' in source[:150]:raise SystemExit('AM245_STOP_UNPROCESSED_BLADE_DIRECTIVE')
with tempfile.TemporaryDirectory(prefix='am245qa_') as d:
 f=Path(d)/'core.js';f.write_text(source,encoding='utf8')
 p=subprocess.run(['node','--check',str(f)],capture_output=True,text=True,timeout=30)
 if p.returncode:raise SystemExit('AM245_NODE_SYNTAX_FAILED '+p.stderr[-950:])
print('AM245_CORE_OSL_JS_SYNTAX_PASS')
print('AM245_INPUT_CO_CONSTRUCTOR_SELECT_DESCRIPTION_QUICK_CREATE_PASS')
print('AM245_READONLY_NO_DB_WRITES')
