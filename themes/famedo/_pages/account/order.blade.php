---
title: igniter.orange::default.account_order_title
layout: default
permalink: /account/order/:hash
security: all

'[igniter-orange::order-preview]':
    hideReorderBtn: false
    showCancelButton: true
'[igniter-orange::leave-review]':
    type: order
---
<div class="container my-4 my-md-5">
    <div class="account-panel">
        @if(\Igniter\User\Facades\Auth::isLogged())
        <a class="account-back" href="{{ page_url('account.orders') }}"><i class="fa fa-chevron-left"></i> Zurück zu Bestellungen</a>
        @else
        <a class="account-back" href="{{ page_url('local.menus') }}"><i class="fa fa-chevron-left"></i> Zur Speisekarte</a>
        @endif
        <livewire:igniter-orange::order-preview/>
    </div>
</div>
