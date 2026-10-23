#!/usr/bin/env python3
"""FIX116: show existing P55 audited alteration inside each ROT Assignment card.
STAGING ONLY, Blade UI ONLY. No routes/controllers/ROT rows/estimates modified.
Usage: python3 .../UNIFIED_ROT_PANEL_APPLY_STAGING.py check|apply [LARAVEL_ROOT]
"""
import sys, os, hashlib, tempfile, shutil, datetime
from pathlib import Path

if len(sys.argv) not in (2,3) or sys.argv[1] not in ("check","apply"):
    raise SystemExit("Usage: UNIFIED_ROT_PANEL_APPLY_STAGING.py check|apply [LARAVEL_ROOT]")
mode=sys.argv[1]
root=Path(sys.argv[2] if len(sys.argv)==3 else
    Path.home()/"domains/assammotors.com/assam-erp-staging").resolve()
view=root/"resources/views/job-cards/rot-alteration.blade.php"
show=root/"resources/views/job-cards/show.blade.php"
routes=root/"routes/web.php"
ctrl=root/"app/Http/Controllers/Web/JobCardRotAlterationController.php"
for p in (root/"artisan",view,show,routes,ctrl):
    if not p.is_file(): raise SystemExit("STOP_MISSING_REQUIRED "+str(p))
raw=view.read_bytes()
src=raw.decode("utf-8","surrogateescape")
show_src=show.read_text(encoding="utf-8",errors="surrogateescape")
route_src=routes.read_text(encoding="utf-8",errors="surrogateescape")
ctrl_src=ctrl.read_text(encoding="utf-8",errors="surrogateescape")
marker='id="AM116_UNIFIED_ROT_UI"'
print("AM116_SOURCE_SHA256="+hashlib.sha256(raw).hexdigest())
if marker in src:
    if src.count(marker)==1:
        print("AM116_ALREADY_APPLIED"); sys.exit(0)
    raise SystemExit("STOP_DUPLICATE_PATCH_MARKER")
for desc,code,fragment in (
 ("P55_PANEL",src,'id="p55-rot-edit"'),
 ("P55_FORMS",src,'data-p55-form'),
 ("P55_ROT_CLASSES",src,'p55-rot'),
 ("ASSIGNMENT_ROWS",show_src,'data-am90-labour-id'),
 ("ASSIGNMENT_CONTAINER",show_src,'class="rot-list"'),
 ("ASSIGN_ROUTE",route_src,"name('erp.job-cards.labour.assign')"),
 ("AUDITED_ALTER_ROUTE",route_src,"name('erp.job-cards.labour.alter')"),
 ("SERVER_VALIDATION",ctrl_src,"'alteration_reason'"),
 ("AUDIT_NOTE",ctrl_src,"'admin_note'")
):
    if fragment not in code: raise SystemExit("STOP_MISSING_ANCHOR_"+desc)
