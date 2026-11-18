#!/usr/bin/env python3
"""AM268 — STAGING-only presentation consolidation for Item Transaction Ledger.

No stock, JC, GRN, purchase, ledger, journal, invoice or historical data writes.
A consolidated voucher row is a *view* of multiple immutable source entries.
The original events remain available with Expand Audit or Raw Events mode.
Safe modes: check|apply|rollback <STAGING_ROOT>.
"""
from pathlib import Path
from datetime import datetime
import hashlib,json,os,subprocess,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('USAGE_AM268 install.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not(root/'artisan').is_file():
    raise SystemExit('STOP_AM268_NOT_STAGING')
pkg=Path(__file__).resolve().parent
controller='app/Http/Controllers/Web/AdminMasterItemLedgerController.php'
view='resources/views/admin/native/item-ledger.blade.php'
service='app/Services/MasterItemLedgerConsolidator.php'
existing=[controller,view]
expected={
 controller:'e6db72caf7b07d152858fbcfb9f551ad865ec5c184116139a993c4ed972092dc',
 view:'87b71e28b8455a3679828c2fc2defcefd9f8f12f529e506dab36b2937bf588dc',
}
backupBase=root/'storage/app/am-backups'
pointer=backupBase/'AM268_CONSOLIDATED_LEDGER_LAST_CODE_BACKUP.txt'
def sha(b):return hashlib.sha256(b).hexdigest()
def run(cmd,timeout=115):return subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=timeout)
def once(s,old,new,tag):
    n=s.count(old)
    if n!=1:raise SystemExit('STOP_AM268_ANCHOR_'+tag+'_'+str(n))
    return s.replace(old,new,1)
def atom(path,data):
    path.parent.mkdir(parents=True,exist_ok=True)
    fd,temp=tempfile.mkstemp(dir=path.parent,prefix='.am268_')
    try:
        with os.fdopen(fd,'wb') as f:f.write(data)
        os.chmod(temp,(path.stat().st_mode&0o777) if path.is_file() else 0o644)
        os.replace(temp,path)
    finally:
        if os.path.exists(temp):os.unlink(temp)
def lint(data,tag):
    with tempfile.NamedTemporaryFile(suffix='.php',delete=False) as file:
        file.write(data);p=Path(file.name)
    try:
        proc=run(['php','-l',str(p)],55)
        if proc.returncode:
            raise SystemExit('STOP_AM268_PHP_LINT_'+tag+' '+(proc.stdout+proc.stderr)[-850:])
    finally:p.unlink(missing_ok=True)
def blade(data,tag):
    with tempfile.TemporaryDirectory(prefix='am268_blade_') as td:
        source=Path(td)/'template.blade.php';out=Path(td)/'compiled.php'
        source.write_bytes(data)
        php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        proc=run(['php','-r',php,str(source),str(out)],80)
        if proc.returncode or not out.is_file():
            raise SystemExit('STOP_AM268_BLADE_COMPILE_'+tag+' '+(proc.stdout+proc.stderr)[-850:])
        lint(out.read_bytes(),'BLADE_'+tag)

if mode=='rollback':
    if not pointer.is_file():raise SystemExit('STOP_AM268_BACKUP_POINTER_MISSING')
    backup=Path(pointer.read_text().strip()).resolve()
    if backupBase.resolve() not in backup.parents or not backup.name.startswith('AM268_LEDGER_CODE_'):
        raise SystemExit('STOP_AM268_BACKUP_PATH_INVALID')
    manifest=json.loads((backup/'manifest.json').read_text())
    for path,digest in manifest['installed'].items():
        if not(root/path).is_file() or sha((root/path).read_bytes())!=digest:
            raise SystemExit('STOP_AM268_SOURCE_CHANGED_AFTER_INSTALL_'+path)
    for path in existing:atom(root/path,(backup/path).read_bytes())
    (root/service).unlink(missing_ok=True)
    proc=run(['php','artisan','optimize:clear'],140)
    if proc.returncode:raise SystemExit('AM268_SOURCE_RESTORED_CACHE_CLEAR_FAILED')
    print('AM268_LEDGER_VIEW_CODE_ROLLBACK_BUSINESS_DATA_UNCHANGED')
    sys.exit(0)

