{{-- jamasa/core override of igniter-orange::includes.cartbox.tip-form (forked from ti-theme-orange v4.1.3) --}}
{{-- Famedo tip buttons. Contracts: root x-data, wire:click="onApplyTip(...)"
     + wire:target, active class logic, x-show custom input + wire:model.change. --}}
<div x-data="{isCustom: '{{$isCustomTip}}', tipAmount: {{$tipAmount}}}" id="cart-tip">
    @if ($tips = $this->tippingAmounts())
        <div class="tips flex-wrap">
            <button
                wire:click="onApplyTip(0)"
                wire:loading.class="disabled"
                wire:target="onApplyTip"
                class="tipbtn"
                type="button"
            >@lang('igniter.cart::default.text_no_tip')</button>
            @foreach ($tips as $tip)
                <button
                    wire:click="onApplyTip({{ $tip->value }})"
                    wire:loading.class="disabled"
                    wire:target="onApplyTip"
                    @class(['tipbtn', 'active' => $tipAmount == $tip->value])
                    type="button"
                >{{ $tip->valueType != 'F' ? round($tip->value).'%' : currency_format($tip->value) }}</button>
            @endforeach
            <button
                x-on:click="isCustom = !isCustom"
                wire:loading.class="disabled"
                wire:target="onApplyTip"
                @class(['tipbtn', 'active' => $isCustomTip])
                type="button"
            >@lang('igniter.cart::default.text_edit_tip')</button>
        </div>
    @endif
    <div
        x-cloak
        x-show="isCustom"
        id="tip-form"
        class="field"
    >
        <input
            wire:model.change="tipAmount"
            type="number"
            placeholder="@lang('igniter.cart::default.text_apply_tip')"
            step="{{ 1 / (10 ** currency()->getDefault()->decimal_position) }}"
        />
    </div>
</div>
