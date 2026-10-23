#!/usr/bin/env bash
# ASSAM MOTORS: preflight for replacing standalone ROT Alteration with inline Assignment controls.
# Staging read-only. Does not edit Laravel, routes, database, or staff records.
set -euo pipefail
ROOT="$HOME/domains/assammotors.com/assam-erp-staging"
if [ "$#" -gt 0 ]; then ROOT="$1"; fi
cd "$ROOT"
test -f artisan || { echo 'STOP: Not a Laravel root'; exit 1; }
python3 - <<'PY'
from pathlib import Path
import re
root=Path.cwd()
print('UNIFIED_ROT_PANEL_READ_ONLY_PREFLIGHT')
needles={
 'LEGACY_PAGE':r'rot[-_ ]alteration|ROT Alteration|Lock\s*/\s*Alter|Save Job Card\s*[—-]\s*ROT Alteration',
 'ASSIGNMENT':r'Assign ROT|rot-card|rot-list|assign-mechanic|rot\.assign',
 'ALTER_DELETE':r'am90-edit|am82-finance-form|standard_hours|std.hours|labour\.delete-admin|planned-rot-delete',
}
files=list((root/'resources/views').rglob('*.blade.php'))+list((root/'routes').rglob('*.php'))
controllers=root/'app/Http/Controllers'
if controllers.is_dir(): files+=list(controllers.rglob('*.php'))
for label,pattern in needles.items():
  rx=re.compile(pattern,re.I)
  print('\n### '+label)
  total=0
  for p in sorted(files):
    try: rows=p.read_text(encoding='utf8',errors='replace').splitlines()
    except OSError: continue
    hits=[(n,line) for n,line in enumerate(rows,1) if rx.search(line)]
    for n,line in hits[:(12 if label=='LEGACY_PAGE' else 4)]:
      if total>=75: break
      print(str(p.relative_to(root))+':'+str(n)+': '+re.sub(r'\s+',' ',line.strip())[:210])
      total+=1
  print('OUTPUT_REFERENCE_COUNT='+str(total))
for rel in ['resources/views/job-cards/show.blade.php',
            'resources/views/job-cards/rot-alteration.blade.php',
            'resources/views/job-cards/partials/planned-rot-delete.blade.php']:
  p=root/rel
  print('FILE '+rel+' PRESENT='+str(p.is_file()))
  if p.is_file():
    s=p.read_text(encoding='utf8',errors='replace')
    print('  finance_edit='+str('am90-edit' in s or 'am82-finance-form' in s))
    print('  delete='+str('planned-rot-delete' in s or 'labour.delete-admin' in s))
    print('  assign='+str('Assign ROT' in s or 'rot.assign' in s))
print('PREFLIGHT_DONE_NO_CHANGES')
PY
printf '\n### JOB CARD ROUTES\n'
php artisan route:list --path=job-cards 2>/dev/null | grep -Ei 'alter|amount|labour|rot|job.cards' | head -60 || true
echo 'NO FILES, ROUTES OR DATA MODIFIED'
