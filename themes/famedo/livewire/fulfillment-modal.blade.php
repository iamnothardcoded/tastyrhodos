{{-- jamasa/core override of igniter-orange::livewire.fulfillment-modal (forked from ti-theme-orange v4.1.3) --}}
{{-- Changes vs stock:
     1. Order-type radios REMOVED — the hero pill switches the type inline
        (famedo.js bridge → $wire.set('orderType')). The orderType property
        itself stays fully functional; order types are NOT disabled anywhere
        (redirect-loop landmine lives in location config, not this markup).
     2. Timeslot UI: vendor isAsap radios + date/time selects replaced by the
        mockup pattern — ASAP card + quarter-hour slot bubbles (:00/:15/:30/:45).
        All state flows through $wire.set (server renders selected states);
        the vendor's Alpine showTimePicker/x-model wiring is no longer used.
     Preserved verbatim: root x-data OrangeFulfillment, wire:ignore.self modal,
     id/data-map-key, wire:submit="onConfirm", the ENTIRE address block
     (search, suggestions, map, saved addresses), previewMode guards. --}}
<div x-data='OrangeFulfillment(@json($timeslotTimes))'>
    <div
        wire:ignore.self
        @class(['modal fade famedo-sheet', 'show' => $showAddressPicker])
        id="fulfillmentModal"
        data-map-key="{{ $mapKey }}"
        tabindex="-1"
        aria-labelledby="fulfillmentModalLabel"
        aria-hidden="true"
        style="display: {{ $showAddressPicker ? 'block' : 'none' }};"
    >
        <div class="modal-dialog modal-dialog-centered">
            <x-igniter-orange::forms.form class="w-100" wire:submit="onConfirm">
                <div class="modal-content">
                    <div class="modal-header px-4 border-bottom-0">
                        <h5 class="modal-title sheet__title fs-5"
                            id="fulfillmentModalLabel">@lang('igniter.orange::default.text_control_title')</h5>
                        <button type="button" class="btn-close famedo-sheet-close" data-bs-dismiss="modal" aria-label="{{ __('jamasa.core::default.ui.close') }}"></button>
                    </div>
                    <div class="modal-body p-4 py-2">
                        <div id="local-timeslot" class="pb-3">
                            <button
                                type="button"
                                @class(['pickopt', 'selected' => $isAsap])
                                x-on:click="$wire.set('isAsap', 1)"
                                @disabled($previewMode)
                            >
                                <span>
                                    <span class="pickopt__t">@lang('igniter.local::default.text_asap')</span>
                                </span>
                                @if($isAsap)
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" class="pickopt__check"><path d="M20 6L9 17l-5-5"/></svg>
                                @endif
                            </button>

                            @if(count($timeslotDates))
                                <div class="pickdiv"><span>@lang('igniter.local::default.text_later')</span></div>

                                @if(count($timeslotDates) > 1)
                                    <div class="pickdates">
                                        @foreach($timeslotDates as $key => $value)
                                            <button
                                                type="button"
                                                @class(['pickdate', 'on' => $orderDate === $key])
                                                x-on:click="$wire.set('orderDate', '{{ $key }}')"
                                                @disabled($previewMode)
                                            >{{ $value }}</button>
                                        @endforeach
                                    </div>
                                @endif

                                @php
                                    // Max 4 bubbles per hour: bucket the real slots into quarter
                                    // hours and show the slot CLOSEST to each quarter (:45 taken
                                    // → :50 stands in). Every bubble stays a real, bookable slot
                                    // key, so validation is untouched. Works for any interval:
                                    // 15 min → exact quarters, 5 min → quarters unless blocked.
                                    $famedoSlots = [];
                                    foreach (array_get($timeslotTimes, $orderDate, []) as $slotKey => $slotLabel) {
                                        $minute = (int) substr((string) $slotKey, -2);
                                        $bucket = substr((string) $slotKey, 0, 2).'-'.intdiv($minute, 15);
                                        $dist = abs($minute - (intdiv($minute, 15) * 15));
                                        if (!isset($famedoSlots[$bucket]) || $dist < $famedoSlots[$bucket]['dist']) {
                                            $famedoSlots[$bucket] = ['key' => $slotKey, 'label' => $slotLabel, 'dist' => $dist];
                                        }
                                    }
                                @endphp
                                <div class="pickslots">
                                    @foreach($famedoSlots as $slot)
                                        <button
                                            type="button"
                                            @class(['slot', 'on' => !$isAsap && $orderTime === $slot['key']])
                                            x-on:click="$wire.set('isAsap', 0, false); $wire.set('orderTime', '{{ $slot['key'] }}')"
                                            @disabled($previewMode)
                                        >{{ $slot['label'] }}</button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        @unless($hideDeliveryAddress)
                            <div x-cloak x-show="!hideDeliveryAddress" class="pb-3 position-relative">
                                <h6 class="my-3">
                                    <i class="fa fa-map-pin"></i>&nbsp;&nbsp;
                                    @lang('igniter.orange::default.text_delivering_to')
                                    @unless($previewMode)
                                        <a
                                            wire:click="onChangeDeliveryAddress"
                                            role="button"
                                            class="small text-primary"
                                        >@lang('igniter.local::default.search.text_change')</a>
                                    @endunless
                                </h6>
                                @if(!$previewMode && $showAddressPicker)
                                    <div class="input-group bg-white rounded border p-1 mb-3 mb-lg-0">
                                        <input
                                            @if($searchAutocompleteEnabled)
                                                wire:model.live.debounce.500ms="searchQuery"
                                            @else
                                                wire:model="searchQuery"
                                            @endif
                                            type="text"
                                            id="search-query"
                                            class="bg-white form-control shadow-none border-none"
                                            placeholder="@lang('igniter.local::default.label_search_query')"
                                        />
                                        <button
                                            type="button"
                                            data-control="user-position"
                                            class="btn shadow-none"
                                        ><i class="fa fa-location-arrow fs-5 align-bottom"></i></button>
                                    </div>
                                    @if($isSearching && $searchAutocompleteEnabled)
                                        @include('igniter-orange::includes.local.autocomplete-suggestions')
                                    @endif
                                    <x-igniter-orange::forms.error
                                        field="searchQuery"
                                        id="searchQueryFeedback"
                                        class="text-danger"
                                    />
                                    @if($searchPoint && $this->searchAutocompleteEnabled)
                                        <div wire:ignore class="mt-3">
                                            <h6>@lang('igniter.orange::default.text_mark_your_location')</h6>
                                            <div id="map" class="map-container rounded pt-2"></div>
                                        </div>
                                    @else
                                        @include('igniter-orange::includes.local.saved-address-picker')
                                    @endif
                                @else
                                    <div class="p-2 border rounded bg-white w-100">
                                        <div
                                            class="pe-2 fw-bold text-truncate"
                                        >{{ $searchQuery ?? $deliveryAddress ?? lang('igniter.local::default.alert_no_search_query') }}</div>
                                    </div>
                                @endif
                            </div>
                        @endunless
                    </div>
                    <div class="modal-footer border-0 sheet__foot">
                        <button
                            type="submit"
                            class="btn-add"
                            wire:loading.class="disabled"
                        ><span class="mx-auto">@lang('igniter.orange::default.button_confirm')</span></button>
                    </div>
                </div>
            </x-igniter-orange::forms.form>
        </div>
    </div>
</div>
