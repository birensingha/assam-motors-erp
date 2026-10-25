#!/usr/bin/env python3
"""AM160 STAGING: remove already-deleted OSL Purchase rows from active Register,
while preserving supplier ledger, purchase batch and audit history. An optional
Deleted History view remains available to Admin. Direct deleted editor links
redirect to Register (no reactivation, no DB write).
python3 apply.py check|apply STAGING_ROOT
"""
from pathlib import Path
from datetime import datetime
import hashlib,os,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
    raise SystemExit('AM160_USAGE check|apply STAGING_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
if root.name!='assam-erp-staging' or not(root/'artisan').is_file():
    raise SystemExit('AM160_STOP_NOT_STAGING')
controller='app/Http/Controllers/Web/AdminWorkshopControlPageController.php'
view='resources/views/workshop-controls/osl-index.blade.php'
files={controller:root/controller,view:root/view}
for p in files.values():
    if not p.is_file() or p.is_symlink():raise SystemExit('AM160_STOP_FILE_MISSING')
original={k:p.read_bytes() for k,p in files.items()}
src={k:b.decode('utf-8','surrogateescape') for k,b in original.items()}
if 'AM160_ACTIVE_OSL_REGISTER' in src[controller]:
    if 'AM160_OSL_HISTORY_UI' in src[view]:
        print('AM160_ALREADY_INSTALLED');sys.exit(0)
    raise SystemExit('AM160_STOP_PARTIAL')
def replace_once(name,old,new,label):
    n=src[name].count(old)
    if n!=1:raise SystemExit('AM160_STOP_'+label+'_ANCHOR_COUNT_'+str(n))
    src[name]=src[name].replace(old,new,1)
old="""        $rows=DB::table('workshop_purchases')->where(function($q)use($typeColumn){
            $q->whereRaw('UPPER('.$typeColumn.") LIKE '%OSL%'")
              ->orWhereRaw('UPPER('.$typeColumn.") LIKE '%OUTSIDE%'");
        })->orderByDesc('id')->limit(300)->get();"""
new="""        // AM160_ACTIVE_OSL_REGISTER: archived OSL records are NOT live
        // supplier purchases. Default shows only active history-safe rows.
        $showDeleted=$request->boolean('show_deleted');
        $query=DB::table('workshop_purchases')->where(function($q)use($typeColumn){
            $q->whereRaw('UPPER('.$typeColumn.") LIKE '%OSL%'")
              ->orWhereRaw('UPPER('.$typeColumn.") LIKE '%OUTSIDE%'");
        });
        if($showDeleted) {
            $query->where(function($q){
                $q->whereRaw("UPPER(TRIM(COALESCE(record_status,'ACTIVE')))='DELETED'")
                  ->orWhereNotNull('deleted_at');
            });
        }else{
            $query->whereRaw("UPPER(TRIM(COALESCE(record_status,'ACTIVE'))) <> 'DELETED'")
                  ->whereNull('deleted_at');
        }
        $rows=$query->orderByDesc('id')->limit(300)->get();"""
replace_once(controller,old,new,'REGISTER_FILTER')
replace_once(controller,"return view('workshop-controls.osl-index',['rows'=>$rows,'term'=>$term]);",
"""return view('workshop-controls.osl-index',['rows'=>$rows,'term'=>$term,'showDeleted'=>$showDeleted]);""",'REGISTER_CONTEXT')
replace_once(controller,"public function editOsl(Request $request,int $purchase): View",
"""public function editOsl(Request $request,int $purchase): View|RedirectResponse""",'EDIT_RETURN')
old="""        $row=$this->oslRow($purchase);
        $jobId=(int)($row->job_card_id ?? 0);"""
new="""        $row=$this->oslRow($purchase);
        // AM160_ACTIVE_OSL_REGISTER: even a saved direct URL must not
        // resurface DELETED items in the active OSL Cost Editor.
        if(strtoupper(trim((string)($row->record_status??'ACTIVE')))==='DELETED'
            || !empty($row->deleted_at)) {
            return redirect()->route('erp.fix48.osl-index')->with(
                'success',
                'This OSL Purchase was already deleted. Its audit history is preserved under Deleted History.'
            );
        }
        $jobId=(int)($row->job_card_id ?? 0);"""
replace_once(controller,old,new,'DELETED_EDITOR_REDIRECT')
anchor='<div class="row head" role="row">'
ui=r'''{{-- AM160_OSL_HISTORY_UI: deleted purchase audit is separate from active OSL register. --}}
<div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin:14px 0">
    <strong style="font-size:13px;color:#23466c">{{ !empty($showDeleted) ? 'Deleted OSL Purchase History (Audit only)' : 'Active OSL Purchases' }}</strong>
    @if(!empty($showDeleted))
      <a href="{{ route('erp.fix48.osl-index') }}" style="font-weight:700;color:#135387">← Back to Active OSL Register</a>
    @else
      <a href="{{ route('erp.fix48.osl-index',['show_deleted'=>1]) }}" style="font-weight:700;color:#135387">View Deleted History</a>
    @endif
</div>
@if(session('success'))
  <p role="status" style="color:#125330;border:1px solid #add7b8;border-radius:6px;padding:10px;background:#f1fbf3">{{ session('success') }}</p>
@endif
'''
replace_once(view,anchor,ui+anchor,'REGISTER_HISTORY_LINK')
changes={k:v.encode('utf-8','surrogateescape') for k,v in src.items()}
for k in changes:print('AM160_HASH_'+k+'='+hashlib.sha256(original[k]).hexdigest()+' -> '+hashlib.sha256(changes[k]).hexdigest())
if mode=='check':
    preview=os.environ.get('AM160_PREVIEW')
    if preview:
        d=Path(preview)
        for k,b in changes.items():
            dest=d/k;dest.parent.mkdir(parents=True,exist_ok=True);dest.write_bytes(b)
    print('AM160_READONLY_PREFLIGHT_PASS_NO_DB_WRITES')
    sys.exit(0)
if any(files[k].read_bytes()!=original[k] for k in files):
    raise SystemExit('AM160_STOP_SOURCE_CONCURRENTLY_MODIFIED')
backup=root/'storage/app'/('am160_deleted_osl_backup_'+datetime.now().strftime('%Y%m%d_%H%M%S')+'_'+str(os.getpid()))
for k,b in original.items():
    dest=backup/k;dest.parent.mkdir(parents=True,exist_ok=True);dest.write_bytes(b)
    if dest.read_bytes()!=b:raise SystemExit('AM160_STOP_BACKUP_VERIFY')
installed=[]
try:
    for k,b in changes.items():
        dest=files[k]
        fd,tmp=tempfile.mkstemp(prefix='.am160_',dir=dest.parent)
        with os.fdopen(fd,'wb') as f:f.write(b)
        os.chmod(tmp,dest.stat().st_mode & 0o777)
        if dest.read_bytes()!=original[k]:raise SystemExit('AM160_STOP_SOURCE_CHANGED')
        os.replace(tmp,dest);installed.append(k)
    print('AM160_BACKUP='+str(backup))
    print('AM160_STAGING_REGISTER_FILTER_APPLIED_NO_DB_MUTATIONS')
except Exception:
    for k in reversed(installed):files[k].write_bytes(original[k])
    raise
