{{-- "Filter active" status strip. Shell only — the theme's JS composes the
     text and sets the color modifier class; the translated fragments travel
     as data attributes so the JS stays language-free. Mount it INSIDE the
     sticky header region so it stays visible while filtered. --}}
<div
    class="fstrip"
    data-diet-strip
    hidden
    data-txt-only="{{ lang('iamnothardcoded.foodlabels::default.filter_only') }}"
    data-txt-dish="{{ lang('iamnothardcoded.foodlabels::default.filter_dish') }}"
    data-txt-dishes="{{ lang('iamnothardcoded.foodlabels::default.filter_dishes') }}"
    data-label-veg="{{ lang('iamnothardcoded.foodlabels::default.diet_veg') }}"
    data-label-vegan="{{ lang('iamnothardcoded.foodlabels::default.diet_vegan') }}"
    data-label-hot="{{ lang('iamnothardcoded.foodlabels::default.diet_hot') }}"
>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 5h18M7 12h10M10 19h4"/></svg>
    <span class="fstrip__txt" data-diet-strip-txt></span>
    <button type="button" class="fstrip__clear" data-diet-clear>{{ lang('iamnothardcoded.foodlabels::default.filter_clear') }} ✕</button>
</div>
