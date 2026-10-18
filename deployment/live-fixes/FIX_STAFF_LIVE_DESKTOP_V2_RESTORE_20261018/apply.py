#!/usr/bin/env python3
"""Restore exact BLUE V2 desktop interaction/layout while retaining mobile-only SOLO V3.

Surgical patch of currently deployed Laravel Blade only. No GPS/ROT/attendance/
API/DB changes. Reject drift, lint both inline JS scripts, backup before apply.
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
assert (root/'artisan').is_file() and target.is_file(), 'Laravel view path missing'
before = target.read_text(encoding='utf-8')
view = before
blue = 'AM_STAFF_LIVE_BLUE_V2_20261018'
solo = 'AM_COMPACT_MOBILE_SOLO_TRACK'
restored = 'AM_RESTORE_DESKTOP_BLUE_V2_MOBILE_SOLO_V31'
for marker in [blue, solo, 'id="slV2Focus"', 'id="slV2Tools"', 'id="slAllStaff"', 'id="slMap"']:
    assert marker in view, 'Expected deployed BLUE V2 + Mobile SOLO V3: '+marker
if restored in view:
    assert "window.matchMedia('(min-width:701px)').matches" in view
    assert "if(selected!==null)drawSelected();" in view
    print('DESKTOP_BLUE_V2_RESTORE_ALREADY_INSTALLED=PASS')
    raise SystemExit(0)

def swap(old,new,label):
    global view
    count=view.count(old)
    assert count==1, f'{label}: expected 1 anchor; actual {count}'
    view=view.replace(old,new,1)

# SOLO styles formerly used <=850px (small laptops/tablets). Restrict SOLO to
# actual phones <=700px, preserving V2's map-left/register-right desktop.
css_start=view.index('/* '+solo)
css_end=view.index('@media(max-width:1190px)',css_start)
css_fragment=view[css_start:css_end]
assert css_fragment.count('@media(max-width:850px){')==1
view=view[:css_start] + css_fragment.replace('@media(max-width:850px){','@media(max-width:700px){',1) + view[css_end:]

# Restore V2 detail panel and staff rows on desktop. V3 has !important
# overrides for .sl-detail and the map; the #amStaffLive specificity is deliberate.
desktop_css = r"""
/* AM_RESTORE_DESKTOP_BLUE_V2_MOBILE_SOLO_V31:
   DESKTOP = original Blue V2 (map LEFT, staff RIGHT, 5 horizontal KPIs).
   Only phone width <=700px supports isolated SOLO view. */
