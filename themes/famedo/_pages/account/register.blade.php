---
title: igniter.orange::default.account_register_title
permalink: /register
description: ''
layout: default
security: guest

'[igniter-orange::register]':
    agreeTermsSlug: terms-and-conditions
---
{{-- famedo override of igniter-orange::_pages.account.register — adds return-url
     capture so a guest who registers from a teaser (menu promo / cart "save X €")
     lands back where they were, with their cart intact, to finish the order.
     Register::onRegister() already calls redirect()->intended(); we seed that
     intended URL here (session survives the Livewire submit). --}}
@php
    // Local-only capture (open-redirect safe); then re-stash WITH ?welcome=1
    // (fragment-safe) so the menu pops the cart open on return.
    if ($return = \Jamasa\Core\Helpers\ReturnUrl::capture(request()->query('redirect') ?: url()->previous())) {
        redirect()->setIntendedUrl(\Jamasa\Core\Helpers\ReturnUrl::withWelcomeFlag($return));
    }
@endphp
<div class="container">
    <div class="row">
        <div class="col-sm-6 mx-auto my-5">
            <livewire:igniter-orange::register />
        </div>
    </div>
</div>
