#!/usr/bin/env python3
from pathlib import Path
import sys,hashlib,re
root=Path(sys.argv[1]);assert (root/'artisan').exists()
view=root/'resources/views/job-cards/show.blade.php'
txt=view.read_text(errors='replace')
print('FIX88_VIEW_SHA256',hashlib.sha256(view.read_bytes()).hexdigest())
for k in ['FIX87_NATIVE_PARTS_NO_SCROLL_AJAX','am85-part-form','am85-discount-rs','am85-taxable','am85-gst-rs','am85-grand','am85-part-search']:
 print('FIX88_MARKER',k,txt.count(k))
for pattern in [r'<output id="am85-discount-rs"[^>]*>[^<]*</output>',r'<output id="am85-taxable"[^>]*>[^<]*</output>',r'<output id="am85-gst-rs"[^>]*>[^<]*</output>',r'<output id="am85-grand"[^>]*>[^<]*</output>']:
 a=re.findall(pattern,txt);print('FIX88_OUTPUT',len(a),a[0] if a else '')
for fragment in ['Search or add Parts directly. Qty / Rate / Discount / GST / Delete editable until Job Card Close. Stock is posted automatically at Close, exactly once.','<select id="am85-part-search"']:
 print('FIX88_EXACT',txt.count(fragment),fragment[:70])
print('FIX88_PHP_READONLY_PASS')
