#!/usr/bin/env python3
"""AM124: expose an explicit Delete button in each eligible direct OSL row.
No business, accounting, schema or controller changes; route/permission/guards
remain FIX91's existing audited deletion endpoint. STAGING ONLY.
check|apply <staging-app-root>
"""
from pathlib import Path
import hashlib,os,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply"):
    raise SystemExit("AM129_USAGE: apply.py check|apply STAGING_ROOT")
mode=sys.argv[1]
root=Path(sys.argv[2]).resolve()
if not (root/"artisan").is_file() or root.name!="assam-erp-staging":
    raise SystemExit("AM129_STOP_INVALID_STAGING_ROOT")
file=root/"resources/views/job-cards/show.blade.php"
route=root/"routes/web.php"
controller=root/"app/Http/Controllers/Web/AdminJobCardOslLineController.php"
if not file.is_file() or file.is_symlink() or not route.is_file() or not controller.is_file():
    raise SystemExit("AM129_STOP_REQUIRED_SOURCE_MISSING")

source=file.read_text(encoding="utf-8",errors="surrogateescape")
marker="AM129_VISIBLE_OSL_DELETE"
if marker in source:
    if source.count(marker)==1 and "data-am124-osl-delete" in source:
        print("AM129_ALREADY_INSTALLED")
        raise SystemExit(0)
    raise SystemExit("AM129_STOP_INCOMPLETE_PATCH")
for required in (
    'id="FIX91_UNIFIED_OSL_ADMIN"',
    'id="FIX93_OSL_MODAL_TOOLS"',
    'data-am91-osl-id',
    'data-am91-osl-edit',
    'id="am90-table-body"',
):
    if required not in source:
        raise SystemExit("AM129_STOP_MISSING_UI_GUARD:"+required)
routes=route.read_text(encoding="utf-8",errors="surrogateescape")
server=controller.read_text(encoding="utf-8",errors="surrogateescape")
for required in ("erp.job-cards.osl.admin-delete","AdminJobCardOslLineController"):
    if required not in routes:
        raise SystemExit("AM129_STOP_MISSING_DELETE_ROUTE")
for required in ("public function delete(", "OSL_DELETE_FIX91", "source_estimate_item_id", "job_card_change_audits"):
    if required not in server:
        raise SystemExit("AM129_STOP_MISSING_BACKEND_SAFEGUARD:"+required)

anchor='<script id="am93-osl-row-sort">'
if source.count(anchor)!=1:
    raise SystemExit("AM129_STOP_AMBIGUOUS_INSERTION_ANCHOR")

