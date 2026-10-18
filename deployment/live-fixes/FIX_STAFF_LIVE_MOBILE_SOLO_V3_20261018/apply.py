#!/usr/bin/env python3
"""Non-destructive SOLO Staff tracking overlay ON TOP of existing BLUE V2.

No user data, GPS logs, backend, routes, attendance or Android settings touched.
Exact anchors + original base script comparison guard against overwriting a
different / more recent dashboard implementation. Backup + atomic replace.
"""
import argparse,datetime,os,pathlib,re,shutil,subprocess,tempfile
parser=argparse.ArgumentParser()
parser.add_argument('--root',required=True)
parser.add_argument('--bundle',required=True)
parser.add_argument('--apply',action='store_true')
args=parser.parse_args()
root=pathlib.Path(args.root).expanduser().resolve()
bundle=pathlib.Path(args.bundle).expanduser().resolve()
target=root/'resources/views/admin/native/staff-live-location.blade.php'
assert (root/'artisan').is_file() and target.is_file()
base=(bundle/'staff-live-location-original-base.blade.php').read_text()
upgrade=(bundle/'staff-live-location.blade.php').read_text()
old=target.read_text()
v2marker='AM_STAFF_LIVE_BLUE_V2_20261018'
marker='AM_COMPACT_MOBILE_SOLO_TRACK'
assert v2marker in old, 'Refusing patch on non-V2 page: preserve current staging modifications'
if marker in old:
    assert ('id="slAllStaff"' in old and 'if(soloMode && selected!==s.id)continue;' in old)
    print('MOBILE_SOLO_V3_ALREADY_APPLIED=PASS')
    raise SystemExit(0)
for id in ['id="slV2Tools"','id="slV2Focus"','id="slV2ToolMenu"','id="slMap"','id="slSearch"']:
    assert old.count(id)==1, 'Expected exactly one existing V2 control: '+id

def once(a,b,label):
    global old
    n=old.count(a)
    assert n==1,'Unsafe replacement '+label+' count='+str(n)
    old=old.replace(a,b,1)

# CSS extracted from reviewed feature source, appended AFTER V2 CSS so it wins
# even when V2's #amStaffLive selectors have higher specificity.
css=upgrade.split('/* '+marker,1)
assert len(css)==2
css='/* '+marker+css[1].split('@media(max-width:1190px)',1)[0]
css+="""
/* Override BLUE V2 only where selecting a single staff requires it. */
#amStaffLive.solo .sl-metrics{display:none!important}
#amStaffLive.solo .sl-staff-panel{display:none!important}
#amStaffLive.solo .sl-body{grid-template-columns:minmax(0,1fr)!important;min-height:0!important}
#amStaffLive.solo .sl-map-wrap{display:flex!important;min-height:0!important;order:1}
#amStaffLive.solo .sl-map{height:max(480px,65dvh)!important;min-height:420px!important}
#amStaffLive.solo .sl-detail{display:block!important}
#amStaffLive:not(.solo) .sl-detail{display:none!important}
#amStaffLive.solo .sl-focus{display:grid}
@media(max-width:700px){
 #amStaffLive:not(.solo) .sl-map-wrap{display:none!important}
 #amStaffLive.solo .sl-map-wrap{display:flex!important}
 #amStaffLive.solo .sl-map{height:min(49dvh,430px)!important;min-height:270px!important}
 #amStaffLive.solo .sl-staff-panel{display:none!important}
 #amStaffLive.solo .sl-metrics{display:none!important}
 #amStaffLive .sl-staff-panel{order:1}
}
"""
once('</style>',css+'</style>','append V3 CSS to BLUE V2 style')

a=upgrade.index(' <section class="sl-focus" id="slFocus"')
b=upgrade.index(' <div class="sl-metrics">',a)
focus=upgrade[a:b]
assert focus.count('id="slAllStaff"')==1 and focus.count('id="slFocusAge"')==1
once(' <div class="sl-metrics">',focus+' <div class="sl-metrics">','insert solo identity/summary')
once('    <input class="sl-input" id="slSearch"',
     '    <div class="sl-list-hint">Choose any staff — only their location will appear on the tracking screen</div>\n    <input class="sl-input" id="slSearch"',
     'insert staff select hint')

