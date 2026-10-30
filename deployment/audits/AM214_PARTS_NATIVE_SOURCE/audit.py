#!/usr/bin/env python3
"""Read-only Parts Master form and controller fields. No customer/master data."""
from pathlib import Path
import re,sys
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM214_AUDIT_STOP_NOT_STAGING')
ctl=root/'app/Http/Controllers/Web/AdminNativeModuleController.php'
view=root/'resources/views/admin/native/parts.blade.php'
a=ctl.read_text('utf8','surrogateescape')
b=view.read_text('utf8','surrogateescape')
def method(name):
    m=re.search(r'\bpublic\s+function\s+'+re.escape(name)+r'\s*\(',a)
    if not m:return ''
    nxt=re.search(r'\n[ \t]*(?:public|private|protected)\s+function\s+\w+\s*\(',a[m.end():])
    end=m.end()+nxt.start() if nxt else len(a)
    return a[m.start():end]
for name in ('createPart','updatePart'):
    src=method(name)
    keys=sorted(set(re.findall(r"'([a-z][a-z0-9_]*)'\s*=>\s*\[",src)))
    mapkeys=sorted(set(re.findall(r"'([a-z][a-z0-9_]*)'\s*=>",src)))
    print('AM214_AUDIT_'+name.upper()+'_VALIDATION_KEYS',','.join(keys))
    print('AM214_AUDIT_'+name.upper()+'_OTHER_MAPPING_KEYS',','.join(mapkeys))
    print('AM214_AUDIT_'+name.upper()+'_USES_EXISTING',int("$this->existing('parts_master'" in src))
    print('AM214_AUDIT_'+name.upper()+'_UPDATE_DATA',int('->update($data)' in src))
forms=list(re.finditer(r'<form\b[\s\S]*?</form>',b,re.I))
for i,m in enumerate(forms):
    chunk=m.group()
    fields=re.findall(r'<(?:input|select|textarea)\b[^>]*\bname\s*=\s*["\x27]([^"\x27]+)',chunk,re.I|re.S)
    print('AM214_AUDIT_PART_FORM_'+str(i+1)+'_FIELDS',','.join(fields))
for field in ('category','sub_category','unit','vehicle_make','vehicle_model','gst_percent'):
    print('AM214_AUDIT_PART_FIELD_'+field.upper()+'_COUNT',len(re.findall(r'\bname\s*=\s*["\x27]'+field+r'["\x27]',b)))
print('AM214_PARTS_NATIVE_SOURCE_READONLY_COMPLETE')
