{{-- jamasa/core override of igniter-orange::includes.cartbox.buttons-mobile (forked from ti-theme-orange v4.1.3) --}}
{{-- Famedo fixed cartbar, all viewports. Teleported to <body> by cart-box —
     MUST always render exactly one root element (empty teleport crashes
     Alpine: _x_teleportBack on null), so visibility is a class, not an @if.
     Opens the cart bottom sheet; suppressed on cart/checkout pages. --}}
@php
    // Stable across Livewire updates (request()->is() would see /livewire/update)
    $famedoPath = trim((string) (\Livewire\Livewire::isLivewireRequest()
        ? \Livewire\Livewire::originalPath()
        : request()->path()), '/');
    $famedoHideBar = !$cart->count() || $famedoPath === 'cart' || str_starts_with($famedoPath, 'checkout');
@endphp
<button
    type="button"
    @class(['cartbar', 'd-none' => $famedoHideBar])
    data-bs-toggle="offcanvas"
    data-bs-target="#famedo-cart-canvas"
    aria-controls="famedo-cart-canvas"
>
    <span class="cartbar__count">{{ $cart->count() }}</span>
    <span class="cartbar__label">@lang('igniter.orange::default.button_view_cart')</span>
    <span class="cartbar__total">{{ $this->cartTotal }}</span>
</button>
