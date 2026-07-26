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
    $return = request()->query('redirect') ?: url()->previous();
    if (
        $return
        && str_starts_with($return, url('/'))
        && !str_contains($return, '/register')
        && !str_contains($return, '/login')
    ) {
        // ?welcome=1 lets the target (menu) pop the cart open so the new
        // customer can finish the order and see the discount applied.
        $return .= (str_contains($return, '?') ? '&' : '?').'welcome=1';
        redirect()->setIntendedUrl($return);
    }
@endphp
<div class="container">
    <div class="row">
        <div class="col-sm-6 mx-auto my-5">
            <livewire:igniter-orange::register />
        </div>
    </div>
</div>
