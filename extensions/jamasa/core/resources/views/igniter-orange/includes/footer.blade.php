{{-- jamasa/core override of igniter-orange::includes.footer (forked from ti-theme-orange v4.1.3) --}}
{{-- Famedo compact footer: legal line + footer-menu links (Impressum/Datenschutz).
     Newsletter box and social icons dropped for pass 1. --}}
<div class="famedo-footer">
    <strong>{{ $site_name }}</strong><br>
    Allergene &amp; Zusatzstoffe gem. LMIV &middot; Alle Preise inkl. MwSt.<br>
    <div class="famedo-footer__nav">
        <x-igniter-orange::nav code="footer-menu" />
    </div>
    Bestellsystem von <strong>Famedo</strong>
</div>
