#!/usr/bin/env python3
"""Conservative, additive STAGING JC summary pagination patch.
Only patches a verified one-page-style Blade; never replaces finance markup.
Usage: python3 patch.py check|apply /absolute/path/to/erp
"""
import pathlib,sys,re,hashlib,datetime,subprocess
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
    raise SystemExit("Usage: patch.py check|apply STAGING_ERP_ROOT")
mode=sys.argv[1]
root=pathlib.Path(sys.argv[2]).resolve()
p=root/'resources/views/job-cards/summary-print.blade.php'
if not p.is_file():
    raise SystemExit("STOP: actual summary Blade path unverified; locate using grep before applying")
s=p.read_text(encoding='utf8')
if 'AM_JC_FULL_LINES_V1' in s:
    print("ALREADY_APPLIED");sys.exit(0)
# Fail closed unless exact FIX69 loop structure exists. Newer templates should
# be inspected and updated manually rather than overwritten.
anchors=[
    '$headParts=$allParts->take(9);$restParts=$allParts->skip(9);',
    '$headLabour=$billableLabour->take(9);$restLabour=$billableLabour->skip(9);',
    '@forelse($headParts as $part)',
    '@forelse($headLabour as $line)',
]
for x in anchors:
    if s.count(x)!=1:raise SystemExit("STOP: missing/ambiguous source anchor "+x)
pat_parts=r'@if\(\$restParts->count\(\)\)<tr class="more">[^\n]*?</tr>@endif'
pat_labour=r'@if\(\$restLabour->count\(\)\)<tr class="more">[^\n]*?</tr>@endif'
if len(re.findall(pat_parts,s))!=1 or len(re.findall(pat_labour,s))!=1:
    raise SystemExit("STOP: condensed rows changed; manual review required")
out=s.replace(anchors[0],'$headParts=$allParts;').replace(anchors[1],'$headLabour=$billableLabour;')
out=re.sub(pat_parts,'',out,count=1);out=re.sub(pat_labour,'',out,count=1)
if 'tr{break-inside:avoid}' not in out or '@media print{' not in out:
    raise SystemExit("STOP: unsupported print stylesheet")
out=out.replace('tr{break-inside:avoid}', 'tr{break-inside:avoid;page-break-inside:avoid}\nthead{display:table-header-group}',1)
out=out.replace('@media print{','/* AM_JC_FULL_LINES_V1: all line items flow over multiple A4 pages */\n@media print{\n.section,.section table{break-inside:auto;page-break-inside:auto}\n.section thead{display:table-header-group}\n.section tr,.money,.footer{break-inside:avoid;page-break-inside:avoid}\n',1)
assert '$restParts' not in out and '$restLabour' not in out and 'take(9)' not in out
print('SOURCE_SHA256',hashlib.sha256(s.encode()).hexdigest())
print('PATCHED_SHA256',hashlib.sha256(out.encode()).hexdigest())
print('PRESERVED_ALL_OTHER_CONTENT',len(out))
if mode=='check':
    print('READ_ONLY_CHECK_PASS');sys.exit(0)
backup_dir=root/'storage/app/am-backups'
backup_dir.mkdir(parents=True,exist_ok=True)
backup=backup_dir/('job-card-summary-pre-multipage-'+datetime.datetime.now().strftime('%Y%m%d-%H%M%S')+'.blade.php')
backup.write_text(s,encoding='utf8')
p.write_text(out,encoding='utf8')
try:
    subprocess.run(['php','artisan','view:cache'],cwd=root,check=True,timeout=75)
except Exception:
    p.write_text(s,encoding='utf8')
    raise SystemExit("STOP: Blade compile failed; original restored from backup "+str(backup))
print('PATCH_APPLIED_BLADE_COMPILE_PASS','BACKUP',backup)
