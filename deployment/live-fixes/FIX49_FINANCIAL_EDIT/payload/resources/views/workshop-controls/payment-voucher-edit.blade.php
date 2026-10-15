@extends('layouts.erp')
@section('title','Edit Payment Voucher — ASSAM MOTORS')
@section('content')
@include('workshop-controls.enterprise-theme')
<div class="am-modern">@include('workshop-controls.enterprise-navigation')<main class="am-content">
@php
  $number=$voucher->voucher_no??$voucher->payment_no??('Voucher #'.$voucher->id);
  $existingNote=$voucher->narration??$voucher->notes??$voucher->note??'';
  $modes=['cash'=>'CASH','bank'=>'BANK','upi'=>'UPI','online'=>'ONLINE','cheque'=>'CHEQUE','neft'=>'NEFT','rtgs'=>'RTGS','imps'=>'IMPS','card'=>'CARD','other'=>'OTHER'];
  $currentMode=strtolower((string)old('payment_mode',$voucher->payment_mode??''));
@endphp
<div class="page-head">
  <div>
    <div class="breadcrumbs"><a href="{{ route('erp.payments.index') }}">Payment Vouchers</a> / Edit</div>
    <span class="am-eyebrow">FINANCE / AUDITED CORRECTION</span><h1>Edit Payment Voucher</h1>
    <p>Voucher {{ $number }} · Original reference and vendor remain linked</p>
  </div>
  <div class="actions"><a class="btn" href="{{ route('erp.payments.index') }}">← Register</a></div>
</div>
@if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert error"><strong>Correction not saved:</strong>
  <ul>@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul></div>@endif
<div class="card am-surface" style="padding:20px;max-width:1150px">
  <div class="am-payment-facts">
    <div class="am-fact"><small>Voucher No</small><strong>{{ $number }}</strong></div>
    <div class="am-fact"><small>Against Type</small><strong>{{ $voucher->source_type??'—' }}</strong></div>
    <div class="am-fact"><small>Against Reference</small><strong>{{ $voucher->source_reference??'—' }}</strong></div>
    <div class="am-fact"><small>Record Status</small><strong><span class="am-chip {{ $active?'am-chip--ok':'am-chip--hold' }}">{{ $voucher->record_status??'ACTIVE' }}</span></strong></div>
  </div>
  @if(!$active)
    <p role="alert" style="padding:14px;background:#fff0ed;border-radius:8px">This voucher is VOID/INACTIVE. Reverse/reissue a new voucher instead of editing the original.</p>
  @else
    <form method="post" action="{{ route('erp.fix49.payment-save',$voucher->id) }}">
      @csrf
      <input type="hidden" name="expected_stamp" value="{{ $stamp }}">
      <div class="am-field-grid">
        <label>Voucher Date *<input type="date" name="payment_date" required value="{{ old('payment_date',substr((string)$voucher->payment_date,0,10)) }}"></label>
        <label>Payment Amount (₹) *<input type="number" name="amount" step="0.01" min="0.01" required value="{{ old('amount',number_format((float)$voucher->amount,2,'.','')) }}"></label>
        <label>Payment Mode *<select name="payment_mode" required>
          @if($currentMode!==''&&!array_key_exists($currentMode,$modes))<option value="{{ $currentMode }}" selected>{{ strtoupper($currentMode) }} (existing)</option>@endif
          @foreach($modes as $key=>$label)
            <option value="{{ $key }}" @selected($currentMode===$key)>{{ $label }}</option>
          @endforeach
        </select></label>
        <label>Cash/Bank Account <input type="text" name="account_name" maxlength="120" value="{{ old('account_name',$voucher->account_name??'') }}"></label>
        <label>Payment Reference <input type="text" name="reference_no" maxlength="120" value="{{ old('reference_no',$voucher->reference_no??'') }}"></label>
        <label>Note / Narration <input type="text" name="narration" maxlength="1000" value="{{ old('narration',$existingNote) }}"></label>
      </div>
      <div style="margin-top:17px;display:grid;gap:9px">
        <label>Mandatory Correction Reason *
          <textarea name="reason" rows="3" minlength="5" maxlength="1000" required placeholder="Explain the original mistake and the correct entry">{{ old('reason') }}</textarea>
        </label>
        <p style="background:#f4f7fb;padding:12px;border-radius:7px;font-size:13px">Amount is validated against the original payable source. Against Reference / Vendor changes require audited reversal and re-issue. All edits keep before/after audit history.</p>
      </div>
      <div class="actions wrap"><button type="submit" class="btn primary am-success-action">Save Audited Correction</button><a class="btn" href="{{ route('erp.payments.index') }}">Cancel</a></div>
    </form>
  @endif
</div>
</main></div>
@endsection