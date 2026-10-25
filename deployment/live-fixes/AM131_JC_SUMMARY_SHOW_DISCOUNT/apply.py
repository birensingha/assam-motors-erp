#!/usr/bin/env python3
"""AM131: add Estimate-style Show Discount to JC Summary without touching tax calculations.
Modes: check (read-only) and apply (timestamped backup, one Blade view only).
No database writes, invoice writes, tax flag changes, or finance routes.
"""
from pathlib import Path
import datetime, os, re, shutil, subprocess, sys, tempfile

if len(sys.argv) != 3 or sys.argv[1] not in ("check", "apply"):
    raise SystemExit("Usage: apply.py check|apply LARAVEL_STAGING_ROOT")
mode = sys.argv[1]
root = Path(sys.argv[2]).expanduser().resolve()
target = root / "resources/views/job-cards/summary-print.blade.php"
if not (root / "artisan").is_file() or not target.is_file():
    raise SystemExit("STOP: Laravel app or JC summary view missing")

original = target.read_text(encoding="utf-8")
marker = "AM131_SHOW_DISCOUNT_ONLY"
if marker in original:
    print("AM131_ALREADY_APPLIED")
    sys.exit(0)
if "AM130_SUMMARY_CUSTOMER_INCLUSIVE" in original:
    raise SystemExit("STOP: faulty AM130 still installed; restore original AM130 backup first")

expected = [
    "@forelse($headParts as $part)", "@forelse($headLabour as $line)",
    "</head>", "</body>", "Print / Save as PDF"
]
if not all(token in original for token in expected):
    raise SystemExit("STOP: summary view changed; inspection required; no writes")

src = original
# Attach saved discount amounts to the existing part/labour data rows.
# These Blade expressions are HTML attributes (not new PHP statements).
def tag_row(s, loop, var):
    pat = r"(@forelse\(" + re.escape(loop) + r"\)(?:(?!@empty).)*?<tr)(?=>)"
    matches = list(re.finditer(pat, s, flags=re.S))
    if len(matches) != 1:
        raise SystemExit("STOP: cannot identify unique " + var + " data row")
    match = matches[0]
    pct = "{{ number_format((float)($" + var + "->discount_percent??0),1) }}"
    amt = "{{ $printPartMoney($" + var + "->discount_amount??0) }}"
    attr = ' data-am131-pct="' + pct + '" data-am131-amt="' + amt + '"'
    return s[:match.end()] + attr + s[match.end():]

src = tag_row(src, "$headParts as $part", "part")
src = tag_row(src, "$headLabour as $line", "line")

# New checkbox beside the existing Print button.
button_re = re.compile(r"(<button\b[^>]*>[^<]*Print\s*/\s*Save as PDF[^<]*</button>)", re.I)
if len(button_re.findall(src)) != 1:
    raise SystemExit("STOP: Print button markup has changed")
control = """<label class="am131-control"><input type="checkbox" id="am131-discount-switch"> Show Discount</label>
"""
src = button_re.sub(lambda m: control + m.group(1), src, count=1)

css = """<style id="AM131_SHOW_DISCOUNT_ONLY">
.am131-control{display:inline-flex;align-items:center;gap:5px;white-space:nowrap;
  color:#173653;background:#fff;border:1px solid #bfd0e2;border-radius:6px;
  padding:7px 10px;font:700 12px Arial,Helvetica,sans-serif}
.am131-control input{accent-color:#18558f;width:15px;height:15px}
.am131-added{min-width:40px}
@media print{.am131-control{display:none!important}}
</style>
"""
src = src.replace("</head>", css + "</head>", 1)

