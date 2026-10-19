#!/usr/bin/env python3
"""Display exact GPS fix age on existing Staff Live V2 desktop and V3 phone UI.

View-only change: no location collection, quality rules, controllers, database,
attendance, ROT, maps, routes or CSS modifications.
Run dry-run first. Apply creates a private backup and rolls back on Blade errors.
"""
import argparse
import datetime
import os
import pathlib
import re
import shutil
import subprocess
import tempfile

parser = argparse.ArgumentParser()
parser.add_argument('--root', required=True)
parser.add_argument('--apply', action='store_true')
args = parser.parse_args()
root = pathlib.Path(args.root).expanduser().resolve()
target = root / 'resources/views/admin/native/staff-live-location.blade.php'
assert (root / 'artisan').is_file() and target.is_file(), 'Staging Blade not found'
original = target.read_text(encoding='utf-8')
view = original
marker = 'AM_STAFF_LIVE_EXACT_GPS_AGE_20261019'
required = [
    'AM_STAFF_LIVE_BLUE_V2_20261018',
    'AM_COMPACT_MOBILE_SOLO_TRACK',
    'AM_RESTORE_DESKTOP_BLUE_V2_MOBILE_SOLO_V31',
    'id="slMapSubtitle"',
    'id="slRecorded"',
    'id="slFocusAge"',
    'setInterval(animateClock,1000);',
    'if(selected!==null)drawSelected();',
]
for needle in required:
    assert needle in view, 'Expected V2+V3 restored view is absent: ' + needle
if marker in view:
    assert "fmtGpsAge(ageMs(p))" in view
    assert "GPS AGE: " in view
    print('EXPLICIT_GPS_AGE_ALREADY_PRESENT=PASS')
    raise SystemExit(0)

def replace_once(old, new, label):
    global view
    found = view.count(old)
    assert found == 1, 'Unexpected anchor ' + label + ': ' + str(found)
    view = view.replace(old, new, 1)

helper = """// AM_STAFF_LIVE_EXACT_GPS_AGE_20261019
// Last actual device GPS fix age: never infer age from dashboard polling time.
const fmtGpsAge=ms=>{
 if(!Number.isFinite(ms))return 'Never';
 const t=Math.floor(Math.max(0,ms)/1000);
 const d=Math.floor(t/86400),h=Math.floor((t%86400)/3600),m=Math.floor((t%3600)/60),s=t%60;
 const pad=n=>String(n).padStart(2,'0');
 return (d?d+'d ':'')+pad(h)+'h '+pad(m)+'m '+pad(s)+'s';
};
"""
replace_once('const quality=p=>{', helper + 'const quality=p=>{', 'time format helper')
replace_once(
    "if(age)age.textContent=p?fmtAge(ageMs(p))+' · '+(gpsAccuracy(p)??'—')+' m accuracy':'No position received';",
    "if(age)age.textContent=p?'GPS AGE: '+fmtGpsAge(ageMs(p))+' · '+(gpsAccuracy(p)??'—')+' m accuracy':'GPS AGE: Never';",
    'staff list live age clock'
)
replace_once(
    "age.textContent=p?fmtAge(ageMs(p))+' · '+(gpsAccuracy(p)??'—')+' m accuracy':'No position received';",
    "age.textContent=p?'GPS AGE: '+fmtGpsAge(ageMs(p))+' · '+(gpsAccuracy(p)??'—')+' m accuracy':'GPS AGE: Never';",
    'staff row first paint'
)
replace_once(
    "setText('slFocusAge',p?fmtAge(ageMs(p)):'Never');",
    "setText('slFocusAge',p?fmtGpsAge(ageMs(p)):'Never');",
    'phone focus GPS age'
)
replace_once(
    "setText('slMapSubtitle',p?'Last location '+fmtAge(ageMs(p))+' · GPS ±'+(gpsAccuracy(p)??'—')+' m':'No coordinate received');",
    "setText('slMapSubtitle',p?'GPS AGE: '+fmtGpsAge(ageMs(p))+' · GPS ±'+(gpsAccuracy(p)??'—')+' m':'GPS AGE: Never · No coordinate received');",
    'map header exact age'
)
replace_once(
    "setText('slRecorded',p?fmtDate(p.recorded_at)+' · '+fmtAge(ageMs(p)):'No coordinate');",
    "setText('slRecorded',p?fmtDate(p.recorded_at)+' · AGE '+fmtGpsAge(ageMs(p)):'No coordinate');",
    'selected detail exact age'
)
replace_once(
    '<small>Location Last Recorded</small><strong id="slRecorded">',
    '<small>GPS Fix Time / GPS Age</small><strong id="slRecorded">',
    'detail label'
)