@media(min-width:701px){
 #amStaffLive:not(.solo) .sl-detail{display:block!important}
 #amStaffLive .sl-list-hint{display:none!important}
 #amStaffLive .sl-staff{min-height:0!important}
 #amStaffLive .sl-focus{display:none!important}
 #amStaffLive:not(.solo) .sl-map-wrap{display:flex!important}
}
@media(min-width:701px) and (max-width:850px){
 #amStaffLive:not(.solo) .sl-metrics{display:grid!important;overflow:visible!important;grid-template-columns:repeat(5,minmax(0,1fr))!important}
 #amStaffLive:not(.solo) .sl-body{display:grid!important;grid-template-columns:minmax(0,1fr) 235px!important}
 #amStaffLive:not(.solo) .sl-map-wrap{display:flex!important;order:1}
 #amStaffLive:not(.solo) .sl-staff-panel{display:flex!important;order:2}
 #amStaffLive:not(.solo) .sl-map{height:max(490px,calc(100dvh - 195px))!important;min-height:490px!important}
}
"""
swap('</style>', desktop_css+'\n</style>', 'desktop V2 CSS')

# Clicking staff on desktop retains all pins, table, header, metrics and
# selected detail as in original screenshot. Phone only enters SOLO.
selector_old = """function selectStaff(id,focus){
 if(!people.has(id))return;
 if(selected!==id)clearHistory();"""
selector_new = """function selectStaff(id,focus){
 if(!people.has(id))return;
 // Desktop / tablet: NEVER replace approved BLUE V2 map-and-staff layout.
 if(window.matchMedia('(min-width:701px)').matches){
  if(selected!==id)clearHistory();
  selected=id;soloMode=false;followSelected=false;lastFollowedFixId=null;
  $('amStaffLive').classList.remove('solo');
  $('slFollow').hidden=true;
  drawStaff();drawSelected();renderMap();
  const p=points.get(id);
  if(map && focus && mapPoint(p)){
   map.setView([p.lat,p.lng],16,{animate:true});
   pins.get(id)?.openTooltip();
  }
  return;
 }
 if(selected!==id)clearHistory();"""
swap(selector_old,selector_new,'original V2 staff-selection behavior')
swap('if(soloMode && selected!==null)drawSelected();',
     'if(selected!==null)drawSelected();','update selected details each second on desktop')
swap('drawStaff();renderMap();if(soloMode)drawSelected();',
     'drawStaff();renderMap();if(selected!==null)drawSelected();','refresh selected details on new GPS feed')

# Mobile-only scroll interactions, not tablet/desktop navigation.
scroll_old = "if(window.matchMedia('(max-width:850px)').matches)"
assert view.count(scroll_old)==2, 'Expected two phone auto-scroll actions'
view=view.replace(scroll_old,"if(window.matchMedia('(max-width:700px)').matches)")
# Handle device rotation: change a SOLO mobile page into BLUE V2 when display
# grows to desktop width, without discarding the selected staff.
anchor=""" document.addEventListener('keydown',event=>{
  if(event.key==='Escape' && soloMode && document.activeElement?.tagName!=='INPUT')showAllStaff();
 });"""
rotated=""" document.addEventListener('keydown',event=>{
  if(event.key==='Escape' && soloMode && document.activeElement?.tagName!=='INPUT')showAllStaff();
 });
 window.addEventListener('resize',()=>{
  if(soloMode && window.matchMedia('(min-width:701px)').matches){
   const staffId=selected;
   showAllStaff();
   if(staffId!==null && people.has(staffId))selectStaff(staffId,false);
  }
 });"""
swap(anchor,rotated,'mobile-to-desktop rotation')

assert view.count(restored)>=1
assert view.count(blue)>=2 and view.count(solo)>=1
assert 'setInterval(()=>poll(),1000)' in view
assert 'if(soloMode && selected!==s.id)continue;' in view, 'Mobile single-staff marker privacy lost'
assert "window.matchMedia('(min-width:701px)').matches" in view
assert "if(selected!==null)drawSelected();" in view
assert view.count('id="slV2Focus"')==1 and view.count('id="slAllStaff"')==1
assert view.count('id="slMap"')==1 and view.count('id="slSearch"')==1

# Syntax check both native and V2 toolbar scripts.
scripts=re.findall(r'<script>\s*(.*?)\s*</script>',view,re.S)
assert len(scripts)==2,'Unexpected script count'
if shutil.which('node'):
    for i,script in enumerate(scripts):
        script=(script
          .replace("@json(route('erp.staff-live-location.feed'))",'"/erp/staff-live-location/feed"')
          .replace("@json(route('erp.staff-live-location.history'))",'"/erp/staff-live-location/history"')
          .replace('@json($mapSettings)','{}'))
        assert '@json(' not in script
        with tempfile.NamedTemporaryFile(mode='w',suffix='.js',delete=False) as f:
            f.write(script); fname=f.name
        try:
            result=subprocess.run(['node','--check',fname],capture_output=True,text=True)
            assert result.returncode==0, 'JavaScript syntax failure: '+result.stderr
        finally:pathlib.Path(fname).unlink(missing_ok=True)
print('DESKTOP_BLUE_V2_RESTORE_VIEW_DIFF=PASS')
print('DESKTOP_BLUE_V2_RESTORE_JS_LINT=PASS')
if not args.apply:
    print('DESKTOP_BLUE_V2_RESTORE_DRY_RUN=PASS')
    raise SystemExit(0)

backupdir=root/'storage/app/patch-backups'
backupdir.mkdir(mode=0o700,parents=True,exist_ok=True)
stamp=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')
backup=backupdir/('staff-live-before-desktop-v2-restore-'+stamp+'.blade.php')
shutil.copy2(target,backup);backup.chmod(0o600)
fd,tmp=tempfile.mkstemp(prefix='.staff-desktop-v2-restore-',suffix='.blade.php',dir=target.parent)
try:
    with os.fdopen(fd,'w') as f:f.write(view)
    os.chmod(tmp,target.stat().st_mode & 0o777)
    os.replace(tmp,target)
    for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
        result=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=90)
        if result.returncode!=0:raise RuntimeError('Laravel view failure: '+result.stderr[-1000:])
    print('DESKTOP_BLUE_V2_RESTORE_STAGING_APPLIED=PASS')
    print('DESKTOP_BLUE_V2_RESTORE_PRIVATE_BACKUP='+str(backup))
except Exception:
    shutil.copy2(backup,target)
    subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True)
    print('DESKTOP_BLUE_V2_RESTORE_ROLLBACK=COMPLETE')
    raise
finally:pathlib.Path(tmp).unlink(missing_ok=True)
