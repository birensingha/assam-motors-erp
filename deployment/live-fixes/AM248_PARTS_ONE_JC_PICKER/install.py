#!/usr/bin/env python3
"""AM248 Parts Purchase: ONE JC / Vehicle searchable field, retained hidden backend select.
check|apply|rollback STAGING_ROOT. Exact source anchor guards; source-only, no DB writes.
"""
from pathlib import Path
import sys,os,hashlib,json,tempfile,subprocess,shutil
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):raise SystemExit('AM248_USAGE check|apply|rollback STAGING_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM248_STOP_NOT_STAGING')
base=Path(__file__).resolve().parent
paths={'view':'resources/views/purchases/create.blade.php','js':'public/js/purchase-entry.js'}
files={k:root/v for k,v in paths.items()}
backup=root/'storage/app/am-backups/AM248_PARTS_ONE_JC_PICKER'
manifest=backup/'hashes.json'
marker='AM248_PARTS_ONE_JC_PICKER'
def read(p):return p.read_text('utf8',errors='surrogateescape')
def digest(s):return hashlib.sha256(s.encode('utf8','surrogateescape')).hexdigest()
def atomic(p,s):
 fd,tmp=tempfile.mkstemp(prefix='.am248_',dir=p.parent)
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as out:out.write(s)
  os.chmod(tmp,p.stat().st_mode & 0o777)
  os.replace(tmp,p)
 finally:
  if os.path.exists(tmp):os.unlink(tmp)
if any(not p.is_file() or p.is_symlink() for p in files.values()) or not (base/'job-picker.js').is_file():
 raise SystemExit('AM248_STOP_SOURCE_NOT_FOUND')
old={n:read(p) for n,p in files.items()}
if mode=='rollback':
 if not manifest.is_file():raise SystemExit('AM248_STOP_BACKUP_MISSING')
 data=json.loads(read(manifest))
 if any(digest(old[n])!=data['after'][n] for n in files):raise SystemExit('AM248_STOP_CONCURRENT_SOURCE_CHANGE')
 for n,p in files.items():atomic(p,read(backup/(n+'.before')))
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=45)
 print('AM248_ROLLBACK_RESTORED_PREVIOUS_PURCHASE_CREATE');sys.exit(0)
if all(marker in old[n] for n in files):
 print('AM248_ALREADY_INSTALLED');sys.exit(0)
if any(marker in old[n] for n in files):
 raise SystemExit('AM248_STOP_PARTIAL_INSTALL')
if old['view'].count('class="job-filter"')!=1 or old['view'].count('class="job-select"')!=1:
 raise SystemExit('AM248_STOP_UNEXPECTED_CREATE_FORM_JOB_INPUTS')
if 'job_card_id' not in old['view'] or 'wireJobFilter(row)' not in old['js']:
 raise SystemExit('AM248_STOP_BACKEND_JOB_BINDING_MISSING')
new=dict(old)
helper=read(base/'job-picker.js')
if helper.count(marker)!=1 or '</script' in helper.lower():raise SystemExit('AM248_STOP_JS_SOURCE_INVARIANT')
begin='  function wireJobFilter(row) {'
end='\n  function wire(row) {'
if new['js'].count(begin)!=1 or new['js'].count(end)!=1:raise SystemExit('AM248_STOP_OLD_JOB_FILTER_ANCHORS')
a=new['js'].index(begin);b=new['js'].index(end,a)
if b<=a or b-a>5000:raise SystemExit('AM248_STOP_UNEXPECTED_OLD_JOB_FILTER')
oldBlock=new['js'][a:b]
for token in ["const filter = row.querySelector('.job-filter');","const select = row.querySelector('.job-select');","const rebuild = () =>"]:
 if token not in oldBlock:raise SystemExit('AM248_STOP_OLD_JOB_FILTER_TOKEN_'+token)
new['js']=new['js'][:a]+helper.rstrip()+'\n'+new['js'][b:]
# Seed/preselected JC ID must synchronize after dynamically building rows.
seedAnchor="""        job.value = '';
        job.disabled = true;
      }
    }

    recalc();"""
seedUpdate="""        job.value = '';
        job.disabled = true;
      }
      // AM248: propagate a restored JC to the ONE visible search field.
      job.dispatchEvent(new Event('change',{bubbles:true}));
    }

    recalc();"""
