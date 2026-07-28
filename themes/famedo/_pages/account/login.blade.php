---
title: igniter.orange::default.account_login_title
layout: default
permalink: /login
security: guest
---
{{-- famedo override of igniter-orange::_pages.account.login — replaces the
     password login (+ socialite) with the passwordless email-code flow
     (jamasa EmailCodeLogin). All existing „Anmelden" links (nav, checkout
     soft-link) route here unchanged. Return-URL capture mirrors the register
     override: whoever arrives from menu/checkout goes back there after
     sign-in via the success step's redirect()->intended(). --}}
@php
    \Jamasa\Core\Helpers\ReturnUrl::capture(request()->query('redirect') ?: url()->previous());
@endphp
<livewire:jamasa::email-code-login />
