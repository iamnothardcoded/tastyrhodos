{{-- jamasa/core override of igniter-orange::livewire.cart-box (forked from ti-theme-orange v4.1.3) --}}
{{-- Famedo cart sheet content. Works both inside the menus-page offcanvas and
     inline on the /cart page. Single root, ids cart-box/cart-items/cart-buttons
     and the buttons-mobile @teleport preserved. --}}
<div>
    <div id="cart-box" class="famedo-cartsheet">
        <div id="cart-items" class="sheet__scroll">
            @if ($cart->count())
                <h5 class="sheet__title">@lang('igniter.cart::default.text_basket')</h5>
                @include('igniter-orange::includes.cartbox.items')

                <div class="cart-sec">@lang('igniter.cart::default.text_apply_coupon')</div>
                @include('igniter-orange::includes.cartbox.coupon-form')

                @include('igniter-orange::includes.cartbox.totals')

                @if(($minOrderTotal = $location->minimumOrderTotal()) && $cart->subtotal() < $minOrderTotal)
                    <div class="minbar">
                        <div class="minbar__txt">
                            @lang('igniter.local::default.text_min_total'): {{ currency_format($minOrderTotal) }}
                            &middot; {!! currency_format($minOrderTotal - $cart->subtotal()) !!} fehlen noch
                        </div>
                        <div class="minbar__track">
                            <div class="minbar__fill" style="width: {{ min(100, round($cart->subtotal() / $minOrderTotal * 100)) }}%"></div>
                        </div>
                    </div>
                @endif
            @else
                <div class="cart-empty">
                    <div class="cart-empty__ic"><i class="fa fa-basket-shopping fa-2x"></i></div>
                    <p class="cart-empty__t">@lang('igniter.cart::default.text_no_cart_items')</p>
                </div>
            @endif
        </div>

        <div id="cart-buttons" class="sheet__foot">
            @include('igniter-orange::includes.cartbox.buttons')
            @teleport('body')
                @include('igniter-orange::includes.cartbox.buttons-mobile')
            @endteleport
        </div>
    </div>
</div>
