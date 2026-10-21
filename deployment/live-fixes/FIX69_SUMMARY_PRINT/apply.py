#!/usr/bin/env python3
"""FIX69: one-page printable JC summary + remove bottom Advanced Reversal Centre from JC summary only.
No finance records, reversal endpoints or other database writes are touched.
"""
from pathlib import Path
import sys,os,re,tempfile,hashlib
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):raise SystemExit('Usage: apply.py check|apply ROOT')
mode=sys.argv[1]; root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file() and (root/'routes/web.php').is_file()
pkg=Path(__file__).resolve().parent
jc=root/'resources/views/job-cards/show.blade.php'
controller=root/'app/Http/Controllers/Web/AdminJobCardPageController.php'
routes=root/'routes/web.php'
printview=root/'resources/views/job-cards/summary-print.blade.php'
raw=jc.read_bytes(); old=raw.decode('utf8',errors='surrogateescape')
ctl=controller.read_bytes().decode('utf8',errors='surrogateescape')
rt=routes.read_bytes().decode('utf8',errors='surrogateescape')
source=(pkg/'summary-print.blade.php').read_text(encoding='utf8')
marker='FIX69_ONE_PAGE_PRINT'
if marker in old or printview.exists():
 if marker in old and printview.exists() and marker in ctl and marker in rt:
  print('FIX69_ALREADY_APPLIED');sys.exit(0)
 raise SystemExit('FIX69_PARTIAL_INSTALL_ABORT')
expectedShow='ac258b48a146017f790508f127da52ffcbf06fc9ff2a7f67cab4b6e6f8747e82'
print('FIX69_EXISTING_SHOW_SHA',hashlib.sha256(raw).hexdigest())
if hashlib.sha256(raw).hexdigest()!=expectedShow:raise SystemExit('FIX69_EXISTING_SHOW_CHANGED_ABORT')
def subonce(source,old,new,label):
 n=source.count(old);print('FIX69_ANCHOR',label,n)
 if n!=1:raise SystemExit('FIX69_FAIL_'+label)
 return source.replace(old,new,1)
topAnchor="@section('content')"
newTop="""@section('content')
{{-- FIX69_ONE_PAGE_PRINT — shortcut above full interactive JC --}}
<div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;margin:6px 0 12px">
 <a class="btn primary" href="{{ route('erp.job-cards.summary-print',$job->id) }}" target="_blank" rel="noopener">🖨 One-Page Printable Job Card Summary</a>
 <a class="btn" href="{{ route('erp.job-cards.credit-correction',$job->id) }}">↩ Billing Correction / Return to JC</a>
</div>
"""
old=subonce(old,topAnchor,newTop,'print_toolbar')
opening='{{-- P19_JOBCARD_ADMIN_PANELS_BOTTOM — admin controls last; no route, permission or business logic change --}}'
closing="@if(strtolower((string)$job->status)==='open')"
n1=old.count(opening);n2=old.count(closing)
print('FIX69_REMOVE_REVERSAL_BLOCK',n1,n2)
if n1!=1 or n2!=1:raise SystemExit('FIX69_BLOCK_ANCHOR_MISMATCH')
pos1=old.index(opening);pos2=old.index(closing,pos1)
deleted=old[pos1:pos2]
if 'Advanced Reversal Centre' not in deleted or 'Simple Billing Correction' not in deleted:
 raise SystemExit('FIX69_WRONG_BLOCK_REFUSE')
old=old[:pos1]+"""{{-- FIX69: legacy finance navigation removed from customer Job Card Summary.
     Existing audited finance/reversal backend records and routes are unchanged. --}}
"""+old[pos2:]
fn="    public function show(Request $request, int $job, JobCardService $service): View"
newfn="""    // FIX69_ONE_PAGE_PRINT — read-only customer cost summary, uses existing Job Card data.
    public function printSummary(Request $request, int $job, JobCardService $service): View
    {
        $this->admin($request);
        return view('job-cards.summary-print',$service->detail($job));
    }

"""+fn
ctl=subonce(ctl,fn,newfn,'print_method')
routeLines=rt.splitlines(keepends=True)
matches=[i for i,line in enumerate(routeLines) if "name('erp.job-cards.show')" in line and 'Route::get(' in line]
print('FIX69_JC_SHOW_ROUTE_COUNT',len(matches))
if len(matches)!=1:raise SystemExit('FIX69_ROUTE_ANCHOR_MISMATCH')
routeLines.insert(matches[0],"    // FIX69_ONE_PAGE_PRINT\n    Route::get('/job-cards/{job}/summary-print', [AdminJobCardPageController::class,'printSummary'])->whereNumber('job')->name('erp.job-cards.summary-print');\n")
rt=''.join(routeLines)
for token in ["@page{size:A4 portrait","ASSAM MOTORS","Job Card Summary","NOT A TAX INVOICE","vehicle_inward_at","$amMismatch"]:
 if token not in source:raise SystemExit('FIX69_MISSING_VIEW_CONTRACT_'+token)
if mode=='check':
 print('FIX69_PREFLIGHT_PASS_NO_FINANCIAL_WRITES');sys.exit(0)
def write(path,text,perm):
 fd,tmp=tempfile.mkstemp(prefix='.fix69_',dir=path.parent)
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(text)
  os.chmod(tmp,perm);os.replace(tmp,path)
 finally:
  if os.path.exists(tmp):os.unlink(tmp)
write(printview,source,0o644)
write(jc,old,jc.stat().st_mode&0o777)
write(controller,ctl,controller.stat().st_mode&0o777)
write(routes,rt,routes.stat().st_mode&0o777)
print('FIX69_APPLIED_PRINT_ROUTE_AND_HIDDEN_REVERSAL_CARD_NO_DB_WRITES')
