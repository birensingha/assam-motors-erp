#!/usr/bin/env python3
"""FIX124: JC Summary has two PRINT PRESENTATION modes, with and without GST breakup.

Source must match SHA of live STAGING summary view verified by read-only workflow.
Only resources/views/job-cards/summary-print.blade.php changes.
No DB/controller/route/stock/tax calculation/invoice updates.
Usage: python3 apply_fix124.py check|apply /absolute/path/to/staging
"""
from pathlib import Path
from datetime import datetime, timezone
import hashlib, os, subprocess, sys, tempfile

BASELINE="a068c8df2c72e25f6cdfe80aec670f6e52f1bfc54bc927ace9f47d5080c6055d"
PATH=Path("resources/views/job-cards/summary-print.blade.php")
MARKER="FIX124_JC_SUMMARY_DUAL_GST_PRINT"
START='<section class="section"><h3>Parts & Consumables ({{ $allParts->count() }})</h3>'
END='<div class="money"><div class="totals">'

MODE_PHP=""" // FIX124_JC_SUMMARY_DUAL_GST_PRINT: display selection only; same saved JC records.
 $am124ShowGst=(string)request()->query('gst','0')!=='0';
 $am124Tax=static fn($line)=>round((float)($line->total??0)*(float)($line->gst_percent??0)/100,2);
 $am124Final=static fn($line)=>round((float)($line->total??0)+$am124Tax($line),2);
 $am124PartsFinal=(float)$allParts->sum(fn($line)=>$am124Final($line));
 $am124LabourFinal=(float)$billableLabour->sum(fn($line)=>$am124Final($line));
"""

