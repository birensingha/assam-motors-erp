#!/usr/bin/env python3
"""AM260 read-only overview: form input layouts, custom autocomplete handlers, scripts.
Never read DB or user data.
"""
from pathlib import Path
import re,sys,collections
if len(sys.argv)!=2:raise SystemExit("USAGE AM260 audit.py STAGING_ROOT")
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').exists():raise SystemExit('STOP_AM260_NOT_STAGING')
views=root/'resources/views'
summary=collections.Counter()
candidates=[]
for p in views.rglob('*.blade.php'):
 if p.stat().st_size>600000:continue
 name=p.relative_to(root).as_posix()
 s=p.read_text('utf8','replace')
 if '<form' not in s.lower():continue
 forms=len(re.findall(r'<form\b',s,re.I))
 inherits=re.findall(r'@extends\(\s*[\'"]([^\'"]+)',s)
 custom=len(re.findall(r'keydown|addEventListener\([\'"]key(?:down|up)|onkeydown|onkeypress',s,re.I))
 autocomplete=len(re.findall(r'autocomplete|listbox|combobox|select2|choices\.js|tomselect|select-search|searchable',s,re.I))
 lineitems=bool(re.search(r'add.line|add.row|tbody.*line|item.row|line.item|row-template',s,re.I))
 base=(inherits[0] if inherits else 'standalone')
 summary[base]+=forms
 candidates.append((custom+autocomplete+int(lineitems)*4,name,forms,base,custom,autocomplete,lineitems))
print("AM260_VIEW_COUNT",len(candidates),"LAYOUT_FORM_COUNTS",dict(summary))
for _,name,forms,base,custom,autocomp,line in sorted(candidates,reverse=True)[:120]:
 print("AM260_VIEW_FORM",name,"FORM_COUNT",forms,"BASE",base,"KEY_HANDLERS",custom,"SEARCH_INPUTS",autocomp,"MULTILINE",int(line))
for rel in ['resources/views/layouts/erp.blade.php','resources/views/layouts/new-admin.blade.php',
'resources/views/partials/am-global-selector.blade.php','resources/views/estimates/create.blade.php',
'resources/views/purchases/create.blade.php','resources/views/purchases/edit.blade.php']:
 p=root/rel
 if not p.is_file():continue
 lines=p.read_text('utf8','replace').splitlines()
 if 'layouts' in rel:
  matches=[i for i,line in enumerate(lines) if re.search(r'<body|</body>|@include|@stack|@yield|<script|autocomplete|keyboard',line,re.I)]
 elif 'am-global-selector' in rel:
  matches=[i for i,line in enumerate(lines) if re.search(r'key(?:down|up)|Tab|Enter|Arrow|closest|chosen\(|addEventListener',line)]
 else:
  matches=[i for i,line in enumerate(lines) if re.search(r'<form|id=".*form|<select|<input|data-[a-z]+|add.line|add.row',line,re.I)]
 selected=set()
 for i in matches[-70:]:
  selected.update(range(max(0,i-2),min(len(lines),i+3)))
 print("AM260_VIEW_FILE",rel,"LINES",len(lines))
 for i in sorted(selected)[-170:]:
  l=lines[i].strip()
  if re.search(r'password|APP_KEY|secret|token|csrf_token|cookie',l,re.I):l='[REDACTED]'
  print("AM260_SOURCE",rel,i+1,l[:460])
print("AM260_READ_ONLY_AUDIT_COMPLETE")
