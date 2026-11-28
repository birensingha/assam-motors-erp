#!/usr/bin/env python3
from pathlib import Path
import sys,re
root=Path(sys.argv[1]).resolve()
assert (root/'artisan').is_file()
paths=[root/'resources/views/job-cards/form.blade.php']
paths+=list((root/'app/Http/Controllers').rglob('*JobCard*Controller.php'))
paths+=list((root/'app/Models').glob('*JobCard*.php'))
paths+=list((root/'app/Services').glob('*JobCard*.php'))
for p in paths:
 if not p.is_file():continue
 lines=p.read_text(errors='replace').splitlines()
 hits=[i for i,s in enumerate(lines) if re.search(r'odometer|kilometer|kilometre|mileage|function update\b|function edit\b|modification.reason|function rules\b|fillable|job.card.edit.audit',s,re.I)]
 if not hits:continue
 print('FILE',p.relative_to(root))
 idx=set()
 for i in hits:idx.update(range(max(0,i-15),min(len(lines),i+35)))
 for i in sorted(idx):print(f'{i+1}: {lines[i]}')
print('ODOMETER_SOURCE_AUDIT_PASS')
