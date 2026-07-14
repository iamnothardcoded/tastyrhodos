---
title: igniter.orange::default.cart_title
layout: default
permalink: /cart

'[igniter-orange::cart-box]': []
---
{{-- famedo page (forked from ti-theme-orange v4.1.3 _pages/cart) --}}
{{-- Fallback/direct-URL cart page: app column, back link, inline cart sheet. --}}
<div class="famedo-page">
    <div class="famedo-page__back" wire:ignore>
        <a href="{{ page_url('local.menus') }}">
            <i class="fa fa-arrow-left-long"></i>&nbsp;&nbsp;
            @lang('igniter.orange::default.button_back')
        </a>
    </div>
    <livewire:igniter-orange::cart-box />
</div>
