{{-- jamasa/core override of igniter-orange::includes.checkout.delivery-address (forked from ti-theme-orange v4.1.3) --}}
{{-- Famedo styling; adds a change-trigger into the fulfillment sheet.
     Contracts: $fields address keys, delivery_address error + -feedback id. --}}
{{-- Whole row is the tap target (a precise-hit „ändern" link felt broken);
     data-famedo-addr-only makes the fulfillment modal open in address-only
     mode (timeslots hidden — changing the address mid-checkout shouldn't
     re-ask the time; see famedo.js + body.famedo-addr-only CSS). --}}
<div
    class="famedo-addr"
    role="button"
    tabindex="0"
    data-bs-toggle="modal"
    data-bs-target="#fulfillmentModal"
    data-famedo-addr-only="1"
>
    {{-- ONE address style everywhere: "Straße Nr, PLZ Stadt" via
         AddressFormat::displayLine (owner rule 2026-08-27). The raw $fields
         here come from the vendor's prepareDeliveryAddress, which composes
         address_1 US-order ("12 Musterstraße") and parks the DISTRICT in city
         with the real city in state — format_address rendered exactly that.
         The helper germanizes + prefers state; see its docblock. --}}
    @php($deliveryAddress = \Jamasa\Core\Helpers\AddressFormat::displayLine(
        $fields['address_1'] ?? null, $fields['city'] ?? null,
        $fields['state'] ?? null, $fields['postcode'] ?? null))
    <i class="fas fa-location-dot famedo-addr__ic"></i>
    <div class="famedo-addr__main">
        @if($deliveryAddress !== '')
            {{ $deliveryAddress }}
        @else
            <span class="famedo-addr__empty">@lang('igniter.orange::default.text_no_delivery_address')</span>
        @endif
    </div>
    <span class="famedo-addr__change">@lang('igniter.local::default.search.text_change')</span>
</div>
{{-- Two possible error keys, one surface: jamasa's checkout listener throws
     under fields.delivery_address (survives Livewire's error-memo dehydrate —
     bare keys without a component property die on the next blur commit), the
     VENDOR's own after()-check still adds bare delivery_address. Prefer the
     surviving key, never render both (same message twice). --}}
@if($errors->has('fields.delivery_address'))
    <x-igniter-orange::forms.error
        field="fields.delivery_address"
        id="delivery-address-feedback"
        class="text-danger fs-6"
    />
@else
    <x-igniter-orange::forms.error
        field="delivery_address"
        id="delivery-address-feedback"
        class="text-danger fs-6"
    />
@endif
{{-- Geocoder-blind fail-open: the order proceeds (restaurant judges by phone),
     but the customer should double-check what they typed — soft info, no block.
     Short line + tap-to-expand ⓘ details. --}}
@if(\Igniter\Local\Facades\Location::userPosition()?->getValue('famedoBlindFallback'))
    <div class="text-muted small mt-1" x-data="{ open: false }">
        <span>@lang('jamasa.core::default.address.unverified')</span>
        <button type="button" class="btn btn-link btn-sm p-0 ms-1 align-baseline" x-on:click="open = !open" aria-label="Info">
            <i class="fas fa-circle-info"></i>
        </button>
        <div x-show="open" class="mt-1">@lang('jamasa.core::default.address.unverified_more')</div>
    </div>
@endif
