{{-- jamasa/core override of igniter-orange::includes.cartbox.totals (forked from ti-theme-orange v4.1.3) --}}
{{-- Famedo summary rows (tables → divs). Contracts: condition loop with
     onRemoveCondition button, tip section gating, $previewMode branches. --}}
<div id="cart-totals" class="cart-summary">
    <div class="row">
        <span>@lang('igniter.cart::default.text_sub_total'):</span>
        <span>{{ currency_format($cart->subtotal()) }}</span>
    </div>

    @foreach ($cart->conditions() as $id => $condition)
        @continue(!$previewMode && $id === 'tip' && $tipConditionValue = $condition->getValue())
        <div @class(['row', 'disc' => in_array($id, ['coupon'])])>
            <span>
                {{ $condition->getLabel() }}:
                @if (!$previewMode && $condition->removeable)
                    <button
                        type="button"
                        wire:click="onRemoveCondition('{{ $id }}')"
                    ><i
                            class="fa fa-times"
                            wire:loading.class="fa fa-spinner fa-spin"
                            wire:loading.class.remove="fa-times"
                            wire:target="onRemoveCondition"
                        ></i></button>
                @endif
            </span>
            <span>{{ is_numeric($result = $condition->getValue()) ? currency_format($result) : $result }}</span>
        </div>
    @endforeach

    @if (!$previewMode && $this->tippingEnabled())
        @php $tipCondition = $cart->getCondition('tip') @endphp
        <div class="cart-sec">{{ $tipCondition->getLabel() }} <span class="ms-auto fw-normal">{{ currency_format($tipConditionValue ?? 0) }}</span></div>
        @include('igniter-orange::includes.cartbox.tip-form')
    @endif

    <div class="row tot">
        <span>@lang('igniter.cart::default.text_order_total'):</span>
        <span>{{ $this->cartTotal }}</span>
    </div>
</div>
