#!/usr/bin/env python3
"""FIX53 fail-closed staging patcher. All 4 files checked before any write."""
import json, os, sys, tempfile
from pathlib import Path
if len(sys.argv)!=4 or sys.argv[1] not in ('--check','--apply'):
    raise SystemExit('Usage: patcher.py --check|--apply ROOT manifest.json')
mode,root,manifest=sys.argv[1:]
root=Path(root).resolve()
spec=json.loads(Path(manifest).read_text())
if spec.get('fix')!='FIX53_JOBCARD_PROCESS_STAGE1':raise SystemExit('Incorrect manifest')
safe_names={'show.blade.php','form.blade.php','edit.blade.php','AdminJobCardPageController.php'}
todo=[]
for entry in spec['files']:
    rel=Path(entry['path'])
    if rel.is_absolute() or '..' in rel.parts or rel.name not in safe_names:
        raise SystemExit('Unexpected target path')
    dest=(root/rel).resolve()
    if not dest.is_relative_to(root) or not dest.is_file():raise SystemExit('Missing expected source '+str(rel))
    original=dest.read_bytes()
    crlf=b'\r\n' in original
    text=original.decode('utf-8','surrogateescape').replace('\r\n','\n')
    for op in entry['ops']:
        if op['kind']=='replace':
            old,new=op['old'],op['new']
            n=text.count(old)
            print('FIX53_REPLACE_MATCH',rel.name,n)
            if n!=1:raise SystemExit('FIX53_CHECK_FAILED '+str(rel))
            text=text.replace(old,new,1)
        elif op['kind']=='span':
            start,end=op['start'],op['end']
            if text.count(start)!=1 or text.count(end)!=1:raise SystemExit('FIX53_SPAN_MISSING '+str(rel))
            a=text.index(start);b=text.index(end)
            if b<=a:raise SystemExit('FIX53_SPAN_ORDER_FAILED '+str(rel))
            print('FIX53_SPAN_MATCH',rel.name,start[:45])
            text=text[:a]+op['new']+text[b:]
        else:raise SystemExit('Invalid operation type')
    if crlf:text=text.replace('\n','\r\n')
    todo.append((dest,text.encode('utf-8','surrogateescape')))
if len(todo)!=4:raise SystemExit('Expected exactly 4 source files')
if mode=='--check':print('FIX53_SOURCE_PREFLIGHT_PASS_NO_WRITES');sys.exit(0)
for dest,b in todo:
    fd,temp=tempfile.mkstemp(prefix='.fix53.',dir=dest.parent)
    try:
        with os.fdopen(fd,'wb') as out:out.write(b)
        os.chmod(temp,dest.stat().st_mode&0o777)
        os.replace(temp,dest)
        print('FIX53_PATCHED',dest.name)
    finally:
        if os.path.exists(temp):os.unlink(temp)