# Client-side table augmentation keeps old Blade @if/@else/@endif intact.
# It also preserves the original With GST / Without GST print mode buttons.
script = """<script id="am131-discount-script">
(function(){
  'use strict';
  function ready(){
    var input=document.getElementById('am131-discount-switch');
    if(!input)return;
    function render(){
      var enabled=input.checked;
      Array.prototype.forEach.call(document.querySelectorAll('section.section'),function(section){
        var heading=section.querySelector('h3');
        if(!heading || !/^(parts|labour)/i.test(heading.textContent.trim()))return;
        var table=section.querySelector('table');
        if(!table || !table.tHead || !table.tBodies.length)return;
        Array.prototype.forEach.call(table.querySelectorAll('[data-am131-added]'),function(el){el.remove()});
        var hr=table.tHead.rows[0];
        if(!hr)return;
        var heads=Array.prototype.slice.call(hr.cells);
        var rateIndex=heads.findIndex(function(c){return /^rate/i.test(c.textContent.trim())});
        var existingDiscountIndex=heads.findIndex(function(c){
          return /^dis(?:c(?:ount)?)?\\s*%$/i.test(c.textContent.trim().replace(/\\s+/g,' '));
        });
        if(rateIndex<0)return;
        var hasOriginalDiscount=existingDiscountIndex>=0;
        if(hasOriginalDiscount){
          heads[existingDiscountIndex].style.display=enabled?'':'none';
        }
        var change=hasOriginalDiscount?(enabled?1:-1):(enabled?2:0);
        Array.prototype.forEach.call(table.tBodies[0].rows,function(row){
          var span=row.querySelector('td[colspan]');
          if(span){
            if(!span.hasAttribute('data-am131-base'))span.dataset.am131Base=String(span.colSpan);
            span.colSpan=Number(span.dataset.am131Base)+change;
            return;
          }
          if(!row.hasAttribute('data-am131-pct'))return;
          if(hasOriginalDiscount && row.cells[existingDiscountIndex]){
            row.cells[existingDiscountIndex].style.display=enabled?'':'none';
          }
          if(!enabled)return;
          var at=hasOriginalDiscount?existingDiscountIndex+1:rateIndex+1;
          if(!hasOriginalDiscount){
            var pct=row.insertCell(at);
            pct.className='num am131-added';
            pct.setAttribute('data-am131-added','1');
            pct.textContent=row.dataset.am131Pct||'0.0';
            at++;
          }
          var amt=row.insertCell(at);
          amt.className='num am131-added';
          amt.setAttribute('data-am131-added','1');
          amt.textContent=row.dataset.am131Amt||'0.00';
        });
        if(!enabled)return;
        var position=hasOriginalDiscount?existingDiscountIndex+1:rateIndex+1;
        if(!hasOriginalDiscount){
          var pctH=document.createElement('th');
          pctH.className='num am131-added';
          pctH.setAttribute('data-am131-added','1');
          pctH.textContent='DIS%';
          hr.insertBefore(pctH,hr.cells[position]||null);
          position++;
        }
        var amountH=document.createElement('th');
        amountH.className='num am131-added';
        amountH.setAttribute('data-am131-added','1');
        amountH.textContent='DIS AMT';
        hr.insertBefore(amountH,hr.cells[position]||null);
      });
    }
    input.addEventListener('change',render);
    render();
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',ready);
  else ready();
})();
</script>
"""
# No Blade statements or PHP expressions occur inside the JavaScript.
if src.count("</body>")!=1:
    raise SystemExit("STOP: ambiguous HTML body closing tag")
src=src.replace("</body>",script+"</body>",1)
if src.count("AM131_SHOW_DISCOUNT_ONLY")!=1 or src.count('id="am131-discount-switch"')!=1:
    raise SystemExit("STOP: patch validation failed")

# Compile and lint the transformed Blade template BEFORE modifying the live view.
# This avoids repeating AM130's 500 due to malformed compiled PHP.
with tempfile.TemporaryDirectory(prefix="am131_check_") as td:
    tmp = Path(td)
    candidate = tmp/"summary-print.blade.php"
    candidate.write_text(src,encoding="utf-8")
    validator = tmp/"compile.php"
    validator.write_text("""<?php
require $argv[1] . '/vendor/autoload.php';
$app=require $argv[1] . '/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$blade=$app->make('blade.compiler');
file_put_contents($argv[3], $blade->compileString(file_get_contents($argv[2])));
""",encoding="utf-8")
    compiled=tmp/"compiled.php"
    compiled_result=subprocess.run(
        ["php",str(validator),str(root),str(candidate),str(compiled)],
        text=True,capture_output=True)
    if compiled_result.returncode!=0 or not compiled.is_file():
        raise SystemExit("STOP: Blade compile failed: "+compiled_result.stderr[-1300:])
    lint=subprocess.run(["php","-l",str(compiled)],text=True,capture_output=True)
    if lint.returncode!=0:
        raise SystemExit("STOP: compiled PHP syntax failed: "+(lint.stdout+lint.stderr)[-1400:])
    print("AM131_BLADE_COMPILE_AND_PHP_LINT_PASS")
if mode=="check":
    print("AM131_PREFLIGHT_PASS_NO_WRITES")
    sys.exit(0)

backup=target.with_name(target.name+".AM131_"+datetime.datetime.now().strftime("%Y%m%d_%H%M%S")+".bak")
shutil.copy2(target,backup)
fd,name=tempfile.mkstemp(prefix=".am131_",dir=target.parent)
try:
    with os.fdopen(fd,"w",encoding="utf-8") as f: f.write(src)
    os.chmod(name,target.stat().st_mode & 0o777)
    os.replace(name,target)
finally:
    if os.path.exists(name):os.unlink(name)
print("AM131_VIEW_UPDATED",str(target))
print("BACKUP",str(backup))
print("NEXT: php artisan view:clear; then browser / print QA")