# Do not relocate forms on the server. The original existing P55 markup and JS
# remain unchanged, but are moved as ONE subtree by the browser, preserving
# existing event delegation, session values, CSRF fields and audit enforcement.
addition=r'''
{{-- AM116: retire the duplicate standalone P55 area on Job Card pages by
     presenting its SAME forms inside a selected Assignment card's Alter button.
     No backend, routes, authorization, invoice locks, or audit changes. --}}
<style id="AM116_UNIFIED_ROT_UI">
.jc-labour-block .rot-actions .am116-edit-btn,
.jc-labour-block .rot-card .am116-edit-btn{
  display:inline-flex;align-items:center;justify-content:center;
  min-height:28px;border:1px solid #a6c6df;border-radius:5px;
  color:#124c79;background:#eaf4ff;font-size:11px;font-weight:750;
  padding:5px 10px;cursor:pointer;margin:3px 4px 3px 0;
}
.jc-labour-block .rot-card .am116-edit-btn:hover{background:#d8ebff}
dialog.am116-dialog{position:fixed;inset:0;width:min(1020px,calc(100vw - 26px));
  max-height:calc(100dvh - 32px);margin:auto;padding:0;
  background:#fff;border:1px solid #9dbbda;border-radius:10px;
  box-shadow:0 16px 56px rgba(13,36,64,.25);overflow:auto;color:#183450}
dialog.am116-dialog::backdrop{background:rgba(12,29,52,.63)}
.am116-dialog .am116-bar{position:sticky;top:0;z-index:10;display:flex;
  align-items:center;justify-content:space-between;gap:10px;padding:10px 13px;
  background:#e9f2fc;border-bottom:1px solid #c7d8eb}
.am116-dialog .am116-title{font-size:14px;font-weight:800;overflow-wrap:anywhere}
.am116-dialog .am116-close{border:1px solid #8aa4c6;border-radius:6px;
  background:#fff;color:#193c5c;padding:5px 10px;font-size:12px;cursor:pointer}
.am116-dialog #p55-rot-edit{margin:0!important;border:0!important;
  border-radius:0!important;box-shadow:none!important;max-width:100%!important}
.am116-dialog #p55-rot-edit .p55-head{display:none!important}
.am116-dialog .p55-rot{max-width:100%!important;box-sizing:border-box!important}
@media(max-width:720px){
 .am116-dialog .am116-bar{padding:8px 10px}
 dialog.am116-dialog{width:calc(100vw - 12px);max-height:calc(100dvh - 12px)}
}
</style>
<script>
(function(){
'use strict';
function init(){
  var panel=document.getElementById('p55-rot-edit');
  var container=document.querySelector('.jc-labour-block .rot-list');
  if(!panel||!container||document.getElementById('am116-rot-dialog'))return;
  if(!window.HTMLDialogElement||typeof document.createElement('dialog').showModal!=='function')return;
  var forms=Array.from(panel.querySelectorAll('form[data-p55-form]'));
  var cards=Array.from(container.querySelectorAll('.rot-card[data-am90-labour-id]'));
  if(!forms.length||!cards.length)return;
  var cardById=new Map();
  cards.forEach(function(card){cardById.set(String(card.dataset.am90LabourId||''),card);});
  var records=[],foundIds=new Set();
  for(var i=0;i<forms.length;i++){
    var form=forms[i],row=form.closest('.p55-rot');
    var action=form.getAttribute('action')||'';
    var match=action.match(/\/job-cards\/[0-9]+\/labour\/([0-9]+)\/alteration(?:[/?#]|$)/);
    var id=match&&match[1];
    // Abort the display-only consolidation if a P55 line cannot map exactly:
    // do not hide any existing control or silently orphan a labour line.
    if(!row||!id||!cardById.has(id)||foundIds.has(id)){
      console.warn('AM116: incomplete alteration mapping; original panel kept.');
      return;
    }
    foundIds.add(id); records.push({id:id,row:row,card:cardById.get(id)});
  }
  // Entire panel moves as one DOM subtree, so P55 delegated JS stays active.
  var dialog=document.createElement('dialog');dialog.id='am116-rot-dialog';
  dialog.className='am116-dialog';dialog.setAttribute('aria-label','Alter selected ROT');
  var top=document.createElement('div');top.className='am116-bar';
  var title=document.createElement('strong');title.className='am116-title';
  title.textContent='Alter ROT';
  var close=document.createElement('button');close.type='button';
  close.className='am116-close';close.textContent='Close';
  close.addEventListener('click',function(){dialog.close();});
  top.appendChild(title);top.appendChild(close);
  dialog.appendChild(top); dialog.appendChild(panel); document.body.appendChild(dialog);
  // Existing controller validation for WIP, invoice, GST and audit remains authoritative.
  function openRow(id){
    if(dialog.open)return;
    var rec=records.find(function(o){return o.id===id;});
    if(!rec)return;
    records.forEach(function(o){o.row.style.display=o.id===id?'':'none';});
    var rot=(rec.card.querySelector('.rot-code')||{}).textContent||'';
    title.textContent='Alter ROT — '+rot.trim();
    try{dialog.showModal();close.focus();}catch(err){console.warn('AM116 dialog error',err);}
  }
  records.forEach(function(rec){
    var btn=document.createElement('button');btn.type='button';
    btn.className='am116-edit-btn';btn.textContent='✎ Alter ROT';
    btn.setAttribute('aria-label','Alter ROT billing and hours');
    btn.dataset.am116Line=rec.id;
    var actions=rec.card.querySelector('.rot-actions')||rec.card;
    actions.appendChild(btn);
    btn.addEventListener('click',function(){openRow(rec.id);});
  });
  dialog.addEventListener('click',function(e){
    if(e.target===dialog)dialog.close();
  });
  console.info('AM116: P55 edit forms consolidated into ROT Assignment cards; count='+records.length);
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});
else init();
})();
</script>
'''
# Always render controls even if JS can't map; abort without hiding originals.
candidate=src+"\n"+addition
if candidate.count(marker)!=1: raise SystemExit("STOP_INVALID_PATCH_MARKER")
for needed in ['data-p55-form','AM116_UNIFIED_ROT_UI','am116-rot-dialog','alteration','dialog.appendChild(panel)']:
    if needed not in candidate: raise SystemExit("STOP_INVALID_CANDIDATE_"+needed)
print("AM116_CHECKS_PASS: safe display-only one-panel migration")
print("AM116_MODIFIES_ONLY="+str(view.relative_to(root)))
print("AM116_AUDIT_BACKEND_UNCHANGED=YES")
if mode=="check":
    print("AM116_CHECK_ONLY_NO_CHANGES")
    sys.exit(0)
backup_dir=root/"storage/app/am-backups"
backup_dir.mkdir(parents=True,exist_ok=True)
name="rot-alteration_AM116_"+datetime.datetime.now().strftime("%Y%m%d_%H%M%S")+".blade.php"
backup=backup_dir/name
shutil.copy2(view,backup)
fd,temp=tempfile.mkstemp(prefix=".am116_",dir=view.parent)
try:
    with os.fdopen(fd,"w",encoding="utf-8",errors="surrogateescape") as f:f.write(candidate)
    os.chmod(temp,view.stat().st_mode & 0o777)
    os.replace(temp,view)
finally:
    if os.path.exists(temp):os.unlink(temp)
print("AM116_SOURCE_APPLIED_BLADE_ONLY")
print("AM116_BACKUP="+str(backup))
print("AM116_NEXT=php artisan view:clear && php artisan view:cache; then browser check Job Card ROT Alter")
