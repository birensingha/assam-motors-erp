<div class="am-suite" role="navigation" aria-label="Assam Motors workshop finance">
  <div class="am-suite-inner">
    <a class="am-brand" href="{{ url('/erp/dashboard') }}">
      <span class="am-logo" aria-hidden="true">AM</span>
      <span class="am-brand-name"><b>ASSAM MOTORS</b><small>Workshop ERP · Finance Control</small></span>
    </a>
    <nav class="am-suite-nav" aria-label="Workshop tools">
      <a href="{{ url('/erp/job-cards') }}" @if(request()->is('erp/job-cards*') || request()->is('erp/workshop-tools/job-cards*')) aria-current="page" @endif>◫ Job Cards</a>
      <a href="{{ route('erp.fix48.osl-index') }}" @if(request()->is('erp/workshop-tools/osl*')) aria-current="page" @endif>▤ OSL Purchases</a>
      <a href="{{ route('erp.fix48.vendor-database') }}" @if(request()->is('erp/workshop-tools/vendor-database')) aria-current="page" @endif>▣ Vendor Database</a>
      <a href="{{ route('erp.fix49.payment-register') }}" @if(request()->is('erp/workshop-tools/payment-vouchers*')) aria-current="page" @endif>⇄ Payment Vouchers</a>
    </nav>
    <span class="am-suite-tag">STAGING · ADMIN TOOLS</span>
  </div>
</div>