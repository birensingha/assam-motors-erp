#!/usr/bin/env bash
set -euo pipefail
cd "$1"
test -f artisan
python3 - <<'PY'
from pathlib import Path
p=Path('resources/views/job-cards/show.blade.php')
lines=p.read_text(errors='replace').splitlines()
print('AM277_JC_DETAIL_START',len(lines))
for term in ['am100','am85-part-options','am85-part-search','am85-part-id','am85-part-form']:
 idx=[i for i,l in enumerate(lines) if term.lower() in l.lower()]
 print('AM277_JC_TERM',term,'COUNT',len(idx),'LINES',[x+1 for x in idx[:25]])
 for i in idx[:4]:
  a=max(0,i-8);b=min(len(lines),i+38)
  for n in range(a,b):print('AM277_JC_LINE',n+1,lines[n][:480])
print('AM277_JC_DETAIL_END')
PY
echo AM277_JC_READONLY_PASS
