{{-- jamasa/core override of igniter-orange::includes.footer (forked from ti-theme-orange v4.1.3) --}}
{{-- Famedo compact footer: legal line + footer-menu links (Impressum/Datenschutz).
     Newsletter box and social icons dropped for pass 1. --}}
<div class="famedo-footer">
    <strong>{{ $site_name }}</strong><br>
    {{-- LMIV/LMIDV: allergen info for loose food may be given orally IF a
         visible notice names the channel — this is that notice. Proper
         per-item allergen popup = backlog (needs data from the restaurant). --}}
    @php($famedoPhone = \Igniter\Local\Facades\Location::current()?->location_telephone)
    @if($famedoPhone)
        Fragen zu Allergenen &amp; Zusatzstoffen (LMIV)? Wir informieren dich gern:
        <a href="tel:{{ preg_replace('/[^+\d]/', '', $famedoPhone) }}">{{ $famedoPhone }}</a><br>
    @else
        Allergene &amp; Zusatzstoffe gem. LMIV &mdash; bitte sprich uns an.<br>
    @endif
    Alle Preise inkl. MwSt.<br>
    <div class="famedo-footer__nav">
        <x-igniter-orange::nav code="footer-menu" />
    </div>
    Bestellsystem von <strong>Famedo</strong>
</div>
