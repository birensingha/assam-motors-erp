#!/usr/bin/env python3
"""Add HSN/SAC summary ONLY to staging GST invoice's left-hand money panel.
Print Blade only, read-before-write hash guard, atomic backup and no DB writes.
"""
import hashlib
import os
import shutil
import stat
import sys
import time
from pathlib import Path

root=Path(sys.argv[1]).resolve()
p=root/'resources/views/invoices/print.blade.php'
if not (root/'artisan').is_file() or not p.is_file():
    raise SystemExit('SAFE_STOP: wrong app or print path')
source=p.read_text(encoding='utf-8')
checksum=hashlib.sha256(source.encode()).hexdigest()
print('SOURCE_HASH',checksum[:16])
if 'AM_HSN_PANEL_V1' in source:
    print('HSN_PANEL_ALREADY_INSTALLED')
    raise SystemExit(0)
if checksum[:16] != '55d1e693516a4080' or 'AM_INVOICE_ROW_V2' not in source:
    raise SystemExit('SAFE_STOP: invoice template changed; must re-audit first')

style=String = """
/* AM_HSN_PANEL_V1 - compact HSN/SAC summary alongside saved totals */
.hsn-panel{margin-top:10px;background:#fff;border:1px solid #bcd1e5;border-radius:6px;padding:7px 7px 5px;color:#204367}
.hsn-title{font-size:9px;font-weight:800;letter-spacing:.02em;color:#16477f;margin-bottom:5px}
.hsn-summary-table{table-layout:fixed;width:100%;border-collapse:collapse}
.hsn-summary-table th{background:#eaf1f9;color:#244a73;font-size:6.9px;text-transform:uppercase;text-align:left;padding:4px 2px;border-bottom:1px solid #b9ccdf}
.hsn-summary-table td{font-size:7.5px;padding:4px 2px;border-bottom:1px solid #e5ecf4;overflow-wrap:anywhere}
.hsn-summary-table .num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
.hsn-summary-table .hsn-code{width:22%;font-weight:700;white-space:normal}
.hsn-summary-table .hsn-money{width:15%}
.hsn-summary-table .hsn-gst{width:17%}
.hsn-summary-table tfoot td{font-weight:800;color:#173d6c;background:#edf4fc}
.hsn-panel-note{margin:5px 0 0;font-size:7.1px;color:#6c6173;line-height:1.3}
@media print{.hsn-summary-table th{font-size:6.5px}.hsn-summary-table td{font-size:7px}.hsn-panel{break-inside:avoid}}
"""
style_anchor='</style>\n</head>'
if source.count(style_anchor)!=1:
    raise SystemExit('SAFE_STOP: style anchor mismatch')
s=source.replace(style_anchor,style+'\n'+style_anchor)

marker="@if($summaryMode)<div class=\"osl-included\"><strong>Tax included where applicable — not a tax invoice.</strong></div>@endif"
if s.count(marker)!=1:
    raise SystemExit('SAFE_STOP: left money panel anchor mismatch')

