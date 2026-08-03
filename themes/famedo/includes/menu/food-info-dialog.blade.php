{{-- Shared food-info dialog (allergens/Zusatzstoffe per LMIV), opened by any
     .infobtn on the page (famedo.js fills it from the button's data-food-info
     payload). Mounted ONCE from _pages/local/menus.blade.php OUTSIDE the
     Livewire menu component so morphs can never wipe an open dialog. Sits
     above the #orange-modal bottom sheet (z-index 10000/10010 vs 9999). --}}
@php($famedoInfoPhone = \Igniter\Local\Facades\Location::current()?->location_telephone)
<div class="food-dialog__backdrop" id="foodDialogBackdrop" hidden></div>
<div
    class="food-dialog"
    id="foodDialog"
    role="dialog"
    aria-modal="true"
    aria-labelledby="foodDialogTitle"
    data-phone="{{ $famedoInfoPhone }}"
    hidden
>
    <button type="button" class="food-dialog__close" data-role="close" aria-label="{{ __('jamasa.core::default.ui.close') }}">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
    </button>
    <div class="food-dialog__head">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
        <h4 id="foodDialogTitle">@lang('iamnothardcoded.foodlabels::default.text_dialog_title')</h4>
    </div>
    <p class="food-dialog__dish">
        <span data-role="dish"></span>
        <span class="food-dialog__status food-dialog__status--declared" data-role="status-declared" hidden>@lang('iamnothardcoded.foodlabels::default.text_status_declared')</span>
        <span class="food-dialog__status food-dialog__status--confirmed" data-role="status-confirmed" hidden>@lang('iamnothardcoded.foodlabels::default.text_status_confirmed')</span>
    </p>
    <div class="food-dialog__list" data-role="allergens" hidden></div>
    <div class="food-dialog__additives" data-role="additives" hidden></div>
    <p class="food-dialog__none" data-role="none" hidden>@lang('iamnothardcoded.foodlabels::default.text_dialog_none')</p>
    <p class="food-dialog__unknown" data-role="unknown" hidden>
        @lang('iamnothardcoded.foodlabels::default.text_dialog_unknown')
        @if($famedoInfoPhone)
            <a data-role="tel" href="tel:{{ preg_replace('/[^+\d]/', '', $famedoInfoPhone) }}">{{ $famedoInfoPhone }}</a>
        @endif
    </p>
    <p class="food-dialog__note">@lang('iamnothardcoded.foodlabels::default.text_dialog_note')</p>
</div>
