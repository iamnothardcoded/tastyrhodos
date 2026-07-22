---
title: igniter.orange::default.account_address_title
layout: default
permalink: /account/address/:addressId?
security: customer

'[igniter-orange::address-book]': []
---
<div class="container my-4 my-md-5">
    <div class="account-panel">
        <a class="account-back" href="{{ page_url('account.account') }}"><i class="fa fa-chevron-left"></i> Zurück zum Konto</a>
        <livewire:igniter-orange::address-book />
    </div>
</div>
