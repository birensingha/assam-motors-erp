#!/usr/bin/env python3
"""AM237 read-only visual source audit. No changes and no business data."""
from pathlib import Path
import sys,re
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
 raise SystemExit('AM237_INVALID_STAGING')
view=root/'resources/views/purchases/create.blade.php'
js=root/'public/js/purchase-entry.js'
if not view.is_file() or not js.is_file():raise SystemExit('AM237_MISSING_SOURCE')
lines=view.read_text('utf8','replace').splitlines()
print('AM237_SOURCE_LINES',len(lines))
for i,l in enumerate(lines,1):
 if i<=180 or 255<=i<=315 or 328<=i<=348 or 401<=i<=445:
  if re.search(r'password|secret|api_key|private_key|env\(',l,re.I):l='[REDACTED]'
  print('AM237_VIEW',i,l[:550])
print('AM237_JS_LINE_COUNT',len(js.read_text('utf8','replace').splitlines()))
print('AM237_AUDIT_COMPLETE_NO_CHANGES')
