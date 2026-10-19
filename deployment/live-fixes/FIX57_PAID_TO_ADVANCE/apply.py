#!/usr/bin/env python3
import json,sys
from pathlib import Path
root=Path(sys.argv[1])
pkg=Path(sys.argv[2])
mode=sys.argv[3]
changes=[]
for name in ('patch-routes.json','patch-invoice.json','patch-ui.json'):
    item=json.loads((pkg/name).read_text())['changes'][0]
    p=root/item['path']
    old=p.read_bytes()
    needle=item['old'].encode()
    if old.count(needle)!=1:
        raise SystemExit('FIX57 exact match failed: '+item['path'])
    changes.append((p,old.replace(needle,item['new'].encode(),1)))
print('FIX57_FILE_CHECK_PASS')
if mode=='apply':
    for p,data in changes:
        p.write_bytes(data)
        print('FIX57_UPDATED',p.name)
