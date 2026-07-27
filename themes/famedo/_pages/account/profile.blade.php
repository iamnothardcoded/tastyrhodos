---
title: igniter.orange::default.account_title
permalink: /account/profile
layout: default
security: customer

'[igniter-orange::account-settings]': []
---
{{-- Mounts the jamasa AccountSettings subclass (immutable email +
     return-to-checkout). Arriving from the checkout identity card
     (?return=checkout): the back-link becomes „Zurück zur Kasse" — the
     account-hub link would break the order flow — and a successful save
     returns to the checkout automatically. --}}
@php($fromCheckout = request()->query('return') === 'checkout')
<div class="container my-4 my-md-5">
    <div class="account-panel">
        @if($fromCheckout)
            <a class="account-back" href="{{ page_url('checkout.checkout') }}"><i class="fa fa-chevron-left"></i> Zurück zur Kasse</a>
        @else
            <a class="account-back" href="{{ page_url('account.account') }}"><i class="fa fa-chevron-left"></i> Zurück zum Konto</a>
        @endif
        <livewire:jamasa::account-settings :return-to="$fromCheckout ? 'checkout' : ''" />
    </div>
</div>
