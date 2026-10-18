#!/usr/bin/env python3
"""Approved Staff Live Location blue-header V2; modifies ONLY existing Blade view.
No controller, routes, location logs, attendance, database or staff app changes.
--dry-run first; --apply creates private backup and atomically swaps the view.
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
assert (root / 'artisan').exists() and target.is_file(), 'Expected staging Laravel Staff Live Location view'
old = target.read_text(encoding='utf-8', errors='surrogateescape')
marker = 'AM_STAFF_LIVE_BLUE_V2_20261018'
assert old.count('id="amStaffLive"') == 1
assert old.count('id="slMap"') == 1
assert old.count('id="slClock"') == 1
assert old.count("@extends('layouts.erp')") == 1

css = r"""
/* AM_STAFF_LIVE_BLUE_V2_20261018: page-local approved compact map-first V2. */
/* Hide ONLY this page's duplicate white header, preserving ERP sidebar. */
body:has(#amStaffLive) .erp-topbar{display:none!important}
body:has(#amStaffLive) .page-shell{padding-top:7px!important}
#amStaffLive.sl-shell{gap:8px;min-width:0}
#amStaffLive .sl-hero{padding:10px 14px;border-radius:11px;min-height:55px;gap:8px;flex-wrap:wrap;box-shadow:0 4px 12px #13436a14}
#amStaffLive .sl-hero-title{display:flex;flex-direction:column;gap:2px;min-width:175px;flex:1}
#amStaffLive .sl-hero-title h1{order:1;font-size:18px!important;line-height:1.15!important;letter-spacing:-.35px;margin:0!important}
#amStaffLive .sl-overline{order:2;font-size:9px;letter-spacing:1px;color:#b9d9f2}
#amStaffLive .sl-hero p{display:none}
#amStaffLive .sl-v2-actions{display:flex;align-items:center;gap:10px;margin-left:auto;min-width:0}
#amStaffLive .sl-live-clock{display:flex;flex-direction:column;gap:1px;border:0;padding:0;background:transparent;text-align:right;box-shadow:none}
#amStaffLive .sl-live-clock small{font-size:9px;white-space:nowrap;color:#c9e6fb}
#amStaffLive .sl-live-clock strong{font-size:14px;line-height:1.2;letter-spacing:0}
#amStaffLive .sl-live-dot{width:6px;height:6px;margin-right:5px;box-shadow:0 0 0 2px #7bf4b32b}
#amStaffLive .sl-v2-date{font-size:10px;color:#c5e7fa;white-space:nowrap}
#amStaffLive .sl-v2-menu-area{position:relative}
#amStaffLive .sl-v2-menu-trigger,#amStaffLive .sl-v2-nav-trigger{border:1px solid #ffffff5c;background:#ffffff19;color:white;font-size:11px;font-weight:750;padding:7px 10px;border-radius:7px;cursor:pointer;white-space:nowrap}
#amStaffLive .sl-v2-menu-trigger:hover,#amStaffLive .sl-v2-nav-trigger:hover{background:#ffffff30}
#amStaffLive .sl-v2-menu{display:none;position:absolute;right:0;top:calc(100% + 5px);z-index:1800;min-width:210px;padding:6px;background:#fff;color:#244767;border-radius:10px;box-shadow:0 12px 35px #10375945;border:1px solid #dce7f1}
#amStaffLive .sl-v2-menu.open{display:grid;gap:2px}
#amStaffLive .sl-v2-menu a,#amStaffLive .sl-v2-menu form button{display:block;width:100%;border:0;background:#fff;color:#244767;text-decoration:none;border-radius:6px;padding:9px 10px;font:inherit;font-size:11px;font-weight:700;text-align:left;cursor:pointer}
#amStaffLive .sl-v2-menu a:hover,#amStaffLive .sl-v2-menu form button:hover{background:#edf6ff}
#amStaffLive .sl-v2-menu form{margin:0;border-top:1px solid #edf0f4;padding-top:4px}
#amStaffLive .sl-v2-menu .sl-v2-user{font-size:10px;color:#8296a9;border-top:1px solid #ecf1f7;padding:6px 9px 2px}
#amStaffLive .sl-metrics{grid-template-columns:repeat(5,minmax(0,1fr));gap:7px}
#amStaffLive .sl-metric{min-height:53px;padding:7px 10px;border-radius:9px;box-shadow:none}
#amStaffLive .sl-metric .sl-label{font-size:9px;letter-spacing:.2px}
#amStaffLive .sl-metric strong{font-size:20px;line-height:1.05;margin:3px 0 0}
#amStaffLive .sl-metric .sl-sub{display:none}
#amStaffLive .sl-body{grid-template-columns:minmax(0,1fr) clamp(230px,23vw,325px);min-height:0;gap:8px}
#amStaffLive .sl-panel{border-radius:10px;box-shadow:0 2px 8px #17466d0a}
#amStaffLive .sl-map-wrap{order:1;min-width:0;min-height:0}
#amStaffLive .sl-staff-panel{order:2;min-width:0;min-height:0;max-height:max(550px,calc(100dvh - 155px))}
#amStaffLive .sl-map-head{padding:7px 9px;min-height:43px;gap:6px}
#amStaffLive .sl-map-head .sl-tools{gap:5px;flex-wrap:wrap;justify-content:flex-end}
#amStaffLive .sl-map-head strong{font-size:12px}
#amStaffLive .sl-map-head small{font-size:9px}
#amStaffLive .sl-btn{border-radius:7px;padding:7px 9px;font-size:10px}
#amStaffLive .sl-map{height:max(530px,calc(100dvh - 195px));min-height:530px;flex:1}
#amStaffLive .sl-panel-head{padding:10px 9px 8px;gap:7px}
#amStaffLive .sl-panel-title h2{font-size:12px!important}
#amStaffLive .sl-input,#amStaffLive .sl-select{padding:8px;font-size:11px;border-radius:7px}
#amStaffLive .sl-staff-list{max-height:none;min-height:180px}
#amStaffLive .sl-staff{padding:9px 8px;grid-template-columns:29px minmax(0,1fr) auto;gap:7px}
#amStaffLive .sl-staff.selected{padding-left:5px}
#amStaffLive .sl-avatar{width:28px;height:28px;border-radius:7px;font-size:10px}
#amStaffLive .sl-staff-name{font-size:11px}
#amStaffLive .sl-staff-caption{font-size:9px}
#amStaffLive .sl-pill{font-size:8px;padding:4px 5px}
#amStaffLive .sl-detail{border-radius:10px}
#amStaffLive .sl-detail-body{gap:9px;padding:10px 12px}
#amStaffLive .sl-foot{font-size:10px;padding:8px 10px}
#amStaffLive.sl-focus-map .sl-staff-panel{display:none}
#amStaffLive.sl-focus-map .sl-body{grid-template-columns:minmax(0,1fr)}
#amStaffLive.sl-focus-map .sl-map{height:max(650px,calc(100dvh - 163px))}
#amStaffLive.sl-focus-map #slV2Focus{background:#1467b8;color:#fff;border-color:#1467b8}
@media(max-width:950px){
 #amStaffLive .sl-body{grid-template-columns:minmax(0,1fr) 235px}
 #amStaffLive .sl-v2-date{display:none}
 #amStaffLive .sl-map-head small{display:none}
 #amStaffLive .sl-map{height:max(490px,calc(100dvh - 195px));min-height:490px}
}
@media(max-width:700px){
 #amStaffLive .sl-hero{padding:9px 10px}
 #amStaffLive .sl-hero-title h1{font-size:15px!important}
 #amStaffLive .sl-overline{font-size:8px}
 #amStaffLive .sl-v2-actions{gap:5px}
 #amStaffLive .sl-live-clock small#slConnection{display:none}
 #amStaffLive .sl-live-clock strong{font-size:12px}
 #amStaffLive .sl-v2-menu-trigger,#amStaffLive .sl-v2-nav-trigger{padding:7px;font-size:10px}
 #amStaffLive .sl-metrics{display:flex;overflow-x:auto;scrollbar-width:thin}
 #amStaffLive .sl-metric{min-width:105px;flex:1}
 #amStaffLive .sl-body{grid-template-columns:minmax(0,1fr)}
 #amStaffLive .sl-staff-panel{order:2;max-height:360px}
 #amStaffLive .sl-staff-list{min-height:90px}
 #amStaffLive .sl-map-wrap{order:1}
 #amStaffLive .sl-map{min-height:460px;height:60dvh}
 #amStaffLive.sl-focus-map .sl-map{height:72dvh;min-height:470px}
 #amStaffLive .sl-map-head .sl-tools{gap:4px}
 #amStaffLive .sl-btn{font-size:9px;padding:6px}
}
@media(prefers-reduced-motion:reduce){#amStaffLive .sl-live-dot{animation:none}}
"""

header_old = '<div><div class="sl-overline">ASSAM MOTORS · WORKFORCE INTELLIGENCE</div>'
clock_old = '<div class="sl-live-clock"><small><span class="sl-live-dot"></span>LIVE DASHBOARD · IST</small><strong id="slClock">--:--:--</strong><small id="slConnection">Connecting securely…</small></div>'
clock_new = '''<div class="sl-v2-actions">
      <button type="button" class="sl-v2-nav-trigger" id="slV2Sidebar" aria-label="Toggle ERP sidebar">☰</button>
      <div class="sl-live-clock"><small><span class="sl-live-dot"></span>LIVE DASHBOARD · IST</small><strong id="slClock">--:--:--</strong><small id="slConnection">Connecting securely…</small></div>
      <span class="sl-v2-date" id="slV2Date"></span>
      <div class="sl-v2-menu-area"><button type="button" class="sl-v2-menu-trigger" id="slV2Tools" aria-controls="slV2ToolMenu" aria-expanded="false">☷ Tools ▾</button>
        <nav id="slV2ToolMenu" class="sl-v2-menu" aria-label="ERP tools and account"></nav>
      </div>
    </div>'''
map_head_old = '<button class="sl-btn" type="button" id="slWorkshop">⌖ Workshop</button>'
map_head_new = '<button class="sl-btn" type="button" id="slV2Focus" aria-pressed="false">⛶ Focus Map</button>'+map_head_old

js = r"""
<script>
/* AM_STAFF_LIVE_BLUE_V2_20261018: UI-only controls; authenticated GPS feed untouched. */
(function(){
 'use strict';
 const root=document.getElementById('amStaffLive');
 const button=document.getElementById('slV2Tools');
 const menu=document.getElementById('slV2ToolMenu');
 const top=document.querySelector('.erp-topbar');
 if(!root||!button||!menu||!top)return;
 const nav=top.querySelector('.am50-top-shortcuts');
 const links=nav?Array.from(nav.querySelectorAll('a')):[];
 links.forEach(a=>{
   const copy=a.cloneNode(true);
   copy.removeAttribute('class');
   menu.appendChild(copy);
 });
 const userbox=top.querySelector('.userbox');
 const username=userbox&&userbox.querySelector('span');
 if(username){
   const who=document.createElement('div');
   who.className='sl-v2-user';
   who.textContent='Signed in: '+username.textContent.trim();
   menu.appendChild(who);
 }
 const logout=userbox&&userbox.querySelector('form');
 if(logout)menu.appendChild(logout.cloneNode(true));
 const existingDate=top.querySelector('.topbar-date');
 const date=document.getElementById('slV2Date');
 if(date && existingDate)date.textContent=existingDate.textContent.trim()+' · IST';
 const close=()=>{menu.classList.remove('open');button.setAttribute('aria-expanded','false')};
 button.addEventListener('click',e=>{
   e.stopPropagation();
   const open=!menu.classList.contains('open');
   menu.classList.toggle('open',open);
   button.setAttribute('aria-expanded',String(open));
 });
 document.addEventListener('click',e=>{
   if(!menu.contains(e.target)&&e.target!==button)close();
 });
 document.addEventListener('keydown',e=>{if(e.key==='Escape')close()});
 const sidebar=document.getElementById('slV2Sidebar');
 if(sidebar)sidebar.addEventListener('click',()=>document.body.classList.toggle('sidebar-open'));
 const focus=document.getElementById('slV2Focus');
 function refreshLeafletSize(){
   window.dispatchEvent(new Event('resize'));
   window.setTimeout(()=>window.dispatchEvent(new Event('resize')),210);
 }
 if(focus)focus.addEventListener('click',()=>{
   const enabled=root.classList.toggle('sl-focus-map');
   focus.textContent=enabled?'⊟ Exit Focus':'⛶ Focus Map';
   focus.setAttribute('aria-pressed',String(enabled));
   refreshLeafletSize();
 });
})();
</script>
"""

if marker in old:
    if all(k in old for k in ('id="slV2Tools"', 'id="slV2Focus"', 'id="slV2ToolMenu"')):
        print('STAFF_BLUE_V2_ALREADY_INSTALLED=PASS')
        raise SystemExit(0)
    raise AssertionError('V2 marker present but incomplete; refusing unsafe duplicate patch')

def replace_once(text, a, b, label):
    assert text.count(a)==1, '{} unexpected occurrence count {}'.format(label,text.count(a))
    return text.replace(a,b,1)

new = replace_once(old,'</style>',css+'\n</style>','CSS insertion')
new = replace_once(new,header_old,'<div class="sl-hero-title"><div class="sl-overline">ASSAM MOTORS · WORKFORCE INTELLIGENCE</div>','hero title')
new = replace_once(new,clock_old,clock_new,'blue action toolbar')
new = replace_once(new,map_head_old,map_head_new,'focus map button')
new = replace_once(new,'</script>\n@endsection','</script>\n'+js+'\n@endsection','V2 JS behavior')

for key in ['id="slMap"','id="slSearch"','id="slFilter"','id="slDutyFilter"','id="slWorkshop"','id="slRefresh"','id="slHistoryButton"','id="slGoogleMaps"','id="slClock"']:
    assert new.count(key)==1, 'Original map/filter control must survive: '+key
assert new.count(marker)>=2 and new.count('id="slV2Tools"')==1
assert 'GPS positions, not simulated movements' in new
assert 'setInterval(()=>poll(),1000)' in new
assert "const ENDPOINT = @json(route('erp.staff-live-location.feed'));" in new
assert "const HISTORY = @json(route('erp.staff-live-location.history'));" in new

# Lint both inline scripts with Blade bindings safely replaced, if Node present.
if shutil.which('node'):
    for i,script in enumerate(re.findall(r'<script>\s*(.*?)\s*</script>',new,re.S),1):
        code=script
        code=code.replace("@json(route('erp.staff-live-location.feed'))",'"/erp/staff-live-location/feed"')
        code=code.replace("@json(route('erp.staff-live-location.history'))",'"/erp/staff-live-location/history"')
        code=code.replace('@json($mapSettings)','{}')
        assert '@json(' not in code
        with tempfile.NamedTemporaryFile(mode='w',suffix='.js',encoding='utf-8',delete=False) as temp:
            temp.write(code); node_file=temp.name
        try:
            result=subprocess.run(['node','--check',node_file],capture_output=True,text=True)
            assert result.returncode==0, 'Staff V2 inline JS syntax: '+result.stderr
        finally:
            os.unlink(node_file)
print('STAFF_BLUE_V2_VIEW_DIFF=PASS')
print('STAFF_BLUE_V2_JS_LINT=PASS')
if not args.apply:
    print('STAFF_BLUE_V2_DRY_RUN=PASS')
    raise SystemExit(0)

backup_dir=root/'storage/app/patch-backups'
backup_dir.mkdir(mode=0o700,parents=True,exist_ok=True)
stamp=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')
backup=backup_dir/('staff-live-blue-v2-'+stamp+'.blade.php')
shutil.copy2(target,backup)
os.chmod(backup,0o600)
fd,tmp=tempfile.mkstemp(prefix='.staff-live-blue-v2-',suffix='.blade.php',dir=target.parent)
try:
    with os.fdopen(fd,'w',encoding='utf-8',errors='surrogateescape') as f:f.write(new)
    os.chmod(tmp,target.stat().st_mode & 0o777)
    os.replace(tmp,target)
    for command in (['php','artisan','view:clear'],['php','artisan','view:cache']):
        result=subprocess.run(command,cwd=root,capture_output=True,text=True,timeout=90)
        if result.returncode!=0:raise RuntimeError('{} failed: {}'.format(command,result.stderr[-900:]))
    print('STAFF_BLUE_V2_STAGING_DEPLOYED=PASS')
    print('STAFF_BLUE_V2_PRIVATE_BACKUP='+str(backup))
except Exception:
    shutil.copy2(backup,target)
    subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True)
    print('STAFF_BLUE_V2_ROLLBACK=RESTORED')
    raise
finally:
    if os.path.exists(tmp):os.unlink(tmp)
