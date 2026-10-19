#!/usr/bin/env python3
"""FIX54 source-only patcher: exact anchors, no writes until every target passes."""
import json,sys,os,tempfile
from pathlib import Path
if len(sys.argv)!=4 or sys.argv[1] not in ('--check','--apply'):
    raise SystemExit("Usage: patcher.py --check|--apply ROOT manifest")
mode,root,manifest=sys.argv[1:]
root=Path(root).resolve()
spec=json.loads(Path(manifest).read_text())
if spec.get('fix')!='FIX54_SIMPLE_PARTS':raise SystemExit('Unexpected manifest')
safe={'show.blade.php','AdminJobCardPageController.php','AdminJobCardLifecyclePageController.php','EstimateService.php'}
pending=[]
for item in spec['files']:
    rel=Path(item['path'])
    target=(root/rel).resolve()
    if rel.is_absolute() or '..' in rel.parts or rel.name not in safe or not target.is_relative_to(root) or not target.is_file():
        raise SystemExit('Unexpected source file')
    data=target.read_bytes()
    crlf=b'\r\n' in data
    content=data.decode('utf8','surrogateescape').replace('\r\n','\n')
    for op in item['ops']:
        if op['kind']=='replace':
            count=content.count(op['old'])
            print('FIX54_ANCHOR',rel.name,count)
            if count!=1:raise SystemExit('FIX54_PREFLIGHT_FAILED '+str(rel))
            content=content.replace(op['old'],op['new'],1)
        elif op['kind']=='span':
            start,end=op['start'],op['end']
            if content.count(start)!=1 or content.count(end)!=1:
                raise SystemExit('FIX54_SPAN_AMBIGUOUS '+str(rel))
            a=content.index(start);b=content.index(end)
            if b<=a:raise SystemExit('FIX54_SPAN_ORDER '+str(rel))
            print('FIX54_SPAN_OK',rel.name,start[:46])
            content=content[:a]+op['new']+content[b:]
        else:raise SystemExit('Unexpected patch operation')
    if crlf:content=content.replace('\n','\r\n')
    pending.append((target,content.encode('utf8','surrogateescape')))
if len(pending)!=4:raise SystemExit('Expected 4 source files')
if mode=='--check':
    print('FIX54_SOURCE_PREFLIGHT_PASS_NO_WRITES')
    sys.exit(0)
for target,out in pending:
    fd,temp=tempfile.mkstemp(prefix='.fix54.',dir=target.parent)
    try:
        with os.fdopen(fd,'wb') as fh:fh.write(out)
        os.chmod(temp,target.stat().st_mode&0o777)
        os.replace(temp,target)
        print('FIX54_PATCHED',target.name)
    finally:
        if os.path.exists(temp):os.unlink(temp)
