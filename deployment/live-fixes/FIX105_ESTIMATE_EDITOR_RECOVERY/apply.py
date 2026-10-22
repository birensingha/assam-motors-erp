#!/usr/bin/env python3
"""FIX105: restore Edit Draft rows, suppress Tab popups, and print display
WITHOUT GST as inclusive unit price = saved line_total / qty.
No database/controller/approval changes. Only Estimate create+print and the
existing source-backed JS, with safe staging backup and precompile.
"""
from pathlib import Path
import sys,os,hashlib,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
 raise SystemExit('Usage: apply.py check|apply APP_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file()
locations={
 'create':root/'resources/views/estimates/create.blade.php',
 'js':root/'public/js/estimate-entry.js',
 'print':root/'resources/views/estimates/print.blade.php',
}
expected={
 'create':'1956cf6acced7e7cf559d84b19b15540beb87063d78274a70c98527e095081d4',
 'js':'172d63f54c888e8ceada270af7b69830682d51ce2d15649d04eca451f9800dc0',
 'print':'5aadc3517e4dce06f7847e999962ae1fdb51392fd8e039ab8c46f138599dfa93',
}
mark='FIX105_EDITOR_LOAD_TAB_AND_WO_GST_RATE'
source={}
for name,p in locations.items():
 raw=p.read_bytes();sha=hashlib.sha256(raw).hexdigest()
 print('FIX105_SOURCE_SHA',name,sha)
 source[name]=raw.decode('utf8','surrogateescape')
 if mark in source[name] and all(mark in q.read_text(errors='replace') for q in locations.values()):
  print('FIX105_ALREADY_APPLIED');sys.exit(0)
 if sha!=expected[name]:raise SystemExit('FIX105_CHANGED_SOURCE_ABORT_'+name)
def swap(s,a,b,k):
 count=s.count(a);print('FIX105_ANCHOR',k,count)
 if count!=1:raise SystemExit('FIX105_ANCHOR_MISMATCH_'+k)
 return s.replace(a,b,1)
create=source['create']
# FIX104 used a new asset path even though the actual public web root doesn't
# serve it (HTTP 404); browser therefore rendered zero rows. Use trusted JS
# bundled in the Laravel app directly (existing working 12-column version).
# This intentionally avoids fragile mapped document root/static paths.
create=swap(create,
 '''<script src="{{ asset('js/estimate-entry-fix104.js') }}?v=104"></script>''',
 '''{{-- FIX105_EDITOR_LOAD_TAB_AND_WO_GST_RATE: the FIX104 static URL returned 404.
     This script is bundled from our own app's checked static JS file,
     so draft lines render even if Hostinger's public asset mirror lags. --}}
<script id="fix105-estimate-editor">{!! file_get_contents(public_path('js/estimate-entry.js')) !!}</script>''',
 'inline_verified_static_script')
create=swap(create,
 '''id="estimate-form">@csrf''',
 '''id="estimate-form" onsubmit="if(!this.querySelector('#estimate-lines tr')){alert('Estimate lines did not load. Draft has NOT been saved. Refresh before retrying.');return false;}return true;">@csrf''',
 'block_save_if_no_rows')
js=source['js']
js=swap(js,
 '''  // FIX103_ESTIMATE_TABLE_GST_INVOICE_STYLE: calculated Discount and GST amounts.''',
 '''  // FIX105_EDITOR_LOAD_TAB_AND_WO_GST_RATE: preserve draft rows, Tab never opens suggestions.''',
 'js_marker')
js=swap(js,
 '''    refSearch.addEventListener('focus', () => renderMenu(tr, matches(type.value, refSearch.value)));
    refSearch.addEventListener('input', () => {
      clearReference(tr);
      renderMenu(tr, matches(type.value, refSearch.value));
    });''',
 '''    // Moving through the table with Tab must not open dropdowns or
    // overwrite saved reference IDs/labels. Only explicit typing searches.
    refSearch.addEventListener('focus', () => closeMenu(tr));
    refSearch.addEventListener('input', () => {
      clearReference(tr);
      const q = String(refSearch.value || '').trim();
      if (q.length < 2) closeMenu(tr);
      else renderMenu(tr, matches(type.value, q));
    });''',
 'tab_focus_does_not_open_menu')
js=swap(js,
 '''      } else if (e.key === 'Escape') {
        closeMenu(tr);
      } else if (e.key === 'Enter') {''',
 '''      } else if (e.key === 'Escape' || e.key === 'Tab') {
        closeMenu(tr);
      } else if (e.key === 'Enter') {''',
 'tab_close_menu')
# Keep arrow-key deliberate search and mouse item selection.
for k in ['const initial = Array.isArray(data.initial) ? data.initial : [];',
          'if (initial.length) initial.forEach(r => add(r)); else add();',
          "menu.addEventListener('mousedown'","class=\"e-disc-amt\"","class=\"e-gst-amt\""]:
 if k not in js:raise SystemExit('FIX105_JS_CRITICAL_MISSING_'+k)
if '</script>' in js.lower():raise SystemExit('FIX105_INLINE_JS_END_TAG_UNSAFE')
pr=source['print']
# Current 9-col without-GST display makes original base rate inconsistent with
# the GST-inclusive line final. Use 7-col W/O GST, hide discount and tax breakup
# together and show gross unit price = persisted inclusive line_total / qty.
pr=swap(pr,
 '<th class="num disc-col">Dis%</th><th class="num">Disc ₹</th>',
 '@if($am104ShowGst)<th class="num disc-col">Dis%</th><th class="num">Disc ₹</th>@endif',
 'conditional_discount_headers')
pr=swap(pr,
 '''<td class="num">{{ number_format((float)$i->rate,2) }}</td>
<td class="num">{{ number_format((float)$i->discount_percent,2) }}%</td>
<td class="num">{{ number_format($am103Disc,2) }}</td>''',
 '''<td class="num">{{ number_format($am104ShowGst ? (float)$i->rate : ((float)$i->qty>0 ? (float)$i->line_total/(float)$i->qty : 0),2) }}</td>
@if($am104ShowGst)
<td class="num">{{ number_format((float)$i->discount_percent,2) }}%</td>
<td class="num">{{ number_format($am103Disc,2) }}</td>
@endif''','gross_rate_per_qty_and_conditional_discounts')
pr=swap(pr,'colspan="{{ $am104ShowGst?11:9 }}"',
           'colspan="{{ $am104ShowGst?11:7 }}"','empty_case_7_columns')
# FIX104 had without-GST 9 columns; replace with precise 7-col total=100%:
# SL 5, Type 9, Description 38, Qty 9, Unit 8, Rate 15, Final 16.
start='''.am103-estimate-lines.without-gst th:nth-child(1){width:4%!important}
.am103-estimate-lines.without-gst th:nth-child(2){width:9%!important}
.am103-estimate-lines.without-gst th:nth-child(3){width:31%!important}
.am103-estimate-lines.without-gst th:nth-child(4){width:7%!important}
.am103-estimate-lines.without-gst th:nth-child(5){width:7%!important}
.am103-estimate-lines.without-gst th:nth-child(6){width:13%!important}
.am103-estimate-lines.without-gst th:nth-child(7){width:7%!important}
.am103-estimate-lines.without-gst th:nth-child(8){width:10%!important}
.am103-estimate-lines.without-gst th:nth-child(9){width:12%!important}'''
new='''/* FIX105_EDITOR_LOAD_TAB_AND_WO_GST_RATE: inclusive unit rate, 7 simple columns. */
.am103-estimate-lines.without-gst th:nth-child(1){width:5%!important}
.am103-estimate-lines.without-gst th:nth-child(2){width:9%!important}
.am103-estimate-lines.without-gst th:nth-child(3){width:38%!important}
.am103-estimate-lines.without-gst th:nth-child(4){width:9%!important}
.am103-estimate-lines.without-gst th:nth-child(5){width:8%!important}
.am103-estimate-lines.without-gst th:nth-child(6){width:15%!important}
.am103-estimate-lines.without-gst th:nth-child(7){width:16%!important}'''
pr=swap(pr,start,new,'print_7cols_widths')
# Keep same saved gross line totals, parts/labour and grand total (no tax mutation).
for x in ["number_format((float)$i->line_total,2)",
          "number_format((float)$estimate->grand_total,2)",
          '@if($am104ShowGst)','?gst=0','?gst=1']:
 if x not in pr:raise SystemExit('FIX105_PRINT_INVARIANT_'+x)
updated={'create':create,'js':js,'print':pr}
if mode=='check':
 for k,v in updated.items():
  target=os.environ.get('FIX105_PREVIEW_'+k.upper())
  if target:Path(target).write_text(v,encoding='utf8',errors='surrogateescape')
 print('FIX105_PREFLIGHT_PASS_NO_LIVE_WRITES');sys.exit(0)
for k,v in updated.items():
 p=locations[k];fd,tmp=tempfile.mkstemp(prefix='.fix105_',dir=p.parent)
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(v)
  os.chmod(tmp,p.stat().st_mode&0o777)
  os.replace(tmp,p)
 finally:
  if os.path.exists(tmp):os.unlink(tmp)
 print('FIX105_UPDATED',p.relative_to(root))
print('FIX105_DRAFT_RECOVERY_TAB_AND_INCLUSIVE_RATE_DEPLOYED_NO_DB_WRITES')
