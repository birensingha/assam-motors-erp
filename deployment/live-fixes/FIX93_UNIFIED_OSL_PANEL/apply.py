#!/usr/bin/env python3
"""FIX93 STAGING: single Labour + OSL register and OSL purchase tools in Add OSL dialog.
Presentation only. Existing billing and supplier-purchase routes stay unchanged.
No data migrations, no customer billing writes; aborted if source changed.
"""
from pathlib import Path
import sys,hashlib,os,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
    raise SystemExit('Usage: apply.py check|apply APP_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file(), 'FIX93_ROOT_NOT_LARAVEL'
view=root/'resources/views/job-cards/show.blade.php'
raw=view.read_bytes()
sha=hashlib.sha256(raw).hexdigest()
print('FIX93_TARGET_SHA256',sha)
expected='d555aca28da90556bfabfd1104bb8095be27e59b06d461245ac603c692644d66'
s=raw.decode('utf8','surrogateescape')
marker='id="FIX93_OSL_MODAL_TOOLS"'
if marker in s:
    if s.count(marker)==1 and 'id="am93-osl-tools-template"' in s:
        print('FIX93_ALREADY_APPLIED');sys.exit(0)
    raise SystemExit('FIX93_PARTIAL_PATCH_ABORT')
if sha!=expected:
    raise SystemExit('FIX93_SOURCE_CHANGED_ABORT')
def once(old,new,label):
    global s
    n=s.count(old);print('FIX93_ANCHOR',label,n)
    if n!=1:raise SystemExit('FIX93_ANCHOR_MISMATCH_'+label)
    s=s.replace(old,new,1)

# Keep the original verified Blade @forelse($labour as $line) loop intact.
# Reorder rendered rows client-side; no billing numbers are changed.

# Only pending supplier purchases get a REFERENCE row (never billed twice).
# Billed OSL already appears above as a real job_card_labour record.
once('''          @endforelse
          </tbody>
        </table>
      </div>
    </section>
    <div class="am90-work-label">Mechanic &amp; ROT Work Controls</div>''',
'''          @endforelse
          @foreach(($am83OslPurchases??collect()) as $am93Purchase)
            @if(empty($am93Purchase->billing_line_created))
              <tr class="am93-purchase-pending" data-am93-purchase-id="{{ $am93Purchase->id }}">
                <td colspan="12">
                  <span class="am93-purchase-badge">OSL · PURCHASE ONLY</span>
                  <strong>{{ $am93Purchase->description ?: 'Outside Service' }}</strong>
                  <span>Vendor: {{ $am93Purchase->supplier_name ?: 'Not specified' }}</span>
                  <span>Purchase: {{ $am93Purchase->purchase_no ?: ('#'.$am93Purchase->id) }}</span>
                  <span>Supplier Cost: ₹{{ number_format((float)($am93Purchase->total_amount??0),2) }}</span>
                  <small>Pending JC customer billing — not included in customer total</small>
                </td>
                <td class="am90-action"><a class="btn small" href="{{ route('erp.fix48.osl-edit',$am93Purchase->id) }}">Review</a></td>
              </tr>
            @endif
          @endforeach
          </tbody>
        </table>
      </div>
    </section>
    <div class="am90-work-label">Mechanic &amp; ROT Work Controls</div>''','pending_purchase_rows_inside_register')

once('No Labour/ROT items yet.</td></tr>\n          @endforelse\n          @foreach',
     'No customer Labour/OSL billing lines yet.</td></tr>\n          @endforelse\n          @foreach',
     'empty_customer_billing_copy')

# Wrap original supplier OSL box in inert HTML template: no second panel.
# Its links, linked supplier purchase records and search/select information are
# preserved and cloned into Add OSL dialog ONLY when opened.
once('    <div class="am83-osl" id="jc-osl">',
     '    <template id="am93-osl-tools-template">\n    <div class="am83-osl" id="jc-osl">',
     'bottom_panel_to_template_open')

note='      <p class="am83-osl-note">Supplier Purchase is a cost/reference.'
pos=s.find(note)
if pos<0 or s.count(note)!=1:raise SystemExit('FIX93_OSL_NOTE_MISSING')
endp=s.find('</p>',pos)
close=s.find('\n    </div>',endp)
if endp<0 or close<0:raise SystemExit('FIX93_OSL_PANEL_CLOSE_NOT_FOUND')
s=s[:close+len('\n    </div>')]+ '\n    </template>'+s[close+len('\n    </div>'):]
if s.count('<template id="am93-osl-tools-template">')!=1 or s.count('</template>')!=1:
    raise SystemExit('FIX93_TEMPLATE_UNBALANCED')

# Explicit selection button beside existing View/Edit inside linked purchase
# popup. Selection copies only name, NOT supplier amount into bill rate.
once('''          @forelse(($am83OslPurchases ?? collect()) as $p)
          <tr>''',
'''          @forelse(($am83OslPurchases ?? collect()) as $p)
          <tr data-am93-service="{{ $p->description ?: 'Outside Service' }}"
              data-am93-purchase-id="{{ $p->id }}">''','supplier_purchase_select_dataset')
once('''<td><a class="btn small" href="{{ route('erp.fix48.osl-edit',$p->id) }}">View / Edit</a></td>''',
'''<td class="am93-select-actions">
              <button type="button" class="btn small am93-select-purchase" data-am93-use-osl>Select</button>
              <a class="btn small" href="{{ route('erp.fix48.osl-edit',$p->id) }}" target="_blank" rel="noopener noreferrer">View / Edit</a>
            </td>''','select_and_original_purchase_edit')
once('<a class="btn small primary" href="{{ route(\'erp.purchases.osl.create\') }}">+ New OSL Purchase</a>',
     '<a class="btn small primary" target="_blank" rel="noopener noreferrer" href="{{ route(\'erp.purchases.osl.create\') }}">+ New OSL Purchase</a>',
     'purchase_create_link_in_modal')
once('<a class="btn small ghost" href="{{ route(\'erp.fix48.osl-index\') }}">OSL Register</a>',
     '<a class="btn small ghost" target="_blank" rel="noopener noreferrer" href="{{ route(\'erp.fix48.osl-index\') }}">OSL Register</a>',
     'osl_register_link_in_modal')

# Retain the actual existing Add OSL modal, financial save route and logic.
# Insert linked purchases and original OSL Purchase/Register functionality there.
once('''   inner.appendChild(note);
   var form=document.createElement('form');form.className='am91-osl-form';''',
'''   inner.appendChild(note);
   if(!editing){
     var toolsTemplate=document.getElementById('am93-osl-tools-template');
     if(toolsTemplate){
       var toolsBlock=document.createElement('details');
       toolsBlock.className='am93-tools-in-dialog';
       toolsBlock.open=true;
       var toolsHeading=document.createElement('summary');
       toolsHeading.textContent='Select Linked OSL / Supplier Purchase Options';
       toolsBlock.appendChild(toolsHeading);
       toolsBlock.appendChild(document.importNode(toolsTemplate.content,true));
       inner.appendChild(toolsBlock);
       inner.addEventListener('click',function(ev){
         var use=ev.target.closest('button[data-am93-use-osl]');
         if(!use)return;
         ev.preventDefault();
         var source=use.closest('tr[data-am93-service]');
         var desc=form.elements.namedItem('description');
         if(source&&desc){
           desc.value=source.dataset.am93Service||'';
           toolsBlock.open=false;
           desc.focus();
           var info=inner.querySelector('.am93-selection-message');
           if(info)info.textContent='OSL selected. Enter customer Rate, Discount and GST, then Save.';
         }
       });
     }
   }
   var form=document.createElement('form');form.className='am91-osl-form';''',
   'purchase_selection_into_add_dialog')
# Note: form is lexical 'var' and available when click fires after modal setup.

# FIX91 AJAX OSL save rehydrates billing rows; regroup Labour then OSL once.
once("body.replaceChildren.apply(body,Array.from(fresh.children).map(function(node){return document.importNode(node,true);}));",
     "body.replaceChildren.apply(body,Array.from(fresh.children).map(function(node){return document.importNode(node,true);}));\n        document.dispatchEvent(new Event('am93-osl-billing-updated'));",
     'regroup_after_osl_ajax_save')

# JS re-render ROT cards shouldn't discard server-rendered pending purchases.
once("    var oslRows=Array.from(body.querySelectorAll('tr[data-am91-osl-id]'));",
     "    var oslRows=Array.from(body.querySelectorAll('tr[data-am91-osl-id],tr[data-am93-purchase-id]'));",
     'preserve_osl_and_purchase_rows_after_ajax')

# Empty billing row in presence of only OSL pending purchases: omit message,
# leaving the purchase reference row as the sole data row.
once('''    if(!cards.length){
      if(oslRows.length){oslRows.forEach(function(row){body.appendChild(row);});return;}''',
'''    if(!cards.length){
      if(oslRows.length){
        oslRows.forEach(function(row){body.appendChild(row);});
        return;
      }''','purchase_only_table_render')

ui=r'''
{{-- FIX93 moves OSL supplier purchase tools into +Add OSL dialog;
   pending supplier purchase reference is shown below customer billing. --}}
<style id="FIX93_OSL_MODAL_TOOLS">
.jc-labour-block .am90-table tr.am93-purchase-pending td{
  background:#fffaf0!important;color:#5b512f!important;
  font-size:10px!important;padding:8px 7px!important;line-height:1.45!important
}
.am93-purchase-pending td:first-child{white-space:normal!important}
.am93-purchase-pending td:first-child strong{margin-right:9px}
.am93-purchase-pending td:first-child span:not(.am93-purchase-badge){margin-right:10px}
.am93-purchase-pending td:first-child small{display:block;color:#916c24!important;font-size:9px!important}
.am93-purchase-badge{display:inline-block;background:#ffebba;color:#734b04;
  border-radius:5px;font-size:9px;font-weight:800;padding:2px 5px;margin-right:9px}
.am91-osl-dialog{width:min(1020px,calc(100vw - 26px))!important;
 max-height:calc(100vh - 30px)!important}
.am91-osl-dialog .am93-tools-in-dialog{
  border:1px solid #c8d9e8!important;border-radius:8px!important;
  margin:0 0 14px!important;overflow:hidden!important;background:white!important
}
.am91-osl-dialog .am93-tools-in-dialog>summary{
  padding:10px 12px!important;background:#eaf3ff!important;color:#1b4b75!important;
  font-size:12px!important;font-weight:800!important;cursor:pointer!important;
  list-style:revert!important
}
.am91-osl-dialog .am83-osl{
  border:0!important;border-radius:0!important;margin:0!important;
  padding:0 8px 8px!important;background:#fff!important;box-shadow:none!important
}
.am91-osl-dialog .am83-osl-head{
  display:flex!important;flex-wrap:wrap!important;gap:8px!important;
  justify-content:space-between!important;align-items:center!important;
  padding:7px 3px!important
}
.am91-osl-dialog .am83-osl-head strong{font-size:11px!important}
.am91-osl-dialog .am83-osl-head small{display:none!important}
.am91-osl-dialog .am83-osl-actions{display:flex!important;flex-wrap:wrap!important;gap:7px!important}
.am91-osl-dialog .am83-osl-scroll{width:100%!important;max-height:190px!important;overflow:auto!important}
.am91-osl-dialog .am83-osl-table{width:100%!important;font-size:10px!important;min-width:620px!important}
.am91-osl-dialog .am83-osl-table th{background:#173d68!important;color:#fff!important;font-size:9px!important}
.am91-osl-dialog .am83-osl-table td{font-size:10px!important;line-height:1.3!important}
.am91-osl-dialog .am83-osl-note{padding:7px!important;margin:0!important;font-size:9px!important;color:#627993!important}
.am91-osl-dialog .am93-select-actions{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.am91-osl-dialog .am93-select-purchase{background:#eaf6ef!important;color:#16613b!important;
 border-color:#7dbd96!important;font-weight:800!important}
.am91-osl-dialog .am93-selection-message{font-size:10px;color:#17603d}
</style>
'''
# UI CSS has no independent fetch/save operations.
s+='\n'+ui
s+=r'''
<script id="am93-osl-row-sort">
(function(){
  'use strict';
  function group(){
    var body=document.getElementById('am90-table-body');if(!body)return;
    var rows=Array.from(body.children);
    var customer=rows.filter(function(row){
      return !row.hasAttribute('data-am91-osl-id') &&
             !row.hasAttribute('data-am93-purchase-id');
    });
    var osl=rows.filter(function(row){
      return row.hasAttribute('data-am91-osl-id') ||
             row.hasAttribute('data-am93-purchase-id');
    });
    customer.concat(osl).forEach(function(row){body.appendChild(row);});
    Array.from(body.querySelectorAll('tr[data-am90-row]')).forEach(function(row,i){
      if(row.cells.length)row.cells[0].textContent=String(i+1);
    });
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',group,{once:true});
  else group();
  document.addEventListener('am93-osl-billing-updated',group);
})();
</script>
'''

must=['id="FIX93_OSL_MODAL_TOOLS"','id="am93-osl-tools-template"',
      'data-am93-use-osl','data-am93-purchase-id',
      '@forelse($labour as $line)','id="am91-add-osl"',
      'OSL Jobs / Outside Services','route(\'erp.purchases.osl.create\')',
      'route(\'erp.fix48.osl-index\')','id="am90-table-body"']
for k in must:
    if k not in s:raise SystemExit('FIX93_REQUIRED_CONTENT_MISSING '+k)
if s.count('id="jc-osl"')!=1 or s.count(marker)!=1:
    raise SystemExit('FIX93_DUPLICATE_OSL_PANEL_OR_STYLE')
if mode=='check':
    if os.environ.get('FIX93_PREVIEW_PATH'):
        Path(os.environ['FIX93_PREVIEW_PATH']).write_text(s,encoding='utf8',errors='surrogateescape')
        print('FIX93_PREVIEW_WRITTEN_NOT_LIVE')
    print('FIX93_OSL_UNIFIED_PREFLIGHT_PASS_NO_WRITES');sys.exit(0)
fd,tmp=tempfile.mkstemp(dir=view.parent,prefix='.fix93_')
try:
    with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:
        f.write(s)
    os.chmod(tmp,view.stat().st_mode&0o777)
    os.replace(tmp,view)
finally:
    if os.path.exists(tmp):os.unlink(tmp)
print('FIX93_OSL_UNIFIED_REGISTER_AND_MODAL_TOOLS_APPLIED_VIEW_ONLY')
