{{-- jamasa/core override of igniter-orange::includes.checkout.one-step-form (forked from ti-theme-orange v4.1.3) --}}
{{-- Famedo sections. Contracts: form id checkout-form, data-checkout-control="submit"
     on the submit button, all four formTabFields includes in order. --}}
<x-igniter-orange::forms.form id="checkout-form" novalidate>
    <div class="famedo-co-sec">
        <div class="co-sec">@lang('igniter.orange::default.label_your_details')</div>
        @include('igniter-orange::includes.checkout.tab-fields', [
            'fields' => $this->formTabFields('details'),
        ])
    </div>

    <div class="famedo-co-sec">
        <div class="famedo-co-fulfillment">
            <x-igniter-orange::fulfillment/>

            @includeWhen($order->isDeliveryType(), 'igniter-orange::includes.checkout.delivery-address')
        </div>
    </div>

    <div class="famedo-co-sec">
        @include('igniter-orange::includes.checkout.tab-fields', [
            'fields' => $this->formTabFields('comments'),
        ])
    </div>

    @include('igniter-orange::includes.checkout.tab-fields', [
        'fields' => $this->formTabFields('payments'),
    ])

    @include('igniter-orange::includes.checkout.tab-fields', [
        'fields' => $this->formTabFields('terms'),
    ])

    <div class="famedo-co-sec">
        <button
            wire:loading.class="disabled"
            data-checkout-control="submit"
            type="submit"
            class="checkout-btn btn-add"
        ><span>@lang('igniter.orange::default.button_confirm')</span></button>
    </div>
</x-igniter-orange::forms.form>
