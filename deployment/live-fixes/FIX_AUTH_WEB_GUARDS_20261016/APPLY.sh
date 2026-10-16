#!/usr/bin/env bash
# ASSAM MOTORS: Permanent split Admin vs Staff/HR Web Laravel session guards.
# Existing web guard and legacy Admin login are kept. No role escalation.
set -euo pipefail
APP_ROOT="$1"
test -n "$APP_ROOT"
cd "$APP_ROOT"
test -f artisan
python3 - <<'PY'
import os, subprocess, json
from pathlib import Path
from datetime import datetime, timezone

base=Path.cwd()
patches={
 'config/auth.php': [
  ("        'web' => [\n            'driver' => 'session',\n            'provider' => 'erp_users',\n        ],",
   "        'web' => [\n            'driver' => 'session',\n            'provider' => 'erp_users',\n        ],\n        // Independent session identity; same trusted ERP user database.\n        'staff_web' => [\n            'driver' => 'session',\n            'provider' => 'erp_users',\n        ],"),
 ],
 'routes/web.php': [
  ("Route::middleware(['auth', EnsureErpAdmin::class])->prefix('erp')->group(function () {",
   "Route::middleware(['auth:web', EnsureErpAdmin::class])->prefix('erp')->group(function () {"),
  ("Route::middleware(['auth', EnsureStaffUser::class, EnsureHrManager::class])",
   "Route::middleware(['auth:staff_web', EnsureStaffUser::class, EnsureHrManager::class])"),
  ("Route::middleware(['auth', EnsureStaffUser::class])->prefix('staff')->group(function () {",
   "Route::middleware(['auth:staff_web', EnsureStaffUser::class])->prefix('staff')->group(function () {"),
 ],
 'app/Http/Controllers/Web/StaffWebAuthController.php': [
  ("$request->user() instanceof ErpAuthUser && $request->user()->isStaff()",
   "Auth::guard('staff_web')->user() instanceof ErpAuthUser && Auth::guard('staff_web')->user()->isStaff()"),
  ("Auth::login($user,false);$request->session()->regenerate();",
   "Auth::guard('staff_web')->login($user,false);$request->session()->regenerate();"),
  ("return redirect()->intended(route('staff.portal'));",
   "// Never reuse a stale Admin intended URL on Staff login.\n        return redirect()->route('staff.portal');"),
  ("Auth::logout();$request->session()->invalidate();$request->session()->regenerateToken();",
   "// Scope logout to Staff/HR only; Admin login remains intact.\n        Auth::guard('staff_web')->logout();$request->session()->regenerate();"),
 ],
 'app/Http/Controllers/Web/HrDirectLoginController.php': [
  ("if (HrAccess::permitted($request->user())) {",
   "$hrUser = Auth::guard('staff_web')->user();\n        if (HrAccess::permitted($hrUser)) {"),
  ("'currentAccount' => $request->user() instanceof ErpAuthUser\n                ? (string)$request->user()->email : null,",
   "'currentAccount' => $hrUser instanceof ErpAuthUser\n                ? (string)$hrUser->email : null,"),
  ("Auth::login($user, false);",
   "Auth::guard('staff_web')->login($user, false);"),
 ],
 'app/Http/Controllers/Web/WebAuthController.php': [
  ("Auth::login($user, false);",
   "Auth::guard('web')->login($user, false);"),
  ("Auth::logout();",
   "Auth::guard('web')->logout();"),
  ("$request->session()->invalidate();",
   "// Scope Admin logout to Admin guard, preserve Staff/HR session.\n        $request->session()->regenerate();"),
 ],
}
staged=[]
for name, transformations in patches.items():
    f=base/name
    if not f.is_file():
        raise RuntimeError("EXPECTED_STAGING_SOURCE_MISSING: "+name)
    original=f.read_text()
    result=original
    for old,new in transformations:
        if old not in result and new in result:
            continue
        count=result.count(old)
        if count!=1:
            raise RuntimeError("UNEXPECTED_SOURCE_DRIFT: "+name+" match count="+str(count)+" for "+old[:80])
        result=result.replace(old,new,1)
    if result==original:
        print("ALREADY_PATCHED "+name)
        continue
    check=subprocess.run(['php','-l'],input=result,text=True,capture_output=True)
    if check.returncode!=0:
        raise RuntimeError("PHP_LINT_FAILED_PRE_WRITE: "+name+" "+check.stdout+" "+check.stderr)
    staged.append((f,original,result))