# No changes to age-based LIVE / DELAYED / STALE / OFFLINE definitions.
for needle in ["if(age>1800000)return 'offline';",
               "if(age>420000)return 'stale';",
               "if(age>120000)return 'delayed';",
               "setInterval(()=>poll(),1000);",
               "if(soloMode && selected!==s.id)continue;",
               "AM_RESTORE_DESKTOP_BLUE_V2_MOBILE_SOLO_V31"]:
    assert needle in view, 'Preserved safety condition missing: ' + needle
for id_name in ['slMap','slMapSubtitle','slList','slSearch','slRecorded','slReceived','slFocusAge']:
    assert view.count('id="' + id_name + '"') == 1, 'Duplicate/missing UI: ' + id_name

scripts = re.findall(r'<script>\s*(.*?)\s*</script>', view, re.S)
assert len(scripts) == 2, 'Unexpected JS scripts'
if shutil.which('node'):
    for code in scripts:
        code = (code
                .replace("@json(route('erp.staff-live-location.feed'))", '"/erp/staff-live-location/feed"')
                .replace("@json(route('erp.staff-live-location.history'))", '"/erp/staff-live-location/history"')
                .replace('@json($mapSettings)', '{}'))
        assert '@json(' not in code
        with tempfile.NamedTemporaryFile(mode='w', suffix='.js', encoding='utf-8', delete=False) as out:
            out.write(code)
            name = out.name
        try:
            result = subprocess.run(['node', '--check', name], capture_output=True, text=True)
            assert result.returncode == 0, 'Invalid JavaScript: ' + result.stderr
        finally:
            pathlib.Path(name).unlink(missing_ok=True)

print('EXPLICIT_GPS_AGE_VIEW_ONLY_DIFF=PASS')
print('EXPLICIT_GPS_AGE_INLINE_JS_LINT=PASS')
if not args.apply:
    print('EXPLICIT_GPS_AGE_DRY_RUN=PASS')
    raise SystemExit(0)

backupdir = root / 'storage/app/patch-backups'
backupdir.mkdir(parents=True, exist_ok=True, mode=0o700)
stamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')
backup = backupdir / ('staff-live-before-gps-age-' + stamp + '.blade.php')
shutil.copy2(target, backup)
backup.chmod(0o600)
fd, tempname = tempfile.mkstemp(prefix='.gps-age-', suffix='.blade.php', dir=target.parent)
try:
    with os.fdopen(fd, 'w', encoding='utf-8') as f:
        f.write(view)
    os.chmod(tempname, target.stat().st_mode & 0o777)
    os.replace(tempname, target)
    for cmd in (['php', 'artisan', 'view:clear'], ['php', 'artisan', 'view:cache']):
        p = subprocess.run(cmd, cwd=root, capture_output=True, text=True, timeout=100)
        if p.returncode:
            raise RuntimeError('Blade view failure: ' + p.stderr[-1000:])
    print('EXPLICIT_GPS_AGE_STAGING_APPLIED=PASS')
    print('EXPLICIT_GPS_AGE_PRIVATE_BACKUP=' + str(backup))
except Exception:
    shutil.copy2(backup, target)
    subprocess.run(['php', 'artisan', 'view:clear'], cwd=root, capture_output=True)
    print('EXPLICIT_GPS_AGE_ROLLBACK_COMPLETE=PASS')
    raise
finally:
    pathlib.Path(tempname).unlink(missing_ok=True)
