#!/usr/bin/env python3
"""AM251 read-only native ROT/JC labour creation feasibility audit.
Reads source code (only relevant lines), schema and JC11 ROT metadata.
No writes, no credentials, no complete business row dumps.
"""
from pathlib import Path
import re,subprocess,sys
if len(sys.argv)!=2:raise SystemExit('AM251_USAGE diagnose.py STAGING_ROOT')
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('STOP_AM251_NOT_STAGING')
targets={
 'routes/web.php':r'job-cards.*(labour|rot)|addDirectLabour|directLabour|assignMechanic',
 'app/Http/Controllers/Web/AdminJobCardDirectLabourController.php':r'function |DB::|ROT|rot_|labour|billing|rate|total|staff_id',
 'app/Http/Controllers/Web/AdminJobCardPageController.php':r'function .*Labour|function .*Rot|function .*Mechanic|rot_sessions|job_card_labour|function assign',
 'app/Http/Controllers/Web/AdminWorkshopControlPageController.php':r'function .*Labour|function .*Rot|rot_sessions|job_card_labour',
 'app/Services/RotWorkflowService.php':r'function |rot_sessions|staff_id|assigned|start|status',
 'resources/views/job-cards/show.blade.php':r'direct-labour|add-labour|labour.assign|labour.direct|am83-inline-add|rot_sessions|ROT',
}
secret=re.compile(r'(?i)(password|secret|bearer|authorization|api[_ -]?key|private[_ -]?key|DB_PASSWORD|APP_KEY|cookie|token=)')
print('AM251_NATIVE_ROT_SOURCE_READONLY_START')
for rel,pattern in targets.items():
    p=root/rel
    if not p.is_file():
        print('AM251_SOURCE_MISSING',rel);continue
    lines=p.read_text('utf8','replace').splitlines()
    pat=re.compile(pattern,re.I)
    hits=[i for i,line in enumerate(lines) if pat.search(line)]
    print('AM251_SOURCE_FILE',rel,'TOTAL_LINES',len(lines),'MATCHES',len(hits))
    if 'AdminJobCardDirectLabourController' in rel:
        sections=range(min(len(lines),270))
    elif rel.endswith('routes/web.php'):
        relevant=set()
        for i in hits:relevant.update(range(max(0,i-2),min(len(lines),i+4)))
        sections=sorted(relevant)[:150]
    elif rel.endswith('show.blade.php'):
        relevant=set()
        for i in hits[:18]:relevant.update(range(max(0,i-2),min(len(lines),i+5)))
        sections=sorted(relevant)[:150]
    else:
        relevant=set()
        for i in hits[:16]:relevant.update(range(max(0,i-5),min(len(lines),i+16)))
        sections=sorted(relevant)[:220]
    for i in sections:
        line=lines[i].strip()
        if secret.search(line):line='[REDACTED_SENSITIVE]'
        print('AM251_CODE',rel,i+1,line[:490])