addition=r'''
{{-- AM129_VISIBLE_OSL_DELETE: explicit action next to Edit in the WIP Labour+OSL billing grid.
     Uses FIX91's existing Admin audit/validation, never deletes supplier purchases. --}}
<style id="am124-osl-delete-style">
#am90-table-body button.am124-osl-delete{
  margin-left:5px!important;border:1px solid #b91c1c!important;
  background:#fff4f4!important;color:#991b1b!important;
  padding:4px 7px!important;border-radius:5px!important;
  font-size:10px!important;font-weight:750!important;cursor:pointer!important
}
#am90-table-body button.am124-osl-delete:hover{background:#fee2e2!important}
#am90-table-body button.am124-osl-delete:focus-visible{outline:2px solid #b91c1c;outline-offset:2px}
#am90-table-body button.am124-osl-delete[disabled]{
  opacity:.5!important;cursor:not-allowed!important
}
</style>
<script id="am124-osl-delete-script">
(function(){
  'use strict';
  function setup(){
    var tbody=document.getElementById('am90-table-body');
    if(!tbody)return;
    function decorate(){
      tbody.querySelectorAll('tr[data-am91-osl-id]').forEach(function(row){
        var edit=row.querySelector('button[data-am91-osl-edit]');
        if(!edit||row.querySelector('button[data-am124-osl-delete]'))return;
        var cell=edit.closest('td');
        if(!cell)return;
        var b=document.createElement('button');
        b.type='button';
        b.className='am124-osl-delete';
        b.dataset.am124OslDelete='1';
        b.textContent='Delete';
        var estimateSourced=row.dataset.am91Estimate==='1';
        if(estimateSourced){
          b.disabled=true;
          b.title='Estimate-linked OSL requires reversal of approved estimate transfer';
        }else{
          b.title='Delete this direct OSL billing line with mandatory Admin reason';
        }
        cell.appendChild(b);
      });
    }
    tbody.addEventListener('click',function(event){
      var button=event.target.closest('button[data-am124-osl-delete]');
      if(!button||button.disabled)return;
      event.preventDefault();
      var row=button.closest('tr[data-am91-osl-id]');
      var edit=row&&row.querySelector('button[data-am91-osl-edit]');
      if(!edit)return;
      // Existing FIX91 opens the authenticated OSL editor synchronously.
      // Reuse its Delete handler, server route, reason validation and audit.
      edit.click();
      var dialog=document.querySelector('dialog.am91-osl-dialog[open]');
      if(!dialog)return;
      var deleteButton=dialog.querySelector('button.am91-delete');
      if(!deleteButton||deleteButton.hidden)return;
      var header=dialog.querySelector('.am91-dialog-head strong');
      if(header)header.textContent='Delete OSL Billing Line';
      var note=dialog.querySelector('.am91-note');
      if(note)note.textContent='Enter the reason, then click Delete OSL. Linked supplier purchase or invoiced/estimate lines are protected by server checks.';
      var form=dialog.querySelector('form.am91-osl-form');
      if(!form)return;
      form.querySelectorAll('label').forEach(function(label){
        if(!label.querySelector('[name="reason"]'))label.hidden=true;
      });
      var save=form.querySelector('button[type="submit"]');
      if(save)save.hidden=true;
      var reason=form.querySelector('[name="reason"]');
      if(reason){
        reason.placeholder='Reason (required, minimum 5 characters)';
        reason.focus();
      }
    });
    // ROT AJAX and OSL AJAX may replace the table. Re-add only button DOM,
    // not business lines, while retaining the original row's internal ID.
    var observer=new MutationObserver(decorate);
    observer.observe(tbody,{childList:true,subtree:true});
    decorate();
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',setup,{once:true});
  else setup();
})();
</script>
'''
changed=source.replace(anchor,addition+"\n"+anchor,1)
if changed.count(marker)!=1 or changed.count('data-am124-osl-delete')<2:
    raise SystemExit("AM129_STOP_PATCH_VALIDATION")
print("AM129_SOURCE_SHA256="+hashlib.sha256(source.encode("utf-8","surrogateescape")).hexdigest())
print("AM129_PREVIEW_SHA256="+hashlib.sha256(changed.encode("utf-8","surrogateescape")).hexdigest())
if mode=="check":
    preview=os.environ.get("AM129_PREVIEW_OUTPUT")
    if preview:
        Path(preview).write_text(changed,encoding="utf-8",errors="surrogateescape")
    print("AM129_READONLY_PREFLIGHT_PASS")
    raise SystemExit(0)

# Make rollback-compatible verified backup BEFORE replacing view; no DB writes.
if file.read_text(encoding="utf-8",errors="surrogateescape")!=source:
    raise SystemExit("AM129_STOP_CONCURRENT_EDIT")
storage=root/"storage/app"
storage.mkdir(exist_ok=True)
backupdir=storage/("am129_visible_osl_delete_backup_"+__import__("datetime").datetime.now().strftime("%Y%m%d_%H%M%S")+"_"+str(os.getpid()))
backupdir.mkdir(mode=0o700)
backup=backupdir/"show.blade.php"
backup.write_text(source,encoding="utf-8",errors="surrogateescape")
os.chmod(backup,0o600)
if hashlib.sha256(backup.read_bytes()).hexdigest()!=hashlib.sha256(source.encode("utf-8","surrogateescape")).hexdigest():
    raise SystemExit("AM129_STOP_BACKUP_VERIFY")
fd,tmp=tempfile.mkstemp(prefix=".am129_",dir=file.parent)
try:
    with os.fdopen(fd,"w",encoding="utf-8",errors="surrogateescape") as w:w.write(changed)
    os.chmod(tmp,file.stat().st_mode & 0o777)
    if file.read_text(encoding="utf-8",errors="surrogateescape")!=source:
        raise SystemExit("AM129_STOP_CONCURRENT_EDIT")
    os.replace(tmp,file)
finally:
    if os.path.exists(tmp):os.unlink(tmp)
if hashlib.sha256(file.read_bytes()).hexdigest()!=hashlib.sha256(changed.encode("utf-8","surrogateescape")).hexdigest():
    file.write_text(source,encoding="utf-8",errors="surrogateescape")
    raise SystemExit("AM129_STOP_POST_WRITE_HASH_MISMATCH")
print("AM129_APPLIED_BACKUP="+str(backup))
print("AM129_STAGING_UI_ONLY_APPLIED_NO_DATABASE_WRITES")
