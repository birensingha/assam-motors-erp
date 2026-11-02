#!/usr/bin/env python3
"""AM JC all-items print: modify only verified STAGING summary Blade.
Never mutate financial data, models, controller, or routing.
Commands: python3 patch.py check|apply /path/to/laravel
"""
import sys, re, hashlib, os, tempfile, subprocess
from pathlib import Path

if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply"):
    raise SystemExit("usage: patch.py check|apply ERP_ROOT")
mode=sys.argv[1]
root=Path(sys.argv[2]).resolve()
view=root/"resources/views/job-cards/summary-print.blade.php"
if not (root/"artisan").is_file() or not view.is_file():
    raise SystemExit("AM_ALL_LINES_STOP_MISSING_LARAVEL_OR_SUMMARY_VIEW")
original=view.read_text(encoding="utf8")
marker="AM_JC_ALL_LINES_FULL_SUMMARY_20261102"

if marker in original:
    if "->take(9)" in original or "$restParts->count()" in original or "$restLabour->count()" in original:
        raise SystemExit("AM_ALL_LINES_STOP_PARTIAL_INSTALL")
    print("AM_ALL_LINES_ALREADY_APPLIED")
    sys.exit(0)

def replace_exact_regex(src,pat,replacement,label):
    found=list(re.finditer(pat,src,flags=re.S))
    if len(found)!=1:
        raise SystemExit("AM_ALL_LINES_STOP_"+label+"_COUNT_"+str(len(found)))
    return src[:found[0].start()]+replacement+src[found[0].end():]

# All existing row markup, saved values, GST/discount toggles and calculations
# are retained. Only the nine-row truncation and synthetic "Other" rows change.
out=replace_exact_regex(
    original,
    r"\$headParts\s*=\s*\$allParts->take\(9\)\s*;\s*\$restParts\s*=\s*\$allParts->skip\(9\)\s*;",
    "$headParts=$allParts;", "PARTS_NINE_ROW_CAP")
out=replace_exact_regex(
    out,
    r"\$headLabour\s*=\s*\$billableLabour->take\(9\)\s*;\s*\$restLabour\s*=\s*\$billableLabour->skip\(9\)\s*;",
    "$headLabour=$billableLabour;", "LABOUR_NINE_ROW_CAP")
out=replace_exact_regex(
    out,
    r"@if\(\s*\$restParts->count\(\)\s*\)\s*<tr class=\"more\">.*?</tr>\s*@endif",
    "", "PARTS_CONDENSED_ROW")
out=replace_exact_regex(
    out,
    r"@if\(\s*\$restLabour->count\(\)\s*\)\s*<tr class=\"more\">.*?</tr>\s*@endif",
    "", "LABOUR_CONDENSED_ROW")
if out.count("@forelse($headParts as $part)")!=1 or out.count("@forelse($headLabour as $line)")!=1:
    raise SystemExit("AM_ALL_LINES_STOP_DATA_LOOP_CHANGED")
if out.count("@media print{")!=1:
    raise SystemExit("AM_ALL_LINES_STOP_PRINT_CSS_CHANGED")
css = """/* AM_JC_ALL_LINES_FULL_SUMMARY_20261102: all saved items, A4 flow */
@media screen {
  .page {height:auto!important;max-height:none!important;overflow:visible!important}
  section.section,section.section table,section.section tbody {max-height:none!important;overflow:visible!important}
}
@media print {
  .page {height:auto!important;max-height:none!important;overflow:visible!important}
  section.section,section.section table {break-inside:auto!important;page-break-inside:auto!important}
  section.section thead {display:table-header-group!important}
  section.section tr {break-inside:avoid!important;page-break-inside:avoid!important}
  .brand,.summary-grid,.money,.footer {break-inside:avoid;page-break-inside:avoid}
}
"""
out=out.replace("</style>",css+"</style>",1)
if "$restParts" in out or "$restLabour" in out or "->take(9)" in out:
    raise SystemExit("AM_ALL_LINES_STOP_LEFTOVER_CAP_OR_CONDENSATION")
if out.count(marker)!=1:
    raise SystemExit("AM_ALL_LINES_STOP_MARKER")
# Never write a template that fails Blade compile or PHP syntax.
with tempfile.TemporaryDirectory(prefix="am_all_lines_") as temp:
    compiled=Path(temp)/"compiled.php"
    runner=String=None
    php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2], $app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
    candidate=Path(temp)/"summary.blade.php"
    candidate.write_text(out,encoding="utf8")
    for command in [
        ["php","-r",php,str(candidate),str(compiled)],
        ["php","-l",str(compiled)]
    ]:
        result=subprocess.run(command,cwd=root,text=True,capture_output=True,timeout=70)
        if result.returncode:
            raise SystemExit("AM_ALL_LINES_STOP_BLADE_OR_PHP_INVALID: "+(result.stdout+result.stderr)[-900:])
print("AM_ALL_LINES_ORIGINAL_SHA256="+hashlib.sha256(original.encode()).hexdigest())
print("AM_ALL_LINES_CANDIDATE_BLADE_AND_PHP_LINT_PASS")
if mode=="check":
    print("AM_ALL_LINES_READ_ONLY_PREFLIGHT_PASS")
    sys.exit(0)
fd,tmp=tempfile.mkstemp(prefix=".am_all_lines_",dir=str(view.parent))
try:
    with os.fdopen(fd,"w",encoding="utf8") as stream:
        stream.write(out)
    os.chmod(tmp,view.stat().st_mode&0o777)
    os.replace(tmp,view)
finally:
    if os.path.exists(tmp):os.unlink(tmp)
print("AM_ALL_LINES_SUMMARY_VIEW_APPLIED_NO_DATABASE_WRITES")
