@extends('layouts.erp')
@section('title','New Payment Voucher · ASSAM MOTORS')
@section('topbar_title','Payment Voucher')
@section('content')
<style>
/* FIX133 Money Receipt-inspired voucher design, existing backend IDs preserved. */
.p133-wrap{max-width:1270px;margin:0 auto;padding:0 3px}
.p133-heading{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:15px}
.p133-heading h1{margin:0;font-size:25px;color:#103d70}.p133-heading p{color:#64778b;margin:4px 0}
.p133-back{padding:10px 14px;background:#f3f8fe;border:1px solid #cbddec;color:#134b7b;border-radius:9px;text-decoration:none;font-size:12px;font-weight:800}
.p133-sheet{background:#fff;border:1px solid #d5e2ee;box-shadow:0 5px 20px #153f6609;border-radius:13px;overflow:hidden;margin-bottom:18px;padding:0}
.p133-band{padding:15px 18px;background:linear-gradient(110deg,#114176,#15649a);color:#fff;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.p133-band strong{font-size:16px}.p133-band small{color:#d2e9fc}
.p133-body{padding:17px;display:grid;grid-template-columns:minmax(0,1.5fr) minmax(290px,1fr);gap:16px}
.p133-group{padding:13px;border:1px solid #dde8f1;border-radius:12px;min-width:0;margin-bottom:13px}
.p133-group h2{font-size:14px;margin:0 0 11px;color:#154976}
.p133-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px}
.p133-full{grid-column:1/-1}.p133-label{display:flex;flex-direction:column;gap:5px;font-size:10.8px;color:#345775;font-weight:800;text-transform:uppercase}
.p133-label>select,.p133-label>input,.p133-label>textarea{min-height:40px;border:1px solid #cbdcea;border-radius:8px;padding:8px 9px;background:#fff;color:#193c5e;font:normal 13px Arial,sans-serif;max-width:100%;width:100%}
.p133-label input:focus,.p133-label select:focus,.p133-label textarea:focus{outline:2px solid #73a9d4}
.p133-label textarea{min-height:70px}
.p133-help{font-size:11px;color:#58718a;margin:5px 0 0}
.p133-toggle{display:flex;align-items:center;gap:9px;margin:5px 0 10px;background:#e8f5ff;border:1px solid #bad9ee;border-radius:9px;padding:10px;color:#174d75;font-size:12px;font-weight:800;text-transform:none}
.p133-toggle input{accent-color:#14609b;min-width:16px;min-height:16px}
.p133-multi{border:1px solid #b7d2e7;background:#f4faff;border-radius:9px;padding:10px;margin:7px 0}
.p133-bills{display:grid;gap:7px;max-height:290px;overflow-y:auto;margin:10px 0}
.p133-bill{display:grid;grid-template-columns:auto minmax(0,1fr) 115px;gap:8px;align-items:center;background:#fff;border:1px solid #cbddec;border-radius:8px;padding:8px}
.p133-bill label{font-size:11px;font-weight:700;color:#143d60;min-width:0;overflow-wrap:anywhere}
.p133-bill input[type=checkbox]{accent-color:#14588e}
.p133-bill input[type=number]{min-width:0;width:100%;height:34px;border:1px solid #bed3e6;border-radius:6px;padding:5px}
.p133-preview{display:grid;gap:9px}
.p133-preview .source-preview{display:grid;grid-template-columns:1fr;gap:9px;background:#f2faff;border:1px solid #d3e6f5;border-radius:9px;padding:11px}
.p133-preview .source-preview>div{display:flex;justify-content:space-between;gap:7px;color:#41617c;font-size:12px}
.p133-preview .source-preview strong{color:#14476f;text-align:right}
.p133-value{display:flex;justify-content:space-between;gap:13px;border:1px solid #dce7f0;padding:10px 12px;border-radius:9px;color:#3a5b75;font-size:12px}
.p133-value strong{font-size:14px;color:#114c7b}
.p133-total{background:#124779;color:#fff;border:0}.p133-total strong{font-size:22px;color:white}
.p133-guide{padding:10px 12px;background:#edf7ff;color:#194e83;border:1px solid #b2d2e9;border-radius:9px;font-size:11px;line-height:1.5;margin-top:9px}
.p133-errors{padding:11px 14px;color:#a43738;background:#fff0ef;border-left:4px solid #c34a47;border-radius:8px;margin-bottom:13px}
.p133-footer{border-top:1px solid #dce7f1;background:#fafcff;padding:14px 17px;display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap}
.p133-note{font-size:11px;color:#775a2d;background:#fff9ea;border:1px solid #ead49c;padding:9px 12px;border-radius:8px;max-width:650px}
.p133-save{background:#16754b;border:0;color:#fff;font-weight:800;padding:12px 19px;border-radius:9px;min-height:43px;cursor:pointer;font-size:13px}
.p133-save:disabled{opacity:.5}.p133-error-inline{color:#9e3131;font-size:12px;font-weight:750}
.p133-sheet #manual-source-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px}
.p133-sheet #known-source-field{display:flex}
.p133-sheet #source-preview{min-height:62px}
@media(max-width:900px){.p133-body{grid-template-columns:1fr}}
@media(max-width:620px){.p133-body{padding:10px}.p133-grid,.p133-sheet #manual-source-fields{grid-template-columns:1fr}.p133-heading h1{font-size:20px}.p133-bill{grid-template-columns:auto minmax(0,1fr) 95px}}
@media print{.p133-footer,.p133-heading .p133-back{display:none}}
</style>
<div class="p133-wrap">
<div class="p133-heading"><div><h1>New Payment Voucher</h1><p>Vendor Payments · Against Reference · Advance · On Account</p></div><a class="p133-back" href="{{ route('erp.payments.index') }}">← Payment Register</a></div>
@if($errors->any())<div class="p133-errors" role="alert"><strong>Payment not saved. Please correct:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="post" action="{{ route('erp.payments.store') }}" class="p133-sheet" id="payment-form">@csrf
<input type="hidden" name="request_key" value="{{ old('request_key',(string)\Illuminate\Support\Str::uuid()) }}">
<div class="p133-band"><strong>ASSAM MOTORS · PAYMENT VOUCHER</strong><small>Voucher number generated on posting · Supplier ledger payment acknowledgement</small></div>
<div class="p133-body">
<div>
<section class="p133-group"><h2>1. Payment Against / Vendor</h2>
<div class="p133-grid">
<label class="p133-label">Payment Date *<input type="date" name="payment_date" value="{{ old('payment_date',now('Asia/Kolkata')->toDateString()) }}" required></label>
<label class="p133-label">Payment Against *
<select name="source_type" id="source-type" required>
<option value="PURCHASE_INVOICE">Against Reference — Purchase Bill</option><option value="EXPENSE_VOUCHER">Expense Voucher</option><option value="OSL_JOB">OSL Job</option><option value="ADVANCE">Advance — Supplier</option><option value="ON_ACCOUNT">On Account — Unallocated</option><option value="OTHER">Other Payment</option>
</select></label>
<div class="p133-full">
<label class="p133-toggle" id="p133-multi-toggle-wrap"><input type="checkbox" id="p133-multi-toggle" name="multi_bill" value="1" @checked(old('multi_bill'))> Multiple Purchase Bills — one Payment Voucher</label>
<label class="p133-label p133-full" id="known-source-field">Select Outstanding Source *
<select name="source_key" id="source-key"><option value="">Select outstanding item</option></select>
<small id="source-help" class="p133-help">Outstanding invoices / vouchers appear here.</small></label>
<div class="p133-multi" id="p133-multi-area" hidden>
 <label class="p133-label">Select Vendor *<select id="p133-multi-vendor"><option value="">— Select Vendor —</option></select></label>
 <div id="p133-multi-bills" class="p133-bills" aria-label="Bills with allocation amounts"></div>
 <div class="p133-error-inline" id="p133-multi-error" role="alert"></div>
 <small class="p133-help">Choose 2 or more outstanding bills from the SAME vendor. Set the amount for each bill. Server verifies balances again before posting.</small>
</div>
</div>
<div id="manual-source-fields" class="p133-full">
<label class="p133-label">Vendor (required for On Account)<select name="vendor_id" id="payment-vendor"><option value="">No Vendor / Other Payee</option>@foreach($vendors as $v)<option value="{{ $v['id'] }}">{{ $v['vendor_code'] }} — {{ $v['vendor_name'] }}</option>@endforeach</select></label>
<label class="p133-label">Payee Name *<input name="payee_name" id="payment-payee" maxlength="180" value="{{ old('payee_name') }}"></label>
<label class="p133-label">Job Card (optional)<select name="job_card_id"><option value="">No Job Card</option>@foreach($jobs as $j)<option value="{{ $j['id'] }}">{{ $j['job_no'] }} — {{ $j['vehicle_reg_no'] }} — {{ $j['customer_name'] }}</option>@endforeach</select></label>
<label class="p133-label">Reference / Purpose<input name="source_reference" maxlength="180" value="{{ old('source_reference') }}" placeholder="Advance reference or purpose"></label>
</div>
</div></section>
<section class="p133-group"><h2>2. Payment Details</h2>
<div class="p133-grid">
<label class="p133-label">Payment Mode *
<select name="payment_mode" id="p133-payment-mode" required>
@foreach(['Cash','UPI','Bank Transfer','Cheque','Card'] as $mode)<option value="{{ $mode }}" @selected(old('payment_mode','Cash')===$mode)>{{ $mode }}</option>@endforeach
</select></label>
<label class="p133-label">Cash / Bank Account<input name="account_name" maxlength="120" value="{{ old('account_name') }}" placeholder="Cash / SBI / HDFC"></label>
<label class="p133-label">Amount Paid ₹ *<input type="number" name="amount" id="payment-value" min="0.01" step="0.01" value="{{ old('amount') }}" required></label>
<label class="p133-label">UTR / Cheque / Transaction Ref<input name="reference_no" maxlength="150" value="{{ old('reference_no') }}" placeholder="UPI Ref / Cheque No."></label>
<label class="p133-label p133-full">Payment Note<textarea name="notes" rows="2" maxlength="2000">{{ old('notes') }}</textarea></label>
</div>
</section>
</div>
<aside class="p133-preview"><section class="p133-group"><h2>3. Payment Preview</h2>
<div id="source-preview" class="source-preview payment-source-wide"></div>
<div class="p133-value"><span>Mode</span><strong id="p133-preview-mode">Cash</strong></div>
<div class="p133-value"><span>References selected</span><strong id="p133-preview-count">—</strong></div>
<div class="p133-value p133-total"><span>Total Amount Paid</span><strong id="p133-preview-total">₹0.00</strong></div>
<p class="p133-guide" id="settlement-guidance">Against Reference settles selected Purchase Bill(s) at the allocated amount. Advance/On Account payments remain unallocated until formally adjusted.</p>
<p class="p133-help">Payment history and underlying bill allocations are retained. Financial posting is final only after server validation.</p>
</section></aside>
</div>
<div class="p133-footer"><div class="p133-note">Verify vendor, payment mode, UTR and all bill allocations. One voucher will be posted, with individual references recorded for each selected bill.</div>
<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap"><a class="p133-back" href="{{ route('erp.payments.index') }}">Cancel</a><button class="p133-save" id="p133-save" type="submit">✓ POST PAYMENT VOUCHER</button></div></div>
</form>
</div>
@endsection
@push('scripts')
<script>window.AM_PAYMENT_SOURCES=@json($sources);window.AM_PAYMENT_PRESET=@json(['source'=>request('source'),'id'=>request('id')]);</script>
<script src="{{ asset('js/payment-voucher.js') }}" defer></script>
<script src="{{ asset('js/payment-voucher-multi.js') }}?v=133" defer></script>
@endpush
