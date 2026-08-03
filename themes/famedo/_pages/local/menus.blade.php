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

{{-- Signup-discount teaser. GUESTS see the welcome offer (links to register);
     LOGGED-IN customers who currently qualify see a reassuring "your discount is
     active" banner (with days-left for the window mode). Wording is generated
     from the extension's settings. --}}
@if(class_exists(\Iamnothardcoded\SignupDiscounts\Classes\DiscountManager::class))
    @unless(\Igniter\User\Facades\Auth::isLogged())
        @php($signupTeaser = \Iamnothardcoded\SignupDiscounts\Classes\DiscountManager::welcomeTeaser())
        @if($signupTeaser)
            <a class="promo" href="{{ page_url('account.login') }}?redirect={{ urlencode(url()->current()) }}">
                <span class="promo__ic">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/><path d="M12 8C11 5 9 4 7.6 4.8 6.2 5.6 6.7 8 12 8zM12 8c1-3 3-4 4.4-3.2C17.8 5.6 17.3 8 12 8z"/></svg>
                </span>
                <span class="promo__body">
                    <span class="promo__t">{{ $signupTeaser['headline'] }}</span>
                    <span class="promo__s">{{ $signupTeaser['subline'] }}</span>
                </span>
            </a>
        @endif
    @else
        @php($activeOffer = \Iamnothardcoded\SignupDiscounts\Classes\DiscountManager::activeOfferFor(\Igniter\User\Facades\Auth::customer(), \Igniter\Cart\Facades\Cart::content()))
        @if($activeOffer)
            <div class="activeoffer">
                <span class="activeoffer__ic">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                </span>
                <span class="activeoffer__body">
                    <span class="activeoffer__t">{{ $activeOffer['headline'] }}</span>
                    <span class="activeoffer__s">{{ $activeOffer['subline'] }}</span>
                </span>
            </div>
        @endif
    @endunless
@endif

{{-- Diet-filter chip row: normal flow, may scroll away — the sticky strip
     below carries the "you are filtered" reminder (decided 2026-08-03). --}}
@includeWhen(class_exists(\Iamnothardcoded\FoodLabels\Classes\FoodInfo::class), 'iamnothardcoded.foodlabels::filter-chips')

<div class="famedo-tabs sticky-top">
    <x-igniter-orange::category-list/>
    {{-- inside the sticky wrapper: stays visible while filtered, and the
         anchor-scroll offset ($('.sticky-top').outerHeight()) auto-corrects --}}
    @includeWhen(class_exists(\Iamnothardcoded\FoodLabels\Classes\FoodInfo::class), 'iamnothardcoded.foodlabels::filter-strip')
</div>

<div class="menu">
    <livewire:igniter-orange::menu-item-list/>
    {{-- sibling of the Livewire root — morphs can't wipe it --}}
    @includeWhen(class_exists(\Iamnothardcoded\FoodLabels\Classes\FoodInfo::class), 'iamnothardcoded.foodlabels::filter-empty')
</div>

@if(class_exists(\Iamnothardcoded\FoodLabels\Classes\FoodInfo::class))
    @include('igniter-orange::includes.menu.food-info-dialog')
@endif

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

{{-- Just came back from registering with a cart? Pop the cart sheet open so the
     new customer can finish the order and see their discount applied. --}}
@if(request()->query('welcome') && \Igniter\Cart\Facades\Cart::content()->count() > 0)
    <script>
    (function () {
        function openCart() {
            var el = document.getElementById('famedo-cart-canvas');
            if (el && window.bootstrap && bootstrap.Offcanvas) {
                bootstrap.Offcanvas.getOrCreateInstance(el).show();
                return true;
            }
            return false;
        }
        function cleanUrl() {
            if (window.history && history.replaceState) {
                var u = new URL(window.location);
                u.searchParams.delete('welcome');
                history.replaceState(null, '', u);
            }
        }
        window.addEventListener('load', function () {
            if (openCart()) { cleanUrl(); return; }
            var tries = 0, t = setInterval(function () {
                if (openCart() || ++tries > 40) { clearInterval(t); cleanUrl(); }
            }, 80);
        });
    })();
    </script>
@endif