old_head='<div><strong>Real Location Map</strong><small id="slMapSubtitle">GPS positions, not simulated movements</small></div><div class="sl-tools">'
new_head='<div><strong id="slMapTitle">Real Location Map</strong><small id="slMapSubtitle">GPS positions, not simulated movements</small></div><div class="sl-tools"><button class="sl-btn" type="button" id="slFollow" aria-pressed="true" hidden>◎ Follow ON</button>'
once(old_head,new_head,'selected staff map title/follow button')

# Only replace V1 primary controller script IF it matches the tracked old base.
# BLUE V2 adds an independent second script which MUST remain untouched.
main=re.compile(r'<script>\s*\(function\(\)\{\s*[\'"]use strict[\'"];.*?\}\)\(\);\s*</script>',re.S)
before=main.findall(base);after=main.findall(upgrade);live=main.findall(old)
assert len(before)==1 and len(after)==1 and len(live)==1,'Unexpected primary script structure'
assert live[0]==before[0], 'Primary tracking JS differs from reviewed base: refusing to overwrite newer live functionality'
once(live[0],after[0],'upgrade only V1 live primary JS')

for name in ['slV2Tools','slV2Focus','slV2ToolMenu','slFocus','slFocusName','slAllStaff',
             'slFocusAge','slFocusSync','slFocusAccuracy','slFocusBadge',
             'slFollow','slMap','slSearch','slHistoryButton','slGoogleMaps']:
    assert old.count('id="'+name+'"')==1,'Duplicate/missing control id='+name
for required in ['if(soloMode && selected!==s.id)continue;', 'function showAllStaff(){',
                 'setInterval(()=>poll(),1000)','AM_STAFF_LIVE_BLUE_V2_20261018']:
    assert required in old,'Lost required protection: '+required
assert old.count(marker)>=1 and old.count(v2marker)>=2
scripts=re.findall(r'<script>\s*(.*?)\s*</script>',old,re.S)
assert len(scripts)==2,'Expected V1 and V2 JS to both remain present'
if shutil.which('node'):
    for i,script in enumerate(scripts):
        for key,val in [
            ("@json(route('erp.staff-live-location.feed'))", '"/erp/staff-live-location/feed"'),
            ("@json(route('erp.staff-live-location.history'))", '"/erp/staff-live-location/history"'),
            ("@json($mapSettings)",'{}')
        ]:script=script.replace(key,val)
        assert '@json(' not in script
        with tempfile.NamedTemporaryFile(mode='w',suffix='.js',delete=False) as fh:
            fh.write(script); fname=fh.name
        try:
            lint=subprocess.run(['node','--check',fname],capture_output=True,text=True)
            assert lint.returncode==0,f'Staff focus JS {i}: {lint.stderr}'
        finally:
            pathlib.Path(fname).unlink(missing_ok=True)
print('MOBILE_SOLO_V3_BLUE_V2_PRESERVED=PASS')
print('MOBILE_SOLO_V3_JS_SYNTAX=PASS')
if not args.apply:
    print('MOBILE_SOLO_V3_DRY_RUN=PASS')
    raise SystemExit(0)
backupdir=root/'storage/app/patch-backups'
backupdir.mkdir(parents=True,exist_ok=True,mode=0o700)
stamp=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')
backup=backupdir/('staff-mobile-solo-v3-'+stamp+'.blade.php')
shutil.copy2(target,backup)
backup.chmod(0o600)
fd,tmp=tempfile.mkstemp(prefix='.staff-solo-v3-',suffix='.blade.php',dir=target.parent)
try:
    with os.fdopen(fd,'w') as f:f.write(old)
    os.chmod(tmp,target.stat().st_mode & 0o777)
    os.replace(tmp,target)
    for cmd in [['php','artisan','view:clear'],['php','artisan','view:cache']]:
        p=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=100)
        if p.returncode!=0:raise RuntimeError('Blade view cache failure: '+p.stderr[-900:])
    print('MOBILE_SOLO_V3_STAGING_APPLIED=PASS')
    print('MOBILE_SOLO_V3_PRIVATE_BACKUP='+str(backup))
except Exception:
    shutil.copy2(backup,target)
    subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True)
    print('MOBILE_SOLO_V3_ROLLED_BACK=PASS')
    raise
finally:
    pathlib.Path(tmp).unlink(missing_ok=True)
