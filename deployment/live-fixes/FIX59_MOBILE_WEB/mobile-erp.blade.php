{{-- FIX59 Mobile Web ERP — authenticated layout only; desktop and print unchanged. --}}
@auth
<link rel="stylesheet" href="{{ asset('mobile-erp/mobile-erp.css') }}?v=59.1" media="screen and (max-width:767px)">
<script src="{{ asset('mobile-erp/mobile-erp.js') }}?v=59.1" defer></script>
@endauth
