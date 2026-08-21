{{-- jamasa/core override of igniter-orange::includes.header (forked from ti-theme-orange v4.1.3) --}}
{{-- Slim famedo topbar: round burger button, brand logo. On the menus page the
     header overlays the hero cover (CSS via body class page-local-menus). --}}
<nav class="navbar famedo-topbar">
    <a class="navbar-brand famedo-brand" href="{{ page_url('home') }}">
        {{-- ⚠️ The inline style is NOT redundant with famedo.css .img-logo. It is
             what stops the logo flashing at full size before the stylesheet
             applies: the <img> has no intrinsic dimensions, so until CSS lands
             the browser lays it out at the file's natural width — on a tenant
             whose upload is 2400px wide that fills the viewport. Inline styles
             apply at parse time, external CSS does not. Keep in sync with
             famedo.css:104 (max-height:34px).
             ⚠️ media_thumb, never media_url: media_url serves the ORIGINAL
             upload (elgrecomarl: 2400x1920, 187 KB) to paint a 34px logo. The
             thumb box is height-driven — TI thumbs default to fit=contain, so
             this scales any logo to 68px tall (2x for retina) without cropping
             and without upscaling a small one. --}}
        @if($theme->logo_image)
            <img
                class="img-logo"
                style="max-height:34px;width:auto"
                alt="{{ setting('site_name') }}"
                src="{{ media_thumb($theme->logo_image, ['width' => 240, 'height' => 68]) }}"
            />
        @elseif($theme->logo_text)
            <span class="text-logo">{{ $theme->logo_text }}</span>
        @else
            <img
                class="img-logo"
                style="max-height:34px;width:auto"
                alt="{{ $site_name }}"
                src="{{ $site_logo !== 'no_photo.png'
                    ? media_thumb($site_logo, ['width' => 240, 'height' => 68])
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
