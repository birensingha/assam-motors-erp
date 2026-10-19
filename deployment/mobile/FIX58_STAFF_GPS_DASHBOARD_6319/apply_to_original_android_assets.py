#!/usr/bin/env python3
"""FIX58 integrate-only source patch. Original APK signing must be done separately."""
import argparse
import shutil
import re
from pathlib import Path

def main():
    p=argparse.ArgumentParser()
    p.add_argument("original_assets_fresh",type=Path)
    p.add_argument("--apply",action="store_true")
    a=p.parse_args()
    folder=a.original_assets_fresh.resolve()
    src=folder/"index.html"
    assert src.is_file() and (folder/"app.js").is_file() and (folder/"owner-extra.js").is_file()
    package=Path(__file__).resolve().parent
    original=src.read_bytes()
    markup=original.decode("utf8")
    assert markup.count('</head>')==1 and markup.count('</body>')==1
    assert markup.count('id="view-owner-locations"')==1
    assert markup.count('<script src="owner-extra.js"></script>')==1
    assert "amGps6320Template" not in markup, "FIX58 already patched"
    frag=(package/"gps-dashboard.fragment.html").read_text()
    ids=re.findall(r'id="([^"]+)"',frag)
    assert len(ids)==len(set(ids)) and not set(ids)&set(re.findall(r'id="([^"]+)"',markup))
    new=markup.replace('</head>','  <link rel="stylesheet" href="gps-dashboard-v6320.css">\n</head>')
    new=new.replace('</body>',frag+'\n<script src="gps-dashboard-v6320.js"></script>\n</body>')
    assert new.index('<script src="owner-extra.js">')<new.index('<script src="gps-dashboard-v6320.js">')
    if not a.apply:
        print("FIX58_ORIGINAL_APP_ASSETS_EXACT_MATCH_PASS_NO_WRITES")
        return
    backup=folder/"index.html.before-fix58"
    assert not backup.exists(), "Existing backup detected"
    backup.write_bytes(original)
    src.write_text(new)
    for filename in ("gps-dashboard-v6320.css","gps-dashboard-v6320.js"):
        target=folder/filename
        assert not target.exists(), "FIX58 target already exists: "+filename
        shutil.copy2(package/filename,target)
    print("FIX58_APK_SOURCE_WEBVIEW_ASSETS_UPDATED; ORIGINAL_SIGNED_BUILD_REQUIRED")

if __name__=="__main__":main()
