{{-- AM242_CUSTOMER_PAYOUT_PANEL: outgoing customer payments use canonical am_customer_master. --}}
<style id="am242-customer-style">
#am242-customer-panel{grid-column:1/-1;margin-top:7px;padding:13px;border:1px solid #bdd6e7;background:#f5faff;border-radius:9px}
#am242-customer-panel[hidden]{display:none!important}
#am242-customer-panel h3{font-size:14px;margin:0 0 8px;font-weight:800;color:#123d64}
#am242-customer-panel .am242-credit{font-size:12px;font-weight:800;color:#164b72;background:#e6f2fb;border-radius:6px;padding:9px 12px;margin:6px 0}
#am242-customer-panel .am242-help{font-size:12px;line-height:1.5;color:#46627c;margin:7px 0}
#payment-form.am242-customer-active #am237-reference-slot,
#payment-form.am242-customer-active #am237-bills-slot{display:none!important}
#payment-form.am242-customer-active #am231-posting-alert{display:none!important}
#payment-form.am242-customer-active .p133-preview:before{display:none!important;content:none!important}
#payment-form.am242-customer-active #p133-save:disabled{opacity:.55;cursor:not-allowed}
</style>
<div id="am242-customer-panel" hidden>
  <h3>Customer Payment / Refund — Customer Ledger Linked</h3>
  <div class="am242-credit" id="am242-credit">Customer Ledger net credit: ₹0.00</div>
  <p class="am242-help" id="am242-customer-notice">Against Reference = refund only verified customer credit. Advance = money paid as receivable from customer. A non-billable expense must use Expense Voucher.</p>
  <input type="hidden" name="customer_payment_against" id="am242-customer-against" disabled>
  <div class="am242-help">
    Customer outgoing payment posts exactly one Payment Voucher and one Customer Ledger Debit.
    Void posts separate audited credit reversal; no vendor/staff account is used.
  </div>
  <a href="{{ route('erp.expenses.create') }}" style="font-size:12px;color:#145c99;font-weight:750">For customer compensation / goodwill expense, open New Expense Voucher</a>
</div>
<script type="application/json" id="am242-customer-balances">@json($am242CustomerBalances ?? [])</script>
