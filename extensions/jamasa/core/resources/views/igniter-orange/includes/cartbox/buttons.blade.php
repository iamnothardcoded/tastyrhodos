{{-- jamasa/core override of igniter-orange::includes.cartbox.buttons (forked from ti-theme-orange v4.1.3) --}}
{{-- Famedo checkout button. Contracts: closed/min-order gate, wire:click,
     buttonLabel (already carries min-order/closed copy). --}}
@php $locationIsClosed = (!$cart->count() || $this->locationIsClosed() || $this->hasMinimumOrder()); @endphp
<button
    @class(['checkout-btn btn-add', 'disabled' => $locationIsClosed])
    wire:loading.class="disabled"
    wire:click="onProceedToCheckout({{ $this->getLocationId() }})"
>
    <span class="mx-auto">{{ $this->buttonLabel($checkout ?? null) }}</span>
</button>
