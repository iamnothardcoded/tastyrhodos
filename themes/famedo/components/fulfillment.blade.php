{{-- jamasa/core override of igniter-orange::components.fulfillment (forked from ti-theme-orange v4.1.3) --}}
{{-- Famedo mode pill (Liefern/Abholen) + info line + pause/closed bars.
     Pause logic unchanged: while paused the schedule is forced closed but the
     order type stays ENABLED (see PauseWorkingSchedule), so we key off
     OrderingState, not isDisabled. NEVER disable/hide an order type here —
     FulfillmentModal redirect-loop landmine. Both pill segments open the
     fulfillment modal; inline switching is a deferred enhancement. --}}
@if ($previewMode)
    {{-- compact line for checkout/preview embeds (previous jamasa behavior) --}}
    <div class="d-flex align-items-center">
        <div>
            <i class="far fa-clock me-2"></i>
            @if (\Jamasa\Core\Helpers\OrderingState::isPaused())
                {{ \Jamasa\Core\Helpers\OrderingState::message() }}
            @elseif (!$activeOrderType || $activeOrderType->isDisabled())
                @lang('igniter.cart::default.text_is_closed')
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
        </div>
    </div>
@else
    @php($orderTypes = \Igniter\Local\Facades\Location::getOrderTypes())
    @php($isPaused = \Jamasa\Core\Helpers\OrderingState::isPaused())

    @if ($activeOrderType && !$activeOrderType->isDisabled() && $orderTypes->isNotEmpty())
        <div @class([
            'toggle',
            'delivery' => $activeOrderType->getCode() === 'delivery',
            'pickup' => $activeOrderType->getCode() !== 'delivery',
            'single' => $orderTypes->count() < 2,
        ])>
            <div class="toggle__thumb"></div>
            @foreach ($orderTypes as $orderType)
                {{-- data-bs-toggle stays as no-JS fallback (opens the sheet);
                     famedo.js additionally switches the order type inline. --}}
                <button
                    type="button"
                    @class(['on' => $orderType->getCode() === $activeOrderType->getCode()])
                    data-famedo-ordertype="{{ $orderType->getCode() }}"
                    data-bs-toggle="modal"
                    data-bs-target="#fulfillmentModal"
                >
                    {{-- FA6 solid pill icons (color follows pill state via currentColor) --}}
                    <i @class(['fa-solid', 'fa-truck-fast' => $orderType->getCode() === 'delivery', 'fa-bag-shopping' => $orderType->getCode() !== 'delivery']) aria-hidden="true"></i>
                    {{ $orderType->getLabel() }}
                </button>
            @endforeach
        </div>
    @endif

    @if ($isPaused)
        <div class="pausebar">
            <div class="pausebar__ic"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="7" y="5" width="3.5" height="14" rx="1.2"/><rect x="13.5" y="5" width="3.5" height="14" rx="1.2"/></svg></div>
            <div>
                <div class="pausebar__t">@lang('jamasa.core::default.pause.title')</div>
                <div class="pausebar__s">{{ \Jamasa\Core\Helpers\OrderingState::message() }}</div>
            </div>
        </div>
    @elseif (!$activeOrderType || $activeOrderType->isDisabled() || !$activeOrderType->getSchedule()->isOpen())
        {{-- Admin-disabled OR after-hours (isDisabled() is only the admin toggle) —
             both show the dark closedbar; next-open time when the schedule has one. --}}
        <div class="closedbar">
            <div class="closedbar__ic"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></div>
            <div>
                <div class="closedbar__t">@lang('igniter.cart::default.text_is_closed')</div>
                @php($cbNextOpen = ($activeOrderType && ($cbOpenTime = $activeOrderType->getSchedule()->getOpenTime()))
                    ? make_carbon($cbOpenTime)->isoFormat(lang('system::lang.moment.day_time_format_short'))
                    : null)
                <div class="closedbar__s">
                    @if ($cbNextOpen)
                        {{ sprintf(lang('jamasa.core::default.closed.opens_at'), $cbNextOpen) }}
                    @else
                        @lang('jamasa.core::default.closed.browse_menu')
                    @endif
                </div>
            </div>
        </div>
    @else
        <p class="mode__info">
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
            {{-- time entrance → time-only modal (no address section) --}}
            &nbsp;<a
                role="button"
                data-bs-toggle="modal"
                data-bs-target="#fulfillmentModal"
                data-famedo-time-only="1"
            >@lang('igniter.local::default.search.text_change')</a>
        </p>
    @endif
@endif
