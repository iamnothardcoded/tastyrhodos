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
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="text-center">
                        <div class="ti-loading spinner-border fa-3x fa-fw" role="status"></div>
                        <div class="fw-bold mt-2">@lang('jamasa.core::default.ui.loading')</div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
