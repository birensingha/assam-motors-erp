#!/usr/bin/env python3
"""FIX61B guarded, idempotent, UNPAID-only single-step Return to JC install."""
from pathlib import Path
import sys, os, tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
    raise SystemExit('Usage: install.py check|apply LARAVEL_ROOT')
mode=sys.argv[1]; root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file() and (root/'routes/web.php').is_file()
package=Path(__file__).resolve().parent
route=root/'routes/web.php'
view=root/'resources/views/job-cards/credit-correction.blade.php'
controller=root/'app/Http/Controllers/Web/AdminReturnToJobCardController.php'
marker='FIX61B_RETURN_TO_JC_UNPAID'
if marker in route.read_text() or controller.exists():
    if marker in route.read_text() and controller.exists():print('FIX61B_ALREADY_APPLIED');sys.exit(0)
    raise SystemExit('FIX61B partial/conflicting installation refused')
src=route.read_text(encoding='utf8')
blade=view.read_text(encoding='utf8')
def one(s,a,b,name):
    n=s.count(a);print('FIX61B_ANCHOR',name,n)
    if n!=1:raise SystemExit('FIX61B_PREFLIGHT_FAIL_'+name)
    return s.replace(a,b,1)
anchor="    Route::post('/job-cards/{job}/credit-note/{invoice}', [\\App\\Http\\Controllers\\Web\\AdminInvoiceCreditNoteController::class,'post'])"
newroute="""    // FIX61B_RETURN_TO_JC_UNPAID — atomic CN + WIP, only for financially verified unpaid issued invoice.
    Route::post('/job-cards/{job}/return-to-jc/{invoice}',
        [\\App\\Http\\Controllers\\Web\\AdminReturnToJobCardController::class,'post'])
        ->whereNumber('job')->whereNumber('invoice')->name('erp.job-cards.return-to-jc');
"""
src=one(src,anchor,newroute+anchor,'route')
# Replace the existing FORM destination (not permissions/finance confirmation),
# so there is only ONE action instead of the confusing legacy two-step flow.
old="route('erp.job-cards.credit-note.post',[$card->id,$am56Active->id])"
new="route('erp.job-cards.return-to-jc',[$card->id,$am56Active->id])"
blade=one(blade,old,new,'form_action')
blade=one(blade,"Issue Full Credit Note (Unpaid Invoice Only)",
    "RETURN TO JOB CARD — Full CN + Open WIP (Unpaid Only)",'button_label')
blade=one(blade,"Post full Credit Note against issued invoice? This is an accounting transaction.",
    "Return this Job Card to WIP? The original unpaid invoice will be fully credited and preserved. Both steps are one atomic transaction.",'confirm')
blade=one(blade,
    "Payment-linked invoices require Finance settlement first. GST/e-Invoice portal reporting is NOT automatic.",
    "Payment-linked invoices are blocked from this action pending the safe receipt-allocation workflow. GST/e-Invoice portal reporting is NOT automatic.",
    'disclaimer')
if mode=='check':
    print('FIX61B_PREFLIGHT_PASS_NEW_CONTROLLER_ROUTE_AND_ONE_STEP_BUTTON')
    sys.exit(0)
assert not controller.exists()
def write(p,s,modebits=None):
    fd,temp=tempfile.mkstemp(prefix='.fix61b.',dir=p.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8') as f:f.write(s)
        if modebits is not None:os.chmod(temp,modebits)
        os.replace(temp,p)
    finally:
        if os.path.exists(temp):os.unlink(temp)
write(controller,(package/'AdminReturnToJobCardController.php').read_text(encoding='utf8'),0o644)
write(route,src,route.stat().st_mode & 0o777)
write(view,blade,view.stat().st_mode & 0o777)
print('FIX61B_APPLIED_CONTROLLER_ROUTE_BUTTON_NO_DB_WRITES')
