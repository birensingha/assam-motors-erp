#!/usr/bin/env python3
"""AM261 read-only source/schema audit Parts, Labour, ROT and OSL HSN/SAC Master.
No customer values, records, passwords, cookies, rows or writes.
"""
from pathlib import Path
import sys,re,subprocess,collections
if len(sys.argv)!=2:raise SystemExit('AM261_USAGE audit.py STAGING_ROOT')
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('STOP_AM261_NOT_STAGING')
needle=re.compile(r'hsn|sac|part.?master|labour.?master|labor.?master|rot.?master|osl.?master|outside.?labour|service.?master|master.?item',re.I)
forms=re.compile(r'edit.part|create.part|add.part|edit.labour|create.labour|add.labour|edit.osl|create.osl|add.osl|edit.rot|create.rot|add.rot|hsn|sac',re.I)
redact=re.compile(r'password|cookie|secret|APP_KEY|DB_PASSWORD|personal_access|authorization|bearer|PRIVATE_KEY|csrf_token\(',re.I)
candidates=[]
for base in ['resources/views','app/Http/Controllers','app/Services','public/js','routes']:
    path=root/base
    if not path.is_dir():continue
    for p in path.rglob('*'):
        if not p.is_file() or p.stat().st_size>350000 or p.suffix not in ('.php','.js'):continue
        if p.suffix=='.php' and 'resources/views' in base and not p.name.endswith('.blade.php'):continue
        rel=p.relative_to(root).as_posix()
        if any(x in rel for x in ('vendor/','node_modules/','legacy/','tests/','archive/')):continue
        lines=p.read_text('utf8','replace').splitlines()
        matched=[i for i,l in enumerate(lines) if needle.search(l)]
        if not matched:continue
        score=sum(5 if 'hsn' in lines[i].lower() or 'sac' in lines[i].lower() else 1 for i in matched)
        if any(x in rel.lower() for x in ['part','labour','labor','rot','osl','master','config','inventory','item','estimate']):score+=10
        candidates.append((score,rel,lines,matched))
candidates.sort(key=lambda v:-v[0])
print('AM261_MATCHING_FILES_COUNT',len(candidates))
for score,rel,lines,matched in candidates[:80]:
    print('AM261_FILE',rel,'LINES',len(lines),'MATCHES',len(matched),'SCORE',score)
for score,rel,lines,matched in candidates[:27]:
    chosen=set()
    for i in matched[:24]:
        if forms.search(lines[i]) or re.search(r'function\s+(store|update|create|edit)|Route::.*master',lines[i],re.I):
            chosen.update(range(max(0,i-4),min(len(lines),i+7)))
    for i in sorted(chosen)[:160]:
        v=lines[i].strip()
        if redact.search(v):v='[REDACTED]'
        print('AM261_CODE',rel,i+1,v[:540])
php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;use Illuminate\Support\Facades\Route;
$tables=DB::select("SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND (TABLE_NAME LIKE '%part%' OR TABLE_NAME LIKE '%labour%' OR TABLE_NAME LIKE '%labor%' OR TABLE_NAME LIKE '%rot%' OR TABLE_NAME LIKE '%osl%' OR TABLE_NAME LIKE '%service%' OR TABLE_NAME LIKE '%master%') ORDER BY TABLE_NAME");
foreach($tables as $t){
 $n=$t->name;
 if(str_contains($n,'audit')||str_contains($n,'log')||str_contains($n,'history'))continue;
 $col=DB::select("SELECT COLUMN_NAME AS c, DATA_TYPE AS d, CHARACTER_MAXIMUM_LENGTH AS length,
 IS_NULLABLE AS nullable FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION",[$n]);
 if(count($col)>95)continue;
 echo 'AM261_TABLE_SCHEMA '.json_encode(['table'=>$n,'columns'=>array_map(fn($c)=>[$c->c,$c->d,$c->length,$c->nullable],$col)],JSON_UNESCAPED_SLASHES)."\n";
}
foreach(Route::getRoutes() as $r){
 $uri=(string)$r->uri();$name=(string)($r->getName()??'');$act=(string)$r->getActionName();
 if(preg_match('/master|part|labour|labor|rot|osl|service/i',$uri.' '.$name) && 
   (in_array('POST',$r->methods(),true)||in_array('PUT',$r->methods(),true)||in_array('PATCH',$r->methods(),true))) {
  echo 'AM261_MASTER_WRITE_ROUTE '.json_encode(['uri'=>$uri,'name'=>$name,'methods'=>$r->methods(),'action'=>$act],JSON_UNESCAPED_SLASHES)."\n";
 }
}
echo "AM261_SCHEMA_AND_ROUTE_AUDIT_READONLY_COMPLETE\n";"""
r=subprocess.run(['php','-r',php],cwd=root,capture_output=True,text=True,timeout=65)
if r.returncode:raise SystemExit('STOP_AM261_SCHEMA_AUDIT '+(r.stdout+r.stderr)[-800:])
print(r.stdout[:54000])
print('AM261_NO_DB_SOURCE_OR_BUSINESS_DATA_CHANGES')
