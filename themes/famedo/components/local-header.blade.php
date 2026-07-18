{{-- jamasa/core override of igniter-orange::components.local-header (forked from ti-theme-orange v4.1.3) --}}
{{-- Famedo hero: cover image, open badge, floating logo card, name + stats.
     Keeps the info/reviews offcanvas includes and their triggers. --}}
@php($schedule = $currentSchedule($locationInfo))
<div class="hero__cover" @if($locationInfo->hasThumb()) style="background-image:url('{{ $locationInfo->getThumb(['width' => 1200, 'height' => 420]) }}')" @endif>
    <div @class(['hero__badge', 'closed' => !$schedule->isOpen()])>
        <span class="dot"></span>
        @if ($schedule->isOpen())
            {{-- single span: each bare text node would become its own flex item (extra gap) --}}
            <span>@lang('igniter.local::default.text_is_opened')@if($famedoCloses = $schedule->getCloseTime()) · {!! sprintf(lang('jamasa.core::default.hero.open_until'), make_carbon($famedoCloses)->isoFormat(lang('igniter::system.moment.time_format'))) !!}@endif</span>
        @elseif ($schedule->isOpening())
            {!! sprintf(lang('igniter.local::default.text_opening_time'), make_carbon($schedule->getOpenTime())->isoFormat(lang('igniter::system.moment.day_time_format_short'))) !!}
        @else
            @lang('igniter.local::default.text_closed')
        @endif
    </div>
</div>
<div class="hero__card">
    @if(isset($theme) && $theme->logo_image)
        <div class="hero__logo"><img src="{{ media_url($theme->logo_image) }}" alt="{{ $locationInfo->name }}"></div>
    @endif
    <h1 class="hero__name">{{ $locationInfo->name }}</h1>
    @if(strlen(strip_tags((string) $locationInfo->description)))
        <p class="hero__cuisine">{{ str_limit(trim(strip_tags((string) $locationInfo->description)), 90) }}</p>
    @endif
    {{-- short German address: "Straße Nr · PLZ Stadt" (full address in Mehr Infos) --}}
    @php($famedoAddr = $locationInfo->address)
    <p class="hero__addr">{{ trim($famedoAddr['address_1'] ?? '') }} · {{ trim(($famedoAddr['postcode'] ?? '').' '.($famedoAddr['city'] ?? '')) }}</p>
    <div class="hero__stats">
        @if($famedoLeadTime = \Igniter\Local\Facades\Location::orderLeadTime())
            <span class="stat"><i class="fa fa-clock" aria-hidden="true"></i> {!! sprintf(lang('jamasa.core::default.hero.lead_time'), $famedoLeadTime) !!}</span>
        @endif
        @if(($famedoMinOrder = \Igniter\Local\Facades\Location::minimumOrderTotal()) > 0)
            <span class="stat stat--muted">{!! sprintf(lang('jamasa.core::default.hero.min_order'), currency_format($famedoMinOrder)) !!}</span>
        @endif
        <span class="stat">
            <a
                class="cursor-pointer"
                data-bs-toggle="offcanvas"
                data-bs-target="#localInfoCanvas"
                aria-controls="localInfoCanvas"
            >@lang('igniter.local::default.text_more_info')</a>
        </span>
        @if ($allowReviews)
            <span class="stat">
                <x-igniter-orange::star-rating :score="$locationInfo->reviewsScore()">
                    <a
                        data-bs-toggle="offcanvas"
                        data-bs-target="#reviewsOffCanvas"
                        aria-controls="reviewsOffCanvas"
                        class="cursor-pointer"
                    ><span class="small">({{ $locationInfo->reviewsCount() }}) @lang('igniter.orange::default.text_reviews')</span></a>
                </x-igniter-orange::star-rating>
            </span>
        @endif
    </div>
</div>
@include('igniter-orange::includes.local.info-offcanvas')
@include('igniter-orange::includes.local.reviews-offcanvas')
