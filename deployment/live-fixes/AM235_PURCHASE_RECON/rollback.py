#!/usr/bin/env python3
"""AM235 guarded rollback: restore backups and remove only known new source files."""
from pathlib import Path
import sys,shutil
if len(sys.argv)!=3:raise SystemExit('Usage: rollback.py STAGING_ROOT BACKUP')
root=Path(sys.argv[1]).resolve()
backup=Path(sys.argv[2]).resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
 raise SystemExit('AM235_ROLLBACK_STOP_INVALID_ROOT')
if backup.parent != root/'storage/app/am-backups' or not backup.name.startswith('AM235_PURCHASE_RECON_'):
 raise SystemExit('AM235_ROLLBACK_STOP_INVALID_BACKUP')
restored=[
 'routes/web.php',
 'resources/views/purchases/create.blade.php',
 'resources/views/purchases/index.blade.php'
]
for rel in restored:
 if not (backup/rel).is_file(): raise SystemExit('AM235_ROLLBACK_MISSING '+rel)
for rel in restored:
 shutil.copy2(backup/rel,root/rel)
for rel in [
 'app/Http/Controllers/Web/AdminPurchaseReconciliationController.php',
 'resources/views/purchases/reconciliation.blade.php'
]:
 target=root/rel
 if target.exists() and 'AM235' in target.read_text('utf8','replace'):
  target.unlink()
print('AM235_ROLLBACK_DONE')
