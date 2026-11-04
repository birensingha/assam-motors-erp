#!/usr/bin/env python3
"""AM235 read-only audit. Reports schema and code shapes only; no data rows/secrets."""
from pathlib import Path
import re,sys,subprocess,tempfile
root=Path(sys.argv[1]).expanduser().resolve()
if root.name != 'assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('AM235_STOP_NOT_STAGING')
print('AM235_PURCHASE_AUDIT_BEGIN_NO_WRITES',flush=True)
paths=[
 'app/Services/PurchaseService.php',
 'app/Http/Controllers/Web/AdminPurchasePageController.php',
 'app/Services/JobCardService.php',
 'app/Http/Controllers/Web/AdminJobCardPageController.php',
 'resources/views/purchases/create.blade.php',
 'public/js/purchase-entry.js'
]
for rel in paths:
    path=root/rel
    if not path.is_file():
        print('AM235_SOURCE_MISSING',rel);continue
    s=path.read_text('utf8','replace')
    lines=s.splitlines()
    names=[]
    for i,line in enumerate(lines,1):
        m=re.search(r'\bfunction\s+([A-Za-z0-9_]+)\s*\(',line)
        if m:names.append((m.group(1),i))
    print('AM235_SOURCE',rel,'LINES',len(lines),'METHODS',','.join(f'{name}:{n}' for name,n in names)[:4000])
    if 'purchases/create.blade' in rel:
        for i,line in enumerate(lines):
            if any(term in line for term in ('Keyboard Entry','purchase-form','<form','purchase-toolbar','job-filter-wrap','name="notes"','purchase-entry.js','<template','purchase-bottom')):
                print('AM235_VIEW',i+1,line[:550])
    if rel.endswith('purchase-entry.js'):
        for i,line in enumerate(lines):
            if any(term in line for term in ('AM234_PURCHASE_JC_PICKER','addEventListener(\'submit\'','function addLine','function recalc','function wireJobFilter','function wirePartSearch','function wire(')):
                print('AM235_JS',i+1,line[:350])
    if rel.endswith('PurchaseService.php'):
        for method,lineno in names:
            if re.search(r'(create|store|save|search|validate|stock|issue|purchase|adjust|recon|register)',method,re.I):
                start=lineno-1;stop=min(len(lines),start+95)
                print('AM235_METHOD_START',rel,method,lineno)
                for j in range(start,stop):
                    line=lines[j]
                    if re.search(r'password|secret|api[_-]?key|private[_-]?key|env\(',line,re.I):
                        line='[REDACTED]'
                    print('SRC',j+1,line[:400])
                print('AM235_METHOD_END',rel,method)
    if rel.endswith('AdminPurchasePageController.php'):
        for method,lineno in names:
            if re.search(r'(create|store|save|search|purchas|edit|show)',method,re.I):
                start=lineno-1;stop=min(len(lines),start+70)
                print('AM235_METHOD_START',rel,method,lineno)
                for j in range(start,stop):
                    line=lines[j]
                    if re.search(r'password|secret|api[_-]?key|private[_-]?key|env\(',line,re.I):
                        line='[REDACTED]'
                    print('SRC',j+1,line[:400])
                print('AM235_METHOD_END',rel,method)
php=r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
$rows=DB::select('SELECT table_name AS name FROM information_schema.tables WHERE table_schema=DATABASE() ORDER BY table_name');
$counter=0;
foreach($rows as $r){
 $name=(string)$r->name;
 if(!preg_match('/purchase|part|stock|invent|job_card|invoice|issue|return|ledger|estimate|bill|credit_note/i',$name))continue;
 if($counter++>100)break;
 echo 'AM235_TABLE '.$name.' '.implode(',',Schema::getColumnListing($name))."\n";
}
foreach(app('router')->getRoutes() as $r){
 $path=(string)$r->uri();
 if(!preg_match('~^erp/(purchases|job-cards/.*/parts|parts|invoices|stock|inventory)~i',$path))continue;
 echo 'AM235_ROUTE '.implode(',',array_diff($r->methods(),['HEAD'])).' '.$path.' '.$r->getActionName()."\n";
}
echo "AM235_SCHEMA_AUDIT_COMPLETE_NO_RECORDS\n";
"""
with tempfile.TemporaryDirectory(prefix='am235_') as t:
    f=Path(t)/'audit.php';f.write_text(php)
    p=subprocess.run(['php',str(f),str(root)],cwd=root,capture_output=True,text=True,timeout=55)
    print(p.stdout[:34000],flush=True)
    if p.returncode:
        print('AM235_SCHEMA_FAILURE_CODE',p.returncode, p.stderr[-400:])
        sys.exit(p.returncode)
print('AM235_AUDIT_END')
