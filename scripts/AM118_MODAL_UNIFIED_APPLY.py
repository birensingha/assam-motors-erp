#!/usr/bin/env python3
"""AM118 - correct Edit Labour Billing modal on Staging:
unstarted/estimate-linked audited delete + real mechanic assignment UI.
Read-only check or guarded view-only apply. No edits to ledger/ROT rows on deploy.
"""
from pathlib import Path
import sys,os,hashlib,datetime,shutil,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
    raise SystemExit('Usage: python3 AM118_MODAL_UNIFIED_APPLY.py check|apply STAGING_ROOT')
mode,root=sys.argv[1],Path(sys.argv[2]).resolve()
view=root/'resources/views/job-cards/show.blade.php'
ctrl=root/'app/Http/Controllers/Web/AdminLabourLineAuditController.php'
route=root/'routes/web.php'
for p in [root/'artisan',view,ctrl,route]:
    if not p.is_file():raise SystemExit('STOP_MISSING '+str(p))
v=view.read_text('utf-8','surrogateescape')
backend=ctrl.read_text('utf-8','surrogateescape')
r=route.read_text('utf-8','surrogateescape')
if 'UNSTARTED_ESTIMATE_ROT_CANCEL_AM117' not in backend:
    raise SystemExit('STOP_AM117_BACKEND_NOT_DEPLOYED: run audited AM117 fix first, do not enable Estimate Delete against old controller')
if "name('erp.job-cards.labour.delete-admin')" not in r or "name('erp.job-cards.labour.assign')" not in r:
    raise SystemExit('STOP_REQUIRED_ROUTES_MISSING')
marker='AM118_UNIFIED_LABOUR_MODAL'
if marker in v:
    print('AM118_ALREADY_APPLIED');sys.exit(0)
def one(a,b,label):
    global v
    count=v.count(a);print('ANCHOR',label,count)
    if count!=1:raise SystemExit('STOP_CHANGED_VIEW_'+label)
    v=v.replace(a,b,1)
# Exact FIX92 modal enhancement. Keep all existing audit handlers, CSRF,
# correction reason checks, and fetch logic untouched.
one("var locked=d.am92Locked==='1';",r"""// AM118_UNIFIED_LABOUR_MODAL: estimate origin alone is NOT work history.
  // Keep all backend transactional invoice, WIP, session, stock and audit guards.
  var am118Wip=@json($am90CanEdit ?? false);
  var am118Admin=@json(strtolower(trim((string)(request()->user()->role ?? ''))) === 'admin');
  var am118Blank=function(v){return !v||v==='—'||v==='-'||v==='Not assigned';};
  var am118Ready=am118Wip&&am118Admin&&
      String(d.am92RotStatus||'').toLowerCase()==='unassigned'&&
      ['planned','pending','not_started','not started',''].indexOf(
        String(d.am92Execution||'').toLowerCase())>=0&&
      am118Blank(d.am92SessionId)&&am118Blank(d.am92FirstStart)&&
      am118Blank(d.am92EndTime)&&am118Blank(d.am92RotStart)&&
      am118Blank(d.am92RotEnd)&&String(d.am92Productive||'00:00:00')==='00:00:00';
  var locked=!am118Ready;""",'eligibility')
one("var del=make('button','am92-delete','Delete Labour');","var del=make('button','am92-delete','Delete ROT');",'label')
one("del.title=locked?'Assigned/started ROT: request reversal instead':'Admin-only delete unstarted Labour';","del.title=locked?'Only Admin may cancel an unassigned, never-started ROT on a WIP, invoice-free Job Card':'Admin audited deletion of unstarted ROT';",'tooltip')
one("  section.appendChild(make('p','am92-audit-hint',locked?\n   'This ROT is assigned, executed or estimate-linked. Its timing and mechanic history cannot be erased. Submit an audited reversal request instead.':\n   'Unstarted direct Labour can be deleted by Admin after reason and tax-total verification. This action cannot be undone.'));", """  section.appendChild(make('p','am92-audit-hint',locked?
   'Assigned/started, invoice-locked, or otherwise ineligible ROT. Use an authorised reversal where needed.':
   'Unassigned and never-started ROT: Admin may delete with a 12–500 character reason. The approved Estimate remains unchanged.'));""",'hint')
