{{-- jamasa/core override of igniter-orange::includes.order.status (forked from ti-theme-orange v4.1.3) --}}
{{-- Famedo success block: done-check, pickup code, 3-step tracker driven by
     getStatusWidthForProgressBars(). Contracts: @pickupCode directive,
     data-status-group/-width attrs, onReOrder/onCancel buttons + error slots. --}}
<div class="done-check"><svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg></div>
<h3 class="done-title">{{ $order->status ? $order->status->status_name : lang('igniter.cart::default.checkout.text_order_no') }}</h3>
<p class="done-sub">
    {{ $order->order_datetime->isoFormat(lang('igniter::system.moment.date_time_format_short')) }}
</p>

<div class="pickup-code">@pickupCode($order->hash)</div>

@if ($order->status)
    <div class="track">
        <div class="track__steps">
            @foreach ($this->getStatusWidthForProgressBars() as $group => $width)
                <div
                    @class(['tstep', 'done' => $width >= 100, 'active' => $width > 0 && $width < 100])
                    data-status-group="{{ $group }}"
                    data-status-width="{{ $width }}"
                >
                    <div class="tstep__dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg></div>
                    <div class="tstep__lbl">
                        @if($loop->first) Eingegangen
                        @elseif($loop->last) Fertig
                        @else In Arbeit
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        @if ($order->status->status_comment)
            <p class="track__eta">{!! $order->status->status_comment !!}</p>
        @endif
    </div>
@endif

<p class="done-sub mb-0">@lang('igniter.cart::default.checkout.text_success_message')</p>

<div class="mt-3 d-flex gap-2 justify-content-center flex-wrap">
    @if (!$hideReorderBtn)
        <button
            type="button"
            class="btn btn-primary re-order"
            wire:click="onReOrder"
        >@lang('igniter.cart::default.orders.button_reorder')</button>
        <x-igniter-orange::forms.error field="onReOrder" class="text-danger text-center" />
    @endif
    @if ($showCancelButton)
        <button
            class="btn btn-light text-danger"
            wire:click="onCancel"
        >@lang('igniter.cart::default.orders.button_cancel')</button>
        <x-igniter-orange::forms.error field="onCancel" class="text-danger text-center" />
    @endif
</div>