SECTIONS=r'''<section class="section"><h3>Parts &amp; Consumables ({{ $allParts->count() }})</h3>
<table class="{{ $am124ShowGst ? 'am124-gst' : 'am124-simple' }}"><thead><tr>
<th style="width:5%">SL</th><th>Part / Description</th>
<th style="width:11%">Part No.</th><th class="num" style="width:7%">Qty</th>
@if($am124ShowGst)
<th class="num" style="width:10%">Rate</th><th class="num" style="width:7%">Dis%</th>
<th class="num" style="width:11%">Taxable</th><th class="num" style="width:7%">GST%</th>
<th class="num" style="width:10%">GST ₹</th>
@else
<th class="num" style="width:15%">Rate (Incl.)</th>
@endif
<th class="num" style="width:12%">Final ₹</th>
</tr></thead><tbody>
@forelse($headParts as $part)
@php
 $am124PartFinal=$am124Final($part);
 $am124PartQty=(float)($part->qty??0);
@endphp
<tr><td>{{ $loop->iteration }}</td>
<td><span class="desc">{{ $part->part_name??$part->description??'Part' }}</span>
@if(!empty($part->note))<div class="note">{{ \Illuminate\Support\Str::limit($part->note,105) }}</div>@endif</td>
<td>{{ $part->part_number??$part->part_code??'—' }}</td>
<td class="num">{{ number_format($am124PartQty,2) }}</td>
@if($am124ShowGst)
<td class="num">{{ $printPartMoney($part->rate??0) }}</td>
<td class="num">{{ number_format((float)($part->discount_percent??0),1) }}</td>
<td class="num">{{ $printPartMoney($part->total??0) }}</td>
<td class="num">{{ number_format((float)($part->gst_percent??0),1) }}</td>
<td class="num">{{ $printPartMoney($am124Tax($part)) }}</td>
@else
<td class="num">{{ $printPartMoney($am124PartQty>0?$am124PartFinal/$am124PartQty:$am124PartFinal) }}</td>
@endif
<td class="num"><strong>{{ $printPartMoney($am124PartFinal) }}</strong></td>
</tr>
@empty
<tr><td colspan="{{ $am124ShowGst?10:6 }}" class="note">No Parts entered.</td></tr>
@endforelse
@if($restParts->count())
<tr class="more"><td colspan="{{ $am124ShowGst?9:5 }}">Other {{ $restParts->count() }} Part line(s) (condensed for one-page summary)</td>
<td class="num">{{ $printPartMoney($restParts->sum(fn($p)=>$am124Final($p))) }}</td></tr>
@endif
</tbody></table></section>

<section class="section"><h3>Labour / ROT &amp; OSL ({{ $billableLabour->count() }} billable)</h3>
<table class="{{ $am124ShowGst ? 'am124-gst' : 'am124-simple' }}"><thead><tr>
<th style="width:5%">SL</th><th>Labour / Work</th><th style="width:13%">ROT</th>
<th class="num" style="width:9%">Std Hrs</th>
@if($am124ShowGst)
<th class="num" style="width:10%">Rate</th><th class="num" style="width:7%">Dis%</th>
<th class="num" style="width:11%">Taxable</th><th class="num" style="width:7%">GST%</th>
<th class="num" style="width:10%">GST ₹</th>
@else
<th class="num" style="width:15%">Rate (Incl.)</th>
@endif
<th class="num" style="width:12%">Final ₹</th>
</tr></thead><tbody>
@forelse($headLabour as $line)
@php
 $am124LabourLineFinal=$am124Final($line);
 $am124StdHrs=(float)($line->standard_hours??0);
@endphp
<tr><td>{{ $loop->iteration }}</td>
<td><span class="desc">{{ \Illuminate\Support\Str::limit($line->description?:'Service Labour',68) }}</span>
@if(!empty($line->note))<div class="note">{{ \Illuminate\Support\Str::limit($line->note,90) }}</div>@endif</td>
<td>{{ $line->rot_code??'LABOUR' }}</td>
<td class="num">{{ number_format($am124StdHrs,2) }}</td>
@if($am124ShowGst)
<td class="num">{{ $printPartMoney($line->labour_rate??0) }}</td>
<td class="num">{{ number_format((float)($line->discount_percent??0),1) }}</td>
<td class="num">{{ $printPartMoney($line->total??0) }}</td>
<td class="num">{{ number_format((float)($line->gst_percent??0),1) }}</td>
<td class="num">{{ $printPartMoney($am124Tax($line)) }}</td>
@else
<td class="num">{{ $printPartMoney($am124StdHrs>0?$am124LabourLineFinal/$am124StdHrs:$am124LabourLineFinal) }}</td>
@endif
<td class="num"><strong>{{ $printPartMoney($am124LabourLineFinal) }}</strong></td>
</tr>
@empty <tr><td colspan="{{ $am124ShowGst?10:6 }}" class="note">No Labour / ROT added.</td></tr>@endforelse
@if($restLabour->count())
<tr class="more"><td colspan="{{ $am124ShowGst?9:5 }}">Other {{ $restLabour->count() }} Labour line(s) (condensed; full JC retains item details)</td>
<td class="num">{{ $printPartMoney($restLabour->sum(fn($l)=>$am124Final($l))) }}</td></tr>
@endif
</tbody></table></section>
'''

CSS=r'''/* FIX124: dual print modes. The print selector is screen-only. */
.toolbar .am124-modes{display:flex;align-items:center;gap:7px;flex-wrap:wrap}
.toolbar a.am124-selected{background:#176b45;color:white}
.am124-gst th{font-size:6.8px;padding:4px 2px}
.am124-gst td{font-size:7.5px;padding:3px 2px}
.am124-simple th{font-size:8px}
@media(max-width:760px){.toolbar{flex-wrap:wrap;justify-content:center}.toolbar .am124-modes{justify-content:center}}
'''

def sha(b):
    return hashlib.sha256(b).hexdigest()

def abort(reason):
    print("FIX124_ABORT="+reason,flush=True)
    raise SystemExit(2)

def unique(s,anchor,label):
    if s.count(anchor)!=1:
        abort("ANCHOR_"+label+"_COUNT_"+str(s.count(anchor)))

