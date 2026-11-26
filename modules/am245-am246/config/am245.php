<?php
// AM245 Hostinger binding remains disabled until actual company scope and
// customer WhatsApp consent are verified on staging.
return [
    'verified_single_company_scope' => false,
    // Only a reviewed customer consent timestamp may enable invitations.
    // Never treat the operator's checkbox as independent customer opt-in.
    'verified_customer_opt_in_column' => null,
];
