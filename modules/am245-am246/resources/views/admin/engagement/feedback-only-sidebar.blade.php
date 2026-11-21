{{-- AM245 only. Include in the existing Customer sidebar after routes & admin Gate are active.
     Never show WhatsApp/financial messaging links here. --}}
@can('manage-customer-engagement')
    <a href="{{ route('am.customer-engagement.index') }}">Reviews &amp; Feedback</a>
@endcan
