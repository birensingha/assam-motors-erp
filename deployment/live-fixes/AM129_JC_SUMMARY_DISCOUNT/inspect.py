#!/usr/bin/env python3
"""Read-only discovery for Job Card Summary discount-column change.
Never modifies production data, views, or ledger.
"""
from pathlib import Path
import hashlib,re,sys
root=Path(sys.argv[1]).expanduser().resolve() if len(sys.argv)>1 else Path.cwd()
if not (root/"artisan").is_file():
    raise SystemExit("ERROR: provide Laravel staging app root")
target=root/"resources/views/job-cards/summary-print.blade.php"
if not target.is_file(): raise SystemExit("ERROR: summary-print.blade.php not found")
data=target.read_text(encoding="utf-8",errors="replace")
print("TARGET",target.relative_to(root),"SHA256",hashlib.sha256(target.read_bytes()).hexdigest(),"LINES",len(data.splitlines()))
lines=data.splitlines()
needles=r"(?i)discount|dis%|dis amt|<table|</table>|<thead|<tbody|<th[ >]|<td[ >]|foreach|parts|labour|line|print"
matches=[i for i,line in enumerate(lines) if re.search(needles,line)]
shown=set()
for i in matches:
    for j in range(max(0,i-1),min(len(lines),i+2)):
        if j in shown: continue
        shown.add(j)
        line=lines[j]
        # Avoid printing form inputs, account details or arbitrary secrets.
        if re.search(r"(?i)password|secret|token|api_key|private_key|authorization|csrf|bank.account",line): continue
        print(f"{j+1:04d}: {line[:350]}")
        if len(shown)>=240: break
    if len(shown)>=240: break
print("READ_ONLY_INSPECTION_COMPLETE")
