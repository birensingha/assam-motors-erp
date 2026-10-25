#!/usr/bin/env python3
"""AM137 STAGING: OSL Labour Edit dialog shows Delete plus linked supplier
Purchase editor, vendor ledger, and single OSL reference Payment Voucher.
Presentation only; existing backend finance guards preserved and no DB writes.
Usage: python3 apply.py check|apply STAGING_ROOT
"""
from pathlib import Path
import sys,os,tempfile,hashlib,datetime

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
    raise SystemExit('AM137_USAGE check|apply STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('AM137_BAD_STAGING_ROOT')
view=root/'resources/views/job-cards/show.blade.php'
routes=root/'routes/web.php'
if not view.is_file() or view.is_symlink() or not routes.is_file():
    raise SystemExit('AM137_REQUIRED_FILES_MISSING')
raw=view.read_bytes()
source=raw.decode('utf8','surrogateescape')
routeSource=routes.read_text(errors='replace')
marker='AM137_OSL_EDIT_PURCHASE_LINKS'
if marker in source:
    if source.count(marker)==1 and 'data-am137-link-line' in source:
        print('AM137_ALREADY_INSTALLED')
        sys.exit(0)
    raise SystemExit('AM137_INCOMPLETE_PATCH')
for check in ['id="FIX91_UNIFIED_OSL_ADMIN"','id="FIX93_OSL_MODAL_TOOLS"',
              'id="am129-osl-delete-script"','id="am90-table-body"',
              "data-am91-osl-edit", "id=\"am93-osl-tools-template\""]:
    if check not in source:raise SystemExit('AM137_MISSING_UI_ANCHOR: '+check)
for check in ('erp.fix48.osl-edit','erp.vendors.ledger','erp.payments.create','erp.job-cards.osl.admin-delete'):
    if check not in routeSource:raise SystemExit('AM137_MISSING_NAMED_ROUTE: '+check)
anchor='<script id="am93-osl-row-sort">'
if source.count(anchor)!=1:raise SystemExit('AM137_AMBIGUOUS_INSERTION')

# Template is rendered by Laravel using CURRENT matching purchase IDs. Only
# active purchase lines linked by FKs are offered; no made-up reference numbers.
# Purchase correction and ledger posting remain in their existing controllers.
addition=r'''
{{-- AM137_OSL_EDIT_PURCHASE_LINKS: linked supplier purchase and ledger under
     the existing OSL Labour EDIT popup; no new financial write endpoint. --}}
<template id="am137-osl-reference-template">
  @foreach(($am83OslPurchases ?? collect()) as $am137Purchase)
    @if((int)($am137Purchase->labour_line_id??0)>0
         && strtoupper(trim((string)($am137Purchase->record_status??'ACTIVE')))==='ACTIVE'
         && empty($am137Purchase->deleted_at))
      <div class="am137-linked-purchase" data-am137-link-line="{{ (int)$am137Purchase->labour_line_id }}">
        <div class="am137-linked-top">
          <strong>OSL PURCHASE · {{ $am137Purchase->purchase_no ?: 'Purchase #'.$am137Purchase->id }}</strong>
          <span>Vendor Payable: ₹{{ number_format((float)($am137Purchase->total_amount??0),2) }}</span>
        </div>
        <div class="am137-linked-meta">
          <span>Vendor: {{ $am137Purchase->supplier_name ?: 'Vendor Master' }}</span>
          <span>Supplier Bill: {{ $am137Purchase->supplier_invoice ?: 'Not recorded' }}</span>
        </div>
        <div class="am137-linked-actions">
          <a href="{{ route('erp.fix48.osl-edit',(int)$am137Purchase->id) }}" target="_blank" rel="noopener noreferrer">Edit OSL Purchase</a>
          @if((int)($am137Purchase->vendor_id??0)>0)
            <a href="{{ route('erp.vendors.ledger',(int)$am137Purchase->vendor_id) }}" target="_blank" rel="noopener noreferrer">Vendor Ledger</a>
          @endif
          <a href="{{ route('erp.payments.create',['source'=>'OSL_JOB','id'=>(int)$am137Purchase->id]) }}" target="_blank" rel="noopener noreferrer">Payment Against Reference</a>
        </div>
      </div>
    @endif
  @endforeach
</template>
<style id="am137-osl-style">
/* Owner selects Delete FROM Edit popup rather than the external per-row action. */
.jc-labour-block button.am129-osl-delete{display:none!important}
.am91-osl-dialog .am137-linked-section{margin:14px 0 0;padding:12px;border:1px solid #b9d4e8;
 background:#f7fbff;border-radius:10px}
.am91-osl-dialog .am137-linked-section h3{margin:0 0 8px;font-size:13px;color:#144976}
.am91-osl-dialog .am137-linked-purchase{border:1px solid #d4e1ed;background:#fff;border-radius:8px;padding:10px;margin:8px 0}
.am91-osl-dialog .am137-linked-top,.am91-osl-dialog .am137-linked-meta{
 display:flex;justify-content:space-between;gap:9px;flex-wrap:wrap;font-size:11px;color:#345773}
.am91-osl-dialog .am137-linked-top strong{font-size:12px;color:#174c76}
.am91-osl-dialog .am137-linked-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.am91-osl-dialog .am137-linked-actions a{padding:8px 10px;font-size:11px;text-decoration:none;font-weight:800;border:1px solid #a8c5de;border-radius:6px;background:#eaf5ff;color:#154b78}
.am91-osl-dialog .am137-linked-actions a:focus-visible{outline:2px solid #154b78;outline-offset:2px}
.am91-osl-dialog .am137-message{font-size:11px;color:#44647f;margin:5px 0 0}
.am91-osl-dialog button.am91-delete{background:#b42323!important;color:#fff!important;
 border-radius:6px!important;padding:8px 11px!important}
@media(max-width:600px){.am91-osl-dialog .am137-linked-actions a{width:100%;text-align:center}}
</style>
<script id="am137-osl-edit-actions">
(function(){
 'use strict';
 function init(){
  var tbody=document.getElementById('am90-table-body');
  var template=document.getElementById('am137-osl-reference-template');
  if(!tbody||!template)return;
  // FIX91 opens the Edit modal synchronously from its body click delegate.
  // This document listener observes only user-initiated Edit button clicks.
  document.addEventListener('click',function(event){
   var edit=event.target.closest('button[data-am91-osl-edit]');
   if(!edit)return;
   var row=edit.closest('tr[data-am91-osl-id]');
   if(!row)return;
   var id=String(row.dataset.am91OslId||'');
   var dialog=document.querySelector('dialog.am91-osl-dialog[open]');
   if(!dialog||!id||dialog.querySelector('.am137-linked-section'))return;
   var inner=dialog.querySelector('.am91-osl-inner');
   if(!inner)return;
   var panel=document.createElement('section');
   panel.className='am137-linked-section';
   var header=document.createElement('h3');
   header.textContent='Linked OSL Purchase · Vendor Ledger';
   panel.appendChild(header);
   var matches=Array.from(template.content.querySelectorAll('[data-am137-link-line]')).filter(
     function(node){return node.dataset.am137LinkLine===id;}
   );
   if(matches.length){
    matches.forEach(function(node){panel.appendChild(document.importNode(node,true));});
   }else{
    var msg=document.createElement('p');msg.className='am137-message';
    msg.textContent='No active supplier purchase linked to this OSL billing line. A customer Labour line is not a Vendor Payable until an OSL Purchase is recorded.';
    panel.appendChild(msg);
    var actions=document.createElement('div');actions.className='am137-linked-actions';
    var create=document.createElement('a');
    create.href=@json(route('erp.purchases.osl.create'));
    create.target='_blank';create.rel='noopener noreferrer';
    create.textContent='+ New OSL Purchase';
    actions.appendChild(create);panel.appendChild(actions);
   }
   var note=document.createElement('p');note.className='am137-message';
   note.textContent='Supplier cost and customer OSL billing are separate. Payments use the exact active OSL Purchase reference. Already-paid supplier bills remain subject to original finance correction rules.';
   panel.appendChild(note);
   inner.appendChild(panel);
   var del=dialog.querySelector('button.am91-delete');
   if(del && !del.hidden){
     del.textContent='Delete OSL Labour';
     del.title='Admin reason required. Estimate or supplier-purchase linked rows may require reversal first.';
   }
  },false);
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});
 else init();
})();
</script>
'''
if source.count(marker)!=0 or 'AM137_OSL_EDIT_PURCHASE_LINKS' not in addition:
    raise SystemExit('AM137_MARKER_INVALID')
changed=source.replace(anchor,addition+'\n'+anchor,1)
if changed.count(marker)!=1 or changed.count('data-am137-link-line')<2:
    raise SystemExit('AM137_BAD_PREVIEW')
print('AM137_SOURCE_SHA='+hashlib.sha256(raw).hexdigest())
print('AM137_CANDIDATE_SHA='+hashlib.sha256(changed.encode('utf8','surrogateescape')).hexdigest())
if mode=='check':
    output=os.environ.get('AM137_PREVIEW')
    if output:Path(output).write_bytes(changed.encode('utf8','surrogateescape'))
    print('AM137_PREFLIGHT_ONLY_PASS')
    sys.exit(0)
if view.read_bytes()!=raw:raise SystemExit('AM137_STAGING_SOURCE_CHANGED')
backupdir=root/'storage/app'/('am137_osl_modal_backup_'+datetime.datetime.now().strftime('%Y%m%d_%H%M%S')+'_'+str(os.getpid()))
backupdir.mkdir(mode=0o700,parents=True)
backup=backupdir/'show.blade.php'
backup.write_bytes(raw)
os.chmod(backup,0o600)
if backup.read_bytes()!=raw:raise SystemExit('AM137_BACKUP_MISMATCH')
fd,tmp=tempfile.mkstemp(dir=view.parent,prefix='.am137_')
try:
 with os.fdopen(fd,'wb') as f:f.write(changed.encode('utf8','surrogateescape'))
 os.chmod(tmp,view.stat().st_mode & 0o777)
 if view.read_bytes()!=raw:raise SystemExit('AM137_SOURCE_CHANGED_BEFORE_WRITE')
 os.replace(tmp,view)
finally:
 if os.path.exists(tmp):os.unlink(tmp)
print('AM137_BACKUP='+str(backup))
print('AM137_STAGING_UI_UPDATED_NO_DB_MUTATION')
