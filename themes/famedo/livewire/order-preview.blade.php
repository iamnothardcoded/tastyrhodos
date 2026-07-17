{{-- jamasa/core override of igniter-orange::livewire.order-preview (forked from ti-theme-orange v4.1.3) --}}
{{-- Famedo cards. Contracts: root wire:poll.120s, id="ti-order-status",
     customer auth branch, leave-review mount, order includes. --}}
<div wire:poll.120s>
    @if (!$order)
        <div class="famedo-card text-center" id="ti-order-status">
            @lang('jamasa.core::default.order.none_found')
        </div>
    @else
        <div class="famedo-card" id="ti-order-status">
            @include('igniter-orange::includes.order.status')
        </div>

        @auth('igniter-customer')
            <livewire:igniter-orange::leave-review />

            <div class="famedo-card">
                @include('igniter-orange::includes.order.restaurant', ['location' => $order->location])
            </div>

            <div class="famedo-card">
                @include('igniter-orange::includes.order.items')
            </div>

            <div class="famedo-card">
                @include('igniter-orange::includes.order.details')
            </div>
        @else
            <div class="famedo-card text-center">
                <a href="{{ $loginUrl }}">@lang('igniter.cart::default.orders.text_login_to_view_more')</a>
            </div>
        @endauth
    @endif
</div>
