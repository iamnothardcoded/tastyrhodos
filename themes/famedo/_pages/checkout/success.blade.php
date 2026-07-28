---
title: igniter.orange::default.checkout_success_title
layout: default
permalink: /checkout/success/:hash?

'[igniter-orange::order-preview]': []
'[igniter-orange::leave-review]':
    type: order
---
{{-- famedo page (forked from ti-theme-orange v4.1.3 _pages/checkout/success) --}}
<div class="famedo-page">
    <livewire:igniter-orange::order-preview />

    {{-- Guest who just ordered? Offer an instant account (their order details are
         already there — they only pick a password) so next time the discount
         applies. Renders nothing for logged-in customers / non-guest orders. --}}
    <livewire:jamasa::signup-from-order />
</div>

{{-- Per-order fields (sessionStorage, checkout tab-fields) die with the
     completed order — the next order starts fresh (phone from the account,
     both notes empty; sticky driver note deferred to the address-bound TODO). --}}
<script>
try {
    sessionStorage.removeItem('checkout_order_phone');
    sessionStorage.removeItem('checkout_order_note');
    sessionStorage.removeItem('checkout_delivery_note');
} catch (e) { /* storage blocked — nothing to clear */ }
</script>
