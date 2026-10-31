#!/usr/bin/env python3
"""AM240 SQL hotfix for deployed Staging payment create GET. No financial writes."""
import sys,os,tempfile,subprocess,shutil
from pathlib import Path
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('Usage: hotfix.py check|apply|rollback STAGING_LARAVEL_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
file=root/'app/Http/Controllers/Web/AdminExpensePaymentPageController.php'
backup=root/'storage/app/am-backups/AM240_STAFF_PAYMENT_VOUCHER/expense-payables-controller.before-sql-hotfix.php'
bad="->havingRaw('e.total_amount-COALESCE(paid.paid,0)>0.009')"
good="->whereRaw('e.total_amount-COALESCE(paid.paid,0)>0.009')"
if not (root/'artisan').is_file() or not file.is_file():raise SystemExit('AM240_HOTFIX_STOP_NO_STAGING_SOURCE')
source=file.read_text('utf8',errors='surrogateescape')
if mode=='rollback':
    if not backup.is_file():raise SystemExit('AM240_HOTFIX_STOP_MISSING_BACKUP')
    file.write_text(backup.read_text('utf8',errors='surrogateescape'),'utf8',errors='surrogateescape')
    print('AM240_HOTFIX_CONTROLLER_RESTORED')
    sys.exit(0)
if source.count(good)==1 and source.count(bad)==0:
    print('AM240_HOTFIX_ALREADY_APPLIED_PASS');sys.exit(0)
if source.count(bad)!=1 or source.count(good):
    raise SystemExit('AM240_HOTFIX_STOP_SOURCE_DRIFT')
updated=source.replace(bad,good,1)
with tempfile.TemporaryDirectory(prefix='am240_sql_') as t:
    p=Path(t)/'controller.php';p.write_text(updated,'utf8',errors='surrogateescape')
    chk=subprocess.run(['php','-l',str(p)],cwd=root,capture_output=True,text=True,timeout=30)
    if chk.returncode:raise SystemExit('AM240_HOTFIX_STOP_PHP_SYNTAX '+chk.stderr[-200:])
print('AM240_HOTFIX_SQL_WHERE_PREVIEW_PASS')
if mode=='check':
    print('AM240_HOTFIX_READONLY_PREFLIGHT_PASS');sys.exit(0)
if backup.exists():raise SystemExit('AM240_HOTFIX_STOP_BACKUP_ALREADY_EXISTS')
backup.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(file,backup)
fd,tmp=tempfile.mkstemp(prefix='.am240_where_',dir=file.parent)
try:
    with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as stream:stream.write(updated)
    os.chmod(tmp,file.stat().st_mode&0o777)
    os.replace(tmp,file)
finally:
    if os.path.exists(tmp):os.unlink(tmp)
print('AM240_HOTFIX_SQL_WHERE_STAGING_APPLIED')
