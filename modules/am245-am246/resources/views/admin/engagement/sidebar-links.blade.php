{{-- AM245/AM246. Insert in the EXISTING authenticated ERP Admin Customer menu.
     Do not show outstanding/messaging to roles without customer-ledger permission.
     Routes and gates must be registered in deployed ERP before including. --}}
@can('manage-customer-engagement')
    <a href="{{ route('am.customer-engagement.index') }}">Reviews &amp; Feedback</a>
    @can('view-customer-ledger')
        <a href="{{ route('am.customer-messaging.index') }}">Customer Outstanding &amp; WhatsApp</a>
    @endcan
@endcan
