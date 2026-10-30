#!/usr/bin/env python3
"""Read-only sources of edit forms, triggers and wrapper HTML. Never print rows/DB."""
import re,sys
from pathlib import Path
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!="assam-erp-staging" or not (root/"artisan").is_file():raise SystemExit("AM217_STOP_NOT_STAGING")
for section in ('parts','labour'):
    source=(root/f'resources/views/admin/native/{section}.blade.php').read_text('utf8','replace')
    print('AM217_VIEW',section,'LINES',len(source.splitlines()))
    for i,line in enumerate(source.splitlines(),1):
        if re.search(r"(?i)(amToggle|onclick=|edit|<details|</details|<summary|<tr\b|<td\b|<form\b|</form|type=.submit|Save\s+(Part|ROT|Labour))",line):
            clean=re.sub(r'value=["\x27][^"\x27]{80,}["\x27]','value="[omitted]"',line)
            print('AM217_LINE',i,clean[:1700])
            if i>65:break
    print('AM217_END',section)
print('AM217_EDIT_HTML_READ_ONLY_PASS')
