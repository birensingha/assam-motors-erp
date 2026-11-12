#!/usr/bin/env python3
"""AM252 read-only source and JC #11 ROT billing audit. Never displays credentials."""
from pathlib import Path
import re,sys,subprocess
if len(sys.argv)!=2:raise SystemExit('USAGE_AM252_DIAG STAGING_ROOT')
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('STOP_AM252_NOT_STAGING')
secret=re.compile(r'(?i)password|secret|bearer|authorization|api[_-]?key|private[_-]?key|DB_PASSWORD|APP_KEY|cookie')
terms={
 'ROT_TEMPLATE': ['Report Work Complaint','Edit ROT Billing','Productive saved','COMPLETE ELAPSED TAT','ROT TIME'],
 'INVOICE': ['billing_included','job_card_labour','createInvoice','saveInvoice'],
 'ALTER': ['function save','billing_included','labour_rate','discount_percent'],
}
print('AM252_SOURCE_SEARCH_START')
hits=[]
for p in (root/'resources/views').rglob('*.blade.php'):
 if p.stat().st_size>600000:continue
 src=p.read_text('utf8','replace')
 found=[k for k in terms['ROT_TEMPLATE'] if k.lower() in src.lower()]
 if found:hits.append((p,src,found))
print('AM252_ROT_VIEW_CANDIDATES',len(hits))
for p,src,found in hits[:12]:
 ls=src.splitlines();mat=[i for i,s in enumerate(ls) if any(k.lower() in s.lower() for k in terms['ROT_TEMPLATE'])]
 print('AM252_ROT_VIEW',p.relative_to(root),'matched',','.join(found),'lines',len(ls))
 chosen=set()
 for i in mat[:20]:chosen.update(range(max(0,i-4),min(len(ls),i+8)))
 for i in sorted(chosen)[:150]:
  v=ls[i].strip()
  if secret.search(v):v='[REDACTED]'
  print('AM252_VIEW_CODE',p.relative_to(root),i+1,v[:550])
for rel in ('app/Http/Controllers/Web/AdminRotBillingController.php','app/Http/Controllers/Web/JobCardRotAlterationController.php'):
 p=root/rel
 if not p.is_file():continue
 ls=p.read_text('utf8','replace').splitlines()
 print('AM252_CONTROLLER',rel,len(ls))
 for i,v in enumerate(ls[:240]):
  if secret.search(v):v='[REDACTED]'
  print('AM252_CONTROLLER_CODE',rel,i+1,v.strip()[:520])
# Source-only invoice guard search paths and few surrounding lines, no business data.
for p in (root/'app/Http/Controllers').rglob('*.php'):
 if p.stat().st_size>350000:continue
 src=p.read_text('utf8','replace')
 if 'job_card_labour' not in src or ('invoice' not in p.name.lower() and 'lifecycle' not in p.name.lower()):continue
 ls=src.splitlines()
 match=[i for i,v in enumerate(ls) if 'billing_included' in v or ('job_card_labour' in v and 'invoice' in src.lower())]
 print('AM252_INVOICE_SOURCE',p.relative_to(root),'matches',len(match))
 for i in match[:15]:
  for j in range(max(0,i-3),min(len(ls),i+4)):
   v=ls[j].strip()
   if secret.search(v):v='[REDACTED]'
   print('AM252_INVOICE_CODE',p.relative_to(root),j+1,v[:500])
php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;use Illuminate\Support\Facades\Schema;
$link=DB::table('am_complaint_rework_links')->where('complaint_id',1)->first();
if(!$link){echo "AM252_NO_COMPLAINT_ASSIGNMENT\n";exit;}
echo 'AM252_COMPLAINT_LINK='.json_encode(['complaint_id'=>$link->complaint_id,'job_card_id'=>$link->job_card_id,'original_rot_session_id'=>$link->original_rot_session_id,'rework_rot_session_id'=>$link->rework_rot_session_id,'assigned_staff_id'=>$link->assigned_staff_id,'status'=>$link->status])."\n";
foreach([$link->original_rot_session_id,$link->rework_rot_session_id] as $id) {
 if(!$id)continue;
 $rot=DB::table('rot_sessions')->where('id',$id)->first();
 if($rot)echo 'AM252_ROT='.json_encode(['id'=>$rot->id,'job_card_id'=>$rot->job_card_id,'labour_line_id'=>$rot->labour_line_id,'staff_id'=>$rot->staff_id,'status'=>$rot->status,'amount'=>$rot->amount])."\n";
 $lab=DB::table('job_card_labour')->where('rot_session_id',$id)->first();
 if($lab)echo 'AM252_LABOUR='.json_encode(['id'=>$lab->id,'job_card_id'=>$lab->job_card_id,'rot_session_id'=>$lab->rot_session_id,'mechanic_id'=>$lab->mechanic_id,'labour_rate'=>$lab->labour_rate,'gst_percent'=>$lab->gst_percent,'total'=>$lab->total,'billing_included'=>$lab->billing_included,'billing_reason'=>$lab->billing_reason,'execution_status'=>$lab->execution_status])."\n";
}
$card=DB::table('job_cards')->where('id',11)->first();
echo 'AM252_JC11_TOTALS='.json_encode(['status'=>$card->status??null,'labour_total'=>$card->labour_total??null,'grand_total'=>$card->grand_total??null,'invoice_id'=>$card->invoice_id??null])."\n";
echo 'AM252_BILLABLE_LABOUR_SUM='.DB::table('job_card_labour')->where('job_card_id',11)->where('billing_included',1)->sum('total')."\n";
echo 'AM252_ALL_LABOUR_SUM='.DB::table('job_card_labour')->where('job_card_id',11)->sum('total')."\n";
echo 'AM252_READONLY_COMPLETE'."\n";"""
p=subprocess.run(['php','-r',php],cwd=root,capture_output=True,text=True,timeout=50)
if p.returncode:raise SystemExit('STOP_AM252_PHP_PROBE '+(p.stderr+p.stdout)[-750:])
print(p.stdout[-7500:])
print('AM252_DIAGNOSTIC_NO_WRITES')
