#!/usr/bin/env python3
"""AM257 read-only inventory of shared item dropdown Enter/Tab and auto-row handlers.
Print source only, not business rows or credentials.
"""
from pathlib import Path
import sys,re
if len(sys.argv)!=2:raise SystemExit('USAGE_AM257 audit.py STAGING_ROOT')
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('STOP_AM257_NOT_STAGING')
dirs=[root/'public/js',root/'resources/views',root/'public/assets']
needle=re.compile(r'keydown|keyup|keypress|ArrowDown|ArrowUp|Enter|Tab|keyCode|event\.key|\.key\s*===|item-search|ref-search|part-search|product-search|suggestions|autocomplete|dropdown|pickItem|selectItem|addRow|addLine|add-row|e-ref-search',re.I)
danger=re.compile(r'(?i)password\s*[=:]|secret\s*[=:]|APP_KEY|DB_PASSWORD|cookie\s*[=:]|access.token|authorization:|bearer\s')
matches=[]
for parent in dirs:
 if not parent.is_dir():continue
 for p in parent.rglob('*'):
  if p.suffix not in ('.js','.php') or p.stat().st_size>400000:continue
  if p.suffix=='.php' and not p.name.endswith('.blade.php'):continue
  rel=p.relative_to(root).as_posix()
  if not any(w in rel.lower() for w in ('estimate','purchase','sale','invoice','part','item','counter','entry','job-card','layout','stock')):continue
  if any(x in rel for x in ('vendor/','node_modules/','build/','cache/')):continue
  lines=p.read_text('utf8','replace').splitlines()
  indexes=[i for i,v in enumerate(lines) if needle.search(v)]
  if not indexes:continue
  score=sum(4 if re.search(r'keydown|ArrowDown|ArrowUp|\.key|keyCode|e-ref-search|\\.e-ref-search',lines[i],re.I) else 1 for i in indexes)
  matches.append((score,rel,p,lines,indexes))
matches.sort(key=lambda x:-x[0])
print('AM257_MATCHING_FILES',len(matches))
for score,rel,p,lines,indexes in matches[:70]:
 print('AM257_FILE',rel,'LINES',len(lines),'MATCHES',len(indexes),'SCORE',score)
 selected=set()
 top=[i for i in indexes if re.search(r'keydown|ArrowDown|ArrowUp|Enter|Tab|keyCode|\\.key|e-ref-search|pickItem|selectItem',lines[i],re.I)]
 for i in top[:34]:
  selected.update(range(max(0,i-6),min(len(lines),i+13)))
 selected.update(range(min(len(lines),16)))
 for i in sorted(selected)[:205]:
  v=lines[i].strip()
  if danger.search(v):v='[REDACTED_SOURCE]'
  print('AM257_CODE',rel,i+1,v[:500])
print('AM257_KEYBOARD_SOURCE_AUDIT_COMPLETE_READ_ONLY')

print('AM257_TARGETED_LINES_BEGIN')
ranges={
 'public/js/estimate-entry.js':[(98,150),(155,272)],
 'public/js/purchase-entry.js':[(35,105),(160,355),(360,465)],
 'public/js/purchase-edit.js':[(125,313)],
 'resources/views/estimates/create.blade.php':[(350,395)],
 'resources/views/purchases/create.blade.php':[(520,580)],
 'resources/views/purchases/edit.blade.php':[(470,528)]
}
for rel,segments in ranges.items():
 p=root/rel
 if not p.is_file():continue
 ls=p.read_text('utf8','replace').splitlines()
 for start,end in segments:
  for i in range(start-1,min(end,len(ls))):
   v=ls[i].strip()
   if danger.search(v):v='[REDACTED_SOURCE]'
   print('AM257_DETAIL',rel,i+1,v[:530])
for p in (root/'resources/views').rglob('*.blade.php'):
 rel=p.relative_to(root).as_posix()
 if any(x in rel.lower() for x in ['counter','direct-sale','sales/','sales-','invoices/create','sales-invoice']):
  print('AM257_SALES_FILE',rel,'lines',len(p.read_text('utf8','replace').splitlines()))
print('AM257_TARGETED_LINES_END')
