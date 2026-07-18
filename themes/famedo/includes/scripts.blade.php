{{-- famedo theme override of igniter-orange::includes.scripts (forked from ti-theme-orange v4.1.3) --}}
{{-- Change: @themeScripts replaced by a FILTERED Assets::getJs() — vendor
     SearchesNearby unconditionally registers Leaflet JS from unpkg.com
     (DSGVO leak). The famedo address flow has no map; strip any third-party
     CDN script before emitting. Everything else identical to stock. --}}
{!! Assets::getJsVars() !!}
{!! preg_replace('#<script[^>]*unpkg\.com[^>]*></script>\s*#i', '', Assets::getJs()) !!}
@stack('scripts')
{!! $theme->ga_tracking_code !!}
@if (!empty($theme->custom_js))
    <script type="text/javascript">{!! $theme->custom_js !!}</script>
@endif