# Add mechanic form before existing finance form; never nest forms or bypass
# existing server-side attendance, timing/active work, reason and role guards.
anchor="  box.insertBefore(details,form);"
mechanic=r"""
  // AM118 modal mechanic control mirrors actual Assignment / Change Mechanic
  // route + original staff choices from THIS ROT card.
  var srcForm=card.querySelector('form[action*="/assign-mechanic"], form[action*="/change-mechanic"]');
  if(srcForm){
    var original=srcForm.querySelector('select[name="staff_id"],select[name="mechanic_id"]');
    if(original && original.options && original.options.length){
      var mech=make('section','am118-mechanic-control');
      mech.appendChild(make('strong',null,'Mechanic Assignment / Change'));
      var mForm=document.createElement('form');
      mForm.method=srcForm.method||'post';mForm.action=srcForm.action;
      mForm.className='am118-mechanic-form';
      Array.from(srcForm.querySelectorAll('input[type="hidden"]')).forEach(function(field){
        var copy=field.cloneNode(true);copy.removeAttribute('id');mForm.appendChild(copy);
      });
      var search=make('input','am118-mechanic-search');
      search.type='search';search.placeholder='Type mechanic name / staff code';
      search.autocomplete='off';search.setAttribute('aria-label','Search mechanic');
      var select=original.cloneNode(true);
      select.removeAttribute('id');select.removeAttribute('style');
      select.removeAttribute('aria-hidden');select.removeAttribute('tabindex');
      select.className='am118-mechanic-select';
      select.disabled=false;select.required=true;
      var opts=Array.from(original.options).map(function(o){
        return {value:o.value,text:o.textContent,disabled:o.disabled};
      });
      function populate(q){
        var ql=(q||'').trim().toLowerCase();
        select.replaceChildren();
        var empty=new Option('Select mechanic...', '');
        select.appendChild(empty);
        var matches=opts.filter(function(o){
          return o.value && !o.disabled && (!ql || o.text.toLowerCase().includes(ql));
        });
        matches.forEach(function(o){select.appendChild(new Option(o.text,o.value));});
        select.value='';
        if(!matches.length)empty.textContent='No matching mechanic';
      }
      populate('');
      search.addEventListener('input',function(){populate(search.value);});
      select.addEventListener('change',function(){
        if(select.value)search.value=select.options[select.selectedIndex].textContent.trim();
      });
      mForm.appendChild(search);mForm.appendChild(select);
      // Preserve any extra required fields on the original Change form,
      // such as a mandatory change reason. No guessing hidden server fields.
      Array.from(srcForm.querySelectorAll('input:not([type="hidden"]),textarea,select')).forEach(function(extra){
        if(extra===original || ['submit','button','reset'].includes(extra.type))return;
        if(!extra.name || extra.type==='search')return;
        var label=make('label','am118-extra-field',extra.name.replaceAll('_',' '));
        var copy=extra.cloneNode(true);
        copy.removeAttribute('id');copy.removeAttribute('style');
        copy.removeAttribute('aria-hidden');copy.className='am118-field';
        label.appendChild(copy);mForm.appendChild(label);
      });
      var save=make('button','am118-mechanic-submit',
        srcForm.action.indexOf('change-mechanic')>=0?'Change Mechanic':'Assign Mechanic');
      save.type='submit';mForm.appendChild(save);
      mForm.addEventListener('submit',function(e){
        if(!select.value){e.preventDefault();select.reportValidity();return;}
        save.disabled=true;
      });
      mech.appendChild(mForm);
      mech.appendChild(make('small','am118-mechanic-note',
        'Uses existing secured mechanic route; current Check-In and active ROT rules still apply.'));
      box.insertBefore(mech,form);
    }
  }
"""
one(anchor,anchor+mechanic,'mechanic_form')
# Keep CSS only appended; do not hide the old modal or duplicate P55.
css=r"""
{{-- AM118_UNIFIED_LABOUR_MODAL: only the proven FIX92 modal is enhanced. --}}
<style id="AM118_UNIFIED_LABOUR_MODAL">
.am90-dialog .am118-mechanic-control{border:1px solid #bbd1e8;border-radius:8px;
  padding:10px 12px;margin:10px 0;background:#f7fbff;font-size:11px}
.am90-dialog .am118-mechanic-control>strong{display:block;margin-bottom:8px;color:#1d4d7a;font-size:12px}
.am90-dialog .am118-mechanic-form{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.am90-dialog .am118-mechanic-search,.am90-dialog .am118-mechanic-select,
.am90-dialog .am118-mechanic-form .am118-field{min-height:35px!important;
  border:1px solid #a9c3df!important;border-radius:6px!important;padding:6px 8px!important;
  background:#fff!important;color:#193858!important;font-size:12px!important;box-sizing:border-box}
.am90-dialog .am118-mechanic-search{flex:1 1 200px;min-width:170px}
.am90-dialog .am118-mechanic-select{flex:1 1 220px;min-width:170px}
.am90-dialog .am118-mechanic-submit{background:#0c336b;color:#fff;border:0;
  border-radius:6px;min-height:35px;padding:7px 14px;cursor:pointer;font-weight:750}
.am90-dialog .am118-extra-field{display:flex;gap:6px;align-items:center}
.am90-dialog .am118-mechanic-note{display:block;margin-top:8px;color:#58708b}
.am90-dialog .am92-delete:not(:disabled){background:#b3262d!important;color:white!important}
</style>
"""
v+='\n'+css
print('AM118_VIEW_SHA256_BEFORE='+hashlib.sha256(view.read_bytes()).hexdigest())
if mode=='check':
    print('AM118_PREFLIGHT_PASS_NO_WRITES');sys.exit(0)
backup_dir=root/'storage/app/am-backups'
backup_dir.mkdir(parents=True,exist_ok=True)
backup=backup_dir/('show_AM118_'+datetime.datetime.now().strftime('%Y%m%d_%H%M%S')+'.blade.php')
shutil.copy2(view,backup)
fd,tmp=tempfile.mkstemp(prefix='.am118_',dir=view.parent)
try:
    with os.fdopen(fd,'w',encoding='utf-8',errors='surrogateescape') as f:f.write(v)
    os.chmod(tmp,view.stat().st_mode&0o777)
    os.replace(tmp,view)
finally:
    if os.path.exists(tmp):os.unlink(tmp)
print('AM118_APPLIED_VIEW_ONLY')
print('AM118_BACKUP='+str(backup))
