{{-- famedo-only partial (no orange counterpart): closed/paused acknowledge-overlay.
     Included body-level by _layouts/default.blade.php, which computes and passes
     $ovState ('open'|'closed'|'paused' — paused checked FIRST: a pause forces the
     schedule closed, see PauseWorkingSchedule). Stacks with the pausebar/closedbar
     banners in components/fulfillment.blade.php: overlay on arrival (once per
     session per state, sessionStorage), banner stays while browsing.
     Poll/dismiss/toast behavior lives in assets/js/famedo.js ("ordering overlay"
     IIFE); lockout CSS keys off body.ordering-paused/.ordering-closed. --}}

{{-- Resume-toast: rendered in EVERY state — it shows on the reloaded, open page
     after a pause ends (famedo.js checks the famedo-resumed sessionStorage flag). --}}
<div class="famedo-toast" id="famedoResumedToast" role="status" aria-live="polite">@lang('jamasa.core::default.overlay.resumed_toast')</div>

@if (($ovState ?? 'open') !== 'open')
    @php
        $ovNextOpen = null;
        if ($ovState === 'closed'
            && ($ovSchedule = \Igniter\Local\Facades\Location::getOrderType()?->getSchedule())
            && ($ovOpenTime = $ovSchedule->getOpenTime())) {
            $ovNextOpen = make_carbon($ovOpenTime)->isoFormat(lang('system::lang.moment.day_time_format_short'));
        }
        // Preorder = same-day by construction → time-only format ("17:00").
        $ovPreorderOpen = null;
        if ($ovState === 'preorder'
            && ($ovSchedule = \Igniter\Local\Facades\Location::getOrderType()?->getSchedule())
            && ($ovOpenTime = $ovSchedule->getOpenTime())) {
            $ovPreorderOpen = make_carbon($ovOpenTime)->isoFormat(lang('system::lang.moment.time_format'));
        }
    @endphp
    {{-- preorder carries NO data-locked-toast: ordering is possible, nothing is
         locked — famedo.js also skips the row-click intercept for this state. --}}
    <div class="closed-overlay" id="famedoOrderingOverlay" data-famedo-ordering-state="{{ $ovState }}"
        data-locked-toast="{{ $ovState === 'preorder' ? '' : ($ovState === 'paused'
            ? \Jamasa\Core\Helpers\OrderingState::message()
            : ($ovNextOpen
                ? sprintf(lang('jamasa.core::default.overlay.closed_text'), $ovNextOpen)
                : lang('jamasa.core::default.overlay.closed_text_notime'))) }}">
        <div class="closed-card" role="alertdialog" aria-modal="true" aria-labelledby="famedoOverlayTitle">
            @if ($ovState === 'preorder')
                <div class="closed-card__ic preorder-ic">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                </div>
                <div class="closed-card__t" id="famedoOverlayTitle">
                    @if ($ovPreorderOpen)
                        {{ sprintf(lang('jamasa.core::default.preorder.overlay_title'), $ovPreorderOpen) }}
                    @else
                        @lang('jamasa.core::default.preorder.banner_title')
                    @endif
                </div>
                <p class="closed-card__s">@lang('jamasa.core::default.preorder.overlay_text')</p>
                <button type="button" class="closed-card__btn" data-famedo-overlay-dismiss>@lang('jamasa.core::default.preorder.overlay_cta')</button>
            @elseif ($ovState === 'paused')
                <div class="closed-card__ic pause-ic">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="7" y="5" width="3.5" height="14" rx="1.2"/><rect x="13.5" y="5" width="3.5" height="14" rx="1.2"/></svg>
                </div>
                <div class="closed-card__t" id="famedoOverlayTitle">@lang('jamasa.core::default.pause.title')</div>
                <p class="closed-card__s">{{ \Jamasa\Core\Helpers\OrderingState::message() }}</p>
                <button type="button" class="closed-card__btn" data-famedo-overlay-dismiss>@lang('jamasa.core::default.overlay.pause_browse')</button>
                <p class="closed-card__s closed-card__note">@lang('jamasa.core::default.overlay.auto_check')</p>
            @else
                <div class="closed-card__ic">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                </div>
                <div class="closed-card__t" id="famedoOverlayTitle">@lang('jamasa.core::default.overlay.closed_title')</div>
                <p class="closed-card__s">
                    @if ($ovNextOpen)
                        {!! sprintf(e(lang('jamasa.core::default.overlay.closed_text')), '<b>'.e($ovNextOpen).'</b>') !!}
                    @else
                        @lang('jamasa.core::default.overlay.closed_text_notime')
                    @endif
                </p>
                <button type="button" class="closed-card__btn" data-famedo-overlay-dismiss>@lang('jamasa.core::default.overlay.closed_browse')</button>
            @endif
        </div>
    </div>
    {{-- Ack check inline (not in famedo.js) so the overlay never flashes:
         runs during parse, before first paint of anything below. --}}
    <script>
        (function () {
            try {
                var ov = document.getElementById('famedoOrderingOverlay');
                if (ov && !sessionStorage.getItem('famedo-ack-' + ov.getAttribute('data-famedo-ordering-state'))) {
                    ov.classList.add('show');
                }
            } catch (e) { /* storage blocked → show nothing extra, banner still informs */ }
        })();
    </script>
@endif
