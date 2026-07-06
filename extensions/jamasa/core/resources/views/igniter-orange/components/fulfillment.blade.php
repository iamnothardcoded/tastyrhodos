{{-- jamasa/core override of igniter-orange::components.fulfillment --}}
{{-- Only change vs stock: when ordering is paused by the printer going offline, --}}
{{-- show a friendly "kitchen on a break" message instead of the bare "CLOSED". --}}
{{-- A normal after-hours / disabled close still shows the stock "CLOSED". --}}
<div class="d-flex align-items-center">
    <div>
        <i class="far fa-clock me-2"></i>
        @if (!$activeOrderType || $activeOrderType->isDisabled())
            @if (\Jamasa\Core\Helpers\OrderingState::isPrinterPaused())
                {{ \Jamasa\Core\Helpers\OrderingState::message() }}
            @else
                @lang('igniter.cart::default.text_is_closed')
            @endif
        @else
            {{ $activeOrderType->getLabel() }}&nbsp;·
            @if ($isAsap)
                @if ($activeOrderType->getSchedule()->isOpen())
                    @if ($activeOrderType->getLeadTime())
                        {!! sprintf(lang('igniter.local::default.text_in_min'), $activeOrderType->getLeadTime()) !!}
                    @endif
                @elseif ($activeOrderType->getSchedule()->isOpening())
                    {!! sprintf(lang('igniter.local::default.text_starts'), make_carbon($activeOrderType->getSchedule()->getOpenTime())->isoFormat(lang('system::lang.moment.day_time_format_short'))) !!}
                @elseif ($activeOrderType->getSchedule()->isClosed())
                    @lang('igniter.cart::default.text_is_closed')
                @endif
            @elseif ($activeOrderType->getSchedule()->isOpen() || $activeOrderType->getSchedule()->isOpening())
                @if($orderDateTime->isToday())
                    @lang('system::lang.date.today')
                    &nbsp;{{$orderDateTime->isoFormat(lang('system::lang.moment.time_format'))}}
                @elseif($orderDateTime->isTomorrow())
                    @lang('system::lang.date.tomorrow')
                    &nbsp;{{$orderDateTime->isoFormat(lang('system::lang.moment.time_format'))}}
                @else
                    {{ $orderDateTime->isoFormat(lang('system::lang.moment.day_time_format')) }}
                @endif
            @endif
        @endif
        &nbsp;&nbsp;
    </div>
    @unless($previewMode || !$activeOrderType || $activeOrderType->isDisabled())
        <a
            role="button"
            class="small text-primary"
            data-bs-toggle="modal"
            data-bs-target="#fulfillmentModal"
        >@lang('igniter.local::default.search.text_change')</a>
    @endunless
</div>