for path in existing:
    if not(root/path).is_file():raise SystemExit('STOP_AM268_SOURCE_FILE_MISSING_'+path)
if not(pkg/'files'/service).is_file():
    raise SystemExit('STOP_AM268_SERVICE_CLASS_PACKAGE_MISSING')
before={path:(root/path).read_bytes() for path in existing}
serviceFile=root/service
if b'AM268_CONSOLIDATED_ITEM_LEDGER' in before[controller] and serviceFile.is_file():
    print('AM268_CONSOLIDATED_LEDGER_ALREADY_INSTALLED');sys.exit(0)
if serviceFile.exists():raise SystemExit('STOP_AM268_SERVICE_CLASS_ALREADY_EXISTS_NO_OVERWRITE')
for path,digest in expected.items():
    if sha(before[path])!=digest:
        raise SystemExit('STOP_AM268_STAGING_SOURCE_FINGERPRINT_CONFLICT_'+path)
c=before[controller].decode('utf-8','strict')
v=before[view].decode('utf-8','strict')
# Server-side grouping takes place only AFTER chronological sorting and
# BEFORE final event count/render; other Labour/OSL modes are unchanged.
inject=r"""
        // AM268_CONSOLIDATED_ITEM_LEDGER: default one physical/financial
        // voucher row per item+Purchase/GRN or item+Job Card. Never merge
        // Shyam's purchase with Jupiter's GRN unless an explicit GRN-bill
        // allocation proves they are the same commercial receipt.
        $report['source_event_count']=count($report['events']);
        if($master && $type==='PART' &&
           strtolower((string)$request->query('ledger_view','consolidated'))!=='raw'){
            $links=DB::table('am260_grn_bill_allocations as a')
                ->join('am260_grn_lines as l','l.id','=','a.grn_line_id')
                ->join('am260_grns as g','g.id','=','l.grn_id')
                ->join('workshop_purchases as p','p.id','=','a.purchase_id')
                ->where('l.part_master_id',(int)$master->id)
                ->whereColumn('g.vendor_id','p.vendor_id')
                ->get(['a.purchase_id','l.grn_id']);
            $purchaseToGrn=[];
            foreach($links->groupBy('purchase_id') as $purchaseId=>$linked){
                $receiptIds=$linked->pluck('grn_id')->unique()->values()->all();
                // Ambiguous many-GRN allocation stays independent.
                if(count($receiptIds)===1)
                    $purchaseToGrn[(int)$purchaseId]=(int)$receiptIds[0];
            }
            $report['events']=\App\Services\MasterItemLedgerConsolidator::consolidate(
                $report['events'],$purchaseToGrn
            );
        }
"""
c=once(c,"        $totalEvents=count($report['events']);",inject+"        $totalEvents=count($report['events']);",'CONTROLLER_CONSOLIDATION')
# Compact visual projection; original raw seven rows remain in details.
v=once(v,"{{-- AM246_MASTER_ITEM_LEDGERS: read-only Tally-style Parts / ROT / OSL searchable transaction ledger --}}",
"{{-- AM246_MASTER_ITEM_LEDGERS: read-only Tally-style Parts / ROT / OSL searchable transaction ledger --}}\n{{-- AM268_CONSOLIDATED_ITEM_LEDGER: one row per source voucher/JC; original source events accessible on expansion. --}}",
'MARKER')
v=once(v,".am246-print{display:inline-flex}",
""".am246-print{display:inline-flex}
.am268-switch{padding:7px 9px;border:1px solid #bfd2e3;border-radius:7px;min-height:35px;background:#fff;color:#16466d;font-size:11px}
.am268-source-details{margin-top:7px;max-width:760px;font-size:11px}
.am268-source-details summary{color:#145b9a;font-weight:850;cursor:pointer;user-select:none;padding:5px 0}
.am268-source-scroll{overflow:auto;max-height:310px;border:1px solid #dce8ef;border-radius:8px;margin-top:5px}
.am268-audit-table{border-collapse:collapse;width:100%;min-width:790px;line-height:1.5}
.am268-audit-table th{background:#eff5fb;color:#28516e;position:static;font-size:9px;padding:7px;text-align:left}
.am268-audit-table td{border-bottom:1px solid #e9f0f5;padding:7px;font-size:10px;white-space:normal}
.am268-source-kind{font-weight:850;color:#215a89}
@media print{.am268-source-details{display:none!important}.am268-switch{display:none!important}
  @page{size:A4 landscape;margin:8mm}}
""",'CSS')
v=once(v,
"""        <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap">
          <label for="am267-event-filter" style="font-size:11px;font-weight:800">Show</label>""",
"""        <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap">
          @if($type==='PART')
            <label for="am268-ledger-mode" style="font-size:11px;font-weight:800">Ledger View</label>
            <select id="am268-ledger-mode" class="am268-switch" name="ledger_view" form="am246-form"
              onchange="document.getElementById('am246-form').submit()">
              <option value="consolidated" @selected(request('ledger_view','consolidated')!=='raw')>Consolidated — One Row / Voucher or JC</option>
              <option value="raw" @selected(request('ledger_view','consolidated')==='raw')>Full Audit — Every Source Event</option>
            </select>
          @endif
          <label for="am267-event-filter" style="font-size:11px;font-weight:800">Show</label>""",
'MODE')
v=once(v,
"""      @if($type==='PART')
        <div class="am267-helper"><strong>Two different dates:</strong>""",
"""      @if($type==='PART')
        <div class="am267-helper"><strong>Consolidated transaction view:</strong>
          @if(request('ledger_view','consolidated')==='raw')
            Every original event is shown separately for audit.
          @else
            <strong>{{ count($report['events']) }} main lines</strong> represent
            <strong>{{ $report['source_event_count']??count($report['events']) }} saved source events</strong>.
            Physical Stock Issue + JC Part Billing stay on one Job Card row; a Purchase Bill and its
            own Stock-IN stay on one Purchase row; unmatched GRNs remain separate.
            Click <strong>View Original Entries</strong> to inspect all records.
          @endif
        </div>
        <div class="am267-helper"><strong>Two different dates:</strong>""",
'NOTICE')
v=once(v,
"""              <tr data-am267-kind="{{ str_starts_with($event['type'],'STOCK ·')?'STOCK':(str_starts_with($event['type'],'PURCHASE ·')?'PURCHASE':'JC') }}">""",
"""              <tr data-am267-kind="{{ str_starts_with($event['type'],'INWARD ·')?'STOCK PURCHASE':(str_starts_with($event['type'],'OUTWARD ·')?'STOCK JC':(str_starts_with($event['type'],'STOCK ·')?'STOCK':(str_starts_with($event['type'],'PURCHASE ·')?'PURCHASE':'JC'))) }}">""",
'FILTER_CLASSIFICATION')
v=once(v,
"""                <td>{{ $event['detail'] }}@if($event['notes'])<small>{{ $event['notes'] }}</small>@endif</td>""",
"""                <td>{{ $event['detail'] }}@if($event['notes'])<small>{{ $event['notes'] }}</small>@endif
                  @if($type==='PART' && (int)($event['source_count']??1)>1)
                    <details class="am268-source-details">
                      <summary>View Original Entries ({{ $event['source_count'] }})</summary>
                      <div class="am268-source-scroll">
                        <table class="am268-audit-table">
                          <thead><tr><th>ERP Date</th><th>Original Type</th><th>Reference</th>
                            <th>Source Date</th><th>Stock IN</th><th>Stock OUT</th>
                            <th>Recorded Amount</th><th>Original Status / Audit</th></tr></thead>
                          <tbody>
                          @foreach($event['source_events'] as $source)
                            <tr>
                              <td>{{ $source['erp_posted_date']?:($source['date']?:'—') }}</td>
                              <td><span class="am268-source-kind">{{ $source['type'] }}</span></td>
                              <td>@if(!empty($source['url']))
                                <a href="{{ $source['url'] }}" target="_blank" rel="noopener">{{ $source['reference'] }}</a>
                                @else{{ $source['reference'] }}@endif</td>
                              <td>{{ $source['document_date']?:'—' }}</td>
                              <td class="num">{{ $source['in']===null?'—':number_format((float)$source['in'],3) }}</td>
                              <td class="num">{{ $source['out']===null?'—':number_format((float)$source['out'],3) }}</td>
                              <td class="num">{{ $source['amount']===null?'—':'₹'.number_format((float)$source['amount'],2) }}</td>
                              <td>{{ $source['status']?:'RECORDED' }}
                                <small>{{ $source['detail'] }}</small>
                                @if(!empty($source['notes']))<small>{{ $source['notes'] }}</small>@endif
                              </td>
                            </tr>
                          @endforeach
                          </tbody>
                        </table>
                      </div>
                    </details>
                  @endif
                </td>""",
'EXPANDABLE_AUDIT')
v=once(v,"row.getAttribute('data-am267-kind')===type",
            "(' '+(row.getAttribute('data-am267-kind')||'')+' ').includes(' '+type+' ')",
'FILTER_TOKENS')
v=once(v,
"""        Source-specific record numbers link to original documents where available. Ledger Date uses the original source date""",
"""        Consolidated rows do NOT create or delete transactions; use the Raw Events view or expand a row to trace every Purchase, GRN, JC Part and physical movement. Combined Stock-IN/OUT sums only real inventory movements; combined Amount sums only saved Purchase or JC billing lines.
        Ledger Date uses the original source date""",
'FOOTNOTE')
patched={
 controller:c.encode('utf-8'),view:v.encode('utf-8'),
 service:(pkg/'files'/service).read_bytes(),
}
for path in [controller,service]:lint(patched[path],Path(path).name)
blade(patched[view],'ITEM_LEDGER_CONSOLIDATED')
php=run(['php',str(pkg/'qa_unit.php')],60)
if php.returncode or 'AM268_PURE_LEDGER_CONSOLIDATION_QA_ALL_PASS' not in php.stdout:
    raise SystemExit('STOP_AM268_UNIT_QA_FAILED '+(php.stdout+php.stderr)[-1400:])