if not staged:
    print("AUTH_GUARD_SPLIT_ALREADY_APPLIED=YES")
stamp=datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ')
applied=[]
try:
    for f,original,modified in staged:
        backup=f.with_name(f.name+".backup-auth-guards-"+stamp)
        backup.write_text(original)
        backup.chmod(f.stat().st_mode&0o777)
        tmp=f.with_name("."+f.name+".auth-guard-new")
        tmp.write_text(modified)
        tmp.chmod(f.stat().st_mode&0o777)
        os.replace(tmp,f)
        applied.append((f,original))
        print("AUTH_GUARD_PATCHED="+str(f.relative_to(base))+" BACKUP="+backup.name)
    for name in patches:
        f=base/name
        lint=subprocess.run(['php','-l',str(f)],text=True,capture_output=True)
        if lint.returncode:
            raise RuntimeError("POST_WRITE_PHP_LINT_FAILED: "+name+" "+lint.stdout)
    cache=subprocess.run(['php','artisan','optimize:clear'],text=True,capture_output=True)
    if cache.returncode:
        raise RuntimeError("CACHE_CLEAR_FAILED: "+cache.stdout+" "+cache.stderr)
    # Verify both session guard drivers exist after Laravel boot.
    bootstrap=r"""require 'vendor/autoload.php'; $app=require 'bootstrap/app.php'; $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap(); $auth=$app->make('auth'); foreach(['web','staff_web'] as $g){if(!($auth->guard($g) instanceof \Illuminate\Auth\SessionGuard))exit(6);} echo "BOTH_SESSION_GUARDS_RESOLVE=YES\n";"""
    runtime=subprocess.run(['php','-r',bootstrap],text=True,capture_output=True)
    if runtime.returncode:raise RuntimeError("AUTH_GUARD_BOOT_FAILED: "+runtime.stdout+" "+runtime.stderr)
    print(runtime.stdout.strip())
    route=subprocess.run(['php','artisan','route:list','--json'],text=True,capture_output=True)
    if route.returncode:raise RuntimeError("ROUTE_LIST_FAILED: "+route.stdout+" "+route.stderr)
    routes=json.loads(route.stdout)
    pairs=[
        ('erp/attendance/{attendance}/reset','auth:web'),
        ('staff/inspections/{job}/work','auth:staff_web'),
        ('erp/hr/operations','auth:staff_web'),
    ]
    for uri,expected in pairs:
        r=next((v for v in routes if v.get('uri')==uri),None)
        if r is None or not any(str(m)==expected or str(m).endswith('\\Authenticate:'+expected.split(':',1)[1]) for m in r.get('middleware',[])):
            raise RuntimeError("GUARDED_ROUTE_MISSING: "+uri+" "+expected)
        print("ROUTE_GUARD_OK="+uri+" "+expected)
    # Staff linkage alone must never confer Admin privileges.
    model=(base/'app/Models/ErpAuthUser.php').read_text()
    assert "['admin', 'supervisor']" in model, "ADMIN_ROLE_PREDICATE_CHANGED"
    print("ADMIN_ROLE_POLICY=UNCHANGED")
    print("AUTH_GUARD_SPLIT_STAGING=DEPLOYED_AND_VERIFIED")
except BaseException:
    for f,old in applied:
        f.write_text(old)
    subprocess.run(['php','artisan','optimize:clear'],capture_output=True,text=True)
    print("AUTH_GUARD_SPLIT_ROLLBACK=PERFORMED")
    raise
PY
