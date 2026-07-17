---
title: igniter.orange::default.menus_title
permalink: '/:location?local/menus/:category?'
description: ''
layout: default

'[igniter-orange::local-header]': []
'[igniter-orange::fulfillment]': []
'[igniter-orange::category-list]': []
'[igniter-orange::menu-item-list]':
    hideMenuSearch: 1
    collapseCategoriesAfter: 100
    showThumb: 1
'[igniter-orange::cart-box]': []
'[igniter-orange::fulfillment-modal]': []
---
{{-- famedo page (forked from ti-theme-orange v4.1.3 _pages/local/menus) --}}
{{-- App-style single column: hero → mode toggle → sticky tabs → menu list.
     cart-box stays mounted (teleports the fixed cartbar; opens as bottom sheet
     via the offcanvas wrapper below). Menu search + category collapsing off. --}}
<header class="hero">
    <x-igniter-orange::local-header/>
    <x-igniter-orange::fulfillment/>
</header>

<div class="famedo-tabs sticky-top">
    <x-igniter-orange::category-list/>
</div>

<div class="menu">
    <livewire:igniter-orange::menu-item-list/>
</div>

<div
    class="offcanvas offcanvas-bottom famedo-sheet-canvas"
    id="famedo-cart-canvas"
    tabindex="-1"
    aria-labelledby="famedo-cart-canvas-label"
>
    <button
        type="button"
        class="sheet__grip"
        data-bs-dismiss="offcanvas"
        aria-label="{{ __('jamasa.core::default.ui.close') }}"
    ></button>
    <livewire:igniter-orange::cart-box/>
</div>

<livewire:igniter-orange::fulfillment-modal/>
