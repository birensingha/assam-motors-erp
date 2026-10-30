{{-- AM213 OSL Purchase View action strip.
     Only include after the live Show controller supplies a verified
     $am213Lines Collection and a canonical $am213Vendor from workshop_vendors.
     NEVER estimate payable balance or post payments in the browser.
     Existing edit and payment controllers remain the sole writers.
--}}
@php
    $am213Lines = ($am213Lines ?? collect())->filter(function($line) {
        return (int)($line->id ?? 0) > 0
            && strtoupper(trim((string)($line->record_status ?? 'ACTIVE'))) === 'ACTIVE'
            && empty($line->deleted_at);
    })->values();
    $am213VendorId = (int)($am213Vendor->id ?? 0);
    $am213ValidVendor = $am213VendorId > 0
        && $am213Lines->isNotEmpty()
        && $am213Lines->every(
            fn($line) => (int)($line->vendor_id ?? 0) === $am213VendorId
        );
    $am213CanEdit = \Illuminate\Support\Facades\Route::has('erp.fix48.osl-edit');
    $am213CanPay = \Illuminate\Support\Facades\Route::has('erp.payments.create');
    $am213CanLedger = \Illuminate\Support\Facades\Route::has('erp.vendors.ledger');
@endphp
<section class="am213-osl-purchase-actions" aria-label="OSL Purchase Financial Actions">
 <style>
 .am213-osl-purchase-actions{margin:14px 0;padding:13px 15px;border:1px solid #c6d7e8;border-radius:10px;background:#f7fbff}
 .am213-osl-purchase-actions h3{font-size:13px;color:#173e68;font-weight:800;margin:0 0 9px}
 .am213-osl-links{display:flex;gap:8px;flex-wrap:wrap}
 .am213-osl-links a{display:inline-flex;padding:9px 13px;border:1px solid #abc5df;border-radius:7px;background:white;text-decoration:none;color:#144d7c;font-size:12px;font-weight:750}
 .am213-osl-links a.am213-pay{background:#15794f;border-color:#15794f;color:white}
 .am213-osl-notice{font-size:11px;color:#6c5f31;margin:8px 0 0}
 </style>
 <h3>OSL Purchase · Vendor Actions</h3>
 @if(!$am213ValidVendor)
   <p class="am213-osl-notice">Vendor Master link is missing or the purchase lines refer to different vendors. Payment is safely blocked until the supplier identity is reconciled; no arbitrary vendor will be selected.</p>
 @else
   <div class="am213-osl-links">
     @if($am213CanLedger)
       <a href="{{ route('erp.vendors.ledger', $am213VendorId) }}">Vendor Ledger</a>
     @endif
     @if($am213Lines->count() === 1)
       @php $am213Line = $am213Lines->first(); @endphp
       @if($am213CanEdit)
         <a href="{{ route('erp.fix48.osl-edit', (int) $am213Line->id) }}">Edit OSL Purchase</a>
       @endif
       @if($am213CanPay)
         <a class="am213-pay" href="{{ route('erp.payments.create', ['source' => 'OSL_JOB', 'id' => (int) $am213Line->id]) }}">Make Payment · Against Reference</a>
       @endif
     @else
       <span class="am213-osl-notice">This purchase has {{ $am213Lines->count() }} active lines. Each line uses its original Purchase ID. Choose the correct line; do not pay the purchase twice.</span>
       @foreach($am213Lines as $am213Line)
         @if($am213CanEdit)
           <a href="{{ route('erp.fix48.osl-edit', (int)$am213Line->id) }}">Edit Line #{{ (int)$am213Line->id }}</a>
         @endif
       @endforeach
     @endif
   </div>
   <p class="am213-osl-notice">A displayed button does not confirm payment eligibility. The existing Payment Voucher must verify outstanding, source reference and vendor ledger before posting. Paid lines require audited financial correction.</p>
 @endif
</section>