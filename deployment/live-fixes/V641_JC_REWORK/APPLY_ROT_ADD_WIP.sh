#!/usr/bin/env bash
# V641: close direct-add bypass around operational ROT WIP rule.
set -euo pipefail
ROOT="$1"
test -f "$ROOT/artisan"
export V641_ROOT="$ROOT"
python3 - <<'PY'
from pathlib import Path
from datetime import datetime,timezone
import subprocess,os
root=Path(os.environ['V641_ROOT'])
paths={'ctrl':'app/Http/Controllers/Web/AdminJobCardPageController.php',
       'view':'resources/views/job-cards/show.blade.php'}
old={k:(root/f).read_bytes() for k,f in paths.items()}
def once(src,find,repl,label):
 if repl in src:return src
 n=src.count(find)
 if n!=1:raise RuntimeError('V641_ROT_ADD_PRECHECK '+label+' count='+str(n))
 return src.replace(find,repl,1)
source=old['ctrl'].decode('utf-8','surrogateescape')
start=source.index('public function addLabourDirect(')
end=source.find('public function ',start+20)
if end<0:end=len(source)
pre,body,post=source[:start],source[start:end],source[end:]
needle="""            if (in_array(strtolower((string)$card->status),['closed','invoiced','cancelled'],true)) {
                throw ValidationException::withMessages(['labour_master_id'=>'Locked Job Card cannot accept Labour/ROT.']);
            }"""
repl="""            if (in_array(strtolower((string)$card->status),['closed','invoiced','cancelled'],true)) {
                throw ValidationException::withMessages(['labour_master_id'=>'Locked Job Card cannot accept Labour/ROT.']);
            }
            if(!in_array(strtolower(trim((string)$card->status)),
                ['wip','in progress','in-progress','work in progress'],true)){
                throw ValidationException::withMessages(['labour_master_id'=>
                    'Direct Labour/ROT can only be added at WIP. Use audited Return to WIP for Work Complete/QC first.']);
            }
            if(!empty($card->invoice_id) || DB::table('invoices')->where('job_card_id',$job)
                ->whereRaw("LOWER(COALESCE(status,'')) <> 'reversed'")->exists()){
                throw ValidationException::withMessages(['labour_master_id'=>
                    'Invoice-linked Job Card locked. Authorized accounting reversal required.']);
            }"""
body=once(body,needle,repl,'direct add WIP condition')
code=(pre+body+post).encode('utf-8','surrogateescape')
lint=subprocess.run(['php','-l'],input=code,capture_output=True)
if lint.returncode:raise RuntimeError('V641 PHP syntax fail '+lint.stderr.decode('utf-8','replace'))
view=old['view'].decode('utf-8','surrogateescape')
# Scope WIP visibility to one unique Labour/ROT direct-add form, ignoring indentation.
marker='<!-- V641_LABOUR_DIRECT_WIP_UI -->'
if marker not in view:
    needle='<form method="post" action="{{ route(\'erp.job-cards.labour.direct\',$job->id) }}"'
    start=view.find(needle)
    if start<0 or view.find(needle,start+len(needle))>=0:raise RuntimeError('V641 Labour Direct Form missing/duplicate')
    finish=view.find('</form>',start)
    if finish<0:raise RuntimeError('V641 Labour Direct Form is unclosed')
    finish+=len('</form>')
    opening="""<!-- V641_LABOUR_DIRECT_WIP_UI -->
        @if(in_array(strtolower(trim((string)$job->status)),['wip','in progress','in-progress','work in progress'],true) && empty($job->invoice_id))
        """
    closing="""
        @else
        <p class="muted">Labour/ROT is editable only at WIP. For Work Complete/QC/Ready, choose <b>Return to WIP</b> in Workshop Lifecycle. Invoices need Finance reversal.</p>
        @endif"""
    view=view[:start]+opening+view[start:finish]+closing+view[finish:]
new={'ctrl':code,'view':view.encode('utf-8','surrogateescape')}
backup=root.parent/('BACKUP_V641_ROT_ADD_GUARD_'+datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ'))
backup.mkdir(mode=0o700)
done=[]
try:
 for k,p in paths.items():
  if new[k]==old[k]:continue
  dest=root/p;save=backup/p;save.parent.mkdir(parents=True,exist_ok=True);save.write_bytes(old[k]);save.chmod(0o600)
  temp=dest.with_name('.'+dest.name+'.v641')
  temp.write_bytes(new[k]);temp.chmod(dest.stat().st_mode&0o777);os.replace(temp,dest);done.append(k)
 p=subprocess.run(['php','-l',str(root/paths['ctrl'])],capture_output=True,text=True)
 if p.returncode:raise RuntimeError(p.stderr+p.stdout)
 p=subprocess.run(['php','artisan','view:cache'],cwd=root,capture_output=True,text=True,timeout=90)
 if p.returncode:raise RuntimeError(p.stderr+p.stdout)
except BaseException:
 for k in done:(root/paths[k]).write_bytes(old[k])
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True)
 print('V641_ROT_ADD_ROLLBACK=RESTORED')
 raise
print('V641_ROT_ADD_PHP_LINT=PASS')
print('V641_ROT_ADD_BLADE=PASS')
print('V641_DIRECT_ADD_WIP_SERVER_GUARD=DEPLOYED')
PY
