#!/usr/bin/env python3
"""FIX126 - guarded STAGING deploy: two batch Parts buttons, one new controller, routes.

Check mode: zero writes. Apply: backed-up view/routes, PHP/Blade precompiled,
no DB changes and no stock movement. Reject any source hashes not previously audited.
"""
import hashlib, os, stat, subprocess, sys, tempfile
from pathlib import Path
from datetime import datetime, timezone
import urllib.request

VIEW_SHA = "0907e40afeac137438e572fa149dfb4c3bbaceb4501b7cc8155c7a3f51365253"
ROUTES_SHA = "84a777e6584a42ae86f892e881e264c90fb4314bab7063badcc97f7dc04a8da6"
CONTROLLER_URL = ("https://raw.githubusercontent.com/birensingha/assam-motors-erp/"
                  "49619424656a82c8355843e33e78426761b5bf71/"
                  "deployment/live-fixes/FIX126_BULK_PARTS/AdminJobCardPartsBatchController.php")
VIEW = Path("resources/views/job-cards/show.blade.php")
ROUTES = Path("routes/web.php")
CONTROLLER = Path("app/Http/Controllers/Web/AdminJobCardPartsBatchController.php")
MARKER = "FIX126_BULK_PARTS_TWO_BUTTONS"
PARTIAL = "@include('job-cards.part-stock-control', ['p' => $p])"
ROUTE_ANCHOR = "// P34_ROT_BILLING_CONTROL"
ADD_ANCHOR = "{{-- FIX85_COMPACT_PARTS_TABLE_ROW"

BULK_UI = r'''{{-- FIX126_BULK_PARTS_TWO_BUTTONS: bulk stock and fit, no requisition step. --}}
@php
  $am126StockReady = in_array(strtolower(trim((string)$job->status)), ['wip','in-progress','in progress','work in progress'], true) && empty($job->invoice_id);
  $am126Issueable = ['', 'PLANNED', 'PENDING', 'NOT ISSUED', 'NOT_ISSUED', 'REQUISITIONED'];
  $am126IssueCount = $parts->filter(fn($p)=>in_array(strtoupper(trim((string)($p->issue_status??''))),$am126Issueable,true))->count();
  $am126FitCount = $parts->filter(fn($p)=>strtoupper(trim((string)($p->issue_status??'')))==='ISSUED')->count();
  $am126FittedCount = $parts->filter(fn($p)=>strtoupper(trim((string)($p->issue_status??'')))==='FITTED')->count();
@endphp
<div class="am126-parts-bulk" role="group" aria-label="Bulk Parts Issue and Fitted">
 <div class="am126-status"><strong>Parts Stock:</strong>
  Planned {{ $am126IssueCount }} · Issued {{ $am126FitCount }} · Fitted {{ $am126FittedCount }}
 </div>
 @if($am126StockReady)
 <div class="am126-actions">
  <form method="post" action="{{ route('erp.job-cards.parts.issue-all',$job->id) }}"
    onsubmit="return confirm('ISSUE ALL planned parts? Available stock will be deducted ONCE per item. If ANY stock is missing, the entire batch is rejected. Continue?')">
   @csrf
   <input type="hidden" name="reason" value="Bulk parts stock issue authorised and confirmed by Admin">
   <button type="submit" class="btn primary" @if($am126IssueCount===0) disabled aria-disabled="true" @endif>
      ① Issue All Parts ({{ $am126IssueCount }})
   </button>
  </form>
  <form method="post" action="{{ route('erp.job-cards.parts.fitted-all',$job->id) }}"
    onsubmit="return confirm('CONFIRM ALL issued parts are physically FITTED and checked on the vehicle? No stock deduction in this step.')">
   @csrf
   <input type="hidden" name="reason" value="Bulk parts installation physically verified and confirmed by Admin">
   <button type="submit" class="btn" @if($am126IssueCount>0 || $am126FitCount===0) disabled aria-disabled="true" @endif>
      ② Mark All Fitted ({{ $am126FitCount }})
   </button>
  </form>
 </div>
 @else
 <span class="am126-muted">Batch actions available only during WIP before Invoice.</span>
 @endif
</div>
<style>
.am126-parts-bulk{border:1px solid #bbd8ca;border-radius:8px;padding:7px 10px;margin:5px 0 9px;
display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;background:#f3faf6}
.am126-status{font-size:11px;color:#244d3b;font-weight:650}
.am126-actions{display:flex;gap:7px;align-items:center;flex-wrap:wrap}
.am126-actions form{display:inline;margin:0}
.am126-actions .btn{font-size:11px;padding:7px 11px;min-height:32px;white-space:nowrap}
.am126-actions button[disabled]{opacity:.48;cursor:not-allowed}
.am126-muted{font-size:11px;color:#687b74}
</style>

'''

