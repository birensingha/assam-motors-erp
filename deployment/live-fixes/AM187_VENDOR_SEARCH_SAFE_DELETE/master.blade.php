@extends('layouts.erp')
@section('title','Vendor Master — Assam Motors')
@section('topbar_title','Vendor Master')
@section('content')
<style>
.am187{--line:#dce5f0;--ink:#213c5f;--sub:#788aa3;font-family:Inter,"Segoe UI",Arial,sans-serif;color:var(--ink);font-size:12px}
.am187 *{box-sizing:border-box}
.am187 .head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:17px 20px;border-radius:12px;background:linear-gradient(110deg,#fff,#eef5ff);border:1px solid var(--line);margin:0 0 15px}
.am187 .head h1{margin:0 0 4px;color:#164071;font-size:23px;font-weight:800}
.am187 .head p{margin:0;color:#7285a1;font-size:12px}
.am187 .pill{font-size:11px;color:#2561a3;background:#eaf3ff;border:1px solid #d0e3fa;padding:7px 11px;border-radius:30px;white-space:nowrap}
.am187 .layout{display:grid;grid-template-columns:minmax(295px,0.9fr) minmax(420px,1.35fr);gap:14px;align-items:start}
.am187 .panel{background:#fff;border:1px solid var(--line);border-radius:12px;box-shadow:0 3px 10px #142f5410;overflow:hidden;min-width:0}
.am187 .panel-title{display:flex;gap:10px;justify-content:space-between;align-items:center;padding:14px 16px;background:#f8fbff;border-bottom:1px solid var(--line);color:#21466e;font-size:14px;font-weight:800}
.am187 .panel-title small{display:block;color:#8594a8;font-size:11px;font-weight:500;margin-top:4px}
.am187 .btn{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;white-space:nowrap;gap:5px;border:1px solid #ccdaea!important;background:#fff!important;border-radius:7px!important;color:#24598e!important;min-height:33px;font-size:11px!important;font-weight:750;cursor:pointer;padding:7px 11px!important;line-height:1.2!important;box-shadow:none!important}
.am187 .btn.primary{background:#195aaa!important;border-color:#195aaa!important;color:#fff!important}
.am187 .btn.danger{border-color:#efc2c2!important;color:#b23939!important;background:#fff6f6!important}
.am187 .btn:disabled{opacity:.45;cursor:not-allowed}
.am187 .btn:hover:not(:disabled){filter:brightness(.95)}
.am187 .search-wrap{position:relative;padding:13px 14px 11px;background:white;border-bottom:1px solid #e8edf5}
.am187 .search-row{display:flex;gap:8px}
.am187 input.am-field,.am187 textarea.am-field{display:block;width:100%;min-width:0;border:1px solid #d1deec!important;border-radius:7px!important;background:#fff!important;box-shadow:none!important;min-height:37px;font-size:12px!important;color:#203c60!important;padding:8px 11px!important;margin:0!important}
.am187 input.am-field:focus,.am187 textarea.am-field:focus{border-color:#3872bf!important;outline:2px solid #e0edff!important}
.am187 textarea.am-field{min-height:74px;resize:vertical}
.am187 .search-row input{flex:1}
.am187 .suggestions{position:absolute;z-index:40;top:calc(100% - 11px);left:14px;right:14px;background:#fff;border:1px solid #bed3ea;border-radius:8px;max-height:270px;overflow-y:auto;box-shadow:0 10px 25px #18385e26}
.am187 .suggestions[hidden]{display:none}
.am187 .am187-option{display:flex!important;flex-direction:column;align-items:stretch;gap:3px;text-align:left;width:100%;padding:10px 12px!important;border:0!important;border-bottom:1px solid #edf1f7!important;background:#fff!important;color:#29415e!important;cursor:pointer}
.am187 .am187-option.am187-active,.am187 .am187-option:hover{background:#e8f2ff!important;box-shadow:inset 3px 0 #2867bc}
.am187 .am187-option-name{font-size:12px;font-weight:750}
.am187 .am187-option-name mark{color:#114f94;background:#fff0a8;border-radius:3px;padding:0 1px}
.am187 .am187-option-meta{font-size:10px;color:#8695a9}
.am187 .am187-empty{padding:10px 12px;color:#8a98aa}
.am187 .search-hint{font-size:10px;color:#8696ac;margin-top:6px}
.am187 .vendor-row{display:flex;align-items:center;justify-content:space-between;gap:9px;padding:11px 15px;border-bottom:1px solid #ecf1f7;min-height:59px}
.am187 .vendor-row.selected{background:#eff6ff;box-shadow:inset 3px 0 #3276c5}
.am187 .vendor-row .name{font-weight:800;color:#263d59;font-size:12px;overflow-wrap:anywhere}
.am187 .vendor-row .meta{font-size:10px;color:#8494a8;margin-top:5px}
.am187 .list-footer{padding:11px 14px;color:#8292a7;font-size:11px}
.am187 .list-footer nav{font-size:11px}
.am187 .detail{padding:18px}
.am187 .vendor-hero{display:flex;align-items:center;gap:11px;flex-wrap:wrap;margin-bottom:15px}
.am187 .vendor-avatar{flex:0 0 48px;width:48px;height:48px;background:#e4efff;color:#22599f;display:flex;align-items:center;justify-content:center;border-radius:11px;font-size:17px;font-weight:800}
.am187 .vendor-title{flex:1;min-width:190px}
.am187 .vendor-title strong{display:block;color:#173c68;font-size:17px;overflow-wrap:anywhere}
.am187 .vendor-title span{display:block;color:#8394aa;font-size:11px;margin-top:4px}
.am187 .info-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px;margin-bottom:16px}
.am187 .info-cell{border:1px solid #e4ebf4;padding:11px 12px;border-radius:8px;background:#fbfdff;min-width:0}
.am187 .info-cell .lbl{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:#8594a8;display:block;margin-bottom:6px}
.am187 .info-cell .value{font-size:12px;color:#27435f;font-weight:650;overflow-wrap:anywhere;white-space:pre-wrap}
.am187 .actionbar{display:flex;gap:9px;flex-wrap:wrap;align-items:center;padding-top:13px;border-top:1px solid #e7edf5}
.am187 .field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}
.am187 .am-label{display:block;font-size:11px;font-weight:750;color:#697c96;margin-bottom:6px}
.am187 .am-label small{color:#b13b3b}
.am187 .span2{grid-column:1/-1}
.am187 .notice{padding:11px 13px;border-radius:8px;font-size:11px;margin-bottom:12px}
.am187 .notice.ok{background:#ebfaf1;color:#17683e;border:1px solid #c7e7d1}
.am187 .notice.err{background:#fff2f2;color:#a93636;border:1px solid #ebc9c9}
.am187 .notice.info{background:#f3f8ff;color:#526e91;border:1px solid #dbe8f8}
.am187 .error{font-size:11px;margin-top:4px;color:#ae3737}
.am187 .confirm{padding:14px;background:#fffaf7;border:1px solid #f1dbcb;border-radius:9px;margin-top:14px}
.am187 .confirm strong{color:#983c29}
.am187 .foot-note{color:#8797a8;font-size:11px;line-height:1.5;margin-top:8px}
.am187 .mini{font-size:11px;color:#8394ab}
@media(max-width:1020px){.am187 .layout{grid-template-columns:1fr}}
@media(max-width:560px){.am187 .head{flex-wrap:wrap}.am187 .info-grid,.am187 .field-grid{grid-template-columns:1fr}.am187 .span2{grid-column:1}.am187 .detail{padding:13px}}
</style>
<div class="am187">
  <div class="head">
    <div><h1>Vendor Master</h1><p>All vendors · Search, View Ledger, Edit, and audited Delete for unused vendors.</p></div>
    <span class="pill">{{ $vendors->total() }} Vendor{{ $vendors->total()===1 ? '' : 's' }}</span>
  </div>
  @if(session('success'))<div class="notice ok">{{ session('success') }}</div>@endif
  @if($errors->any())
    <div class="notice err">Please check the form details. @if($errors->has('delete')) {{ $errors->first('delete') }} @endif</div>
  @endif
  <div class="layout">
    <section class="panel">
      <div class="panel-title">
        <div>Vendor List<small>Search by name, code, mobile or GSTIN</small></div>
        <a class="btn primary" href="{{ route('erp.vendors.index', ['mode'=>'new']) }}">+ Add Vendor</a>
      </div>
      <form id="am187-search-form" class="search-wrap" method="get" action="{{ route('erp.vendors.index') }}">
        <div class="search-row">
          <input id="am187-vendor-search" class="am-field" name="q"
            value="{{ $q }}" data-search-url="{{ route('erp.vendors.master.search') }}"
            data-manage-url="{{ route('erp.vendors.index') }}"
            role="combobox" aria-label="Search vendor" aria-controls="am187-vendor-results"
            aria-expanded="false" aria-autocomplete="list" autocomplete="off"
            placeholder="Type vendor name...">
          <button class="btn" type="submit">Search</button>
        </div>
        <div id="am187-vendor-results" class="suggestions" role="listbox" hidden></div>
        <div class="search-hint">Matching names highlight instantly. ↑ ↓ to choose, Enter to open.</div>
      </form>
      @forelse($vendors as $v)
        <div class="vendor-row {{ $selected && $selected->id===$v->id ? 'selected' : '' }}">
          <div>
            <div class="name">{{ $v->vendor_name }}</div>
            <div class="meta">{{ $v->vendor_code ?? '—' }} @if($v->mobile ?? null) · {{ $v->mobile }} @endif</div>
          </div>
          <a class="btn" href="{{ route('erp.vendors.index', ['vendor'=>$v->id, 'mode'=>'view', 'q'=>$q]) }}">View</a>
        </div>
      @empty
        <div class="am187-empty">No vendors found.</div>
      @endforelse
      <div class="list-footer">
        Showing {{ $vendors->count() }} of {{ $vendors->total() }} · Page {{ $vendors->currentPage() }}/{{ $vendors->lastPage() }}
        @if($vendors->hasPages())<div style="margin-top:10px">{{ $vendors->links() }}</div>@endif
      </div>
    </section>
    <section class="panel">
      @if($viewMode === 'new')
        <div class="panel-title">Add New Vendor <a class="btn" href="{{ route('erp.vendors.index') }}">Close</a></div>
        <div class="detail">
          <div class="notice info">Create vendors here. The existing NEW ERP vendor creation service handles the record.</div>
          <form method="post" action="{{ url('/erp/vendors') }}">
            @csrf
            <div class="field-grid">
              @foreach($fields as $field => $meta)
                <div class="{{ in_array($field,['address','notes']) ? 'span2' : '' }}">
                  <label class="am-label" for="am187-new-{{ $field }}">{{ $meta[0] }} @if($field==='vendor_name') <small>*</small> @endif</label>
                  @if(in_array($field,['address','notes']))
                    <textarea class="am-field" name="{{ $field }}" id="am187-new-{{ $field }}">{{ old($field) }}</textarea>
                  @else
                    <input class="am-field" id="am187-new-{{ $field }}" name="{{ $field }}" value="{{ old($field) }}" maxlength="{{ $meta[1] }}" {{ $field==='vendor_name' ? 'required' : '' }}>
                  @endif
                  @error($field)<div class="error">{{ $message }}</div>@enderror
                </div>
              @endforeach
            </div>
            <div class="actionbar" style="margin-top:15px">
              <button type="submit" class="btn primary">Save New Vendor</button>
              <a href="{{ route('erp.vendors.index') }}" class="btn">Cancel</a>
            </div>
          </form>
        </div>
      @elseif($selected && $viewMode === 'edit')
        <div class="panel-title">Edit Vendor Details <a class="btn" href="{{ route('erp.vendors.index', ['vendor'=>$selected->id, 'mode'=>'view']) }}">Cancel</a></div>
        <div class="detail">
          <div class="notice info">Vendor ID <strong>#{{ $selected->id }}</strong> and Code <strong>{{ $selected->vendor_code ?? '—' }}</strong> cannot be changed.</div>
          <form method="post" action="{{ route('erp.vendors.master.update', $selected->id) }}">
            @csrf
            <div class="field-grid">
              @foreach($fields as $field => $meta)
                <div class="{{ in_array($field,['address','notes']) ? 'span2' : '' }}">
                  <label class="am-label" for="am187-{{ $field }}">{{ $meta[0] }} @if($field==='vendor_name') <small>*</small> @endif</label>
                  @if(in_array($field,['address','notes']))
                    <textarea class="am-field" name="{{ $field }}" id="am187-{{ $field }}" maxlength="{{ $meta[1] }}">{{ old($field, $selected->{$field} ?? '') }}</textarea>
                  @else
                    <input class="am-field" id="am187-{{ $field }}" name="{{ $field }}" value="{{ old($field, $selected->{$field} ?? '') }}" maxlength="{{ $meta[1] }}" {{ $field==='vendor_name' ? 'required' : '' }}>
                  @endif
                  @error($field)<div class="error">{{ $message }}</div>@enderror
                </div>
              @endforeach
              <div class="span2">
                <label class="am-label" for="am187-reason">Correction Reason <small>*</small> (min 10 characters)</label>
                <textarea class="am-field" id="am187-reason" name="reason" required minlength="10" maxlength="500" placeholder="Explain the reason for changing vendor master details">{{ old('reason') }}</textarea>
                @error('reason')<div class="error">{{ $message }}</div>@enderror
              </div>
            </div>
            <div class="actionbar" style="margin-top:15px">
              <button type="submit" class="btn primary">Save Changes</button>
              <a href="{{ route('erp.vendors.index', ['vendor'=>$selected->id, 'mode'=>'view']) }}" class="btn">Cancel</a>
            </div>
          </form>
        </div>
      @elseif($selected)
        <div class="panel-title">Vendor Details <span class="mini">Vendor #{{ $selected->id }}</span></div>
        <div class="detail">
          <div class="vendor-hero">
            <div class="vendor-avatar">{{ mb_strtoupper(mb_substr($selected->vendor_name ?? '?',0,1)) }}</div>
            <div class="vendor-title"><strong>{{ $selected->vendor_name }}</strong>
              <span>Code: {{ $selected->vendor_code ?? '—' }}</span>
            </div>
          </div>
          <div class="info-grid">
            @foreach($fields as $field=>$meta)
              @if($field !== 'vendor_name')
                <div class="info-cell">
                  <span class="lbl">{{ $meta[0] }}</span>
                  <span class="value">{{ $selected->{$field} ?: '—' }}</span>
                </div>
              @endif
            @endforeach
          </div>
          <div class="actionbar">
            <a class="btn primary" href="{{ url('/erp/vendors/'.$selected->id.'/ledger') }}">View Ledger</a>
            <a class="btn" href="{{ route('erp.vendors.index', ['vendor'=>$selected->id, 'mode'=>'edit']) }}">Edit</a>
            @if($deleteEligibility['allowed'])
              <button class="btn danger" type="button" id="am187-toggle-delete" aria-controls="am187-delete-box" aria-expanded="false">Delete</button>
            @else
              <button class="btn danger" type="button" disabled title="Vendor has linked records or verification is unavailable">Delete Locked</button>
            @endif
          </div>
          @if(!$deleteEligibility['allowed'])
            <p class="foot-note">Delete is disabled when transactions exist or the reference check cannot verify an empty history. Existing purchases and ledger entries remain protected.</p>
          @else
            <div id="am187-delete-box" class="confirm" hidden>
              <strong>Delete unused vendor?</strong>
              <p class="foot-note">Only a vendor with no linked records can be deleted. This operation is audited and cannot be undone through the interface.</p>
              <form method="post" action="{{ route('erp.vendors.master.delete', $selected->id) }}">
                @csrf @method('DELETE')
                <label class="am-label" for="am187-delete-reason">Reason (minimum 10 characters)</label>
                <textarea id="am187-delete-reason" class="am-field" name="reason" minlength="10" maxlength="500" required placeholder="Reason for removing this unused vendor"></textarea>
                <label class="am-label" for="am187-delete-confirm" style="margin-top:10px">Type DELETE to confirm</label>
                <input class="am-field" name="confirmation" id="am187-delete-confirm" pattern="DELETE" required autocomplete="off" placeholder="DELETE">
                <div class="actionbar" style="margin-top:12px">
                  <button class="btn danger" type="submit">Permanently Delete Unused Vendor</button>
                </div>
              </form>
            </div>
          @endif
        </div>
      @else
        <div class="panel-title">Vendor Details</div>
        <div class="am187-empty" style="padding:45px 18px;text-align:center;color:#8193aa">Search and select a vendor to view details, ledger, edit or delete.</div>
      @endif
    </section>
  </div>
</div>
<script defer src="{{ asset('js/vendor-master-search-am187.js') }}?v=1871"></script>
<script>
document.addEventListener('DOMContentLoaded',function(){
 const button=document.getElementById('am187-toggle-delete');
 const box=document.getElementById('am187-delete-box');
 if(button && box)button.addEventListener('click',function(){
  box.hidden=!box.hidden;
  button.setAttribute('aria-expanded',box.hidden?'false':'true');
 });
});
</script>
@endsection
