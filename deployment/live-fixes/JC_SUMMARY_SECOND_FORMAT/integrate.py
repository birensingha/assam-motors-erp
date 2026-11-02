#!/usr/bin/env python3
"""Add a SECOND detailed JC print route without replacing the original summary.
Preflight refuses unknown/changed STAGING sources. No database writes.
"""
from pathlib import Path
import sys, re, shutil, subprocess, datetime
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
    raise SystemExit('Usage: integrate.py check|apply ERP_ROOT')
mode=sys.argv[1]; root=Path(sys.argv[2]).resolve()
files={
 'routes':root/'routes/web.php',
 'controller':root/'app/Http/Controllers/Web/AdminJobCardPageController.php',
 'show':root/'resources/views/job-cards/show.blade.php',
 'new':root/'resources/views/job-cards/summary-detailed-print.blade.php',
}
source=Path(__file__).with_name('detailed-summary-print.blade.php')
if not source.is_file() or any(not files[k].is_file() for k in ['routes','controller','show']):
    raise SystemExit('STOP_MISSING_APP_SOURCE')
rt=files['routes'].read_text();ctl=files['controller'].read_text();sh=files['show'].read_text()
marker='AM_SECOND_JC_SUMMARY_20261102'
if marker in rt or marker in ctl or marker in sh or files['new'].exists():
    if all(marker in x for x in [rt,ctl,sh]) and files['new'].is_file():
        print('ALREADY_APPLIED');sys.exit(0)
    raise SystemExit('STOP_PARTIAL_OR_CONFLICTING_INSTALL')
route_anchor="->name('erp.job-cards.summary-print');"
fn_anchor="    public function printSummary(Request $request, int $job, JobCardService $service): View"
link_anchor="route('erp.job-cards.summary-print',$job->id)"
if rt.count(route_anchor)!=1 or ctl.count(fn_anchor)!=1 or sh.count(link_anchor)!=1:
    raise SystemExit('STOP_UNVERIFIED_ACTIVE_FIX69_ANCHORS')
route_new="    // "+marker+"\n    Route::get('/job-cards/{job}/summary-detailed-print', [AdminJobCardPageController::class,'printDetailedSummary'])->whereNumber('job')->name('erp.job-cards.summary-detailed-print');\n"
route_lines=rt.splitlines(keepends=True)
idx=next((i for i,l in enumerate(route_lines) if route_anchor in l),None)
if idx is None:raise SystemExit('STOP_ROUTE')
route_lines.insert(idx+1,route_new);newrt=''.join(route_lines)
fn_new=("    // "+marker+": same authorization and read-only JC detail source as original.\n"
"    public function printDetailedSummary(Request $request, int $job, JobCardService $service): View\n"
"    {\n"
"        $this->admin($request);\n"
"        return view('job-cards.summary-detailed-print', $service->detail($job));\n"
"    }\n\n")
newctl=ctl.replace(fn_anchor,fn_new+fn_anchor,1)
# Insert adjacent link without removing the existing print link.
pattern=r'(<a\b[^>]*href="\{\{\s*route\(\x27erp\.job-cards\.summary-print\x27,\$job->id\)\s*\}\}"[^>]*>.*?</a>)'
matches=list(re.finditer(pattern,sh,re.S))
if len(matches)!=1:raise SystemExit('STOP_JC_PRINT_LINK_NOT_UNIQUE')
link='\n {{-- '+marker+' --}}\n <a class="btn" href="{{ route(\'erp.job-cards.summary-detailed-print\',$job->id) }}" target="_blank" rel="noopener">Print Detailed Job Card Summary (All Lines)</a>'
newsh=sh[:matches[0].end()]+link+sh[matches[0].end():]
newview=source.read_text()
if 'take(9)' in newview or '$restParts' in newview or '$restLabour' in newview:
    raise SystemExit('STOP_DETAIL_TEMPLATE_TRUNCATES_LINES')
print('PREFLIGHT_PASS',marker,'NO_DB_WRITES')
if mode=='check':sys.exit(0)
backup=root/'storage/app/am-backups'/('AM_SECOND_JC_SUMMARY_'+datetime.datetime.now().strftime('%Y%m%d_%H%M%S'))
backup.mkdir(parents=True,exist_ok=False)
for key in ('routes','controller','show'):shutil.copy2(files[key],backup/(key+'.before'))
try:
    for key,val in [('routes',newrt),('controller',newctl),('show',newsh),('new',newview)]:
        files[key].write_text(val)
    subprocess.run(['php','-l',str(files['controller'])],check=True,timeout=30)
    subprocess.run(['php','-l',str(files['routes'])],check=True,timeout=30)
    subprocess.run(['php','artisan','view:cache'],cwd=root,check=True,timeout=90)
    subprocess.run(['php','artisan','route:list','--name=erp.job-cards.summary-detailed-print'],cwd=root,check=True,timeout=60)
except Exception:
    for key in ('routes','controller','show'):shutil.copy2(backup/(key+'.before'),files[key])
    files['new'].unlink(missing_ok=True)
    raise SystemExit('STOP_VALIDATION_FAILED_ROLLED_BACK')
print('INTEGRATION_APPLIED_VALIDATE_BROWSER_PRINT_BEFORE_CUSTOMER_DELIVERY')
