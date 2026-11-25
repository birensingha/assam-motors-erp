#!/usr/bin/env python3
"""AM259 fix TYPE -> REFERENCE Tab skip in Estimate editor.
Usage: install.py check|apply|verify|rollback STAGING_LARAVEL_ROOT.
Safe: JS focus only; no financial, DB, auth, or customer changes.
"""
from pathlib import Path
from datetime import datetime
import hashlib
import json
import os
import re
import subprocess
import sys
import tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','verify','rollback'):
    raise SystemExit('AM259_USAGE check|apply|verify|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
relative='public/js/estimate-entry.js'
target=root/relative
backups=root/'storage/app/am-backups'
pointer=backups/'AM259_LAST_BACKUP.txt'
marker='AM259_TYPE_TAB_REFERENCE_FOCUS'
if root.name!='assam-erp-staging' or not (root/'artisan').is_file() or not target.is_file():
    raise SystemExit('STOP_AM259_ONLY_VALID_STAGING_ROOT')

def digest(raw):return hashlib.sha256(raw).hexdigest()

def atomic(p,raw):
    p.parent.mkdir(parents=True,exist_ok=True)
    fd,name=tempfile.mkstemp(prefix='.am259_',dir=p.parent)
    try:
        with os.fdopen(fd,'wb') as fp:fp.write(raw)
        os.chmod(name,(p.stat().st_mode & 0o777) if p.exists() else 0o644)
        os.replace(name,p)
    finally:
        if os.path.exists(name):os.unlink(name)

def run(args,timeout=75):
    return subprocess.run(args,cwd=root,text=True,capture_output=True,timeout=timeout)

def target_blocks(src):
    start=src.find("type.addEventListener('change', () => {")
    if start<0 or src.count("type.addEventListener('change', () => {")!=1:
        raise SystemExit('STOP_AM259_TYPE_CHANGE_HANDLER_UNEXPECTED')
    end=src.find("refSearch.addEventListener('focus',",start)
    if end<0 or end-start>2400:
        raise SystemExit('STOP_AM259_TYPE_CHANGE_BOUNDARY_UNEXPECTED')
    return start,end

def patch():
    raw=target.read_bytes()
    src=raw.decode('utf8','surrogateescape')
    if marker in src:raise SystemExit('STOP_AM259_ALREADY_PATCHED_USE_VERIFY')
    # Refocus during a SELECT change is the root cause. Browsers fire change
    # before completing native Tab navigation, skipping the next control.
    start,end=target_blocks(src)
    block=src[start:end]
    pat=re.compile(r"(closeMenu\(tr\);\s*recalc\(\);\s*)refSearch\.focus\(\);",re.M)
    matches=list(pat.finditer(block))
    if len(matches)!=1:
        raise SystemExit('STOP_AM259_CHANGE_REFOCUS_ANCHOR_COUNT_'+str(len(matches)))
    replacement=(
       r"\1// AM259_TYPE_TAB_REFERENCE_FOCUS: let native Tab go TYPE -> REFERENCE."
       "\n      // Never focus the Reference programmatically from TYPE change."
    )
    newblock=pat.sub(replacement,block,count=1)
    # Never modify Search input click/keyboard/Enter reference picker code.
    changed=src[:start]+newblock+src[end:]
    if changed.count(marker)!=1:
        raise SystemExit('STOP_AM259_PATCH_MARKER_NOT_UNIQUE')
    if changed.count("refSearch.addEventListener('keydown'")!=src.count("refSearch.addEventListener('keydown'"):
        raise SystemExit('STOP_AM259_REFERENCE_KEYBOARD_HANDLER_CHANGED')
    if changed.count("AM258_ALWAYS_READY_ENTRY_ROW")!=src.count("AM258_ALWAYS_READY_ENTRY_ROW"):
        raise SystemExit('STOP_AM259_PREVIOUS_BLANK_ROW_GUARD_CHANGED')
    print('AM259_TYPE_CHANGE_FOCUS_CONFLICT_IDENTIFIED')
    print('AM259_PREVIOUS_AM257_ITEM_PICK_AND_AM258_BLANK_ROW_PRESERVED')
    print('AM259_PATCH_ONLY_TYPE_CHANGE_FOCUS')
    return raw,changed.encode('utf8','surrogateescape')

def verify():
    src=target.read_text('utf8','replace')
    if src.count(marker)!=1:raise SystemExit('STOP_AM259_MARKER_NOT_FOUND')
    start,end=target_blocks(src)
    block=src[start:end]
    if re.search(r'refSearch\.focus\(\)',block):
        raise SystemExit('STOP_AM259_REFOCUS_STILL_INSIDE_TYPE_CHANGE')
    if not all(x in src for x in (
       "refSearch.addEventListener('keydown'",
       "AM257_PICKER_ENTER_TAB",
       "AM258_ALWAYS_READY_ENTRY_ROW"
    )):
        raise SystemExit('STOP_AM259_PREVIOUS_EDITOR_CONTROLS_NOT_PRESENT')
    print('AM259_TYPE_TAB_REFERENCE_SKIP_FIX_VERIFIED')
    print('AM259_ESTIMATE_REFERENCE_ENTER_TAB_AND_READY_ROW_PRESERVED')
    print('AM259_NO_FINANCIAL_DATABASE_OR_OTHER_FORMS_CHANGED')

if mode=='verify':verify();sys.exit(0)
if mode=='rollback':
    if not pointer.is_file():raise SystemExit('STOP_AM259_NO_BACKUP')
    backup=Path(pointer.read_text().strip()).resolve()
    if backups.resolve() not in backup.parents or not backup.name.startswith('AM259_TYPE_TAB_'):
        raise SystemExit('STOP_AM259_BAD_BACKUP_LOCATION')
    meta=json.loads((backup/'manifest.json').read_text())
    if digest(target.read_bytes())!=meta['installed']:
        raise SystemExit('STOP_AM259_FILE_MODIFIED_SINCE_INSTALL')
    atomic(target,(backup/'estimate-entry.js.original').read_bytes())
    print('AM259_ROLLBACK_SOURCE_ONLY')
    sys.exit(0)

old,new=patch()
if mode=='check':
    print('AM259_CHECK_READ_ONLY_PASS')
    sys.exit(0)
backup=backups/('AM259_TYPE_TAB_'+datetime.now().strftime('%Y%m%d_%H%M%S')+'_'+str(os.getpid()))
backup.mkdir(parents=True,exist_ok=False);os.chmod(backup,0o700)
(backup/'estimate-entry.js.original').write_bytes(old)
os.chmod(backup/'estimate-entry.js.original',0o600)
try:
    if target.read_bytes()!=old:raise RuntimeError('SOURCE_CHANGED_AFTER_PREFLIGHT')
    atomic(target,new)
    verify()
    # The script is served inline via resources/views/estimates/create.blade.php,
    # so clear cached Blade output and leave all templates untouched.
    result=run(['php','artisan','view:clear'])
    if result.returncode:raise RuntimeError('LARAVEL_VIEW_CLEAR_FAILED '+(result.stdout+result.stderr)[-700:])
except Exception as error:
    atomic(target,old)
    raise SystemExit('STOP_AM259_RESTORED_ORIGINAL_JS '+str(error))
(backup/'manifest.json').write_text(json.dumps({'installed':digest(new)},indent=2))
os.chmod(backup/'manifest.json',0o600)
pointer.write_text(str(backup)+'\n')
os.chmod(pointer,0o600)
print('AM259_ESTIMATE_TYPE_TO_REFERENCE_TAB_SKIP_FIXED')
print('AM259_STAGING_SOURCE_BACKUP',backup)
print('AM259_USER_BROWSER_KEYBOARD_TEST_PENDING')
