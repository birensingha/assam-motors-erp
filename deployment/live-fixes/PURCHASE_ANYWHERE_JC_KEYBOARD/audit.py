#!/usr/bin/env python3
"""Read-only staging audit for purchase search and OSL keyboard fix.
Print only source code from known purchase files; no environment or database data.
"""
from pathlib import Path
import sys,re
root=Path(sys.argv[1]).resolve()
if not (root/'artisan').is_file():raise SystemExit('Not Laravel root')
paths=[root/'app/Http/Controllers/Web/AdminPurchasePageController.php',root/'public/js/purchase-entry.js',root/'public/js/purchase-edit.js',root/'app/Services/PurchaseService.php']
paths+=list((root/'resources/views/purchases').rglob('*.blade.php'))
for p in (root/'resources/views').rglob('*.blade.php'):
 if re.search(r'osl.*purchase|purchase.*osl',str(p.relative_to(root)),re.I):paths.append(p)
pattern=re.compile(r'partSearch|searchParts|renderResults|startsWith|ArrowDown|ArrowUp|job.filter|job.search|job.select|job_card_id|searchJobs|jobResults|keydown',re.I)
for p in dict.fromkeys(paths):
 if not p.is_file():continue
 lines=p.read_text(errors='replace').splitlines()
 hits=[i for i,line in enumerate(lines) if pattern.search(line)]
 if not hits:continue
 print('FILE',p.relative_to(root),flush=True)
 indices=set()
 for i in hits:indices.update(range(max(0,i-12),min(len(lines),i+20)))
 for i in sorted(indices):print(f'{i+1}: {lines[i]}')
print('PURCHASE_SEARCH_AUDIT_PASS')
