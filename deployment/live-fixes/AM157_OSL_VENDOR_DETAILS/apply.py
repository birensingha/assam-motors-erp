#!/usr/bin/env python3
"""AM157 - display supplier Vendor Master in STAGING OSL Purchase Cost Editor.
No purchase amount, job card, voucher, balance or ledger write.
check|apply STAGING_ROOT. Byte-preserving Blade read and guarded backup.
"""
from pathlib import Path
import hashlib,os,sys,tempfile
from datetime import datetime
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
    raise SystemExit('AM157_USAGE apply.py check|apply STAGING_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('AM157_STOP_INVALID_STAGING')
cpath='app/Http/Controllers/Web/AdminWorkshopControlPageController.php'
vpath='resources/views/workshop-controls/osl-edit.blade.php'
paths={cpath:root/cpath,vpath:root/vpath}
for k,p in paths.items():
    if not p.is_file() or p.is_symlink():raise SystemExit('AM157_STOP_SOURCE_MISSING '+k)
orig={k:p.read_bytes() for k,p in paths.items()}
src={k:b.decode('utf8','surrogateescape') for k,b in orig.items()}
marker='AM157_VENDOR_MASTER_DETAILS'
if marker in ''.join(src.values()):
    if marker in src[cpath] and marker in src[vpath]:
        print('AM157_ALREADY_INSTALLED');sys.exit(0)
    raise SystemExit('AM157_STOP_PARTIALLY_INSTALLED')
def once(path,old,new,label):
    count=src[path].count(old)
    if count!=1:raise SystemExit('AM157_STOP_ANCHOR_'+label+'_COUNT='+str(count))
    src[path]=src[path].replace(old,new,1)

old="        return view('workshop-controls.osl-edit',['row'=>$row,'job'=>$job,'blocked'=>$blocked]);"
new="""        // AM157_VENDOR_MASTER_DETAILS: OSL supplier identity comes from Vendor
        // Database, not the numeric vendor_id on workshop_purchases.
        // Read-only display: no costing, payment or ledger mutation.
        $vendorId=(int)($row->vendor_id??0);
        $vendor=$vendorId>0 && Schema::hasTable('workshop_vendors')
            ? DB::table('workshop_vendors')->where('id',$vendorId)->first()
            : null;
        return view('workshop-controls.osl-edit',[
            'row'=>$row,'job'=>$job,'blocked'=>$blocked,'vendor'=>$vendor
        ]);"""
once(cpath,old,new,'CONTROLLER_VENDOR_LOOKUP')

old="{{ $row->vendor_name ?? $row->vendor ?? $row->vendor_id ?? '—' }}"
new="{{ $vendor->vendor_name ?? $row->supplier_name ?? $row->vendor_name ?? ('Vendor #'.($row->vendor_id??'—')) }}"
once(vpath,old,new,'VENDOR_NAME')

anchor='<form method="post" action="{{ route(\'erp.fix48.osl-save\',$row->id) }}"'
addition=r'''
{{-- AM157_VENDOR_MASTER_DETAILS - source Vendor Database, not numeric Vendor ID. --}}
@php
  $am157AddressParts=array_filter([
    trim((string)($vendor->address??$vendor->address_line1??$vendor->address_line_1??'')),
    trim((string)($vendor->city??'')),
    trim((string)($vendor->state??'')),
    trim((string)($vendor->pincode??$vendor->pin_code??$vendor->postal_code??'')),
  ],static fn($x)=>$x!=='');
  $am157Address=implode(', ',$am157AddressParts);
  $am157VendorName=trim((string)($vendor->vendor_name??$row->supplier_name??$row->vendor_name??''));
  $am157VendorCode=trim((string)($vendor->vendor_code??''));
@endphp
<style id="am157-vendor-details-style">
.am157-vendor-details{border:1px solid #bfd6eb!important;border-left:4px solid #1763a1!important;background:#f8fbff!important}
.am157-vendor-title{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:12px}
.am157-vendor-title h2{font-size:15px!important;color:#173f69!important;margin:0!important;line-height:1.3}
.am157-vendor-title a{background:#e5f1fb;border:1px solid #aac8e2;padding:8px 12px;border-radius:7px;color:#194c7f!important;text-decoration:none;font-size:12px;font-weight:750}
.am157-vendor-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
.am157-vendor-cell{min-width:0;background:#fff;padding:10px 12px;border-radius:7px;border:1px solid #e1eaf4;overflow-wrap:anywhere}
.am157-vendor-cell span{display:block;font-size:11px;color:#5a6c82;margin-bottom:4px}
.am157-vendor-cell strong{font-size:13px;color:#183d64;font-weight:700;line-height:1.4}
.am157-vendor-note{font-size:11px;color:#536c86;margin:10px 0 0}
@media(max-width:780px){.am157-vendor-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:460px){.am157-vendor-grid{grid-template-columns:minmax(0,1fr)}}
</style>
<section class="panel am-surface am157-vendor-details" aria-label="OSL Supplier Vendor Details">
  <div class="am157-vendor-title">
    <h2>Supplier / Vendor Details</h2>
    @if($vendor)
      <a href="{{ route('erp.vendors.ledger',(int)$vendor->id) }}" target="_blank" rel="noopener noreferrer">View Vendor Ledger</a>
    @endif
  </div>
  <div class="am157-vendor-grid">
    <div class="am157-vendor-cell"><span>Vendor Name</span><strong>{{ $am157VendorName!==''?$am157VendorName:'Vendor details not found in Master' }}</strong></div>
    <div class="am157-vendor-cell"><span>Vendor Code / ID</span><strong>{{ $am157VendorCode!==''?$am157VendorCode:'—' }} · #{{ $row->vendor_id??'—' }}</strong></div>
    <div class="am157-vendor-cell"><span>Mobile</span><strong>{{ $vendor->mobile??$vendor->phone??$vendor->contact_no??'Not recorded' }}</strong></div>
    <div class="am157-vendor-cell"><span>GSTIN</span><strong>{{ $vendor->gstin??$vendor->gst_no??'Not recorded' }}</strong></div>
    <div class="am157-vendor-cell"><span>Vendor Address</span><strong>{{ $am157Address!==''?$am157Address:'Not recorded in Vendor Master' }}</strong></div>
    <div class="am157-vendor-cell"><span>Supplier Invoice / Bill Reference</span><strong>{{ $row->supplier_invoice??'Not recorded' }}</strong></div>
    <div class="am157-vendor-cell"><span>OSL Purchase Reference</span><strong>{{ $row->purchase_no??('#'.$row->id) }}</strong></div>
    <div class="am157-vendor-cell"><span>Batch Reference</span><strong>{{ $row->purchase_batch_no??'—' }}</strong></div>
    <div class="am157-vendor-cell"><span>Linked Job Card</span><strong>{{ $job->job_no??$row->job_card_id??'—' }}</strong></div>
    <div class="am157-vendor-cell"><span>Supplier Purchase Total</span><strong>₹{{ number_format((float)($row->total_amount??0),2) }}</strong></div>
    <div class="am157-vendor-cell"><span>Purchase Status</span><strong>{{ $row->record_status??'—' }}</strong></div>
    <div class="am157-vendor-cell"><span>Outstanding / Allocations</span><strong>@if($vendor)See Vendor Ledger for verified balance @else Vendor Master record not found @endif</strong></div>
  </div>
  <p class="am157-vendor-note">Supplier purchase details are read-only here. A closed or invoiced Job Card remains locked; supplier corrections and posted payments require authorised financial revision.</p>
</section>
'''
once(vpath,anchor,addition+"\n"+anchor,'SUPPLIER_DETAIL_CARD')
changed={k:v.encode('utf8','surrogateescape') for k,v in src.items()}
print('AM157_SOURCE_FILES='+','.join(paths))
for k in paths: print('AM157_'+k+'_SHA='+hashlib.sha256(orig[k]).hexdigest()+' -> '+hashlib.sha256(changed[k]).hexdigest())
if mode=='check':
    d=os.environ.get('AM157_PREVIEW_DIR')
    if d:
        out=Path(d)
        for k,b in changed.items():
            p=out/k;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(b)
    print('AM157_READ_ONLY_PREFLIGHT_PASS')
    sys.exit(0)
if any(paths[k].read_bytes()!=orig[k] for k in paths):
    raise SystemExit('AM157_STOP_CONCURRENT_EDIT')
backup=root/'storage/app'/('am157_osl_vendor_backup_'+datetime.now().strftime('%Y%m%d_%H%M%S')+'_'+str(os.getpid()))
for k,b in orig.items():
    dest=backup/k;dest.parent.mkdir(parents=True,exist_ok=True);dest.write_bytes(b)
    if dest.read_bytes()!=b:raise SystemExit('AM157_STOP_BACKUP_FAILED')
installed=[]
try:
    for k,b in changed.items():
        p=paths[k]
        fd,tmp=tempfile.mkstemp(dir=p.parent,prefix='.am157_')
        with os.fdopen(fd,'wb') as f:f.write(b)
        os.chmod(tmp,p.stat().st_mode & 0o777)
        if p.read_bytes()!=orig[k]:raise SystemExit('AM157_STOP_CONCURRENT_CHANGE')
        os.replace(tmp,p);installed.append(k)
    print('AM157_BACKUP='+str(backup))
    print('AM157_STAGING_SOURCE_APPLIED_NO_DATABASE_WRITES')
except Exception:
    for k in reversed(installed):
        paths[k].write_bytes(orig[k])
    raise
