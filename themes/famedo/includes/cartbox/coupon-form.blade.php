{{-- jamasa/core override of igniter-orange::includes.cartbox.coupon-form (forked from ti-theme-orange v4.1.3) --}}
{{-- Famedo coupon row. Contracts: form id, wire:submit, wire:model, data-replace-loading. --}}
<div id="cart-coupon">
    <x-igniter-orange::forms.form id="coupon-form" wire:submit="onApplyCoupon">
        <div class="coupon">
            <input
                wire:model="couponCode"
                type="text"
                placeholder="@lang('igniter.cart::default.text_apply_coupon')"
            />
            <button
                type="submit"
                data-replace-loading="fa fa-spinner fa-spin"
            ><i class="fa fa-check"></i></button>
        </div>
    </x-igniter-orange::forms.form>
</div>
