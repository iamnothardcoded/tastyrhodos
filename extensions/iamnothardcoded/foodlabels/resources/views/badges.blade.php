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
                    {{-- sprout (not the seedling: too close to the veg leaf at 11px — decided 2026-08-03) --}}
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 20h10"/><path d="M10 20c5.5-2.5.8-6.4 3-10"/><path d="M9.5 9.4c1.1.8 1.8 2.2 2.3 3.7-2 .4-3.5.4-4.8-.3-1.2-.6-2.3-1.9-3-4.2 2.8-.5 4.4 0 5.5.8z"/><path d="M14.1 6a7 7 0 0 0-1.1 4c1.9-.1 3.3-.6 4.3-1.4 1-1 1.6-2.3 1.7-4.6-2.7.1-4 1-4.9 2z"/></svg>
                @elseif($__code === 'hot')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A4.5 4.5 0 0013 19c4 0 6-3.5 6-7 0-1.5-.5-3-1.5-4-.3 1.3-1 2-2 2 .5-2-.5-4.5-3-6-.3 2-1.5 3-3 4.5-1.7 1.7-3 3.5-3 6a4.5 4.5 0 002 3.8"/></svg>
                @endif
            </span>
        @endforeach
    </span>
@endif
