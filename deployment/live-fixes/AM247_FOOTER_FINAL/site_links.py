#!/usr/bin/env python3
"""AM247 FINAL: update public website footer Admin/Staff links to user-confirmed staging URLs.
Usage: python3 site_links.py check|apply|rollback STAGING_ROOT SITE_PUBLIC_HTML
No database writes, ERP auth changes, or unrelated website edits.
"""
from pathlib import Path
from datetime import datetime
from urllib.parse import urlsplit
import hashlib, json, os, re, subprocess, sys, tempfile

if len(sys.argv) != 4 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('USAGE_AM247_FINAL check|apply|rollback STAGING_ROOT SITE_ROOT')
mode = sys.argv[1]
stage = Path(sys.argv[2]).expanduser().resolve()
site = Path(sys.argv[3]).expanduser().resolve()
if stage.name != 'assam-erp-staging' or not (stage/'artisan').is_file() or site.name != 'public_html':
    raise SystemExit('STOP_AM247_FINAL_BAD_ROOT')
if stage in site.parents or site in stage.parents or stage == site:
    raise SystemExit('STOP_AM247_FINAL_ROOT_OVERLAP')
targets = {
    'admin': 'https://staging.assammotors.com/erp/login',
    'staff': 'https://staging.assammotors.com/staff/login',
}
backup_base = stage/'storage/app/am-backups'
pointer = backup_base/'AM247_FINAL_LAST_BACKUP.txt'
anchors = re.compile(r'<a\b[^>]{0,2500}>[\s\S]{0,800}?</a\s*>',re.I)
href = re.compile(r'\bhref\s*=\s*(?P<q>["\'])(?P<url>[^"\']*)(?P=q)',re.I)

def sha(blob): return hashlib.sha256(blob).hexdigest()
def atomic(path,blob):
    fd,tmp=tempfile.mkstemp(prefix='.am247final_',dir=path.parent)
    try:
        with os.fdopen(fd,'wb') as f:f.write(blob)
        os.chmod(tmp,path.stat().st_mode&0o777)
        os.replace(tmp,path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
def role_for(url, body):
    p=urlsplit(url.strip())
    if p.scheme and p.scheme not in ('http','https'):return None
    if p.hostname and p.hostname.lower() not in ('assammotors.com','www.assammotors.com','staging.assammotors.com'):
        return None
    path=p.path.lstrip('/').lower()
    label=re.sub(r'<[^>]+>',' ',body)
    label=re.sub(r'\s+',' ',label).lower()
    if path in ('admin/admin.html','erp/login') and 'admin' in label:return 'admin'
    if path in ('staff.html','staff/login') and 'staff' in label:return 'staff'
    return None
def lint(path,blob):
    if path.suffix.lower()!='.php':return
    with tempfile.NamedTemporaryFile(suffix='.php',delete=False) as f:
        f.write(blob); temp=Path(f.name)
    try:
        x=subprocess.run(['php','-l',str(temp)],capture_output=True,text=True,timeout=25)
        if x.returncode:raise SystemExit('STOP_AM247_FINAL_PHP_LINT '+(x.stdout+x.stderr)[-300:])
    finally:temp.unlink(missing_ok=True)

if mode=='rollback':
    if not pointer.is_file():raise SystemExit('STOP_AM247_FINAL_NO_BACKUP_POINTER')
    back=Path(pointer.read_text().strip()).resolve()
    if backup_base.resolve() not in back.parents or not back.name.startswith('AM247_FINAL_'):
        raise SystemExit('STOP_AM247_FINAL_BACKUP_PATH')
    rows=json.loads((back/'manifest.json').read_text())['files']
    for row in rows:
        t=(site/row['relative']).resolve()
        if site not in t.parents or not t.exists() or sha(t.read_bytes())!=row['installed_sha']:
            raise SystemExit('STOP_AM247_FINAL_CHANGED_AFTER_DEPLOY_'+row['relative'])
    for row in rows:atomic(site/row['relative'],(back/row['relative']).read_bytes())
    print('AM247_FINAL_PREVIOUS_FOOTER_LINKS_RESTORED')
    sys.exit(0)

files=[site/name for name in ('index.html','index.php','index.htm') if (site/name).is_file()]
if not files:raise SystemExit('STOP_AM247_FINAL_NO_WEBSITE_INDEX')
matches={r:[] for r in targets}
raw={}
updated={}
for path in files:
    raw[path]=path.read_bytes()
    if len(raw[path])>1500000:raise SystemExit('STOP_AM247_FINAL_HOMEPAGE_TOO_LARGE')
    src=raw[path].decode('utf8','surrogateescape')
    spans=[]
    for m in anchors.finditer(src):
        hm=href.search(m.group(0))
        if not hm:continue
        value=hm.group('url')
        role=role_for(value,m.group(0))
        if role is None:continue
        matches[role].append((path,value))
        if value != targets[role]:
            part=m.group(0)
            changed=part[:hm.start('url')]+targets[role]+part[hm.end('url'):]
            spans.append((m.start(),m.end(),changed))
    for a,b,frag in reversed(spans):
        src=src[:a]+frag+src[b:]
    new=src.encode('utf8','surrogateescape')
    if new!=raw[path]:
        lint(path,new)
        updated[path]=new

for role in targets:
    print('AM247_FINAL_'+role.upper()+'_FOOTER_MATCHES='+str(len(matches[role])))
    for path,url in matches[role]:
        print('AM247_FINAL_'+role.upper()+'_SOURCE='+path.name+' OLD='+url+' NEW='+targets[role])
    if not matches[role] or len(matches[role])>3:
        raise SystemExit('STOP_AM247_FINAL_MISSING_OR_AMBIGUOUS_'+role.upper())
if not updated:
    print('AM247_FINAL_BOTH_LINKS_ALREADY_CORRECT_NO_CHANGE')
    sys.exit(0)
print('AM247_FINAL_PUBLIC_SITE_HREF_PREFLIGHT_PASS')
print('AM247_FINAL_NO_DATABASE_OR_AUTH_EDITS')
if mode=='check':
    print('AM247_FINAL_READONLY_CHECK_PASS')
    sys.exit(0)

backup_base.mkdir(parents=True,exist_ok=True)
back=backup_base/('AM247_FINAL_'+datetime.now().strftime('%Y%m%d_%H%M%S'))
back.mkdir(mode=0o700,exist_ok=False)
rows=[]
for path,new in updated.items():
    rel=path.relative_to(site)
    (back/rel).write_bytes(raw[path])
    rows.append({'relative':str(rel),'installed_sha':sha(new)})
written=[]
try:
    for path,new in updated.items():
        if path.read_bytes()!=raw[path]:raise RuntimeError('SITE_CHANGED_BETWEEN_CHECK_AND_WRITE')
        atomic(path,new);written.append(path)
except BaseException as exc:
    for path in written:atomic(path,raw[path])
    raise SystemExit('STOP_AM247_FINAL_ROLLED_BACK_'+str(exc))
(back/'manifest.json').write_text(json.dumps({'files':rows},indent=2))
pointer.write_text(str(back)+'\n')
print('AM247_FINAL_PUBLIC_ADMIN_STAFF_LINKS_UPDATED')
print('AM247_FINAL_ADMIN_URL='+targets['admin'])
print('AM247_FINAL_STAFF_URL='+targets['staff'])
print('AM247_FINAL_BACKUP='+str(back))
