#!/usr/bin/env bash
set -euo pipefail
cd "$1"
test -f artisan
python3 - <<'PY'
from pathlib import Path
p=Path('public/js/estimate-entry.js')
lines=p.read_text().splitlines()
print('AM277_ESTIMATE_JS_START')
for i,line in enumerate(lines):
 if 50 <= i <= 310:print(f'{i+1:03d} {line[:440]}')
print('AM277_ESTIMATE_JS_END')
p=Path('resources/views/job-cards/show.blade.php')
lines=p.read_text().splitlines()
print('AM277_JC_SOURCE_START')
seen=set()
for i,l in enumerate(lines):
 if any(x in l for x in ['am100','AM100','am85-part-options','am85-part-search','am85-part-id']):
  if i in seen:continue
  print('AM277_JC_ANCHOR',i+1,l[:500])
  if any(x in l for x in ['am85-part-options','am100','am85-part-search']):
   for n in range(max(0,i-3),min(len(lines),i+15)):
    if n not in seen:
     print('AM277_JC_LINE',n+1,lines[n][:420]);seen.add(n)
print('AM277_JC_SOURCE_END')
PY
echo AM277_FOCUSED_READONLY_PASS
