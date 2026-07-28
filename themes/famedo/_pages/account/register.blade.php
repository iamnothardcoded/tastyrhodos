---
title: igniter.orange::default.account_register_title
permalink: /register
description: ''
layout: default
security: guest
---
{{-- famedo override — accounts are passwordless (email-code), so /register is
     the SAME unified flow as /login (it signs up unknown emails automatically).
     Mounting the component here — rather than the vendor password-register form —
     closes the orphan: that form created password accounts whose password could
     never be changed (the profile has no password section). Any stray /register
     link now lands on the correct flow. --}}
@php
    \Jamasa\Core\Helpers\ReturnUrl::capture(request()->query('redirect') ?: url()->previous());
@endphp
<livewire:jamasa::email-code-login />
