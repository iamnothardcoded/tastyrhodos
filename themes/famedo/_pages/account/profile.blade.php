---
title: igniter.orange::default.account_title
permalink: /account/profile
layout: default
security: customer

'[igniter-orange::account-settings]': []
---
<div class="container my-4 my-md-5">
    <div class="account-panel">
        <a class="account-back" href="{{ page_url('account.account') }}"><i class="fa fa-chevron-left"></i> Zurück zum Konto</a>
        <livewire:igniter-orange::account-settings />
    </div>
</div>
