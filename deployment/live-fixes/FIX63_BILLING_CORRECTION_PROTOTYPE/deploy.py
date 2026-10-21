#!/usr/bin/env python3
"""FIX63 single-file, fail-closed STAGING Blade replacement; no DB mutations."""
from pathlib import Path
import hashlib,os,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
    raise SystemExit('Usage: deploy.py check|apply ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file() and (root/'routes/web.php').is_file()
dest=root/'resources/views/job-cards/credit-correction.blade.php'
src=Path(__file__).resolve().parent/'credit-correction.blade.php'
assert dest.is_file() and src.is_file()
new=src.read_text(encoding='utf8')
old=dest.read_text(encoding='utf8')
marker='AM63_BILLING_PROTOTYPE_V1'
expected='9d794c72ede27a031ebb8c421191c2b1d290728a6298ac7a07ec4994f2193c05'
if old==new:print('FIX63_ALREADY_APPLIED');sys.exit(0)
if marker in old:raise SystemExit('FIX63_DIFFERENT_PATCH_ALREADY_PRESENT')
current_sha=hashlib.sha256(old.encode('utf8')).hexdigest()
print('FIX63_CURRENT_SHA256',current_sha)
assert current_sha==expected,'FIX63_LIVE_SOURCE_CHANGED_REQUIRE_REVIEW'
for token in ["route('erp.job-cards.return-to-jc.unpaid'","route('erp.job-cards.lifecycle.reopen'","@foreach($notes as $cn)","@foreach($sessions as $rot)",'am56-wrap']:
    assert token in old,'FIX63_OLD_SOURCE_CONTRACT_MISMATCH: '+token
for token in [
    marker,
    "route('erp.job-cards.return-to-jc.unpaid'",
    "route('erp.job-cards.lifecycle.reopen'",
    "route('erp.job-cards.show'",
    "route('erp.invoices.show'",
    "route('erp.money-receipts.index'",
    "@foreach($notes as $cn)",
    "@forelse($sessions as $rot)",
    "name=\"confirmed_finance\"",
    "name=\"credit_date\"",
    "id=\"am63-start-return\"",
    "id=\"am63-return-modal\"",
    "FINANCE HOLD",
]:
    assert token in new,'FIX63_NEW_VIEW_MISSING: '+token
routes=(root/'routes/web.php').read_text(encoding='utf8')
for name in ['erp.job-cards.return-to-jc.unpaid','erp.job-cards.lifecycle.reopen','erp.job-cards.credit-correction']:
    assert routes.count("name('"+name+"')")==1,'FIX63_ROUTE_MISSING_OR_DUPLICATED '+name
assert new.count("@extends(")==1 and new.count("@section('content')")==1
assert new.count('@endsection')==1
if mode=='check':
    print('FIX63_PREFLIGHT_PASS_SINGLE_VIEW_FINANCE_ROUTES_UNCHANGED')
    sys.exit(0)
fd,temp=tempfile.mkstemp(prefix='.fix63.',dir=dest.parent)
try:
    with os.fdopen(fd,'w',encoding='utf8') as f:f.write(new)
    os.chmod(temp,dest.stat().st_mode & 0o777)
    os.replace(temp,dest)
finally:
    if os.path.exists(temp):os.unlink(temp)
print('FIX63_NEW_BILLING_CORRECTION_VIEW_APPLIED_NO_DATABASE_WRITES')
