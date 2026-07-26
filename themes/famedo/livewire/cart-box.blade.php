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
                            &middot; {!! __('jamasa.core::default.cart.amount_missing', ['amount' => currency_format($minOrderTotal - $cart->subtotal())]) !!}
                        </div>
                        <div class="minbar__track">
                            <div class="minbar__fill" style="width: {{ min(100, round($cart->subtotal() / $minOrderTotal * 100)) }}%"></div>
                        </div>
                    </div>
                @endif

                {{-- Guest-only "discount you miss": the exact € a not-yet-registered
                     visitor would save on THIS cart via the welcome discount.
                     Re-renders with the cart (this is the CartBox Livewire view). --}}
                @unless(\Igniter\User\Facades\Auth::isLogged())
                    @php($signupSave = class_exists(\Iamnothardcoded\SignupDiscounts\Classes\DiscountManager::class)
                        ? \Iamnothardcoded\SignupDiscounts\Classes\DiscountManager::wouldBeWelcomeAmount(\Igniter\Cart\Facades\Cart::content()) : 0)
                    @if($signupSave > 0)
                        <a class="cart-save" href="{{ page_url('account.register') }}">
                            <span class="cart-save__ic">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/><path d="M12 8C11 5 9 4 7.6 4.8 6.2 5.6 6.7 8 12 8zM12 8c1-3 3-4 4.4-3.2C17.8 5.6 17.3 8 12 8z"/></svg>
                            </span>
                            <span class="cart-save__body">
                                <span class="cart-save__t">{{ __('iamnothardcoded.signupdiscounts::default.teaser_cart', ['amount' => currency_format($signupSave)]) }}</span>
                                <span class="cart-save__s">@lang('iamnothardcoded.signupdiscounts::default.teaser_cart_sub')</span>
                            </span>
                        </a>
                    @endif
                @endunless
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
