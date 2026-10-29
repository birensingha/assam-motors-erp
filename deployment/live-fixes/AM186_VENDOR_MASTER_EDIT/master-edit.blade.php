@extends('layouts.erp')
@section('title','Vendor Master — Edit | Assam Motors')
@section('topbar_title','Vendor Master — Edit')
@section('content')
<style>
.am186{--am-border:#dfe7f0;--am-ink:#203450;--am-muted:#6b7f9b;--am-blue:#1959ad;color:var(--am-ink);font-family:Inter,"Segoe UI",Arial,sans-serif}
.am186 *{box-sizing:border-box}
.am186-head{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:17px 20px;margin-bottom:14px;background:linear-gradient(100deg,#fff,#edf5ff);border:1px solid var(--am-border);border-radius:12px}
.am186-head h1{font-size:20px;margin:0 0 5px;font-weight:800;color:#163c70}
.am186-head p{margin:0;color:var(--am-muted);font-size:12px}
.am186-btn{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:8px 14px;border-radius:7px;border:1px solid #cddbec;background:#fff;color:#1d579d;text-decoration:none;font-size:12px;font-weight:700;cursor:pointer}
.am186-btn.primary{background:#1755a4;color:#fff;border-color:#1755a4}
.am186-btn:hover{filter:brightness(.955)}
.am186-layout{display:grid;grid-template-columns:minmax(320px,.92fr) minmax(390px,1.25fr);gap:14px;align-items:start}
.am186-panel{background:#fff;border:1px solid var(--am-border);border-radius:11px;overflow:hidden;box-shadow:0 3px 10px rgba(14,45,86,.04)}
.am186-panel-title{padding:15px 17px;border-bottom:1px solid #e6ecf4;background:#fbfdff;font-weight:800;color:#24486f;font-size:14px}
.am186-subtle{display:block;font-size:11px;color:var(--am-muted);font-weight:400;margin-top:4px}
.am186-search{display:flex;gap:8px;padding:13px 15px;border-bottom:1px solid var(--am-border)}
.am186-search input,.am186-field input,.am186-field textarea{width:100%;min-width:0;max-width:100%;background:#fff!important;border:1px solid #d4e0ed!important;border-radius:7px!important;padding:9px 10px!important;outline:none;min-height:37px;font-size:12px!important;color:#213a57!important;box-shadow:none!important}
.am186-field input:focus,.am186-field textarea:focus,.am186-search input:focus{border-color:#3275c8!important;box-shadow:0 0 0 3px #3275c81a!important}
.am186-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:center;padding:11px 15px;border-bottom:1px solid #edf1f6}
.am186-row:last-child{border-bottom:0}.am186-row.selected{background:#edf5ff}
.am186-vendor{font-size:13px;font-weight:700;overflow-wrap:anywhere}.am186-code{display:block;font-size:11px;color:#7385a0;margin-top:3px;font-weight:500}
.am186-page{padding:12px 15px;font-size:11px;color:#7487a3}
.am186-form{padding:16px}
.am186-readonly{display:flex;flex-wrap:wrap;gap:8px;padding:11px 13px;margin-bottom:14px;background:#f4f8fe;border:1px solid #e1eafa;border-radius:8px;color:#4a6282;font-size:11px}
.am186-readonly strong{color:#1d4475}
.am186-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.am186-field{display:block;min-width:0}
.am186-field label{display:block;margin:0 0 5px;font-weight:700;color:#5d708b;font-size:11px}
.am186-field textarea{min-height:80px;resize:vertical}
.am186-field.wide{grid-column:1/-1}
.am186-reason{margin:17px 0 12px;padding:13px;background:#f8fbff;border-radius:8px;border:1px solid #e1eafa}
.am186-reason label{display:block;font-weight:750;font-size:11px;margin-bottom:7px}
.am186-help{display:block;color:#7688a1;font-size:10.5px;line-height:1.45;margin-top:5px}
.am186-actions{display:flex;align-items:center;gap:9px;flex-wrap:wrap}
.am186-message{padding:11px 14px;border-radius:8px;margin-bottom:12px;font-size:12px}
.am186-message.ok{background:#ecf9f1;border:1px solid #bbe3ca;color:#166b40}
.am186-message.error{background:#fff1f1;border:1px solid #efc1c1;color:#a22d2d}
.am186-field-error{font-size:10px;color:#b72f2f;margin-top:4px}
.am186-empty{padding:35px 15px;text-align:center;color:#7b8da6;font-size:13px}
@media(max-width:950px){.am186-layout{grid-template-columns:1fr}}
@media(max-width:520px){.am186-head{flex-wrap:wrap;padding:14px}.am186-grid{grid-template-columns:1fr}.am186-layout{gap:10px}.am186-form{padding:13px}}
</style>
<div class="am186">
  <div class="am186-head">
    <div>
      <h1>Vendor Master</h1>
      <p>Correct vendor details with audit history. Purchases, OSL, payments and ledger stay linked to the original Vendor ID.</p>
    </div>
    <a class="am186-btn" href="{{ route('erp.vendors.index') }}">Back to Vendor Master</a>
  </div>

  @if(session('success'))
    <div class="am186-message ok">{{ session('success') }}</div>
  @endif
  @if($errors->any())
    <div class="am186-message error">Could not save Vendor Master changes. Please correct the fields shown below.</div>
  @endif

  <div class="am186-layout">
    <section class="am186-panel">
      <div class="am186-panel-title">Select Vendor <span class="am186-subtle">Search by name, Vendor Code, mobile or GSTIN</span></div>
      <form method="get" action="{{ route('erp.vendors.master.manage') }}" class="am186-search">
        <input name="q" value="{{ $q }}" placeholder="Search vendors..." autocomplete="off">
        <button class="am186-btn primary" type="submit">Search</button>
      </form>
      @forelse($vendors as $v)
        <div class="am186-row {{ $selected && $selected->id == $v->id ? 'selected' : '' }}">
          <div class="am186-vendor">
            {{ $v->vendor_name }}
            <span class="am186-code">{{ $v->vendor_code ?? ('Vendor #'.$v->id) }} @if(!empty($v->mobile)) · {{ $v->mobile }} @endif</span>
          </div>
          <a class="am186-btn" href="{{ route('erp.vendors.master.manage', ['edit'=>$v->id, 'q'=>$q]) }}">Edit</a>
        </div>
      @empty
        <div class="am186-empty">No vendors found.</div>
      @endforelse
      <div class="am186-page">Showing {{ $vendors->count() }} of {{ $vendors->total() }} vendors · Page {{ $vendors->currentPage() }} of {{ $vendors->lastPage() }}</div>
      @if($vendors->hasPages())
        <div class="am186-page">{{ $vendors->links() }}</div>
      @endif
    </section>

    <section class="am186-panel">
      <div class="am186-panel-title">Edit Vendor Details
        <span class="am186-subtle">Only master information is editable. Vendor Code and historical financial records cannot be overwritten.</span>
      </div>
      @if($selected)
        <form class="am186-form" method="post" action="{{ route('erp.vendors.master.update', $selected->id) }}">
          @csrf
          <div class="am186-readonly">
            <span>Vendor ID: <strong>#{{ $selected->id }}</strong></span>
            <span>Vendor Code: <strong>{{ $selected->vendor_code ?? '—' }}</strong></span>
          </div>
          <div class="am186-grid">
            @foreach($fields as $field => $meta)
              <div class="am186-field {{ in_array($field, ['address','address_line1','address_line2','notes','remarks']) ? 'wide' : '' }}">
                <label for="am186-{{ $field }}">{{ $meta['label'] }} @if($field==='vendor_name') * @endif</label>
                @if(in_array($field,['notes','remarks','address']))
                  <textarea id="am186-{{ $field }}" name="{{ $field }}" maxlength="{{ $meta['limit'] }}">{{ old($field, $selected->{$field} ?? '') }}</textarea>
                @else
                  <input id="am186-{{ $field }}" name="{{ $field }}" value="{{ old($field, $selected->{$field} ?? '') }}"
                    maxlength="{{ $meta['limit'] }}" {{ $field==='vendor_name' ? 'required' : '' }}>
                @endif
                @error($field)<div class="am186-field-error">{{ $message }}</div>@enderror
              </div>
            @endforeach
          </div>
          <div class="am186-reason">
            <label for="am186-reason">Correction Reason * (minimum 10 characters)</label>
            <textarea id="am186-reason" name="reason" rows="2" required minlength="10" maxlength="500"
              style="width:100%;min-height:66px;border:1px solid #cbd9ed;border-radius:7px;padding:9px;font-size:12px"
              placeholder="Explain why vendor details are being corrected">{{ old('reason') }}</textarea>
            @error('reason')<div class="am186-field-error">{{ $message }}</div>@enderror
            <span class="am186-help">Changes are recorded with before/after values, reason and Admin account. Existing ledgers, purchases and payments remain unchanged.</span>
          </div>
          <div class="am186-actions">
            <button class="am186-btn primary" type="submit">Save Vendor Changes</button>
            <a class="am186-btn" href="{{ url('/erp/vendors/'.$selected->id.'/ledger') }}">View Vendor Ledger</a>
            <a class="am186-btn" href="{{ route('erp.vendors.master.manage') }}">Cancel</a>
          </div>
        </form>
      @else
        <div class="am186-empty">Select a vendor using the <strong>Edit</strong> button to correct its master details.</div>
      @endif
    </section>
  </div>
</div>
@endsection