block = String = r"""
    @unless($summaryMode)
    @php
      // Display-only HSN/SAC groups. Never update saved GST or customer ledger.
      // The existing tax reconciliation gate controls tax-invoice printing.
      $amHsnGroups=[];
      $amMissingTaxCode=false;
      foreach ([['kind'=>'HSN','lines'=>$parts],['kind'=>'SAC','lines'=>$labour]] as $dataset) {
          foreach ($dataset['lines'] as $entry) {
              $raw=(array)$entry;
              $isPart=$dataset['kind']==='HSN';
              $code=trim((string)($isPart
                  ? ($raw['hsn_code']??$raw['hsn']??'')
                  : ($raw['sac_code']??$raw['sac']??'')));
              if ($code==='') { $code='Not set'; $amMissingTaxCode=true; }
              $rate=round((float)($raw['gst_percent']??0),2);
              $taxable=round((float)($raw['total']??0),2);
              $lineGST=$amTaxOnLine($entry);
              $key=$dataset['kind'].'|'.$code.'|'.number_format($rate,2,'.','');
              if (!isset($amHsnGroups[$key])) {
                  $amHsnGroups[$key]=[
                      'kind'=>$dataset['kind'], 'code'=>$code,'rate'=>$rate,
                      'taxable'=>0.0,'cgst'=>0.0,'sgst'=>0.0,'igst'=>0.0,'gst'=>0.0,
                  ];
              }
              // The visible State of Supply on this invoice is 18-Assam.
              // Intra-state GST is split 50/50 into CGST and SGST.
              $cgst=round($lineGST/2,2);
              $sgst=round($lineGST-$cgst,2);
              $amHsnGroups[$key]['taxable']+=$taxable;
              $amHsnGroups[$key]['cgst']+=$cgst;
              $amHsnGroups[$key]['sgst']+=$sgst;
              $amHsnGroups[$key]['gst']+=$lineGST;
          }
      }
      ksort($amHsnGroups);
      $amHsnTaxable=array_sum(array_column($amHsnGroups,'taxable'));
      $amHsnCgst=array_sum(array_column($amHsnGroups,'cgst'));
      $amHsnSgst=array_sum(array_column($amHsnGroups,'sgst'));
      $amHsnIgst=array_sum(array_column($amHsnGroups,'igst'));
      $amHsnGst=array_sum(array_column($amHsnGroups,'gst'));
    @endphp
    <div class="hsn-panel">
      <div class="hsn-title">HSN / SAC-wise Tax Summary</div>
      <table class="hsn-summary-table">
        <thead><tr>
          <th class="hsn-code">HSN/SAC</th>
          <th class="num hsn-money">Taxable</th>
          <th class="num hsn-money">CGST</th>
          <th class="num hsn-money">SGST</th>
          <th class="num hsn-money">IGST</th>
          <th class="num hsn-gst">GST Total</th>
        </tr></thead>
        <tbody>
        @forelse($amHsnGroups as $g)
          <tr>
            <td class="hsn-code">{{ $g['kind'] }} {{ $g['code'] }}<div style="font-weight:400;font-size:6.5px">{{ number_format($g['rate'],2) }}% GST</div></td>
            <td class="num">{{ number_format($g['taxable'],2) }}</td>
            <td class="num">{{ number_format($g['cgst'],2) }}</td>
            <td class="num">{{ number_format($g['sgst'],2) }}</td>
            <td class="num">{{ number_format($g['igst'],2) }}</td>
            <td class="num">{{ number_format($g['gst'],2) }}</td>
          </tr>
        @empty<tr><td colspan="6">No HSN/SAC taxable lines.</td></tr>
        @endforelse
        </tbody>
        <tfoot><tr>
          <td>Total</td>
          <td class="num">{{ number_format($amHsnTaxable,2) }}</td>
          <td class="num">{{ number_format($amHsnCgst,2) }}</td>
          <td class="num">{{ number_format($amHsnSgst,2) }}</td>
          <td class="num">{{ number_format($amHsnIgst,2) }}</td>
          <td class="num">{{ number_format($amHsnGst,2) }}</td>
        </tr></tfoot>
      </table>
      @if(!$amTaxReconciled)
        <p class="hsn-panel-note"><strong>GST mismatch:</strong> Breakup is calculated from line values for review only. GST Invoice printing remains blocked until saved tax reconciles.</p>
      @endif
      @if($amMissingTaxCode)
        <p class="hsn-panel-note"><strong>HSN/SAC missing:</strong> Set missing item classifications before issuing a tax invoice.</p>
      @endif
    </div>
    @endunless
"""
s=s.replace(marker,marker+'\n'+block)
if s.count('AM_HSN_PANEL_V1')!=1 or s.count('HSN / SAC-wise Tax Summary')!=1:
    raise SystemExit('SAFE_STOP: HSN patch structure check failed')
if s.count('<div class="amounts">')!=1 or s.count('<div class="money-message">')!=1:
    raise SystemExit('SAFE_STOP: the totals layout was unexpectedly changed')
backup=p.with_name(p.name+'.before-hsn-panel-'+time.strftime('%Y%m%d%H%M%S')+'.bak')
shutil.copy2(p,backup)
tmp=p.with_name(p.name+'.hsn-panel.tmp')
tmp.write_text(s,encoding='utf-8')
os.chmod(tmp,stat.S_IMODE(p.stat().st_mode))
os.replace(tmp,p)
print('HSN_PANEL_APPLIED','BACKUP='+backup.name,'NEW_HASH='+hashlib.sha256(s.encode()).hexdigest()[:16])