if new['js'].count(seedAnchor)!=1:raise SystemExit('AM248_STOP_SEEDED_JC_ANCHOR')
new['js']=new['js'].replace(seedAnchor,seedUpdate,1)
# The native select remains named and enabled for JOB rows, hidden only visually.
style="""{{-- AM248_PARTS_ONE_JC_PICKER: one visible searchable JC / Vehicle per Parts Purchase row. --}}
<style id="am248-parts-single-jc-picker">
#purchase-form .job-filter-wrap{position:relative;display:block!important;min-width:230px}
#purchase-form .job-filter-wrap .job-filter{display:block!important;width:100%!important;min-width:0!important;
box-sizing:border-box!important;min-height:34px;font-size:11px}
#purchase-form .job-filter-wrap select.job-select{display:none!important;visibility:hidden!important;position:absolute!important;
left:-99999px!important;width:0!important;height:0!important;pointer-events:none!important}
.am248-job-menu[hidden]{display:none!important}
.am248-job-menu{position:fixed;z-index:2147483000;overflow-y:auto;overflow-x:hidden;background:#fff;
border:1px solid #9dbedd;border-radius:7px;padding:4px;box-shadow:0 12px 26px #0e345536}
.am248-job-option{width:100%;display:block;border:0;border-bottom:1px solid #e9eef5;background:#fff;
padding:9px 8px;text-align:left;color:#184b72;font-size:11px;cursor:pointer;white-space:normal}
.am248-job-option:hover,.am248-job-option.active,.am248-job-option:focus-visible{background:#e8f3fc}
.am248-job-empty{margin:0;padding:8px;font-size:11px;color:#687c91}
.am248-sr-only{position:absolute!important;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}
</style>
"""
anchor='<template id="line-template">'
if new['view'].count(anchor)!=1:raise SystemExit('AM248_STOP_PURCHASE_ROW_TEMPLATE_ANCHOR')
new['view']=new['view'].replace(anchor,style+anchor,1)
if new['view'].count(marker)!=1 or new['js'].count(marker)!=1:
 raise SystemExit('AM248_STOP_MARKERS_NOT_UNIQUE')
# Invariants: preserve POST field and allocation options / financial inputs.
for source in ['name="lines[__INDEX__][job_card_id]"','name="lines[__INDEX__][allocation_type]"',
               'name="lines[__INDEX__][unit_cost]"','name="lines[__INDEX__][part_master_id]"',
               'name="lines[__INDEX__][qty]"','name="lines[__INDEX__][discount_percent]"',
               'name="lines[__INDEX__][gst_percent]"']:
 if source not in new['view']:raise SystemExit('AM248_STOP_REQUIRED_FIELD_LOST_'+source)
for part in ['row.querySelector(\'.job-select\').value','filter.classList.add(\'invalid\')',
             'job.disabled = !needsJob','filter.disabled = !needsJob',
             'job.dispatchEvent(new Event(\'change\'']:
 if part not in new['js']:raise SystemExit('AM248_STOP_REQUIRED_JOB_LOGIC_'+part)
with tempfile.TemporaryDirectory(prefix='am248qa_') as d:
 f=Path(d)
 node=shutil.which('node')
 if node:
  js=f/'purchase-entry.js';js.write_text(new['js'],encoding='utf8',errors='surrogateescape')
  res=subprocess.run([node,'--check',str(js)],capture_output=True,text=True,timeout=40)
  if res.returncode:raise SystemExit('AM248_STOP_JS_SYNTAX '+res.stderr[-500:])
 source=f/'purchase-create.blade.php';source.write_text(new['view'],encoding='utf8',errors='surrogateescape')
 compiled=f/'compiled.php';h=f/'compile.php'
 h.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
 res=subprocess.run(['php',str(h),str(root),str(source),str(compiled)],cwd=root,capture_output=True,text=True,timeout=60)
 if res.returncode:raise SystemExit('AM248_STOP_BLADE_COMPILE '+res.stderr[-480:])
 res=subprocess.run(['php','-l',str(compiled)],cwd=root,capture_output=True,text=True,timeout=30)
 if res.returncode:raise SystemExit('AM248_STOP_COMPILED_PHP_LINT '+(res.stdout+res.stderr)[-480:])
 if 'am248-parts-single-jc-picker' not in read(compiled):raise SystemExit('AM248_STOP_COMPILED_STYLE_MISSING')
print('AM248_BLADE_PHP_JS_PREFLIGHT_PASS')
if mode=='check':print('AM248_CHECK_ONLY_NO_DB_OR_SOURCE_WRITES');sys.exit(0)
if backup.exists() or any(read(files[n])!=old[n] for n in files):
 raise SystemExit('AM248_STOP_BACKUP_COLLISION_OR_SOURCE_RACE')
backup.mkdir(parents=True,mode=0o700,exist_ok=False)
for n in files:
 p=backup/(n+'.before');p.write_text(old[n],encoding='utf8',errors='surrogateescape');os.chmod(p,0o600)
try:
 for n,p in files.items():atomic(p,new[n])
 manifest.write_text(json.dumps({'after':{n:digest(new[n]) for n in files}}),encoding='ascii');os.chmod(manifest,0o600)
 for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
  res=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=90)
  if res.returncode:raise RuntimeError('AM248_STOP_VIEW_CACHE '+res.stderr[-450:])
except BaseException as ex:
 for n,p in files.items():atomic(p,old[n])
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=40)
 raise SystemExit('AM248_DEPLOY_FAILED_ROLLED_BACK '+str(ex))
print('AM248_PARTS_SINGLE_JC_SEARCH_STAGING_SOURCE_DEPLOYED_NO_DB_WRITES')
print('AM248_AUTHENTICATED_BROWSER_AND_PURCHASE_SAVE_QA_PENDING')
