#!/usr/bin/env python3
"""FIX62: real prototype-matched Job Card Register, safe GET Return navigation.
Fixes duplicate route names without changing finance posting semantics.
Does NOT write Job Card, Money Receipt, Invoice or CN records.
"""
from pathlib import Path
import os,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
    raise SystemExit('Usage: apply.py check|apply ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file() and (root/'routes/web.php').is_file()
pkg=Path(__file__).resolve().parent
controller=root/'app/Http/Controllers/Web/AdminJobCardPageController.php'
index=root/'resources/views/job-cards/index.blade.php'
route=root/'routes/web.php'
correction=root/'resources/views/job-cards/credit-correction.blade.php'
marker='FIX62_MATCH_ADMIN_PROTOTYPE'
original={
 controller:controller.read_text(encoding='utf8'),
 index:index.read_text(encoding='utf8'),
 route:route.read_text(encoding='utf8'),
 correction:correction.read_text(encoding='utf8'),
}
if marker in original[index] or marker in original[controller]:
    if marker in original[index] and marker in original[controller] and "name('erp.job-cards.return-to-jc.unpaid')" in original[route]:
        print('FIX62_ALREADY_APPLIED');sys.exit(0)
    raise SystemExit('FIX62_PARTIAL_OR_DIFFERENT_VERSION_ABORT')
def once(s,old,new,key):
    n=s.count(old);print('FIX62_ANCHOR',key,n)
    if n!=1:raise SystemExit('FIX62_PREFLIGHT_FAILED_'+key)
    return s.replace(old,new,1)
# Database read only: one grouped active invoice search for the 400 listed job cards;
# 2 existence batches for original money receipts and allocations; zero N+1.
c=original[controller]
insert="""        // FIX62_MATCH_ADMIN_PROTOTYPE — load invoice/payment statuses in batches
        // for the native Job Card register (read-only, no financial writes).
        $am62Invoices=collect();
        $am62Linked=collect();
        if($jobIds && Schema::hasTable('invoices')){
            $am62Invoices=DB::table('invoices')->whereIn('job_card_id',$jobIds)
                ->whereRaw("LOWER(COALESCE(status,'')) <> 'reversed'")
                ->orderByDesc('id')->get()
                ->groupBy('job_card_id')->map(fn($group)=>$group->first());
            $am62InvoiceIds=$am62Invoices->pluck('id')->map(fn($x)=>(int)$x)->all();
            if($am62InvoiceIds){
                $am62Refs=[];
                foreach(['customer_payments','customer_payment_allocations'] as $am62Table){
                    if(!Schema::hasTable($am62Table))continue;
                    foreach(DB::table($am62Table)->whereIn('invoice_id',$am62InvoiceIds)->distinct()->pluck('invoice_id') as $am62InvoiceId){
                        $am62Refs[(int)$am62InvoiceId]=true;
                    }
                }
                $am62Linked=$am62Refs;
            }
        }
        foreach($rows as $r){
            $am62Invoice=$am62Invoices->get((int)$r->id);
            $r->am62_invoice=$am62Invoice;
            $am62Paid=$am62Invoice?(float)($am62Invoice->paid_amount??0):0.0;
            $am62Status=$am62Invoice?strtolower(trim((string)($am62Invoice->status??''))):'';
            $am62IsLinked=$am62Invoice &&
                (!empty($am62Linked[(int)$am62Invoice->id]) ||
                 $am62Paid>0.009 ||
                 (float)($am62Invoice->paid_total??0)>0.009 ||
                 in_array($am62Status,['paid','part_paid','partially_paid'],true));
            $r->am62_payment_linked=(bool)$am62IsLinked;
            $r->am62_finance_text= !$am62Invoice?'NO INVOICE':
                ($am62IsLinked
                   ? (($am62Status==='paid' ||
                     ($am62Paid>0 && $am62Paid>=(float)($am62Invoice->grand_total??0)-0.009))
                         ? 'FULLY PAID'
                         : ($am62Paid>0.009 || $am62Status==='part_paid'?'PARTIALLY PAID':'PAYMENT LINKED'))
                   : ($am62Status==='issued'?'ISSUED / UNPAID':strtoupper($am62Status)));
        }

"""
c=once(c,"        $base=DB::table('job_cards');",insert+"        $base=DB::table('job_cards');",'job_register_batch')
# Maintain original route for existing Job Card return partial, give FIX61B 2-param route
# a different explicit name used only by the Billing Correction page.
rt=original[route]
rt=once(rt,
"->whereNumber('job')->whereNumber('invoice')->name('erp.job-cards.return-to-jc');",
"->whereNumber('job')->whereNumber('invoice')->name('erp.job-cards.return-to-jc.unpaid');",
'duplicate_route_fix')
corr=once(original[correction],
"route('erp.job-cards.return-to-jc',[$card->id,$am56Active->id])",
"route('erp.job-cards.return-to-jc.unpaid',[$card->id,$am56Active->id])",
'correction_form_endpoint')
current=original[index]
assert current.count('@extends')==1 and "@section('content')" in current
assert '<table class="jc-table">' in current and 'No Job Cards found' in current
assert 'am62-root' not in current
newIndex=(pkg/'job-cards-index.blade.php').read_text(encoding='utf8')
assert 'AM62_PROTOTYPE_MATCH' in newIndex and "@endsection" in newIndex
if mode=='check':
    print('FIX62_PREFLIGHT_PASS_ROUTE_ALIAS_AND_REGISTER_UI')
    sys.exit(0)
def save(path,s):
    fd,tmp=tempfile.mkstemp(prefix='.am62-',dir=path.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8') as file:file.write(s)
        os.chmod(tmp,path.stat().st_mode & 0o777)
        os.replace(tmp,path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
save(controller,c);save(route,rt);save(correction,corr);save(index,newIndex)
print('FIX62_APPLIED_PROTOTYPE_MATCH_UI_NO_DATABASE_WRITES')
