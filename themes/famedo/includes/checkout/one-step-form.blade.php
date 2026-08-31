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
        {{-- Surface for the same-day/order-time guard: jamasa throws under
             fields.order_time — before 2026-08-26 NO view echoed that key, so
             the rejection was 100% invisible to the customer. --}}
        <x-igniter-orange::forms.error field="fields.order_time" id="order-time-feedback"
            class="text-danger fs-6"/>
    </div>

    <div class="famedo-co-sec">
        @include('igniter-orange::includes.checkout.tab-fields', [
            'fields' => $this->formTabFields('comments'),
        ])
    </div>

    {{-- famedo-co-sec wrappers: a Bootstrap .row's negative side margins must
         land inside a padded container — as a direct <form> child they stick
         out past the page edge (~8px each side at g-3) and horizontally
         scroll narrow phones. The details/comments sections above get this
         for free; payments/terms were bare (fixed 2026-08-31). --}}
    <div class="famedo-co-sec">
        @include('igniter-orange::includes.checkout.tab-fields', [
            'fields' => $this->formTabFields('payments'),
        ])
    </div>

    <div class="famedo-co-sec">
        @include('igniter-orange::includes.checkout.tab-fields', [
            'fields' => $this->formTabFields('terms'),
        ])
    </div>

    {{-- Validation summary — guaranteed-visible recap of EVERY objection,
         directly where the eye is after tapping Bestellen. Inline surfaces
         stay the scroll anchors: the container id deliberately does NOT end
         in -feedback (famedo.js firstError() contract). array_unique: the
         jamasa listener double-keys messages (fields.* + bare) on purpose. --}}
    @if($errors->any())
        <div class="famedo-co-sec famedo-co-errors" id="checkout-error-summary" role="alert">
            <div class="famedo-co-errors__title">@lang('jamasa.core::default.checkout.summary_title')</div>
            <ul class="famedo-co-errors__list">
                @foreach(array_unique($errors->all()) as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="famedo-co-sec">
        <button
            wire:loading.class="disabled"
            data-checkout-control="submit"
            type="submit"
            class="checkout-btn btn-add"
        ><span>@lang('igniter.orange::default.button_confirm')</span></button>
    </div>
</x-igniter-orange::forms.form>