php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
echo "AM251_DB_SCHEMA_READONLY_START\n";
foreach(['rot_sessions','job_card_labour','job_cards','labour_master','labour_masters','am_complaint_rework_links','am_rot_work_complaints'] as $table){
 if(!Schema::hasTable($table)){echo "AM251_TABLE_MISSING ".$table."\n";continue;}
 $cols=DB::select('SELECT COLUMN_NAME AS n,DATA_TYPE AS t,IS_NULLABLE AS nullable,COLUMN_DEFAULT AS d FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION',[$table]);
 $arr=[];
 foreach($cols as $col){$arr[]=$col->n.':'.$col->t.':'.$col->nullable.':'.($col->d===null?'NULL':substr((string)$col->d,0,28));}
 echo 'AM251_COLUMNS '.$table.' '.implode('|',$arr)."\n";
}
if(Schema::hasTable('rot_sessions')){
 $rows=DB::table('rot_sessions')->where('job_card_id',11)->orderByDesc('id')->limit(12)->get();
 foreach($rows as $v){
  $out=['id'=>$v->id??null,'staff_id'=>$v->staff_id??null,'job_card_id'=>$v->job_card_id??null,'status'=>$v->status??null,
   'job_card_labour_id'=>$v->job_card_labour_id??null,'labour_id'=>$v->labour_id??null,'rot_id'=>$v->rot_id??null];
  echo 'AM251_JC11_ROT_META='.json_encode($out)."\n";
 }
}
if(Schema::hasTable('job_card_labour')){
 $rows=DB::table('job_card_labour')->where('job_card_id',11)->limit(8)->get();
 foreach($rows as $v){
  $out=['id'=>$v->id??null,'rot_session_id'=>$v->rot_session_id??null,'labour_id'=>$v->labour_id??null,
   'mechanic_id'=>$v->mechanic_id??null,'staff_id'=>$v->staff_id??null,'execution_status'=>$v->execution_status??null,
   'rate'=>$v->rate??null,'qty'=>$v->qty??null,'total'=>$v->total??null];
  echo 'AM251_JC11_LABOUR_META='.json_encode($out)."\n";
 }
}
if(Schema::hasTable('am_complaint_rework_links')){
 $r=DB::table('am_complaint_rework_links')->where('complaint_id',1)->first();
 if($r)echo 'AM251_COMPLAINT_ASSIGNMENT='.json_encode([
 'complaint_id'=>$r->complaint_id,'assigned_staff_id'=>$r->assigned_staff_id,
 'rework_rot_session_id'=>$r->rework_rot_session_id,'status'=>$r->status])."\n";
}
echo "AM251_DB_SCHEMA_READONLY_COMPLETE\n";"""
p=subprocess.run(['php','-r',php],cwd=root,capture_output=True,text=True,timeout=45)
if p.returncode:
    print('STOP_AM251_DB_SCHEMA_PHP',(p.stdout+p.stderr)[-650:]);raise SystemExit(3)
print(p.stdout[:26000])

print('AM251_CREATION_HANDLER_DETAILS_BEGIN')
for rel,method in [
 ('app/Http/Controllers/Web/AdminJobCardPageController.php','assignMechanic'),
 ('app/Http/Controllers/Web/AdminJobCardPageController.php','addLabourDirect'),
 ('app/Http/Controllers/Web/AdminRotBillingController.php','setBilling'),
 ('app/Services/RotWorkflowService.php','assign')]:
 p=root/rel
 if not p.is_file():
  print('AM251_DETAILS_SOURCE_MISSING',rel);continue
 lines=p.read_text('utf8','replace').splitlines()
 found=[i for i,v in enumerate(lines) if re.search(r'function\s+'+re.escape(method)+r'\s*\(',v)]
 if len(found)!=1:
  print('AM251_DETAILS_METHOD_NOT_UNIQUE',rel,method,len(found));continue
 start=found[0]
 if method=='addLabourDirect':maxcount=70
 elif method=='assignMechanic':maxcount=260
 elif method=='setBilling':maxcount=180
 else:maxcount=140
 stop=min(len(lines),start+maxcount)
 for i in range(start,stop):
  if i>start and re.search(r'^\s*(?:(?:public|private|protected)\s+)?function\s+\w+\s*\(',lines[i]):
   break
  v=lines[i].strip()
  if secret.search(v):v='[REDACTED_SENSITIVE]'
  print('AM251_DETAIL_CODE',rel,i+1,v[:500])
print('AM251_CREATION_HANDLER_DETAILS_END')


print('AM251_ROT_ASSIGNMENT_SERVICE_DETAIL_START')
for rel in ['app/Services/RotAssignmentService.php',
            'app/Models/JobCardLabour.php','app/Models/RotSession.php']:
 p=root/rel
 if not p.is_file():
  print('AM251_SERVICE_FILE_MISSING',rel);continue
 lines=p.read_text('utf8','replace').splitlines()
 print('AM251_SERVICE_FILE',rel,'LINES',len(lines))
 for n,line in enumerate(lines[:340],1):
  if secret.search(line):line='[REDACTED_SENSITIVE]'
  print('AM251_SERVICE_CODE',rel,n,line.strip()[:560])
print('AM251_ROT_ASSIGNMENT_SERVICE_DETAIL_DONE')

print('AM251_NO_SOURCE_OR_DATABASE_CHANGES')
print('AM251_READ_ONLY_DONE')
