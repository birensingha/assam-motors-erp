#!/usr/bin/env python3
"""FIX71 — existing JC Summary has no visible reversal card; remove stale comment.
Normalize Return-to-JC consent checkbox to compact accessible dimensions.
Strictly presentation-only; no invoices, CN, routes or DB changes.
"""
from pathlib import Path
import sys,os,tempfile,hashlib
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
 raise SystemExit('Usage: apply.py check|apply APP_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file()
show=root/'resources/views/job-cards/show.blade.php'
correction=root/'resources/views/job-cards/credit-correction.blade.php'
expected={
  'show':'f8d6df5f4bc6d787337081e3562b4de65b995823f3f8a34b0db1b098e24780ab',
  'correction':'30c1e4f091d2bc505fbd63c98c2549d801b9c2551f7aa72105e757666ce70850',
}
raw={k:p.read_bytes() for k,p in [('show',show),('correction',correction)]}
text={k:val.decode('utf8',errors='surrogateescape') for k,val in raw.items()}
already='FIX71_COMPACT_CONSENT_CHECKBOX' in text['correction']
if already and 'FIX71_REMOVED_REVERSAL_COMMENT' in text['show']:
 print('FIX71_ALREADY_APPLIED');sys.exit(0)
if already or 'FIX71_REMOVED_REVERSAL_COMMENT' in text['show']:
 raise SystemExit('FIX71_PARTIAL_DEPLOY_BLOCKED')
for k,v in raw.items():
 actual=hashlib.sha256(v).hexdigest();print('FIX71_SHA',k,actual)
 if actual!=expected[k]:raise SystemExit('FIX71_SOURCE_CHANGED_'+k)
oldComment="""{{-- FIX69: Advanced Reversal Centre removed from customer Job Card Summary.
     Existing audited finance/reversal backend records and routes are unchanged. --}}"""
newComment="""{{-- FIX71_REMOVED_REVERSAL_COMMENT: no bottom reversal controls on JC Summary.
     Existing finance/reversal endpoints and audit records remain unchanged. --}}"""
if text['show'].count(oldComment)!=1:raise SystemExit('FIX71_OLD_COMMENT_NOT_UNIQUE')
if 'Advanced Reversal Centre →' in text['show'] or 'Job Card Reversal / Cancel Centre' in text['show']:
 raise SystemExit('FIX71_VISIBLE_REVERSAL_STILL_PRESENT')
text['show']=text['show'].replace(oldComment,newComment,1)
oldCss='.am63-consent input{margin-top:3px}'
newCss="""/* FIX71_COMPACT_CONSENT_CHECKBOX: override ERP-wide full-width input and block label rules. */
.am63-dialog label.am63-consent{
  display:flex!important;align-items:flex-start!important;justify-content:flex-start!important;
  flex-direction:row!important;gap:10px!important;width:100%;margin:12px 0 16px!important;
  font-size:12px;line-height:1.5;font-weight:600;cursor:pointer;text-align:left;
}
.am63-dialog label.am63-consent input[type="checkbox"]{
  display:inline-block!important;appearance:auto!important;-webkit-appearance:checkbox!important;
  position:static!important;float:none!important;
  width:17px!important;height:17px!important;min-width:17px!important;min-height:17px!important;
  max-width:17px!important;max-height:17px!important;flex:0 0 17px!important;
  padding:0!important;margin:1px 0 0!important;transform:none!important;
  box-shadow:none!important;border-radius:3px!important;vertical-align:top!important;
  accent-color:#1e62b8;cursor:pointer;
}
.am63-dialog label.am63-consent span{
  display:block;flex:1 1 auto;min-width:0;line-height:1.55;font-weight:600;
}
.am63-dialog label.am63-consent input[type="checkbox"]:focus-visible{
  outline:2px solid #2563eb!important;outline-offset:3px!important;
}"""
if text['correction'].count(oldCss)!=1:raise SystemExit('FIX71_CHECKBOX_CSS_NOT_UNIQUE')
text['correction']=text['correction'].replace(oldCss,newCss,1)
assert text['correction'].count('name="confirmed_finance"')==1
assert text['correction'].count('<input type="checkbox" name="confirmed_finance"')==1
assert text['correction'].count('id="am63-return-modal"')==1
assert text['correction'].count('name="reason"')==1
assert text['show'].count("route('erp.job-cards.credit-correction',$job->id)")==1
assert 'Advanced Reversal Centre' not in text['show']
if mode=='check':
 print('FIX71_PREFLIGHT_PASS_UI_ONLY');sys.exit(0)
def save(path,value):
 fd,tmp=tempfile.mkstemp(prefix='.fix71_',dir=path.parent)
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(value)
  os.chmod(tmp,path.stat().st_mode&0o777);os.replace(tmp,path)
 finally:
  if os.path.exists(tmp):os.unlink(tmp)
save(show,text['show'])
save(correction,text['correction'])
print('FIX71_APPLIED_COMPACT_CHECKBOX_AND_REMOVED_REVERSAL_COMMENT_NO_DB_WRITES')
