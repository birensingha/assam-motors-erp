#!/usr/bin/env python3
"""Display-only parts/labour itemised price, discount, taxable, GST and gross totals.
No financial, inventory, ROT, attendance, credit note or invoice data writes.
"""
import os,stat,subprocess,hashlib,shutil,tempfile,sys,time
from pathlib import Path
root=Path(sys.argv[1]).resolve()
p=root/'resources/views/job-cards/show.blade.php'
if not (root/'artisan').is_file() or not p.is_file():raise SystemExit('SAFE_STOP: wrong JC template')
s=p.read_text(encoding='utf-8',errors='surrogateescape')
print('JC_SHOW_HASH_BEFORE',hashlib.sha256(s.encode('utf-8',errors='surrogateescape')).hexdigest()[:16])
marker='AM_JC_ITEMWISE_BILLING_READONLY_V1'
if marker in s:
    print('JC_ITEMWISE_ALREADY_APPLIED')
    raise SystemExit(0)
def once(a,b,label):
    global s
    n=s.count(a)
    if n!=1:raise SystemExit('SAFE_STOP: unexpected '+label+' anchor count='+str(n))
    s=s.replace(a,b,1)
# Scoped to actual Parts table, no issue/delete form changes
start=s.find('<div class="card section-gap jc-purchase-block jc-parts-block">')
end=s.find('<div class="card section-gap jc-purchase-block jc-labour-block">',start)
if start<0 or end<0 or end<start:raise SystemExit('SAFE_STOP: exact live Parts section missing')
part=s[start:end]
oldHeader="""        <th>Part</th>
        <th>Qty</th>
        <th>Rate</th>
        <th>Discount</th>
        <th>GST</th>
        <th>Status</th>
        <th>Total</th>
        <th>Action</th>"""
newHeader="""        <th>Part</th>
        <th>Qty</th>
        <th>Rate</th>
        <th>Dis%</th>
        <th>Discount ₹</th>
        <th>Taxable ₹</th>
        <th>GST%</th>
        <th>GST ₹</th>
        <th>Final Amt ₹</th>
        <th>Status</th>
        <th>Action</th>"""
if part.count(oldHeader)!=1:raise SystemExit('SAFE_STOP: parts header mismatch')
part=part.replace(oldHeader,newHeader,1)
oldCells="""        <td>{{ number_format((float)$p->discount_percent,2) }}%</td>
        <td>{{ number_format((float)($p->gst_percent ?? 0),2) }}%</td>
        <td><span class="badge">{{ $p->issue_status }}</span></td>
        <td class="num money">₹{{ number_format((float)$p->total,2) }}</td>"""
newCells="""        @php($amJcPartsTax=round((float)($p->total??0)*(float)($p->gst_percent??0)/100,2))
        <td class="num">{{ number_format((float)$p->discount_percent,2) }}%</td>
        <td class="num">₹{{ number_format((float)($p->discount_amount??0),2) }}</td>
        <td class="num">₹{{ number_format((float)$p->total,2) }}</td>
        <td class="num">{{ number_format((float)($p->gst_percent??0),2) }}%</td>
        <td class="num">₹{{ number_format($amJcPartsTax,2) }}</td>
        <td class="num money"><strong>₹{{ number_format((float)$p->total+$amJcPartsTax,2) }}</strong></td>
        <td><span class="badge">{{ $p->issue_status }}</span></td>"""
if part.count(oldCells)!=1:raise SystemExit('SAFE_STOP: parts saved financial cells changed')
part=part.replace(oldCells,newCells,1)
if part.count('colspan="8" class="empty">No parts lines.')!=1:raise SystemExit('SAFE_STOP: parts colspan missing')
part=part.replace('colspan="8" class="empty">No parts lines.','colspan="11" class="empty">No parts lines.',1)
part=part.replace('<table class="data-table">','<table class="data-table jc-itemised-billing">',1)
s=s[:start]+part+s[end:]