def mutate(old):
    unique(old,START,"PARTS_START")
    unique(old,END,"TOTALS_START")
    if old.index(END)<old.index(START):abort("ANCHOR_ORDER")
    unique(old," $amMismatch=abs((float)$calc['grand_total']-$grand)>0.02 || abs((float)$calc['gst_total']-$gstTotal)>0.02;","PHP")
    old=old.replace(" $amMismatch=abs((float)$calc['grand_total']-$grand)>0.02 || abs((float)$calc['gst_total']-$gstTotal)>0.02;",
                    " $amMismatch=abs((float)$calc['grand_total']-$grand)>0.02 || abs((float)$calc['gst_total']-$gstTotal)>0.02;\n"+MODE_PHP,1)
    unique(old,"</style></head><body>","CSS")
    old=old.replace("</style></head><body>",CSS+"</style></head><body>",1)
    a=old.index(START);b=old.index(END,a)
    old=old[:a]+SECTIONS+old[b:]
    # Keep item sums and total entries consistent with the SAVED Job Card values.
    unique(old,"<div><span>Parts Taxable</span><b>₹{{ $printPartMoney($partTotal) }}</b></div>","PART_TOTAL")
    old=old.replace("<div><span>Parts Taxable</span><b>₹{{ $printPartMoney($partTotal) }}</b></div>",
                    "@if($am124ShowGst)\n<div><span>Parts Taxable</span><b>₹{{ $printPartMoney($partTotal) }}</b></div>\n"
                    "<div><span>Labour Taxable</span><b>₹{{ $printPartMoney($labourTotal) }}</b></div>\n"
                    "<div><span>GST Total</span><b>₹{{ $printPartMoney($gstTotal) }}</b></div>\n"
                    "@else\n<div><span>Parts Final (Incl.)</span><b>₹{{ $printPartMoney($am124PartsFinal) }}</b></div>\n"
                    "<div><span>Labour Final (Incl.)</span><b>₹{{ $printPartMoney($am124LabourFinal) }}</b></div>\n"
                    "@endif",1)
    for line in ["<div><span>Labour Taxable</span><b>₹{{ $printPartMoney($labourTotal) }}</b></div>",
                 "<div><span>GST Total</span><b>₹{{ $printPartMoney($gstTotal) }}</b></div>"]:
        # Remove original line, not the newly inserted conditional equivalent.
        if old.count(line)!=2:abort("TOTALS_DUPLICATE_EXPECTED")
        idx=old.rfind(line)
        old=old[:idx]+old[idx+len(line):]
    unique(old,'<div class="doc-head"><h2>Job Card Summary</h2><div>Customer Cost Summary</div>',"DOC_HEADER")
    old=old.replace('<div class="doc-head"><h2>Job Card Summary</h2><div>Customer Cost Summary</div>',
                    '<div class="doc-head"><h2>Job Card Summary</h2><div>{{ $am124ShowGst ? "With GST Breakup" : "Customer Cost Summary" }}</div>',1)
    unique(old,'<div class="toolbar"><b>ASSAM MOTORS · One-page Job Card Summary (not a Tax Invoice)</b><span>',"TOOLBAR")
    old=old.replace('<div class="toolbar"><b>ASSAM MOTORS · One-page Job Card Summary (not a Tax Invoice)</b><span>',
                    '''<div class="toolbar"><b>ASSAM MOTORS · Job Card Summary (not a Tax Invoice)</b><div class="am124-modes">
<a href="{{ route('erp.job-cards.show',$job->id) }}">← Job Card</a>
<a class="{{ $am124ShowGst?'am124-selected':'' }}" href="{{ route('erp.job-cards.summary-print',$job->id) }}?gst=1">With GST</a>
<a class="{{ !$am124ShowGst?'am124-selected':'' }}" href="{{ route('erp.job-cards.summary-print',$job->id) }}?gst=0">Without GST</a>
<span>''',1)
    # Drop duplicate Job Card link (keeps one back link and print action).
    # New and old toolbar Job Card links are both present until the next guard.
    if old.count('<a href="{{ route(\'erp.job-cards.show\',$job->id) }}">← Job Card</a>')!=2:
        abort("BACK_LINK_EXPECTED_TWICE")
    return old