NEW_ROUTES = r'''    // FIX126_BULK_PARTS_TWO_BUTTONS — one audited atomic batch, no per-line requisition required.
    Route::post('/job-cards/{job}/parts/issue-all', [\App\Http\Controllers\Web\AdminJobCardPartsBatchController::class,'issueAll'])
        ->whereNumber('job')->name('erp.job-cards.parts.issue-all');
    Route::post('/job-cards/{job}/parts/fitted-all', [\App\Http\Controllers\Web\AdminJobCardPartsBatchController::class,'fittedAll'])
        ->whereNumber('job')->name('erp.job-cards.parts.fitted-all');
'''

def hashb(b):return hashlib.sha256(b).hexdigest()
def fail(s):print("FIX126_ABORT="+s,flush=True);raise SystemExit(2)
def execute(args, cwd, timeout=55):
    p=subprocess.run(args,cwd=cwd,capture_output=True,text=True,timeout=timeout)
    if p.returncode:
        print("FIX126_DIAGNOSTIC="+(p.stdout+"\n"+p.stderr)[-800:])
        fail("COMMAND_FAILED_"+str(args[:3]))
    return p.stdout.strip()
def mutate_view(raw):
    src=raw.decode("utf-8","surrogateescape")
    if src.count(ADD_ANCHOR)!=1:fail("PARTS_INSERTION_ANCHOR_COUNT")
    if src.count(PARTIAL)!=1:fail("EXISTING_PART_STOCK_ACTIONS_ANCHOR_COUNT")
    start=src.index(ADD_ANCHOR)
    at=src.rfind("  ",0,start)
    # Only insert before a real Blade comment, preserving existing markup.
    open_comment=src.rfind("{{--",0,start)
    if open_comment<0 or start-open_comment>4:fail("PARTS_COMMENT_UNEXPECTED")
    src=src[:open_comment]+BULK_UI+src[open_comment:]
    src=src.replace(PARTIAL,"{{-- FIX126: individual Requisition/Issue/Fitted controls replaced by two bulk actions. --}}",1)
    if src.count(MARKER)!=1:fail("VIEW_PATCH_BAD")
    return src.encode("utf-8","surrogateescape")
def mutate_routes(raw):
    s=raw.decode("utf-8","surrogateescape")
    if s.count(ROUTE_ANCHOR)!=1:fail("ROUTES_INSERT_ANCHOR_COUNT")
    s=s.replace(ROUTE_ANCHOR,NEW_ROUTES+"    "+ROUTE_ANCHOR,1)
    for route in ["erp.job-cards.parts.issue-all","erp.job-cards.parts.fitted-all"]:
        if s.count(route)!=1:fail("ROUTE_DUPLICATE")
    return s.encode("utf-8","surrogateescape")
def compile_blade(root,source):
    with tempfile.TemporaryDirectory(prefix="fix126_compile_") as d:
        dest=Path(d)/"compiled.php"
        php="require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();file_put_contents($argv[2],\\Illuminate\\Support\\Facades\\Blade::compileString(file_get_contents($argv[1])));"
        execute(["php","-r",php,str(source),str(dest)],root)
        execute(["php","-l",str(dest)],root)
    print("FIX126_BLADE_COMPILE_PHP_LINT=PASS")
