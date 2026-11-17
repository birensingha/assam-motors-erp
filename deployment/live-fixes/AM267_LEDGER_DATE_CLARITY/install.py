#!/usr/bin/env python3
"""AM267 — STAGING-only, read-only Item Transaction Ledger display clarity.
No schema/data changes: distinguishes document/physical receipt dates from ERP
posting dates, fixes vendor shown on stock-IN, and offers event-type filtering.
Safe install: check | apply | rollback <STAGING_ROOT>.
"""
from pathlib import Path
from datetime import datetime
import hashlib,json,os,subprocess,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('USAGE_AM267 install.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('STOP_AM267_NOT_STAGING')
ctl='app/Http/Controllers/Web/AdminMasterItemLedgerController.php'
view='resources/views/admin/native/item-ledger.blade.php'
paths=[ctl,view]
expected={
 ctl:'38efc9d70a5ccc479bb9f885f6206d1e6d99adb5d59325b0b1209cb67a572f81',
 view:'8f5650ab03763cbde5850c24841f1b75fd46cf5a9f59d920d2338e01775489d9',
}
backupBase=root/'storage/app/am-backups'
pointer=backupBase/'AM267_ITEM_LEDGER_DATE_BACKUP.txt'
def sha(b):return hashlib.sha256(b).hexdigest()
def run(args,timeout=110):return subprocess.run(args,cwd=root,capture_output=True,text=True,timeout=timeout)
def once(t,old,new,tag):
    n=t.count(old)
    if n!=1:raise SystemExit('STOP_AM267_ANCHOR_'+tag+'_COUNT_'+str(n))
    return t.replace(old,new,1)
def atom(path,data):
    path.parent.mkdir(parents=True,exist_ok=True)
    fd,tmp=tempfile.mkstemp(prefix='.am267_',dir=path.parent)
    try:
        with os.fdopen(fd,'wb') as f:f.write(data)
        os.chmod(tmp,(path.stat().st_mode&0o777) if path.is_file() else 0o644)
        os.replace(tmp,path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
def lint(content,label):
    with tempfile.NamedTemporaryFile(suffix='.php',delete=False) as f:
        f.write(content);p=Path(f.name)
    try:
        out=run(['php','-l',str(p)],55)
        if out.returncode:raise SystemExit('STOP_AM267_PHP_LINT_'+label+': '+(out.stdout+out.stderr)[-850:])
    finally:p.unlink(missing_ok=True)
def blade(content,label):
    with tempfile.TemporaryDirectory(prefix='am267blade_') as td:
        source=Path(td)/'source.blade.php';compiled=Path(td)/'compiled.php'
        source.write_bytes(content)
        php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        out=run(['php','-r',php,str(source),str(compiled)],80)
        if out.returncode or not compiled.exists():
            raise SystemExit('STOP_AM267_BLADE_COMPILE_'+label+': '+(out.stdout+out.stderr)[-650:])
        lint(compiled.read_bytes(),'BLADE_'+label)
if mode=='rollback':
    if not pointer.is_file():raise SystemExit('STOP_AM267_BACKUP_NOT_FOUND')
    backup=Path(pointer.read_text().strip()).resolve()
    if backupBase.resolve() not in backup.parents or not backup.name.startswith('AM267_LEDGER_CODE_'):
        raise SystemExit('STOP_AM267_BACKUP_LOCATION_INVALID')
    manifest=json.loads((backup/'manifest.json').read_text())
    for path,digest in manifest['installed'].items():
        if not(root/path).is_file() or sha((root/path).read_bytes())!=digest:
            raise SystemExit('STOP_AM267_SOURCE_CHANGED_AFTER_DEPLOY_'+path)
    for path in paths:atom(root/path,(backup/path).read_bytes())
    cmd=run(['php','artisan','optimize:clear'],130)
    if cmd.returncode:raise SystemExit('AM267_CODE_RESTORED_BUT_CACHE_CLEAR_FAILED')
    print('AM267_LEDGER_DISPLAY_ROLLBACK_NO_TRANSACTIONS_CHANGED')
    sys.exit(0)
for path in paths:
    if not(root/path).is_file():raise SystemExit('STOP_AM267_SOURCE_MISSING_'+path)
orig={path:(root/path).read_bytes() for path in paths}
if b'AM267_LEDGER_DATE_CLARITY' in orig[ctl] and b'AM267_LEDGER_DATE_CLARITY' in orig[view]:
    print('AM267_LEDGER_DISPLAY_ALREADY_INSTALLED')
    sys.exit(0)
for path,digest in expected.items():
    if sha(orig[path])!=digest:
        raise SystemExit('STOP_AM267_SOURCE_FINGERPRINT_MISMATCH_'+path)
controller=orig[ctl].decode('utf-8')
template=orig[view].decode('utf-8')

sourceLookup=r"""
        // AM267_LEDGER_DATE_CLARITY: lookup receipt/invoice source metadata
        // WITHOUT changing any inventory_movements timestamps or quantities.
        // ERP stock posting and supplier/GRN document dates are distinct.
        $movementReferences=$movements->pluck('reference_no')->filter()->unique()->values()->all();
        $grnDocs=DB::table('am260_grns as g')
            ->leftJoin('workshop_vendors as v','v.id','=','g.vendor_id')
            ->whereIn('g.grn_no',$movementReferences)
            ->get(['g.id','g.grn_no','g.receipt_date','g.challan_date',
                'g.challan_no','v.vendor_name'])->keyBy('grn_no');
        $purchaseDocs=DB::table('workshop_purchases as p')
            ->leftJoin('workshop_vendors as v','v.id','=','p.vendor_id')
            ->where('p.part_master_id',$id)
            ->where(function($q)use($movementReferences){
                $q->whereIn('p.supplier_invoice',$movementReferences)
                  ->orWhereIn('p.purchase_no',$movementReferences)
                  ->orWhereIn('p.purchase_batch_no',$movementReferences);
            })->orderByDesc('p.id')
            ->get(['p.id','p.purchase_no','p.purchase_batch_no',
                'p.supplier_invoice','p.supplier_name','v.vendor_name',
                'p.purchase_date']);
        $purchaseSourceByRef=[];
        foreach($purchaseDocs as $pd){
            foreach([$pd->supplier_invoice,$pd->purchase_no,$pd->purchase_batch_no] as $key){
                $key=trim((string)$key);
                if($key!=='' && !isset($purchaseSourceByRef[$key]))
                    $purchaseSourceByRef[$key]=$pd;
            }
        }
"""
controller=once(controller,
  "            ->get(['m.*','jc.job_no','jc.customer_name','jc.vehicle_reg_no']);\n        foreach($movements as $m){",
  "            ->get(['m.*','jc.job_no','jc.customer_name','jc.vehicle_reg_no']);\n"+sourceLookup+"        foreach($movements as $m){",
  'SOURCE_LOOKUP')
stockPatch=r"""
            $ix=array_key_last($timeline);
            $timeline[$ix]['erp_posted_date']=self::dateOf($m->created_at);
            $timeline[$ix]['document_date']=null;
            $timeline[$ix]['date_kind']='Stock posted';
            // Supplier / physical receipt dates do not change posting chronology.
            if(isset($grnDocs[$reference])){
                $g=$grnDocs[$reference];
                $timeline[$ix]['document_date']=self::dateOf($g->receipt_date);
                $timeline[$ix]['date_kind']='Physical receipt';
                $timeline[$ix]['party']=(string)($g->vendor_name?:'');
                $timeline[$ix]['url']=url('/erp/procurement/grn/'.$g->id);
            }elseif(isset($purchaseSourceByRef[$reference]) &&
                (string)$m->movement_type==='PURCHASE_IN'){
                $pd=$purchaseSourceByRef[$reference];
                $timeline[$ix]['document_date']=self::dateOf($pd->purchase_date);
                $timeline[$ix]['date_kind']='Supplier bill date';
                $timeline[$ix]['party']=(string)($pd->vendor_name?:$pd->supplier_name);
                $timeline[$ix]['url']=url('/erp/purchases/'.$pd->id);
            }
"""
controller=once(controller,
 "            $timeline[array_key_last($timeline)]['balance']=$running;",
 "            $timeline[array_key_last($timeline)]['balance']=$running;\n"+stockPatch,
 'STOCK_DATE_AND_VENDOR')
controller=once(controller,
  "'p.description','p.qty','p.unit_cost','p.total_amount','p.record_status',",
  "'p.description','p.qty','p.unit_cost','p.total_amount','p.record_status','p.created_at',",
  'PURCHASE_CREATED_AT')
billPatch=r"""
            $ix=array_key_last($timeline);
            $timeline[$ix]['document_date']=self::dateOf($p->purchase_date);
            $timeline[$ix]['erp_posted_date']=self::dateOf($p->created_at);
            $timeline[$ix]['date_kind']='Supplier bill date';
"""
controller=once(controller,
  "                'Supplier document; stock addition is shown separately from inventory_movements.');",
  "                'Supplier document; stock addition is shown separately from inventory_movements.');\n"+billPatch,
  'BILL_DATE')
controller=once(controller,
  "            'amount'=>$amount,'url'=>$url,'status'=>$status,'notes'=>$notes,'balance'=>null,",
  "            'amount'=>$amount,'url'=>$url,'status'=>$status,'notes'=>$notes,'balance'=>null,\n"
  "            'document_date'=>null,'erp_posted_date'=>null,'date_kind'=>null,",
  'EVENT_FIELDS')

template=once(template,
 ".am246-print{display:inline-flex}",
 ".am246-print{display:inline-flex}\n"
 ".am267-filter{font-size:11px;border:1px solid #b9d2e5;color:#134a72;padding:7px 9px;border-radius:7px;background:#fff;min-height:35px}\n"
 ".am267-date-meta{display:block;color:#748aa0;font-size:10px;margin-top:4px}\n"
 ".am267-helper{font-size:11px;background:#f5f9fc;color:#496f8e;border:1px solid #d5e5f0;border-radius:7px;padding:10px 12px;margin:9px 0;line-height:1.7}",
 'CSS')
template=once(template,
 '        <span class="am246-count">Showing {{ number_format(count($report[\'events\'])) }} of {{ number_format($totalEvents) }} recorded events</span>',
 '''        <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap">
          <label for="am267-event-filter" style="font-size:11px;font-weight:800">Show</label>
          <select id="am267-event-filter" class="am267-filter" aria-label="Filter ledger transaction categories">
            <option value="ALL">All Events</option>
            <option value="STOCK">Stock IN / OUT Only</option>
            <option value="PURCHASE">Purchase Bills Only</option>
            <option value="JC">Job Card / Billing Only</option>
          </select>
          <span class="am246-count" id="am267-visible-count">Showing {{ number_format(count($report['events'])) }} of {{ number_format($totalEvents) }} recorded events</span>
        </div>''',
 'FILTER')
template=once(template,
 '''      <div class="am246-table-scroll">
        <table class="am246-table">''',
 '''      @if($type==='PART')
        <div class="am267-helper"><strong>Two different dates:</strong> Supplier Bill Date or physical GRN Received Date can be earlier than the day the operator saved it. <strong>ERP Posted</strong> is when the transaction was entered into the stock/accounting system. Posting a later-dated bill does not backdate physical stock, and entering a GRN and a separate normal purchase may create two real stock-IN movements. This ledger does not alter any historical dates or balances.</div>
      @endif
      <div class="am246-table-scroll">
        <table class="am246-table">''',
 'EXPLANATION')
template=once(template,
 '''            <th style="width:85px">Date</th><th style="width:145px">Transaction Type</th>''',
 '''            <th style="width:95px">Ledger Date</th><th style="width:105px">Invoice / Receipt Date</th>
            <th style="width:105px">ERP Posted</th><th style="width:145px">Transaction Type</th>''',
 'HEADERS')
template=once(template,
 '''              <tr>
                <td>{{ $event['date'] ?: '—' }}</td>
                <td><span class="kind">{{ $event['type'] }}</span></td>''',
 '''              <tr data-am267-kind="{{ str_starts_with($event['type'],'STOCK ·')?'STOCK':(str_starts_with($event['type'],'PURCHASE ·')?'PURCHASE':'JC') }}">
                <td>{{ $event['date'] ?: '—' }}</td>
                <td>
                  {{ $event['document_date'] ?: '—' }}
                  @if($event['date_kind'])<small>{{ $event['date_kind'] }}</small>@endif
                </td>
                <td>{{ $event['erp_posted_date'] ?: ($event['date'] ?: '—') }}</td>
                <td><span class="kind">{{ $event['type'] }}</span></td>''',
 'ROWS')
template=once(template,
 '''              <tr><td colspan="11" class="am246-empty">No linked transactions found for this master item within the selected dates.</td></tr>''',
 '''              <tr><td colspan="13" class="am246-empty">No linked transactions found for this master item within the selected dates.</td></tr>''',
 'COLSPAN')
template=once(template,
 '''        Source-specific record numbers link to original documents where available. Purchase cost, JC billing amount, invoice and stock movements are separate accounting/physical events; do not total all amount cells as sales or purchases. Voided/deleted purchase records remain audit-visible.''',
 '''        Source-specific record numbers link to original documents where available. Ledger Date uses the original source date (invoice date for Purchase Bills; ERP movement date for Stock movements). Invoice / Receipt Date and ERP Posted are shown separately so backdated documents cannot masquerade as earlier physical stock. Existing From / To filters retain their source-date behavior. Stock quantities come ONLY from inventory movements; no double counting of Purchase Bill, GRN or JC billing rows. Voided/deleted purchase records remain audit-visible.''',
 'FOOTNOTE')
template=once(template,
 '''<script id="am246-ledger-picker">''',
 '''<script id="am267-ledger-event-filter">
(function(){
'use strict';
var select=document.getElementById('am267-event-filter');
var rows=Array.from(document.querySelectorAll('[data-am267-kind]'));
var count=document.getElementById('am267-visible-count');
if(!select||!count)return;
function render(){
  var type=select.value,n=0;
  rows.forEach(function(row){var show=type==='ALL'||row.getAttribute('data-am267-kind')===type;
    row.hidden=!show;if(show)n++;});
  count.textContent='Showing '+n.toLocaleString('en-IN')+' of '+rows.length.toLocaleString('en-IN')+' loaded events';
}
select.addEventListener('change',render);
})();
</script>
<script id="am246-ledger-picker">''',
 'CLIENT_FILTER')
patched={ctl:controller.encode('utf-8'),view:template.encode('utf-8')}
for path,b in patched.items():
    print('AM267_PATCH_SOURCE_'+path+'_BYTES='+str(len(b)))
lint(patched[ctl],'ITEM_LEDGER_CONTROLLER')
blade(patched[view],'ITEM_LEDGER_VIEW')
print('AM267_READONLY_PHP_BLADE_PREFLIGHT_PASS')
if mode=='check':
    print('AM267_CHECK_PASS_STOCK_BILLS_AND_LEDGER_DATA_UNCHANGED')
    sys.exit(0)
backup=backupBase/('AM267_LEDGER_CODE_'+datetime.now().strftime('%Y%m%d_%H%M%S'))
backup.mkdir(parents=True,exist_ok=False,mode=0o700)
for path,data in orig.items():
    dest=backup/path;dest.parent.mkdir(parents=True,exist_ok=True);dest.write_bytes(data)
installed=[]
try:
    for path,data in patched.items():
        if (root/path).read_bytes()!=orig[path]:
            raise RuntimeError('AM267_CONCURRENT_CHANGE_'+path)
        atom(root/path,data);installed.append(path)
    for cmd in [['php','artisan','optimize:clear'],['php','artisan','view:cache']]:
        p=run(cmd,145)
        if p.returncode:raise RuntimeError('AM267_CACHE_FAILURE '+(p.stdout+p.stderr)[-800:])
except BaseException as e:
    for path in installed:atom(root/path,orig[path])
    run(['php','artisan','optimize:clear'],110)
    raise SystemExit('STOP_AM267_ROLLED_BACK_CODE_'+str(e))
(backup/'manifest.json').write_text(json.dumps({'installed':{p:sha(b) for p,b in patched.items()}},indent=2))
pointer.write_text(str(backup)+'\n')
print('AM267_ITEM_LEDGER_DUAL_DATE_AND_STOCK_VENDOR_CLARITY_INSTALLED_STAGING')
print('AM267_NO_STOCK_IN_OUT_PURCHASE_GRN_JC_INVOICE_OR_HISTORY_ROWS_CHANGED')
print('AM267_BACKUP='+str(backup))
