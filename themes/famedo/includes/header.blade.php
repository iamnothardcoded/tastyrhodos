{{-- jamasa/core override of igniter-orange::includes.header (forked from ti-theme-orange v4.1.3) --}}
{{-- Slim famedo topbar: round burger button, brand logo. On the menus page the
     header overlays the hero cover (CSS via body class page-local-menus). --}}
<nav class="navbar famedo-topbar">
    <a class="navbar-brand famedo-brand" href="{{ page_url('home') }}">
        @if($theme->logo_image)
            <img
                class="img-logo"
                alt="{{ setting('site_name') }}"
                src="{{ media_url($theme->logo_image) }}"
            />
        @elseif($theme->logo_text)
            <span class="text-logo">{{ $theme->logo_text }}</span>
        @else
            <img
                class="img-logo"
                alt="{{ $site_name }}"
                src="{{ $site_logo !== 'no_photo.png'
                    ? media_thumb($site_logo)
                    : asset('vendor/igniter-orange/images/favicon.ico') }}"
            />
        @endif
    </a>
    <button
        class="iconbtn navbar-toggler border-0"
        type="button"
        data-bs-toggle="collapse"
        data-bs-target="#navbarMainHeader"
        aria-controls="navbarMainHeader"
        aria-expanded="false"
        aria-label="{{ __('jamasa.core::default.ui.toggle_navigation') }}"
    ><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>

    <div class="collapse navbar-collapse famedo-nav-panel" id="navbarMainHeader">
        <x-igniter-orange::nav code="main-menu"/>
    </div>
</nav>
