#!/usr/bin/env python3
"""FIX60: exact-anchored, idempotent staging-only searchable selector injection.
Only resources/views/layouts/erp.blade.php and a new partial change. No DB writes.
"""
import argparse,hashlib,os,sys
from pathlib import Path
p=argparse.ArgumentParser()
p.add_argument("action",choices=("check","apply"))
p.add_argument("root")
p.add_argument("package")
args=p.parse_args()
root=Path(args.root).resolve();pkg=Path(args.package).resolve()
assert (root/"artisan").is_file() and (root/"routes/web.php").is_file(),"Not a Laravel app"
layout=root/"resources/views/layouts/erp.blade.php"
partial=root/"resources/views/partials/am-global-selector.blade.php"
js=(pkg/"am-select.js").read_text(encoding="utf8")
css=(pkg/"am-select.css").read_text(encoding="utf8")
marker="AM_FIX60_GLOBAL_SELECT_V1"
target="{{-- "+marker+" --}}\n<style>\n"+css+"\n</style>\n<script>\n"+js+"\n</script>\n"
assert "</script>" not in js.lower(),"Unsafe end script token"
assert marker in target and len(js)>4000 and len(css)>400
raw=layout.read_text(encoding="utf8")
anchor="    @include('partials.mobile-erp')"
if marker in raw:raise SystemExit("Unexpected inline marker: manual review")
if partial.exists():
    if partial.read_text(encoding="utf8")==target and raw.count("@include('partials.am-global-selector')")==1:
        print("FIX60_ALREADY_APPLIED");sys.exit(0)
    raise SystemExit("FIX60 partial conflict: refused")
assert raw.count(anchor)==1 and raw.lower().count("</body>")==1,"FIX60_LAYOUT_ANCHOR_MISMATCH"
assert "@include('partials.am-global-selector')" not in raw,"FIX60 unexpected include"
new=raw.replace(anchor,anchor+"\n    @include('partials.am-global-selector')",1)
if args.action=="check":
    print("FIX60_PREFLIGHT_PASS_ONLY_LAYOUT_AND_NEW_PARTIAL")
    print("FIX60_SELECTOR_BYTES",len(js),"CSS_BYTES",len(css))
    sys.exit(0)
partial.write_text(target,encoding="utf8")
tmp=layout.with_suffix(".blade.php.fix60.tmp")
tmp.write_text(new,encoding="utf8")
os.chmod(tmp,layout.stat().st_mode & 0o777)
os.replace(tmp,layout)
print("FIX60_APPLIED_STAGING_LAYOUT_AND_PARTIAL")