print(php.stdout[-1250:])
print('AM268_SOURCE_PHP_BLADE_AND_SOURCE_GROUP_UNIT_PREFLIGHT_PASS')
if mode=='check':
    print('AM268_READONLY_CHECK_NO_BUSINESS_WRITES')
    sys.exit(0)

backup=backupBase/('AM268_LEDGER_CODE_'+datetime.now().strftime('%Y%m%d_%H%M%S'))
backup.mkdir(parents=True,exist_ok=False,mode=0o700)
for path,data in before.items():
    target=backup/path;target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(data)
installed=[]
try:
    for path,data in patched.items():
        if path in before and (root/path).read_bytes()!=before[path]:
            raise RuntimeError('AM268_CONCURRENT_STAGING_EDIT_'+path)
        atom(root/path,data);installed.append(path)
    for cmd in [['php','artisan','optimize:clear'],['php','artisan','view:cache']]:
        proc=run(cmd,155)
        if proc.returncode:raise RuntimeError('AM268_CACHE_FAILURE '+(proc.stdout+proc.stderr)[-850:])
except BaseException as exc:
    for path in installed:
        if path in before:atom(root/path,before[path])
        else:(root/path).unlink(missing_ok=True)
    run(['php','artisan','optimize:clear'],115)
    raise SystemExit('STOP_AM268_SOURCE_ROLLED_BACK_'+str(exc))
(backup/'manifest.json').write_text(json.dumps({'installed':{p:sha(b) for p,b in patched.items()}},indent=2))
pointer.write_text(str(backup)+'\n')
print('AM268_STAGING_CONSOLIDATED_ITEM_LEDGER_INSTALLED')
print('AM268_NO_STOCK_JC_GRN_PURCHASE_OR_ACCOUNTING_DATA_CHANGED')
print('AM268_BACKUP='+str(backup))
