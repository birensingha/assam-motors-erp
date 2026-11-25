#!/usr/bin/env bash
set -euo pipefail
cd "$1"
test -f artisan
python3 - <<'PY'
from pathlib import Path
s=Path('resources/views/job-cards/show.blade.php').read_text(errors='replace').splitlines()
for a,b in [(1150,1245),(2060,2140),(2350,2430),(2550,2640),(3850,3930)]:
 print('AM277_JC_EXCERPT',a,b)
 for i in range(a-1,min(b,len(s))):print('JC',i+1,s[i][:500])
print('AM277_JC_EXCERPT_FINISHED')
PY
