#!/usr/bin/env python3
"""AM177 READ ONLY: reconcile public Hiring applications vs NEW ERP records.

Executes two isolated PHP CLI processes (public config and Laravel ERP config).
Only application-table schema and HMAC/sha256 fingerprints are passed privately
to this Python process; prints counts exclusively, NEVER applicants' personal
details, identifiers, email, phone, CV, database hostname or credentials.

No writes. Run: python3 audit.py LARAVEL_STAGING_ROOT
"""
from pathlib import Path
import collections,hashlib,json,subprocess,sys,tempfile

if len(sys.argv)!=2:
    raise SystemExit("Usage: audit.py LARAVEL_STAGING_ROOT")
root=Path(sys.argv[1]).expanduser().resolve()
if not (root/"artisan").is_file():
    raise SystemExit("AM177_STOP_NOT_STAGING_ROOT")
domain=root.parent
cfg=domain/"public_html/api/config.php"
if not cfg.is_file():
    raise SystemExit("AM177_STOP_PUBLIC_CONFIG_NOT_FOUND")
print("AM177_HIRING_RECONCILIATION_READ_ONLY")

common=r"""
function queryRows($pdo) {
 $cols=$pdo->query("SHOW COLUMNS FROM job_applications")->fetchAll(PDO::FETCH_ASSOC);
 $fields=array_column($cols,'Field');
 $needed=['id','name','phone','position','created_at','status'];
 foreach($needed as $field){
   if(!in_array($field,$fields,true))throw new RuntimeException('MISSING_COLUMN_'.$field);
 }
 // Each source is handled by its own process. No output contains raw PII.
 $rows=$pdo->query("SELECT id,name,phone,position,created_at,status FROM job_applications")->fetchAll(PDO::FETCH_ASSOC);
 $summary=[];
 foreach($rows as $row){
   $phone=preg_replace('/\D+/','',(string)($row['phone']??''));
   if(strlen($phone)>=10)$phone=substr($phone,-10);
   $name=mb_strtolower(trim(preg_replace('/\s+/u',' ',(string)($row['name']??''))));
   $pos=mb_strtolower(trim((string)($row['position']??'')));
   $when=substr((string)($row['created_at']??''),0,19);
   $summary[]=[
     'id'=>(int)$row['id'],
     'exact'=>hash('sha256',json_encode([$phone,$name,$pos])),
     'loose'=>hash('sha256',json_encode([$phone,$pos])),
     'dated'=>hash('sha256',json_encode([$phone,$name,$pos,$when])),
     'status'=>strtolower(trim((string)($row['status']??''))),
   ];
 }
 return ['cols'=>$fields,'records'=>$summary];
}
"""
public_php="""<?php
require $argv[1];
"""+common+"""
try{
 $pdo=getDB();
 if(!($pdo instanceof PDO))throw new RuntimeException('NOT_PDO');
 echo json_encode(queryRows($pdo));
}catch(Throwable $e){
 fwrite(STDERR,'PUBLIC_PROBE_FAILED: '.get_class($e));
 exit(2);
}
"""
erp_php="""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
"""+common+"""
try{
 $pdo=$app->make('db')->connection()->getPdo();
 echo json_encode(queryRows($pdo));
}catch(Throwable $e){
 fwrite(STDERR,'ERP_PROBE_FAILED: '.get_class($e));
 exit(3);
}
"""
def run(phpfile,arg):
    p=subprocess.run(["php",str(phpfile),str(arg)],
       text=True,capture_output=True,timeout=35)
    if p.returncode:
        raise SystemExit("AM177_STOP_SOURCE_PROBE_FAILED "+p.stderr[:160])
    try: return json.loads(p.stdout)
    except (ValueError,TypeError):
        raise SystemExit("AM177_STOP_UNEXPECTED_PROBE_OUTPUT")
with tempfile.TemporaryDirectory(prefix="am177_") as temp:
    td=Path(temp)
    pp=td/"public.php";ep=td/"erp.php"
    pp.write_text(public_php,encoding="utf8")
    ep.write_text(erp_php,encoding="utf8")
    a=run(pp,cfg);b=run(ep,root)
    ac=a["records"];bc=b["records"]
    cnt_a=collections.Counter(x["exact"] for x in ac)
    cnt_b=collections.Counter(x["exact"] for x in bc)
    loose_b=collections.Counter(x["loose"] for x in bc)
    ids_b={x["id"]:x["exact"] for x in bc}
    exact_matched=sum(min(v,cnt_b.get(k,0)) for k,v in cnt_a.items())
    unique_candidates=sum(
      v for k,v in cnt_a.items() if cnt_b.get(k,0)==0
    )
    loose_candidates=sum(
      1 for x in ac if cnt_b.get(x["exact"],0)==0 and
       loose_b.get(x["loose"],0)>0
    )
    id_collisions=sum(
      1 for x in ac if x["id"] in ids_b and x["exact"]!=ids_b[x["id"]]
    )
    p_cols=set(a["cols"]);e_cols=set(b["cols"])
    required={"id","name","phone","email","position","qualification",
      "experience","address","message","status","created_at"}
    print("PUBLIC_COUNT",len(ac))
    print("NEW_ERP_COUNT",len(bc))
    print("EXACT_NAME_PHONE_POSITION_MATCHES",exact_matched)
    print("PUBLIC_ROWS_WITH_NO_EXACT_ERP_MATCH",len(ac)-exact_matched)
    print("POTENTIAL_PHONE_POSITION_MATCH_DIFFERENT_NAME",loose_candidates)
    print("PUBLIC_DUPLICATE_NAME_PHONE_POSITION_GROUPS",
          sum(1 for n in cnt_a.values() if n>1))
    print("PRIMARY_KEY_ID_COLLISIONS_DIFFERENT_APPLICANT",id_collisions)
    print("PUBLIC_SCHEMA_INSERT_COLUMNS_AVAILABLE",required<=p_cols)
    print("ERP_SCHEMA_INSERT_COLUMNS_AVAILABLE",required<=e_cols)
    print("PUBLIC_RESUME_URL_COLUMN","resume_url" in p_cols)
    print("ERP_RESUME_URL_COLUMN","resume_url" in e_cols)
    if exact_matched:
        print("IMPORTANT_EXISTING_OVERLAP_REQUIRES_STATUS_REVIEW")
    if loose_candidates:
        print("IMPORTANT_SIMILAR_APPLICANTS_REQUIRE_MANUAL_REVIEW")
print("AM177_READ_ONLY_COMPLETE_NO_WRITES")
