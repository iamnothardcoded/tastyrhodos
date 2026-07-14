{{-- jamasa/core override of igniter-orange::includes.head (forked from ti-theme-orange v4.1.3) --}}
{{-- Changes: Google Fonts + cdnjs Font Awesome removed (DSGVO) — self-hosted via
     /vendor/jamasa/ (publish tag jamasa-assets); @font-face lives in famedo.css.
     Viewport re-declared without maximum-scale=1 (a11y): last viewport meta wins. --}}
{!! get_metas() !!}
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
@if ($favicon = $theme->favicon)
    <link href="{{ media_url($favicon) }}" rel="shortcut icon" type="image/ico">
@elseif ($site_logo !== 'no_photo.png')
    <link href="{{ media_thumb($site_logo, ['width' => 64, 'height' => 64]) }}" rel="shortcut icon" type="image/ico">
@else
    {!! get_favicon() !!}
@endif
<title>{{ lang(get_title()).lang('igniter.orange::default.title_separator').setting('site_name') }}</title>
@if ($page->description)
    <meta name="description" content="{{ $page->description }}">
@endif
@if ($page->keywords)
    <meta name="keywords" content="{{ $page->keywords }}">
@endif
<link rel="preload" href="/vendor/jamasa/fonts/plus-jakarta-sans-latin-400.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/vendor/jamasa/fonts/archivo-latin-800.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/vendor/jamasa/fontawesome/css/all.min.css">
@themeStyles
@if (!empty($theme->custom_css))
    <style>{{$theme->custom_css}}</style>
@endif
