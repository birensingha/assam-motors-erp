{{-- AM246_MASTER_ITEM_LEDGERS: read-only Tally-style Parts / ROT / OSL searchable transaction ledger --}}
@extends('layouts.erp')
@section('title','Master Item Ledger · ASSAM MOTORS ERP')
@section('topbar_title','Master Transaction Ledger')
@section('content')
<style>
.am246{max-width:1530px;margin:0 auto;padding:0 3px;color:#193950}
.am246-h{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin:0 0 16px}
.am246-h h1{font-size:23px;margin:0;color:#0e3562;font-weight:850}
.am246-h p{color:#607a91;font-size:12px;margin:3px 0}
.am246-links{display:flex;align-items:center;flex-wrap:wrap;gap:6px}
.am246-link{font-size:11px;font-weight:800;background:#f0f6fd;color:#124c85;border:1px solid #cbdbea;text-decoration:none;border-radius:7px;padding:9px 12px}
.am246-box{background:#fff;border:1px solid #d5e3ef;border-radius:13px;margin-bottom:13px;padding:15px;box-shadow:0 2px 15px rgba(14,60,103,.025)}
.am246-search{display:grid;grid-template-columns:180px minmax(240px,1fr) 155px 155px auto;gap:10px;align-items:end}
.am246-label{font-size:11px;font-weight:800;color:#4d6880;display:block}
.am246-input,.am246-select{display:block;width:100%;box-sizing:border-box;margin:5px 0 0;border:1px solid #bdcede;border-radius:7px;padding:9px 10px;height:37px;background:white;color:#133b65;font-size:12px}
.am246-input:focus,.am246-select:focus{outline:2px solid rgba(47,130,205,.18);border-color:#498acb}
.am246-btn{min-height:37px;padding:8px 13px;border-radius:8px;border:1px solid #b7cbde;background:#f1f7ff;color:#14497c;font-size:12px;font-weight:800;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
.am246-btn.primary{background:#155ba6;border-color:#155ba6;color:#fff}
.am246-autocomplete{position:relative;min-width:0}
.am246-menu{position:absolute;top:calc(100% + 3px);left:0;right:0;z-index:4000;max-height:310px;overflow:auto;background:white;border:1px solid #afc9e4;box-shadow:0 11px 24px rgba(24,62,105,.13);border-radius:8px}
.am246-menu[hidden]{display:none!important}
.am246-option{display:block;width:100%;border:0;border-bottom:1px solid #e7eef8;padding:10px 12px;text-align:left;background:white;color:#19466f;cursor:pointer;font-size:12px}
.am246-option:hover,.am246-option:focus{background:#e8f3ff}
.am246-option strong{color:#0a417e}
.am246-empty{padding:16px;color:#67829a;font-size:12px}
.am246-master{display:flex;gap:12px;justify-content:space-between;align-items:center;flex-wrap:wrap}
.am246-master h2{font-size:17px;margin:0;color:#16426e}
.am246-master p{font-size:11px;color:#6b8296;margin:3px 0}
.am246-tag{padding:5px 10px;border-radius:100px;background:#eaf2fa;color:#245682;font-size:10px;font-weight:800}
.am246-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:13px 0}
.am246-kpi{border:1px solid #d9e6f3;background:#fff;border-radius:10px;padding:13px 14px}
.am246-kpi small{display:block;font-size:10px;text-transform:uppercase;color:#667f95;font-weight:800}
.am246-kpi strong{font-size:20px;display:block;font-variant-numeric:tabular-nums;margin-top:5px;color:#133e70}
.am246-kpi em{display:block;color:#607a90;font-size:10px;font-style:normal;margin-top:5px}
.am246-table-scroll{overflow:auto;max-height:610px;border:1px solid #d9e5f0;border-radius:10px}
.am246-table{width:100%;min-width:1180px;border-collapse:collapse}
.am246-table th{position:sticky;top:0;background:#e7f0f9;color:#245174;z-index:1;text-transform:uppercase;letter-spacing:.15px;font-size:10px;padding:12px 9px;text-align:left}
.am246-table td{border-bottom:1px solid #edf2f6;padding:9px;font-size:11px;vertical-align:top}
.am246-table tr:hover{background:#f6fbff}
.am246-table .num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
.am246-table td small{display:block;color:#70869a;font-size:10px;line-height:1.55;margin-top:3px}
.am246-table td .kind{font-size:10px;color:#1c5b94;font-weight:850;text-transform:uppercase}
.am246-table td a{color:#12569b;font-weight:850;text-decoration:none}
.am246-table td a:hover{text-decoration:underline}
.am246-status{font-size:10px;font-weight:800;border-radius:5px;background:#f1f6fb;padding:4px 6px;color:#4e6c87;display:inline-block;margin-top:3px;white-space:nowrap}
.am246-notice{font-size:11px;line-height:1.6;color:#506d86;background:#f1f7fd;border:1px solid #d4e4f3;border-radius:8px;padding:11px 12px;margin-bottom:10px}
.am246-warning{background:#fff7ed;border-color:#f4dbbd;color:#9b5f14}
.am246-count{color:#5b7995;font-size:11px;font-weight:700}
.am246-print{display:inline-flex}
@media(max-width:850px){.am246-search{grid-template-columns:1fr 1fr}.am246-autocomplete{grid-column:1/-1}.am246-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:570px){.am246-search{grid-template-columns:1fr 1fr}.am246-search>label:first-child{grid-column:1/-1}.am246-kpis{grid-template-columns:1fr 1fr}.am246-kpi strong{font-size:16px}}
@media print{body *{visibility:hidden} .am246,.am246 *{visibility:visible} .am246{position:absolute;left:0;top:0;width:100%;max-width:none} .am246-h .am246-links,.am246-search,.am246-menu{display:none!important}.am246-table-scroll{overflow:visible;max-height:none}.am246-table th{position:static} .am246-box{box-shadow:none;border-color:#999} .am246-kpi{break-inside:avoid}}
</style>
<div class="am246">
  <div class="am246-h">
    <div><h1>Item Transaction Ledger</h1>
      <p>Parts · ROT / Labour · OSL Master — Tally-style purchase, stock, customer billing and execution history</p>
    </div>
    <div class="am246-links">
      <a class="am246-link" href="{{ route('erp.masters.parts') }}">Parts Master</a>
      <a class="am246-link" href="{{ route('erp.masters.labour') }}">ROT / Labour Master</a>
      <a class="am246-link" href="{{ route('erp.osl-masters.index') }}">OSL Master</a>
      <button type="button" class="am246-btn am246-print" onclick="window.print()">Print Ledger</button>
    </div>
  </div>
  @if($errors->any())<div class="am246-notice am246-warning" role="alert">{{ $errors->first() }}</div>@endif
  <div class="am246-box">
    <form method="get" action="{{ route('erp.masters.item-ledger') }}" id="am246-form" class="am246-search">
      <label class="am246-label">Master Type
        <select name="type" id="am246-type" class="am246-select">
          <option value="PART" @selected($type==='PART')>Parts Master</option>
          <option value="LABOUR" @selected($type==='LABOUR')>ROT / Labour Master</option>
          <option value="OSL" @selected($type==='OSL')>OSL Master</option>
        </select>
      </label>
      <div class="am246-autocomplete">
        <label class="am246-label" for="am246-search">Item Name / Code — type to search</label>
        <input type="search" id="am246-search" name="q" class="am246-input"
          placeholder="Type AM-0050, oil filter, ROT-ENG, OSL work..." value="{{ $master ? ($master->{['PART'=>'part_code','LABOUR'=>'rot_code','OSL'=>'osl_code'][$type]}.' — '.$master->{['PART'=>'part_name','LABOUR'=>'rot_description','OSL'=>'osl_name'][$type]}) : $q }}"
          autocomplete="off" aria-autocomplete="list" aria-controls="am246-menu" role="combobox" aria-expanded="false">
        <input type="hidden" name="id" id="am246-id" value="{{ $master ? $master->id : '' }}">
        <div id="am246-menu" class="am246-menu" role="listbox" hidden></div>
      </div>
      <label class="am246-label">From Date
        <input class="am246-input" type="date" name="from" value="{{ $from }}">
      </label>
      <label class="am246-label">To Date
        <input class="am246-input" type="date" name="to" value="{{ $to }}">
      </label>
      <button type="submit" class="am246-btn primary">View Transactions</button>
    </form>
    @if(!$master)
      <p style="color:#627f9a;font-size:11px;margin:10px 0 0">Select a matching item to open its full ledger. Search supports Part code/name, ROT code/description and OSL code/name, including inactive masters.</p>
      @if(count($choices))
      <div style="margin:10px 0 0;display:flex;gap:7px;flex-wrap:wrap">
        @foreach(array_slice($choices,0,15) as $item)
          <a class="am246-link" href="{{ route('erp.masters.item-ledger',['type'=>$type,'id'=>$item['id'],'q'=>$item['code']]) }}">{{ $item['code'] }} — {{ \Illuminate\Support\Str::limit($item['name'],33) }}</a>
        @endforeach
      </div>
      @endif
    @endif
  </div>
  @if($master)
    <div class="am246-box am246-master">
      <div>
        <h2>{{ ['PART'=>'Parts','LABOUR'=>'ROT / Labour','OSL'=>'OSL'][$type] }} Ledger —
          {{ $master->{['PART'=>'part_code','LABOUR'=>'rot_code','OSL'=>'osl_code'][$type]} }}
        </h2>
        <p>{{ $master->{['PART'=>'part_name','LABOUR'=>'rot_description','OSL'=>'osl_name'][$type]} }}
          · {{ $type==='PART' ? ($master->unit ?? 'PCS') : 'SERVICE' }}
          · {{ $from ?: 'All dates' }} to {{ $to ?: 'Current' }}
        </p>
      </div>
      <span class="am246-tag">{{ number_format($totalEvents) }} transaction events</span>
    </div>
    @if($type==='PART')
      <div class="am246-kpis">
        <div class="am246-kpi"><small>Opening Stock (Period)</small><strong>{{ number_format((float)$report['opening'],3) }}</strong><em>{{ $report['opening_kind'] }}</em></div>
        <div class="am246-kpi"><small>Purchased (Period, Supplier Lines)</small><strong>{{ number_format((float)$report['purchase_qty'],3) }}</strong><em>Purchase document quantity, not an additional stock movement</em></div>
        <div class="am246-kpi"><small>Stock OUT (Movement Ledger)</small><strong>{{ number_format((float)$report['issued_qty'],3) }}</strong><em>JC issued + counter sales and other recorded stock-out</em></div>
        <div class="am246-kpi"><small>Closing Stock (Period)</small><strong>{{ number_format((float)$report['closing'],3) }}</strong><em>Current Parts Master balance {{ number_format((float)$report['current_stock'],3) }}</em></div>
      </div>
      <div class="am246-notice {{ abs((float)$report['variance'])>0.0001 ? 'am246-warning' : '' }}">
        Stock opening/closing is reconstructed from the recorded inventory movement history. Purchases and JC billing are shown for traceability but <strong>not counted again</strong> in stock.
        @if(abs((float)$report['variance'])>0.0001)
        <strong>Reconciliation Difference: {{ number_format((float)$report['variance'],3) }} units.</strong> Current Parts Master stock differs from movement-based reconstruction; investigate older/manual entries.
        @else
        <strong>Current stock reconciles with movement-based reconstruction.</strong>
        @endif
        A JC Part line is not proof of physical issue. JC-linked Invoice is not necessarily the original saved line snapshot.
      </div>
    @else
      <div class="am246-notice">
        {{ $type==='LABOUR' ? 'ROT/Labour is a service: show Job Cards, mechanics, work sessions and billing. Physical stock opening/purchases do not apply.' : 'OSL is an outside service: show supplier purchases and linked customer billing separately. There is no physical stock quantity.' }}
        Historical rows without a reliable Master ID or ROT/OSL code link are not silently assigned to the wrong service.
      </div>
    @endif

    <div class="am246-box">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:11px">
        <strong style="color:#164771;font-size:14px">Date-wise Transaction Register</strong>
        <span class="am246-count">Showing {{ number_format(count($report['events'])) }} of {{ number_format($totalEvents) }} recorded events</span>
      </div>
      @if($totalEvents>3000)
      <div class="am246-notice am246-warning">This item has more than 3,000 events. Only the latest 3,000 are shown. Use From/To Date to narrow the ledger. The displayed list is not a complete all-time register.</div>
      @endif
      <div class="am246-table-scroll">
        <table class="am246-table">
          <thead><tr>
            <th style="width:85px">Date</th><th style="width:145px">Transaction Type</th>
            <th style="width:165px">Reference</th><th>Details / Audit</th>
            <th style="width:150px">Purchased From / Billed To</th>
            <th style="width:104px">Vehicle</th>
            <th class="num" style="width:90px">Stock In</th>
            <th class="num" style="width:90px">Stock Out</th>
            <th class="num" style="width:90px">Balance</th>
            <th class="num" style="width:100px">Saved Amount</th>
            <th style="width:128px">Status</th>
          </tr></thead>
          <tbody>
            @forelse($report['events'] as $event)
              <tr>
                <td>{{ $event['date'] ?: '—' }}</td>
                <td><span class="kind">{{ $event['type'] }}</span></td>
                <td>
                  @if($event['url'])<a href="{{ $event['url'] }}" target="_blank" rel="noopener">{{ $event['reference'] ?: 'View source' }}</a>
                  @else<strong>{{ $event['reference'] ?: '—' }}</strong>@endif
                </td>
                <td>{{ $event['detail'] }}@if($event['notes'])<small>{{ $event['notes'] }}</small>@endif</td>
                <td>{{ $event['party'] ?: '—' }}</td>
                <td>{{ $event['vehicle'] ?: '—' }}</td>
                <td class="num">{{ $event['in']===null?'—':number_format((float)$event['in'],3) }}</td>
                <td class="num">{{ $event['out']===null?'—':number_format((float)$event['out'],3) }}</td>
                <td class="num">{{ ($event['balance']??null)===null?'—':number_format((float)$event['balance'],3) }}</td>
                <td class="num">{{ $event['amount']===null?'—':'₹'.number_format((float)$event['amount'],2) }}</td>
                <td><span class="am246-status">{{ $event['status'] ?: 'RECORDED' }}</span></td>
              </tr>
            @empty
              <tr><td colspan="11" class="am246-empty">No linked transactions found for this master item within the selected dates.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <p style="font-size:10px;color:#758ba0;margin:12px 0 0">
        Source-specific record numbers link to original documents where available. Purchase cost, JC billing amount, invoice and stock movements are separate accounting/physical events; do not total all amount cells as sales or purchases. Voided/deleted purchase records remain audit-visible.
      </p>
    </div>
  @endif
</div>
<script id="am246-ledger-picker">
(function(){
'use strict';
const type=document.getElementById('am246-type');
const search=document.getElementById('am246-search');
const id=document.getElementById('am246-id');
const menu=document.getElementById('am246-menu');
const form=document.getElementById('am246-form');
if(!type||!search||!menu||!form)return;
let timer=0,serial=0,results=[],active=-1;
function close(){menu.hidden=true;search.setAttribute('aria-expanded','false');active=-1;}
function show(items){
  menu.replaceChildren();results=items;active=-1;
  if(!items.length){const e=document.createElement('div');e.className='am246-empty';e.textContent='No matching master item';menu.append(e);}
  for(const [index,item] of items.entries()){
    const b=document.createElement('button');b.type='button';b.className='am246-option';b.setAttribute('role','option');
    const code=document.createElement('strong');code.textContent=item.code+' — ';
    const name=document.createTextNode(item.name);b.append(code,name);
    b.addEventListener('pointerdown',e=>e.preventDefault());
    b.addEventListener('click',()=>choose(index));menu.append(b);
  }
  menu.hidden=false;search.setAttribute('aria-expanded','true');
}
function choose(i){
  if(!results[i])return;
  id.value=String(results[i].id);
  search.value=results[i].label;
  close();form.requestSubmit();
}
function lookup(){
  id.value='';
  const q=search.value.trim(),t=type.value;
  const n=++serial;
  fetch(@json(route('erp.masters.item-ledger.suggest'))+'?type='+encodeURIComponent(t)+'&q='+encodeURIComponent(q),
      {credentials:'same-origin',headers:{'Accept':'application/json'}})
    .then(r=>{if(!r.ok)throw Error('Search unavailable');return r.json();})
    .then(data=>{if(n!==serial)return;show(Array.isArray(data.items)?data.items:[]);})
    .catch(()=>{if(n!==serial)return;menu.replaceChildren();let e=document.createElement('div');e.className='am246-empty';e.textContent='Live search unavailable. Press View Transactions to search master records.';menu.append(e);menu.hidden=false;});
}
search.addEventListener('focus',lookup);
search.addEventListener('input',()=>{id.value='';clearTimeout(timer);timer=setTimeout(lookup,180);});
type.addEventListener('change',()=>{id.value='';search.value='';close();search.focus();});
search.addEventListener('keydown',e=>{
 if(e.key==='Escape'){close();return;}
 if(e.key==='ArrowDown'||e.key==='ArrowUp'){
   e.preventDefault();if(menu.hidden){lookup();return;}
   const buttons=[...menu.querySelectorAll('button')];if(!buttons.length)return;
   active=(active+(e.key==='ArrowDown'?1:-1)+buttons.length)%buttons.length;
   buttons.forEach((b,i)=>b.style.background=i===active?'#e6f1ff':'');
   buttons[active].scrollIntoView({block:'nearest'});
 }
 if(e.key==='Enter'&&!menu.hidden&&results.length){e.preventDefault();choose(active<0?0:active);}
});
document.addEventListener('pointerdown',e=>{if(!menu.contains(e.target)&&e.target!==search)close();});
})();
</script>
@endsection
