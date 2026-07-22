---
title: igniter.orange::default.account_orders_title
layout: default
permalink: /account/orders
security: customer

'[igniter-orange::order-list]': []
---
<div class="container my-4 my-md-5">
    <div class="account-panel">
        <a class="account-back" href="{{ page_url('account.account') }}"><i class="fa fa-chevron-left"></i> Zurück zum Konto</a>
        <div class="card">
            <div class="card-body">
                <x-igniter-orange::order-list/>
            </div>
        </div>
    </div>
</div>
