{{-- jamasa/core override of igniter-orange::livewire.utils.modal (forked from ti-theme-orange v4.1.3) --}}
{{-- Changes: famedo-sheet class hook; backdrop no longer static so tapping
     outside the sheet dismisses it (vendor had data-bs-backdrop="static" +
     keyboard=false). The bottom-sheet look is pure CSS riding Bootstrap's
     .show choreography. All modal.js contracts preserved. --}}
<div
    class="modal fade famedo-sheet"
    id="orange-modal"
    tabindex="-1"
    aria-hidden="true"
    style="z-index: 9999;"
    wire:ignore.self
>
    @if ($component)
        @livewire($component, $arguments, key($activeModal))
    @else
        {{-- Loading skeleton (was a lone spinner): shapes the item sheet — title,
             a description line, an option group with rows, a confirm button — so
             the wait reads as intentional. Styles in assets/css/fixes.css. --}}
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="fm-skel" role="status" aria-label="@lang('jamasa.core::default.ui.loading')">
                        <div class="fm-skel__bar fm-skel__title"></div>
                        <div class="fm-skel__bar fm-skel__line"></div>
                        <div class="fm-skel__bar fm-skel__head"></div>
                        <div class="fm-skel__row"></div>
                        <div class="fm-skel__row"></div>
                        <div class="fm-skel__row"></div>
                        <div class="fm-skel__bar fm-skel__btn"></div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
