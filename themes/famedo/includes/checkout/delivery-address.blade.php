{{-- jamasa/core override of igniter-orange::includes.checkout.delivery-address (forked from ti-theme-orange v4.1.3) --}}
{{-- Famedo styling; adds a change-trigger into the fulfillment sheet.
     Contracts: $fields address keys, delivery_address error + -feedback id. --}}
<div class="famedo-addr">
    @php($deliveryAddress = array_filter(array_only($fields, ['address_1', 'city', 'state', 'postcode'])))
    <i class="fas fa-location-dot famedo-addr__ic"></i>
    <div class="famedo-addr__main">
        @if($deliveryAddress)
            {{ html(format_address($deliveryAddress, false)) }}
        @else
            <span class="famedo-addr__empty">@lang('igniter.orange::default.text_no_delivery_address')</span>
        @endif
    </div>
    <a
        role="button"
        class="famedo-addr__change"
        data-bs-toggle="modal"
        data-bs-target="#fulfillmentModal"
    >@lang('igniter.local::default.search.text_change')</a>
</div>
<x-igniter-orange::forms.error
    field="delivery_address"
    id="delivery-address-feedback"
    class="text-danger fs-6"
/>