def main():
    if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply"):fail("USAGE")
    mode,root=sys.argv[1],Path(sys.argv[2]).resolve()
    if not (root/"artisan").is_file() or not (root/"vendor/autoload.php").is_file():fail("NOT_LARAVEL_ROOT")
    view,routes,controller=root/VIEW,root/ROUTES,root/CONTROLLER
    if not view.is_file() or not routes.is_file():fail("MISSING_TARGET")
    vr=view.read_bytes();rr=routes.read_bytes()
    print("FIX126_MODE="+mode)
    print("FIX126_VIEW_SHA="+hashb(vr))
    print("FIX126_ROUTES_SHA="+hashb(rr))
    if MARKER in vr.decode("utf-8","surrogateescape"):
        print("FIX126_ALREADY_APPLIED=YES");return
    if hashb(vr)!=VIEW_SHA or hashb(rr)!=ROUTES_SHA:fail("LIVE_SOURCE_HASH_CHANGED")
    if controller.exists():fail("BATCH_CONTROLLER_ALREADY_EXISTS")
    newview=mutate_view(vr);newroutes=mutate_routes(rr)
    if mode=="check":
        print("FIX126_CHECK_PASS_NO_WRITES")
        print("FIX126_VIEW_TARGET_SHA="+hashb(newview))
        print("FIX126_ROUTES_TARGET_SHA="+hashb(newroutes))
        print("FIX126_NO_STOCK_WRITES_UNTIL_BUTTON_PRESS=YES")
        return
    # Download pinned code, PHP lint, compile view BEFORE touching live sources.
    with tempfile.TemporaryDirectory(prefix="fix126_prepare_") as d:
        source=Path(d)/"AdminJobCardPartsBatchController.php"
        urllib.request.urlretrieve(CONTROLLER_URL,str(source))
        execute(["php","-l",str(source)],root)
        tmpview=Path(d)/"show.blade.php"
        tmpview.write_bytes(newview)
        compile_blade(root,tmpview)
        tmproutes=Path(d)/"web.php"
        tmproutes.write_bytes(newroutes)
        execute(["php","-l",str(tmproutes)],root)
        print("FIX126_CONTROLLER_ROUTES_SYNTAX=PASS")
        backup_dir=root/"storage/app/am-backups"
        backup_dir.mkdir(parents=True,exist_ok=True)
        tag=datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
        bv=backup_dir/("FIX126_show_"+tag+".blade.php.bak")
        br=backup_dir/("FIX126_routes_"+tag+".php.bak")
        for f,b,original in [(view,bv,vr),(routes,br,rr)]:
            with os.fdopen(os.open(b,os.O_CREAT|os.O_EXCL|os.O_WRONLY,0o600),"wb") as out:
                out.write(original);out.flush();os.fsync(out.fileno())
            if hashb(b.read_bytes())!=hashb(original):fail("BACKUP_VERIFY_FAILED")
        print("FIX126_BACKUPS_VERIFIED=YES")
        # Preserve legacy bytes and permissions. Roll back both routes and view on any exception.
        changed=[]
        try:
            for dest,new in [(controller,source.read_bytes()),(routes,newroutes),(view,newview)]:
                temp=dest.with_name("."+dest.name+".fix126.tmp")
                if temp.exists():fail("STALE_TEMP_FILE")
                temp.write_bytes(new)
                os.chmod(temp, (dest.stat().st_mode & 0o777) if dest.exists() else 0o644)
                os.replace(temp,dest)
                changed.append(dest)
            if hashb(view.read_bytes())!=hashb(newview) or hashb(routes.read_bytes())!=hashb(newroutes):
                raise RuntimeError("POST_WRITE_HASH_BAD")
            execute(["php","artisan","route:clear"],root)
            execute(["php","artisan","view:clear"],root)
            routeslist=execute(["php","artisan","route:list","--name=erp.job-cards.parts.issue-all"],root)
            if "erp.job-cards.parts.issue-all" not in routeslist:
                raise RuntimeError("BATCH_ISSUE_ROUTE_NOT_REGISTERED")
            routeslist=execute(["php","artisan","route:list","--name=erp.job-cards.parts.fitted-all"],root)
            if "erp.job-cards.parts.fitted-all" not in routeslist:
                raise RuntimeError("BATCH_FITTED_ROUTE_NOT_REGISTERED")
            print("FIX126_BATCH_ROUTES_REGISTERED=YES")
            print("FIX126_STAGING_APPLIED=YES")
            print("FIX126_NEW_VIEW_SHA="+hashb(view.read_bytes()))
            print("FIX126_STOCK_AND_FINANCIAL_DATA_UNCHANGED=YES")
            print("FIX126_BACKUP_VIEW="+str(bv))
            print("FIX126_BACKUP_ROUTES="+str(br))
        except Exception as e:
            print("FIX126_ROLLBACK_REASON="+str(e))
            if view in changed:view.write_bytes(vr)
            if routes in changed:routes.write_bytes(rr)
            if controller in changed:controller.unlink(missing_ok=True)
            try:execute(["php","artisan","route:clear"],root)
            except Exception:pass
            try:execute(["php","artisan","view:clear"],root)
            except Exception:pass
            fail("DEPLOY_FAILED_ROLLED_BACK")
if __name__=="__main__":main()
