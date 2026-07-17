{{-- famedo theme override of igniter-orange::includes.eucookiebanner (forked from ti-theme-orange v4.1.3) --}}
{{-- Changes: the "more info" link renders ONLY when gdpr_more_info_link resolves to a
     real page — upstream falls back to page_url('') = current URL, so the link just
     reloaded the page. Texts stay theme-data-driven (seeded in German by new-tenant.sh).
     JS CONTRACT (cookie-banner.js): [data-control="cookie-banner"] + data-active toggle
     visibility; #eu-cookie-action sets the 30-day complianceCookie on click. Keep both. --}}
@php
    $privacyPage = \Igniter\Pages\Models\Page::find($theme->gdpr_more_info_link)
@endphp
<div
    id="euCookieBanner"
    data-control="cookie-banner"
    data-active="{{ $theme->enable_gdpr }}"
    style="display:none;"
>
    <div
        style="background-color: {{ $theme->gdpr_background_color }}; color: {{ $theme->gdpr_text_color }};"
    >
        <div class="container">
            <div class="d-flex align-items-center">
                <p id="eu-cookie-message" class="mb-0">
                    <span>{!! $theme->gdpr_cookie_message !!}</span>
                    @if ($privacyPage)
                        <a
                            href="{{ page_url($privacyPage->permalink_slug) }}"
                        >{{ $theme->gdpr_more_info_text }}</a>
                    @endif
                </p>
                <a
                    id="eu-cookie-action"
                    class="btn btn-secondary ms-auto"
                    href="javascript:void(0);"
                >{{ $theme->gdpr_accept_text }}</a>
            </div>
        </div>
    </div>
</div>
