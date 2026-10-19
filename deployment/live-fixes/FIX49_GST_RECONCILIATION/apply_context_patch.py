#!/usr/bin/env python3
"""Apply a two-file unified diff with exact context only (no fuzzy matches)."""
import sys, os, tempfile
from pathlib import Path

if len(sys.argv)!=4 or sys.argv[1] not in ('--check','--apply'):
    raise SystemExit('usage: script --check|--apply root diff')
mode, root, patchfile=sys.argv[1:]
root=Path(root).resolve()
lines=Path(patchfile).read_text().splitlines(keepends=True)
hunks=[]
i=0
while i<len(lines):
    if not lines[i].startswith('--- a/'):
        raise SystemExit('unsupported patch header')
    relative=lines[i][6:].strip()
    i+=1
    if not lines[i].startswith('+++ b/') or lines[i][6:].strip()!=relative:
        raise SystemExit('patch path mismatch')
    i+=1
    if not lines[i].startswith('@@ '):raise SystemExit('hunk missing')
    i+=1
    before=[];after=[]
    while i<len(lines) and not lines[i].startswith('--- a/'):
        line=lines[i];i+=1
        if line.startswith(' '):before.append(line[1:]);after.append(line[1:])
        elif line.startswith('+'):after.append(line[1:])
        elif line.startswith('-'):before.append(line[1:])
        else:raise SystemExit('invalid unified diff')
    relative=Path(relative)
    if relative.is_absolute() or '..' in relative.parts or relative.name not in ('AdminJobCardPageController.php','AdminJobCardLifecyclePageController.php'):
        raise SystemExit('unsafe patch path')
    target=(root/relative).resolve()
    if not target.is_relative_to(root):raise SystemExit('target escapes root')
    original=target.read_bytes()
    crlf=b'\r\n' in original
    normalized=original.replace(b'\r\n',b'\n')
    old=''.join(before).encode('utf8')
    new=''.join(after).encode('utf8')
    count=normalized.count(old)
    print('EXACT_MATCH',target.name,count)
    if count!=1:raise SystemExit('expected exactly one anchor; no change made')
    updated=normalized.replace(old,new,1)
    if crlf:updated=updated.replace(b'\n',b'\r\n')
    hunks.append((target,updated))
if len(hunks)!=2:raise SystemExit('expected 2 controllers')
if mode=='--apply':
    for target,updated in hunks:
        fd,temp=tempfile.mkstemp(prefix='.fix49.',dir=target.parent)
        try:
            with os.fdopen(fd,'wb') as fp:fp.write(updated)
            os.chmod(temp,target.stat().st_mode&0o777)
            os.replace(temp,target)
            print('PATCHED',target.name)
        finally:
            if os.path.exists(temp):os.unlink(temp)
else:
    print('FIX49_EXACT_ANCHOR_DRY_RUN_PASS')
