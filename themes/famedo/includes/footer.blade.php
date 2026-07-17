{{-- famedo theme override of igniter-orange::includes.footer (forked from ti-theme-orange v4.1.3) --}}
{{-- Famedo compact footer: legal line + footer-menu links (Impressum/Datenschutz).
     Newsletter box and social icons dropped for pass 1. --}}
<div class="famedo-footer">
    <strong>{{ $site_name }}</strong><br>
    {{-- LMIV/LMIDV: allergen info for loose food may be given orally IF a
         visible notice names the channel — this is that notice. Proper
         per-item allergen popup = backlog (needs data from the restaurant). --}}
    @php($famedoPhone = \Igniter\Local\Facades\Location::current()?->location_telephone)
    @if($famedoPhone)
        @lang('jamasa.core::default.footer.allergens_q')<br>
        @lang('jamasa.core::default.footer.allergens_phone')
        <a href="tel:{{ preg_replace('/[^+\d]/', '', $famedoPhone) }}">{{ $famedoPhone }}</a><br>
    @else
        @lang('jamasa.core::default.footer.allergens')<br>
    @endif
    @lang('jamasa.core::default.footer.prices_vat')<br>
    <div class="famedo-footer__nav">
        <x-igniter-orange::nav code="footer-menu" />
    </div>
    {{-- brand is always written lowercase: "famedo"; the link is the B2B
         touchpoint for restaurant owners → the marketing site --}}
    @lang('jamasa.core::default.footer.powered_by')
    <a href="https://get-famedo.de" target="_blank" rel="noopener"><strong>famedo</strong></a>
</div>
