@extends('layouts.erp')
@section('title','Edit Payment Voucher — ASSAM MOTORS')
@section('content')
@php
  $number=$voucher->voucher_no??$voucher->payment_no??('Voucher #'.$voucher->id);
  $existingNote=$voucher->narration??$voucher->notes??$voucher->note??'';
  $modes=['cash'=>'CASH','bank'=>'BANK','upi'=>'UPI','online'=>'ONLINE','cheque'=>'CHEQUE','neft'=>'NEFT','rtgs'=>'RTGS','imps'=>'IMPS','card'=>'CARD','other'=>'OTHER'];
  $currentMode=strtolower((string)old('payment_mode',$voucher->payment_mode??''));
@endphp
<div class="page-head">
  <div>
    <div class="breadcrumbs"><a href="{{ route('erp.payments.index') }}">Payment Vouchers</a> / Edit</div>
    <h1>Payment Voucher — Edit</h1>
    <p>Voucher {{ $number }} · Original reference and vendor remain linked</p>
  </div>
  <div class="actions"><a class="btn" href="{{ route('erp.payments.index') }}">← Register</a></div>
</div>
@if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert error"><strong>Correction not saved:</strong>
  <ul>@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul></div>@endif
<div class="card" style="padding:18px;max-width:1150px">
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px;margin-bottom:20px">
    <div><small>Voucher</small><h3>{{ $number }}</h3></div>
    <div><small>Against Type</small><h3>{{ $voucher->source_type??'—' }}</h3></div>
    <div><small>Against Reference</small><h3>{{ $voucher->source_reference??'—' }}</h3></div>
    <div><small>Record Status</small><h3>{{ $voucher->record_status??'ACTIVE' }}</h3></div>
  </div>
  @if(!$active)
    <p role="alert" style="padding:14px;background:#fff0ed;border-radius:8px">This voucher is VOID/INACTIVE. Reverse/reissue a new voucher instead of editing the original.</p>
  @else
    <form method="post" action="{{ route('erp.fix49.payment-save',$voucher->id) }}">
      @csrf
      <input type="hidden" name="expected_stamp" value="{{ $stamp }}">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:15px">
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
      <div class="actions wrap"><button type="submit" class="btn primary">Save Voucher Correction</button><a class="btn" href="{{ route('erp.payments.index') }}">Cancel</a></div>
    </form>
  @endif
</div>
@endsection