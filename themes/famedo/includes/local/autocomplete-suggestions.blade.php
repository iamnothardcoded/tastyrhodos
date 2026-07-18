{{-- famedo theme override of igniter-orange::includes.local.autocomplete-suggestions (forked from ti-theme-orange v4.1.3) --}}
{{-- Famedo address flow: suggestions are deduped street rows (jamasa
     NominatimProvider adds road/houseNumber/postcode/city data). A click fills
     the Alpine address fields (FamedoAddress.addrPick) instead of the vendor's
     wire:click="onSelectSuggestion" — no server round trip, no map dispatch,
     searchPoint stays null. OSM attribution required (ODbL) — keep the
     "Powered by" footer. --}}
<div class="autocomplete-suggestions list-group mt-2 border-top rounded-bottom-0">
    @forelse($placesSuggestions as $key => $suggestion)
        <button
            type="button"
            class="list-group-item list-group-item-action famedo-suggestion"
            x-on:click="addrPick(
                @js(array_get($suggestion, 'data.road')),
                @js(array_get($suggestion, 'data.houseNumber')),
                @js(array_get($suggestion, 'data.postcode')),
                @js(array_get($suggestion, 'data.city'))
            )"
        >
            <div class="fw-bold">{{ array_get($suggestion, 'data.road') }}@if(array_get($suggestion, 'data.houseNumber')) {{ array_get($suggestion, 'data.houseNumber') }}@endif</div>
            <div class="famedo-suggestion__sub">{{ trim(array_get($suggestion, 'data.postcode').' '.array_get($suggestion, 'data.city')) }}</div>
        </button>
    @empty
        <div class="list-group-item text-center">@lang('jamasa.core::default.address.no_suggestions')</div>
    @endforelse
</div>
<div class="p-1 text-end border rounded rounded-top-0">
    <small>
        Powered by
        <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>
    </small>
</div>
