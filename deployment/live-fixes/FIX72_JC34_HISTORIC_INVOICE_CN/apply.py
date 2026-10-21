#!/usr/bin/env python3
"""FIX72: One specifically verified legacy unpaid invoice GST exception on JC34.
Both the financial credit note and WIP reopening continue through original
guarded controllers, outer DB::transaction, duplicate/payment/ledger locks.
NEVER turns off GST verification for another invoice or on re-invoicing.
Also prevents current JC GST lines being shown as original CN tax history.
"""
from pathlib import Path
import sys,os,tempfile,hashlib
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
 raise SystemExit('Usage: apply.py check|apply LARAVEL_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve();assert (root/'artisan').is_file()
controller=root/'app/Http/Controllers/Web/AdminInvoiceCreditNoteController.php'
printview=root/'resources/views/invoices/credit-note.blade.php'
screen=root/'resources/views/job-cards/credit-correction.blade.php'
paths={'controller':controller,'printview':printview,'screen':screen}
sha={
 'controller':'88b204e6514cbf2f408145b26ca21005c723e83dc8b502697552a09cada902ff',
 'printview':'395efe6ae4eee05c04977d51bb7dda08f8ab3445ba355921112f93e0badc3375',
 'screen':'52a231deb173b05bc1c6be36a50fa91582d00e015a6c31d25b8ca6d819515b62',
}
raw={k:p.read_bytes() for k,p in paths.items()}
src={k:b.decode('utf8',errors='surrogateescape') for k,b in raw.items()}
marker='FIX72_JC34_ORIGINAL_INVOICE_AMOUNT_ONLY'
if any(marker in s for s in src.values()):
 if all(marker in s for s in src.values()):
  print('FIX72_ALREADY_APPLIED');sys.exit(0)
 raise SystemExit('FIX72_PARTIAL_OR_DIFFERENT_PATCH_ABORT')
for k,b in raw.items():
 digest=hashlib.sha256(b).hexdigest()
 print('FIX72_SOURCE_SHA',k,digest)
 if digest!=sha[k]:raise SystemExit('FIX72_LIVE_SOURCE_CHANGED_ABORT_'+k)
def exactly(s,old,new,key):
 n=s.count(old)
 print('FIX72_ANCHOR',key,n)
 if n!=1:raise SystemExit('FIX72_AMBIGUOUS_'+key)
 return s.replace(old,new,1)
code=src['controller']
oldGuard="""            if(abs($expected['grand_total']-(float)$inv->grand_total)>0.02 ||
               abs($expected['gst_total']-(float)$inv->gst_total)>0.02)
                throw ValidationException::withMessages(['invoice'=>'Invoice and Job Card lines differ. Finance reconciliation required before credit note.']);"""
newGuard="""            // FIX72_JC34_ORIGINAL_INVOICE_AMOUNT_ONLY:
            // Historical invoice already issued with ZERO GST by legacy software.
            // Permit only THIS verified STAGING test invoice, NEVER blanket-bypass GST.
            $am72Mismatch=abs($expected['grand_total']-(float)$inv->grand_total)>0.02 ||
                          abs($expected['gst_total']-(float)$inv->gst_total)>0.02;
            $am72Cents=static fn($value):(int)=>(int)round((float)$value*100);
            $am72HistoricException=$am72Mismatch
                && $job===34 && $invoice===20
                && (string)$card->job_no==='JC-20261014-00034'
                && (string)$inv->invoice_no==='INV-2026-00020'
                && (int)$inv->job_card_id===34
                && $am72Cents($inv->parts_total??0)===88000
                && $am72Cents($inv->labour_total??0)===45000
                && $am72Cents($inv->subtotal??0)===133000
                && $am72Cents($inv->gst_total??0)===0
                && $am72Cents($inv->round_off??0)===0
                && $am72Cents($inv->grand_total??0)===133000
                && $am72Cents($card->parts_total??0)===88000
                && $am72Cents($card->labour_total??0)===45000
                && $am72Cents($card->gst_total??0)===0
                && $am72Cents($card->grand_total??0)===133000
                && $am72Cents($expected['parts_total'])===88000
                && $am72Cents($expected['labour_total'])===45000
                && $am72Cents($expected['gst_total'])===23940
                && $am72Cents($expected['grand_total'])===156900
                && preg_match('/\\\\b(?:gst|tax)\\\\b/i',(string)$data['reason'])===1;
            if($am72Mismatch && !$am72HistoricException)
                throw ValidationException::withMessages(['invoice'=>'Invoice and Job Card lines differ. Finance reconciliation required before credit note. Historical JC-34 exception requires unchanged invoice values and reason mentioning GST or TAX.']);"""
code=exactly(code,oldGuard,newGuard,'scoped_gst_gate')
oldSnapshot="""            $snapshot=['invoice'=>(array)$inv,'job'=>(array)$card,
                'parts'=>$parts,'labour'=>$labour];"""
newSnapshot="""            // FIX72: current JC lines are NOT a reliable historical invoice-line
            // snapshot in the legacy GST exception case. Original invoice money,
            // including ₹0 GST, is the sole authoritative reversal basis.
            $snapshot=['invoice'=>(array)$inv,'job'=>(array)$card,
                'parts'=>$parts,'labour'=>$labour,
                'historical_invoice_amount_exception'=>$am72HistoricException,
                'line_source'=>$am72HistoricException
                    ?'CURRENT_JC_LINES_NOT_VERIFIED_AS_ORIGINAL'
                    :'CURRENT_JC_AT_CREDIT_TIME',
                'historical_gst_review'=>$am72HistoricException?[
                    'original_issued_gst'=>(float)$inv->gst_total,
                    'original_issued_total'=>(float)$inv->grand_total,
                    'current_line_gst'=>$expected['gst_total'],
                    'current_line_grand_total'=>$expected['grand_total'],
                    'note'=>'Legacy original invoice GST/line conflict. Tax filing review required.'
                ]:null];"""
code=exactly(code,oldSnapshot,newSnapshot,'snapshot_line_truthfulness')
oldAction="""                'action'=>'FULL_INVOICE_CREDIT_NOTE_POST',"""
newAction="""                'action'=>$am72HistoricException?'FULL_CN_JC34_HISTORIC_GST_EXCEPTION':'FULL_INVOICE_CREDIT_NOTE_POST',"""
code=exactly(code,oldAction,newAction,'explicit_exception_audit_action')
oldAfter="""                    'invoice_status'=>'reversed','job_status'=>'closed','credited'=>$inv->grand_total]),"""
newAfter="""                    'invoice_status'=>'reversed','job_status'=>'closed','credited'=>$inv->grand_total,
                    'historical_invoice_gst_exception'=>$am72HistoricException,
                    'original_gst'=>(float)$inv->gst_total,'current_jc_gst'=>(float)$expected['gst_total']]),"""
code=exactly(code,oldAfter,newAfter,'exception_audit_values')
assert code.count('FIX72_JC34_ORIGINAL_INVOICE_AMOUNT_ONLY')==1
src['controller']=code

v=src['printview']
start="""$labour=collect($snapshot['labour']??[]);
@endphp"""
replacement="""$labour=collect($snapshot['labour']??[]);
// FIX72_JC34_ORIGINAL_INVOICE_AMOUNT_ONLY: suppress misleading current GST-rated
// JC lines on historical credit note; only original issued money is credited.
$am72Historical=(bool)($snapshot['historical_invoice_amount_exception']??false);
@endphp"""
v=exactly(v,start,replacement,'print_exception_flag')
head="""<table><thead><tr><th>Part / Labour / OSL</th><th class="num">Qty</th><th class="num">Taxable</th><th class="num">GST %</th><th class="num">GST ₹</th></tr></thead><tbody>"""
newHead="""@if($am72Historical)
<section style="margin-top:16px;padding:12px;background:#fff7e8;border:1px solid #eac67f;color:#664415">
 <strong>HISTORICAL INVOICE GST EXCEPTION — FINANCE REVIEW</strong>
 <p>Original invoice was posted with GST ₹0. Current Job Card service/part GST rates are not verified historical invoice lines and are intentionally NOT reproduced here. This Credit Note reverses only the original issued invoice amounts. Any GST filing correction requires separate authorised tax review.</p>
</section>
<table><thead><tr><th>Original invoice billed component</th><th class="num">Amount originally invoiced</th></tr></thead><tbody>
<tr><td>Parts (historical invoice amount)</td><td class="num">₹{{ number_format((float)($invoice->parts_total??0),2) }}</td></tr>
<tr><td>Labour (historical invoice amount)</td><td class="num">₹{{ number_format((float)($invoice->labour_total??0),2) }}</td></tr>
<tr><td>GST recorded on original invoice (tax treatment under review)</td><td class="num">₹{{ number_format((float)($invoice->gst_total??0),2) }}</td></tr>
<tr><td>Original invoice Round Off</td><td class="num">₹{{ number_format((float)($invoice->round_off??0),2) }}</td></tr>
</tbody></table>
@else
<table><thead><tr><th>Part / Labour / OSL</th><th class="num">Qty</th><th class="num">Taxable</th><th class="num">GST %</th><th class="num">GST ₹</th></tr></thead><tbody>"""
v=exactly(v,head,newHead,'print_do_not_misrepresent_old_gst')
tail="""</tbody></table>
<p>Taxable: ₹{{ number_format((float)$cn->taxable_amount,2) }} · GST: ₹{{ number_format((float)$cn->gst_amount,2) }}</p>"""
newTail="""</tbody></table>
@endif
<p>Taxable: ₹{{ number_format((float)$cn->taxable_amount,2) }} · GST: ₹{{ number_format((float)$cn->gst_amount,2) }}</p>"""
v=exactly(v,tail,newTail,'print_both_branches')
src['printview']=v

ui=src['screen']
oldUI="""       @elseif($am63CanReturn)
        <div class="am63-callout info">"""
newUI="""       @elseif($am63CanReturn)
        {{-- FIX72_JC34_ORIGINAL_INVOICE_AMOUNT_ONLY: visible special-case disclosure. --}}
        @if((int)$card->id===34 && (int)($am63Active->id??0)===20)
        <div class="am63-callout">
          <b>Legacy GST calculation mismatch — JC-34 approved testing only.</b>
          <div>Original issued invoice: ₹1,330 / GST ₹0. Current JC lines recompute a different tax total.
          The audited Return will reverse ONLY original invoice ₹1,330 and its recorded GST ₹0;
          the corrected JC must undergo GST review before issuing a NEW invoice.
          Write GST or TAX in the mandatory reason field. Original invoice and receipt history will be preserved.</div>
        </div>
        @endif
        <div class="am63-callout info">"""
ui=exactly(ui,oldUI,newUI,'ui_historical_tax_disclosure')
src['screen']=ui

if mode=='check':
 print('FIX72_PREFLIGHT_PASS_PRECISE_ORIGINAL_INVOICE_REVERSAL_NO_WRITES');sys.exit(0)
def save(path,s):
 fd,tmp=tempfile.mkstemp(prefix='.fix72_',dir=path.parent)
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
  os.chmod(tmp,path.stat().st_mode&0o777)
  os.replace(tmp,path)
 finally:
  if os.path.exists(tmp):os.unlink(tmp)
for k,p in paths.items():save(p,src[k])
print('FIX72_SCOPED_HISTORIC_GST_EXCEPTION_DEPLOYED_NO_POSTED_DOCUMENTS')
