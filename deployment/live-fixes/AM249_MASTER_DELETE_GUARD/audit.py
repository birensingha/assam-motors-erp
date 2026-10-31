#!/usr/bin/env python3
"""AM249 READ-ONLY audit of accounting master deletion gates and PB-261031192828-156 code paths."""
from pathlib import Path
import re,sys
if len(sys.argv)!=2:raise SystemExit('AM249_USAGE ROOT')
root=Path(sys.argv[1]).resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM249_STOP_NOT_STAGING')
patterns=[
 r'function\s+(?:destroy|delete|remove|void|archive)\s*\(',
 r'->delete\s*\(',r'::destroy\s*\(',r'function\s+.*[Dd]elet',
 r'part.?master.?delete',r'vendor.?delete',r'customer.?delete',
 r'expense.?head',r'purchase_lines|workshop_purchases|part_master_id',
 r'account_heads|expense_heads',r'foreign.?key|onDelete|cascadeOnDelete',
]
pats=[re.compile(x,re.I) for x in patterns]
print('AM249_INDEX_BEGIN')
candidates=[]
for directory in ['app/Http/Controllers/Web','app/Services','routes','database/migrations','resources/views']:
 path=root/directory
 if not path.exists():continue
 files=[path] if path.is_file() else path.rglob('*.php')
 for p in files:
  if p.stat().st_size>350000:continue
  lines=p.read_text('utf8','replace').splitlines()
  hits=[i for i,v in enumerate(lines) if any(q.search(v) for q in pats)]
  name=str(p.relative_to(root))
  if not hits:continue
  focus=bool(re.search(r'(Master|Part|Vendor|Customer|Expense|Purchase|Head|Configuration|Ledger|web\.php|migration)',name,re.I))
  if focus:
   candidates.append((p,lines,hits))
   print('AM249_FILE',name,'N',len(lines),'HITS',','.join(str(i+1) for i in hits[:90]))
print('AM249_INDEX_END')
for p,lines,hits in candidates:
 name=str(p.relative_to(root))
 if 'Controller' not in name and not name.endswith('routes/web.php') and 'migrations' not in name:continue
 if not re.search(r'(Master|Part|Vendor|Customer|Expense|Purchase|Ledger|web\.php|migration)',name,re.I):continue
 picked=[]
 for i in hits[:65]:
  v=lines[i]
  if re.search(r'(delete|destroy|archive|part_master_id|purchase_lines|expense.head|vendor_id|customer_id|onDelete|cascade)',v,re.I):
   picked.append(i)
 if not picked:continue
 print('AM249_SNIPPET_BEGIN',name)
 already=set()
 for i in picked[:28]:
  for k in range(max(0,i-3),min(len(lines),i+8)):
   if k in already:continue
   already.add(k)
   value=lines[k]
   if re.search(r'(password|secret|bearer|authorization|api_key|private_key)',value,re.I):continue
   print('AM249_LINE',k+1,value[:650])
 print('AM249_SNIPPET_END',name)
print('AM249_AUDIT_FINISHED_NO_DB_READS')
