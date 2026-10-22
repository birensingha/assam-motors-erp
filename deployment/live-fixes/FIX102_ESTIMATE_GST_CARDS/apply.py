#!/usr/bin/env python3
"""FIX102: GST-inclusive Parts/Labour summary badges on estimate detail page.

Derived only from SAVED line_total values already displayed in the estimate
item table and print preview. Do not change database amounts, invoicing,
approval, status or print. Each item amount is converted to integer paise
before sum. Existing Grand Total and Round Off are unchanged.
"""
from pathlib import Path
import hashlib,os,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
 raise SystemExit('Usage: apply.py check|apply LARAVEL_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file()
p=root/'resources/views/estimates/show.blade.php'
raw=p.read_bytes();sha=hashlib.sha256(raw).hexdigest()
s=raw.decode('utf8','surrogateescape')
print('FIX102_VIEW_SHA256',sha)
marker='FIX102_GST_INCLUDED_ESTIMATE_SUMMARY'
if marker in s:
 if s.count(marker)==1:
  print('FIX102_ALREADY_APPLIED');sys.exit(0)
 raise SystemExit('FIX102_PARTIALLY_APPLIED_ABORT')
expected='c78dd0bb23bd61dc544e2889708b27d31cec576c2f117713b94f6e31679df72b'
if sha!=expected:raise SystemExit('FIX102_UNEXPECTED_VIEW_CHANGE_ABORT')

orig_php='''$sentToJob=(bool)$estimate->converted_to_job_at;
@endphp'''
new_php='''$sentToJob=(bool)$estimate->converted_to_job_at;
// FIX102_GST_INCLUDED_ESTIMATE_SUMMARY: line_total is already tax-inclusive.
// Use saved per-line totals exactly as the estimate display/print do.
// Integer paise avoid cumulative float-rounding differences.
$am102PartsGrossPaise=0;
$am102LabourGrossPaise=0;
foreach($items as $am102Item){
    $am102LinePaise=(int)round(((float)$am102Item->line_total)*100);
    $am102Type=strtoupper(trim((string)$am102Item->item_type));
    if(in_array($am102Type,['PART','PARTS'],true)){
        $am102PartsGrossPaise+=$am102LinePaise;
    }else{
        $am102LabourGrossPaise+=$am102LinePaise;
    }
}
@endphp'''
if s.count(orig_php)!=1:raise SystemExit('FIX102_INITIAL_PHP_ANCHOR_CHANGED')
s=s.replace(orig_php,new_php,1)
old_cards='''  <div class="metric"><span>Parts</span><strong>₹{{ number_format((float)$estimate->parts_total,2) }}</strong></div>
  <div class="metric"><span>Labour / ROT / OSL</span><strong>₹{{ number_format((float)$estimate->labour_total,2) }}</strong></div>'''
# Support exact codepoint from mounted staging PHP (₹ may be user-facing literal).
if s.count(old_cards)!=1:raise SystemExit('FIX102_SUMMARY_CARDS_CHANGED')
new_cards='''  <div class="metric"><span>Parts (Incl. GST)</span><strong>₹{{ number_format($am102PartsGrossPaise/100,2) }}</strong></div>
  <div class="metric"><span>Labour / ROT / OSL (Incl. GST)</span><strong>₹{{ number_format($am102LabourGrossPaise/100,2) }}</strong></div>'''
s=s.replace(old_cards,new_cards,1)
for token in ['FIX102_GST_INCLUDED_ESTIMATE_SUMMARY','Parts (Incl. GST)',
              'Labour / ROT / OSL (Incl. GST)','number_format($am102PartsGrossPaise/100,2)',
              'number_format($am102LabourGrossPaise/100,2)',
              'number_format((float)$estimate->grand_total,2)',
              'number_format((float)$i->line_total,2)',
              "route('erp.estimates.print',$estimate->id)",
              "route('erp.estimates.send-to-job',$estimate->id)"]:
 if token not in s:raise SystemExit('FIX102_CORE_OUTPUT_MISSING '+token)
if s.count(marker)!=1:raise SystemExit('FIX102_DUPLICATE_MARKER')
if mode=='check':
 if os.environ.get('FIX102_PREVIEW_PATH'):
  Path(os.environ['FIX102_PREVIEW_PATH']).write_text(s,encoding='utf8',errors='surrogateescape')
  print('FIX102_PREVIEW_TEMP_FILE_ONLY')
 print('FIX102_PREFLIGHT_PASS_NO_WRITES');sys.exit(0)
fd,tmp=tempfile.mkstemp(prefix='.fix102_',dir=p.parent)
try:
 with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
 os.chmod(tmp,p.stat().st_mode&0o777)
 os.replace(tmp,p)
finally:
 if os.path.exists(tmp):os.unlink(tmp)
print('FIX102_GST_INCLUDED_SUMMARY_VIEW_ONLY_APPLIED_NO_DB_WRITES')
