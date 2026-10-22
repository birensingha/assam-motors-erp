#!/usr/bin/env python3
"""FIX95 STAGING UI-only: remove duplicate ROT Customer Billing blue panel,
keep authoritative Labour/OSL billing register, compact completed work actions
into ONE horizontal row. No finance controller/route/session/timer changes.
"""
from pathlib import Path
import hashlib,sys,os,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
 raise SystemExit('Usage: apply.py check|apply LARAVEL_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file(), 'FIX95_NOT_LARAVEL_ROOT'
view=root/'resources/views/job-cards/show.blade.php'
raw=view.read_bytes();sha=hashlib.sha256(raw).hexdigest()
s=raw.decode('utf8','surrogateescape')
marker='id="FIX95_ROT_ONE_ROW_NO_BILLING_BOX"'
print('FIX95_SOURCE_SHA256',sha)
if marker in s:
 if s.count(marker)==1 and "@include('job-cards.rot-billing-control')" not in s:
  print('FIX95_ALREADY_APPLIED');sys.exit(0)
 raise SystemExit('FIX95_PARTIAL_PATCH_ABORT')
expected='ea86fb6f15b4ec8b0bf17e858acb442fae1356e10931e4592ab6ce4898447f90'
if sha!=expected:raise SystemExit('FIX95_SOURCE_CHANGED_PREVENT_OVERWRITE')
def one(old,new,label):
 global s
 count=s.count(old)
 print('FIX95_ANCHOR',label,count)
 if count!=1:raise SystemExit('FIX95_ANCHOR_MISMATCH_'+label)
 s=s.replace(old,new,1)

# NO ROT-workflow billing badge/form; billing remains in single Labour + OSL register.
one("""      @include('job-cards.rot-billing-control')""",
"""      {{-- FIX95: ROT Customer Billing box removed from the work-control card.
           Source amounts and Admin billing Edit remain in Labour + OSL Billing Register.
           The underlying ROT billing records/guards are NOT modified. --}}""",
'remove_blue_customer_billing_panel')

# Work actions: no cloned links/forms, no duplicate timers. All original endpoints remain.
one('<div class="rot-actions">',"""<div class="rot-actions {{ $rot && $rot->status==='Completed' ? 'am95-completed-actions' : 'am95-active-actions' }}">""",
    'label_completed_work_actions')

style=r'''
{{-- FIX95_ROT_ONE_ROW_NO_BILLING_BOX: preserve FIX94 timer, remove duplicate billing panel
     and align completed actions: Re-open | Edit ROT Start/End | History. --}}
<style id="FIX95_ROT_ONE_ROW_NO_BILLING_BOX">
/* Remove extra gaps after ROT timer, without touching the accounting register. */
.jc-labour-block .am83-workspace .rot-card{
  padding:7px 10px 6px!important;margin:6px 0 8px!important;
}
.jc-labour-block .rot-card .rot-main{padding-bottom:5px!important}
.jc-labour-block .rot-card .rot-meta{padding:5px 0 3px!important}
.jc-labour-block .rot-card .am-rot-live{
  padding:5px 8px 4px!important;margin:0 0 4px!important
}
/* A single, non-wrapping line for completed ROT work controls. */
.jc-labour-block .rot-card .rot-actions.am95-completed-actions{
  display:flex!important;flex-direction:row!important;
  flex-wrap:nowrap!important;align-items:flex-start!important;
  justify-content:flex-start!important;gap:7px!important;
  width:100%!important;max-width:100%!important;
  margin:3px 0 0!important;padding:0!important;box-sizing:border-box!important;
  overflow:visible!important;min-height:30px!important
}
.jc-labour-block .rot-card .rot-actions.am95-completed-actions>details,
.jc-labour-block .rot-card .rot-actions.am95-completed-actions>a,
.jc-labour-block .rot-card .rot-actions.am95-completed-actions>form,
.jc-labour-block .rot-card .rot-actions.am95-completed-actions>.am-v647-time-editor{
  display:block!important;position:relative!important;
  flex:0 0 auto!important;min-width:0!important;
  max-width:max-content!important;width:auto!important;
  margin:0!important;align-self:flex-start!important
}
.jc-labour-block .rot-card .rot-actions.am95-completed-actions>details>summary,
.jc-labour-block .rot-card .rot-actions.am95-completed-actions>a.btn{
  display:inline-flex!important;align-items:center!important;
  justify-content:center!important;width:auto!important;
  height:29px!important;min-height:29px!important;
  padding:5px 9px!important;white-space:nowrap!important;
  margin:0!important;line-height:1.2!important;
  font-size:10px!important;font-weight:700!important;
  box-sizing:border-box!important
}
/* The V647 time editor is a details/summary; prevent original width:100%
   declarations from pushing its control to another line. */
.jc-labour-block .rot-card .rot-actions.am95-completed-actions .am-v647-time-editor{
  flex:0 0 auto!important;width:max-content!important;
  min-width:0!important;max-width:max-content!important
}
.jc-labour-block .rot-card .rot-actions.am95-completed-actions .am-v647-time-editor>summary{
  display:inline-flex!important;align-items:center!important;
  box-sizing:border-box!important;
  height:29px!important;min-height:29px!important;width:auto!important;
  max-width:100%!important;
  padding:5px 9px!important;white-space:nowrap!important;
  font-size:10px!important;font-weight:700!important;line-height:1.2!important
}
/* When Edit Start/End opens, keep its fields visible without creating a
   second action row or clipping the time-editor form. */
.jc-labour-block .rot-card .rot-actions.am95-completed-actions details[open]{
  z-index:150!important
}
.jc-labour-block .rot-card .rot-actions.am95-completed-actions .am-v647-time-editor[open]>div{
  position:absolute!important;top:calc(100% + 5px)!important;
  left:0!important;z-index:151!important;
  width:min(540px,calc(100vw - 50px))!important;
  max-width:min(540px,calc(100vw - 50px))!important;
  box-sizing:border-box!important;
  border-radius:8px!important;border:1px solid #c5d7e7!important;
  background:#fff!important;box-shadow:0 12px 30px rgba(19,45,70,.18)!important;
  padding:10px!important;overflow:visible!important
}
/* On small screens preserve single row; horizontal scroll instead of stacking.
   Closed action popups may extend outside in desktop to remain readable. */
@media(max-width:620px){
  .jc-labour-block .rot-card .rot-actions.am95-completed-actions{
    overflow-x:auto!important;overscroll-behavior-x:contain!important;
    gap:5px!important;padding-bottom:5px!important
  }
  .jc-labour-block .rot-card .rot-actions.am95-completed-actions>details>summary,
  .jc-labour-block .rot-card .rot-actions.am95-completed-actions>a.btn{
    font-size:9px!important;padding:5px 7px!important
  }
}
</style>
'''
s+='\n'+style
for token in ['id="FIX94_COMPACT_MECHANIC_ROT_CARD"',
              'id="FIX93_OSL_MODAL_TOOLS"','id="FIX92_LABOUR_MECHANICS_DELETE_DIALOG"',
              'id="am90-labour-billing"', 'class="am82-finance-form"',
              'job-cards.rot-time-editor',"route('erp.rot.reopen'","route('erp.rot.history'",
              "route('erp.rot.complete'","route('erp.rot.pause'",
              "route('erp.rot.change-mechanic'",'id="FIX95_ROT_ONE_ROW_NO_BILLING_BOX"']:
 if token not in s:raise SystemExit('FIX95_LOST_CRITICAL_UI_'+token)
if s.count(marker)!=1 or "@include('job-cards.rot-billing-control')" in s:
 raise SystemExit('FIX95_UNEXPECTED_OUTPUT')
if mode=='check':
 if os.environ.get('FIX95_PREVIEW_PATH'):
  Path(os.environ['FIX95_PREVIEW_PATH']).write_text(s,encoding='utf8',errors='surrogateescape')
  print('FIX95_CANDIDATE_WRITTEN_TO_TEMP_ONLY')
 print('FIX95_PREFLIGHT_PASS_NO_WRITES')
 sys.exit(0)
fd,tmp=tempfile.mkstemp(dir=view.parent,prefix='.fix95_')
try:
 with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
 os.chmod(tmp,view.stat().st_mode&0o777)
 os.replace(tmp,view)
finally:
 if os.path.exists(tmp):os.unlink(tmp)
print('FIX95_NO_CUSTOMER_BILLING_BOX_AND_SINGLE_ACTION_ROW_APPLIED_VIEW_ONLY')
