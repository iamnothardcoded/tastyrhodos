{{-- Diet badges for a menu item row. Expects: $menuItem (Igniter\Cart\Models\Menu).
     Empty-safe: renders nothing when no diet labels are set. Theme provides
     the .diet-badge CSS (see README theme-compat section). --}}
@php($__dietCodes = \Iamnothardcoded\FoodLabels\Classes\DietLabels::displayCodes((array)($menuItem->diet_labels ?? [])))
@if($__dietCodes !== [])
    <span class="item__badges">
        @foreach($__dietCodes as $__code)
            <span
                class="diet-badge diet-badge--{{ $__code }}"
                title="{{ lang('iamnothardcoded.foodlabels::default.diet_'.$__code) }}"
                aria-label="{{ lang('iamnothardcoded.foodlabels::default.diet_'.$__code) }}"
            >
                @if($__code === 'veg')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 20A7 7 0 014 13c0-6 8-9 15-9 0 7-3 15-8 16z"/><path d="M4 20c2-5 6-8 10-9"/></svg>
                @elseif($__code === 'vegan')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c-4-3-6-7-6-11 3 1 5 3 6 6 1-3 3-5 6-6 0 4-2 8-6 11z"/><path d="M12 22V11"/></svg>
                @elseif($__code === 'hot')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A4.5 4.5 0 0013 19c4 0 6-3.5 6-7 0-1.5-.5-3-1.5-4-.3 1.3-1 2-2 2 .5-2-.5-4.5-3-6-.3 2-1.5 3-3 4.5-1.7 1.7-3 3.5-3 6a4.5 4.5 0 002 3.8"/></svg>
                @endif
            </span>
        @endforeach
    </span>
@endif