def compile_view(root,source):
    with tempfile.TemporaryDirectory(prefix="fix124_blade_") as d:
        compiled=Path(d)/"summary.php"
        php="require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();$r=\\Illuminate\\Support\\Facades\\Blade::compileString(file_get_contents($argv[1]));file_put_contents($argv[2],$r);"
        a=subprocess.run(["php","-r",php,str(source),str(compiled)],cwd=root,capture_output=True,text=True,timeout=60)
        if a.returncode!=0: abort("BLADE_COMPILE_FAILED")
        b=subprocess.run(["php","-l",str(compiled)],cwd=root,capture_output=True,text=True,timeout=40)
        if b.returncode!=0:abort("COMPILED_BLADE_PHP_LINT_FAILED")
        print("FIX124_BLADE_COMPILE_PHP_LINT=PASS")

def main():
    if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply"):abort("USAGE_CHECK_APPLY_ROOT")
    mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
    if not (root/"artisan").is_file() or not (root/"vendor/autoload.php").is_file():abort("NOT_LARAVEL_ROOT")
    target=root/PATH
    if not target.is_file():abort("VIEW_MISSING")
    raw=target.read_bytes()
    print("FIX124_MODE="+mode)
    print("FIX124_LIVE_SHA256="+sha(raw))
    src=raw.decode("utf-8")
    if MARKER in src:
        print("FIX124_ALREADY_APPLIED");return
    if sha(raw)!=BASELINE:abort("LIVE_SOURCE_CHANGED_STOP")
    updated=mutate(src)
    # Remove original duplicate back link after the Print selector; target only the duplicate after <span>.
    old='<span>\n<a href="{{ route(\'erp.job-cards.show\',$job->id) }}">← Job Card</a>'
    if updated.count(old)!=1:abort("TOOLBAR_DUPLICATE_BACKLINK_COUNT")
    updated=updated.replace(old,"<span>",1)
    if updated.count('With GST</a>')!=1 or updated.count('Without GST</a>')!=1:abort("MODE_SELECTOR_MISSING")
    if updated.count("Job Card Grand Total")!=1:abort("GRAND_TOTAL_CHANGED")
    if updated.count("<!--")>1:abort("UNEXPECTED_HTML")
    print("FIX124_TWO_FORMATS=YES")
    print("FIX124_SAVED_GRAND_TOTAL_UNCHANGED=YES")
    print("FIX124_ONLY_PRINT_VIEW=YES")
    print("FIX124_TARGET_SHA256="+sha(updated.encode("utf-8")))
    if mode=="check":
        print("FIX124_CHECK_PASS_NO_WRITES")
        return
    backups=root/"storage/app/am-backups"
    backups.mkdir(parents=True,exist_ok=True)
    stamp=datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
    backup=backups/("FIX124_summary_print_"+stamp+".blade.php.bak")
    with os.fdopen(os.open(backup,os.O_CREAT|os.O_EXCL|os.O_WRONLY,0o600),"wb") as f:
        f.write(raw)
        f.flush()
        os.fsync(f.fileno())
    if sha(backup.read_bytes())!=BASELINE:abort("BACKUP_SHA_BAD")
    print("FIX124_BACKUP="+str(backup))
    tmp=target.with_name(".summary-print.fix124.tmp.blade.php")
    if tmp.exists():abort("TEMP_FILE_EXISTS")
    try:
        tmp.write_text(updated,encoding="utf-8")
        os.chmod(tmp,os.stat(target).st_mode & 0o777)
        compile_view(root,tmp)
        if sha(target.read_bytes())!=BASELINE:abort("SOURCE_CHANGED_DURING_PREVIEW")
        os.replace(tmp,target)
        if sha(target.read_bytes())!=sha(updated.encode("utf-8")):
            target.write_bytes(raw)
            abort("VERIFY_MISMATCH_ROLLED_BACK")
        print("FIX124_STAGING_APPLIED=YES")
        print("FIX124_SAVED_TOTALS_ROUTES_DB_UNCHANGED=YES")
    finally:
        tmp.unlink(missing_ok=True)

if __name__=="__main__":
    main()
