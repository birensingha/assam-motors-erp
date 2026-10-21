#!/usr/bin/env python3
"""Source-only FIX62 modal enhancement; atomic replacement of current index view."""
from pathlib import Path
import sys,os,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
    raise SystemExit('Usage: apply_modal.py check|apply APP_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file()
pkg=Path(__file__).resolve().parent
dest=root/'resources/views/job-cards/index.blade.php'
old=dest.read_text(encoding='utf8')
new=(pkg/'job-cards-index.blade.php').read_text(encoding='utf8')
assert 'AM62_PROTOTYPE_MATCH' in new
if old==new:
    print('FIX62_MODAL_ALREADY_APPLIED')
    sys.exit(0)
checks=[
 ('existing_prototype','AM62_PROTOTYPE_MATCH' in old),
 ('no_modal', 'id="am62-modal"' not in old),
 ('existing_register','data-am62-row' in old),
 ('existing_post','data-post=' not in old),
 ('new_modal','id="am62-modal"' in new),
 ('new_unpaid_post',"route('erp.job-cards.return-to-jc.unpaid'" in new),
 ('new_finance_block','id="am62-return-blocked"' in new),
 ('new_keep_original','name="reason"' in new and 'confirmed_finance' in new),
 ('named_unpaid_route',"name('erp.job-cards.return-to-jc.unpaid')" in (root/'routes/web.php').read_text()),
]
for key,good in checks:
 print('FIX62_MODAL_CHECK',key,good)
 if not good:raise SystemExit('FIX62_MODAL_FAILED_'+key)
if mode=='check':
 print('FIX62_MODAL_PREFLIGHT_PASS')
 sys.exit(0)
fd,tmp=tempfile.mkstemp(prefix='.am62_modal-',dir=dest.parent)
try:
 with os.fdopen(fd,'w',encoding='utf8') as f:f.write(new)
 os.chmod(tmp,dest.stat().st_mode & 0o777)
 os.replace(tmp,dest)
finally:
 if os.path.exists(tmp):os.unlink(tmp)
print('FIX62_MODAL_APPLIED_UI_ONLY_NO_RECORD_WRITES')