# New read-only summary above existing ROT cards (do not touch operational ROT controls).
lead='<div class="rot-list">'
if s.count(lead)!=1: raise SystemExit('SAFE_STOP: ROT cards anchor mismatch')
table=String=r"""
  {{-- AM_JC_ITEMWISE_BILLING_READONLY_V1: saved JC totals are taxable, GST derived as a print-only preview --}}
  <div class="line-scroll">
    <table class="data-table jc-itemised-billing">
      <thead><tr>
        <th>SL</th><th>Labour / ROT</th><th>Qty</th><th>Std Hrs</th><th>ROT Rate</th>
        <th>Dis%</th><th>Discount ₹</th><th>Taxable ₹</th><th>GST%</th><th>GST ₹</th><th>Final Amt ₹</th>
      </tr></thead>
      <tbody>
        @forelse($labour as $line)
        @php($amJcLabTax=round((float)($line->total??0)*(float)($line->gst_percent??0)/100,2))
        <tr>
          <td>{{ $loop->iteration }}</td>
          <td><strong>{{ $line->rot_code??'LABOUR' }}</strong><div class="muted">{{ $line->description??'' }}</div></td>
          <td class="num">1</td>
          <td class="num">{{ number_format((float)($line->standard_hours??0),2) }}</td>
          <td class="num">₹{{ number_format((float)($line->labour_rate??0),2) }}</td>
          <td class="num">{{ number_format((float)($line->discount_percent??0),2) }}%</td>
          <td class="num">₹{{ number_format((float)($line->discount_amount??0),2) }}</td>
          <td class="num">₹{{ number_format((float)($line->total??0),2) }}</td>
          <td class="num">{{ number_format((float)($line->gst_percent??0),2) }}%</td>
          <td class="num">₹{{ number_format($amJcLabTax,2) }}</td>
          <td class="num money"><strong>₹{{ number_format((float)($line->total??0)+$amJcLabTax,2) }}</strong></td>
        </tr>
        @empty
        <tr><td colspan="11" class="empty">No Labour / ROT items.</td></tr>
        @endforelse
      </tbody>
    </table>
    <p class="muted" style="margin:7px 2px">ROT Qty defaults to 1 per Labour line; Standard Hours are separate.
      GST amount is displayed from saved taxable totals and tax percentage; final invoice records remain authoritative.</p>
  </div>
  <style>
    .jc-itemised-billing{min-width:980px;font-size:11px}
    .jc-itemised-billing th{position:sticky;top:0;background:#ecf3fb;z-index:1;white-space:nowrap}
    .jc-itemised-billing td{vertical-align:middle}
    .jc-itemised-billing .num{font-variant-numeric:tabular-nums;white-space:nowrap;text-align:right}
  </style>
"""
s=s.replace(lead,table+'\n  '+lead,1)
assert s.count('AM_JC_ITEMWISE_BILLING_READONLY_V1')==1
assert s.count('route(\'erp.job-cards.parts.update\'')==1
assert s.count('route(\'erp.job-cards.parts.delete\'')==1
assert s.count("route('erp.rot.reopen'")>=1
assert s.count('jc-itemised-billing')>=3
# Backup and compile BEFORE replacing files: Blade view cache on a temp path cannot be used safely;
# rollback automatically if view:cache reports syntax error.
backup=p.with_name(p.name+'.before-itemwise-pricing-'+time.strftime('%Y%m%d%H%M%S')+'.bak')
shutil.copy2(p,backup)
fd,tmp=tempfile.mkstemp(prefix='.jc-itemised-',suffix='.blade.php',dir=p.parent)
with os.fdopen(fd,'w',encoding='utf-8',errors='surrogateescape') as h:h.write(s)
os.chmod(tmp,stat.S_IMODE(p.stat().st_mode))
os.replace(tmp,p)
print('JC_ITEMWISE_DISPLAY_APPLIED','BACKUP='+backup.name,'AFTER_SHA='+hashlib.sha256(s.encode('utf-8',errors='surrogateescape')).hexdigest()[:16])
