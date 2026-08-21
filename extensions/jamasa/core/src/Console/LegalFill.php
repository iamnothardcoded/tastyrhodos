<?php

declare(strict_types=1);

namespace Jamasa\Core\Console;

use Igniter\Pages\Models\Page;
use Illuminate\Console\Command;

/**
 * Renders the Impressum + Datenschutzerklärung from owner-supplied data.
 *
 * WHY THIS EXISTS: both pages ship as templates full of "[Straße Hausnummer]"
 * placeholders, and every tenant so far went live with them unfilled — which
 * makes the ordering page unlawful to advertise (§ 5 DDG needs a complete
 * provider block, Art. 13 DSGVO the controller). Filling them by hand is
 * error-prone and nobody remembers the field list, so it never happened.
 *
 * THE ONE RULE: tenant legal data comes from the OWNER (questionnaire), never
 * from a platform we scraped. Lieferando's colophon looks like a shortcut and
 * publishes THEIR helpdesk as the restaurant's legal contact — see the header
 * of lieferando-to-tastyigniter/scrape_lieferando.py.
 *
 * ⚠️ Filling happens on the TEMPLATE, before the result is written to the page.
 * TI runs page content through HTMLPurifier on save, which strips class and
 * data-* attributes — so the markers only survive in the theme file, never in
 * the stored page. Re-running always re-renders from the template.
 */
class LegalFill extends Command
{
    protected $signature = 'famedo:legal-fill
        {--from= : JSON file with the legal data (machine-readable Fragebogen output)}
        {--dry-run : Render and report, write nothing}
        {--show-fields : Print the field list the templates require, then exit}';

    protected $description = 'Fill Impressum + Datenschutz from owner-supplied data';

    /** Fields without which a page must not be published. */
    protected const REQUIRED = [
        'company', 'street', 'city', 'representative', 'phone', 'email',
        'controller', 'restaurant_name', 'supervisory_authority',
    ];

    /** Optional — an empty value drops the whole surrounding data-optional block. */
    protected const OPTIONAL = [
        'register_court', 'register_number', 'vat_id',
        'content_responsible', 'payment_methods', 'payment_provider', 'stand',
    ];

    /** Bundesland data-protection authority, derived from the postcode so the
     *  owner never has to look it up. Only the leading digits are needed. */
    protected const AUTHORITY_BY_PLZ = [
        '4' => 'Landesbeauftragte für Datenschutz und Informationsfreiheit Nordrhein-Westfalen (LDI NRW), Kavalleriestraße 2–4, 40213 Düsseldorf, www.ldi.nrw.de',
        '5' => 'Landesbeauftragte für Datenschutz und Informationsfreiheit Nordrhein-Westfalen (LDI NRW), Kavalleriestraße 2–4, 40213 Düsseldorf, www.ldi.nrw.de',
    ];

    public function handle(): int
    {
        if ($this->option('show-fields')) {
            $this->line('PFLICHT:   '.implode(', ', self::REQUIRED));
            $this->line('OPTIONAL:  '.implode(', ', self::OPTIONAL));
            $this->newLine();
            $this->line('Leeres optionales Feld => der zugehörige Abschnitt wird komplett entfernt');
            $this->line('(Registereintrag bei Einzelunternehmen, USt-IdNr. wenn keine vorhanden).');

            return self::SUCCESS;
        }

        $data = $this->loadData();
        if ($data === null) {
            return self::FAILURE;
        }

        $data = $this->derive($data);

        $missing = array_values(array_filter(
            self::REQUIRED,
            fn($f) => trim((string)($data[$f] ?? '')) === '',
        ));
        if ($missing !== []) {
            $this->error('Pflichtfelder fehlen — nichts geschrieben:');
            foreach ($missing as $f) {
                $this->line("   · {$f}");
            }
            $this->newLine();
            $this->warn('§ 5 DDG verlangt einen vollständigen Anbieterblock inkl. E-Mail.');
            $this->warn('Ohne diese Angaben darf die Bestellseite nicht öffentlich beworben werden.');

            return self::FAILURE;
        }

        foreach ([
            'impressum' => ['impressum.html', 'Impressum'],
            'datenschutz' => ['datenschutz.html', 'Datenschutzerklärung'],
        ] as $slug => [$file, $title]) {
            $tpl = $this->template($file);
            if ($tpl === null) {
                $this->error("Vorlage fehlt: themes/famedo/legal/{$file}");

                return self::FAILURE;
            }

            $html = $this->render($tpl, $data);
            $left = preg_match_all('/data-field="(\w+)"/', $html, $m) ? array_unique($m[1]) : [];
            if ($left !== []) {
                $this->error("{$slug}: nicht ersetzte Felder: ".implode(', ', $left));

                return self::FAILURE;
            }

            if ($this->option('dry-run')) {
                $this->line("  [dry-run] {$slug}: ".strlen($html).' Bytes gerendert');
                continue;
            }

            $page = Page::firstOrNew(['permalink_slug' => $slug]);
            $page->title = $page->title ?: $title;
            $page->language_id = $page->language_id ?: 1;
            $page->status = 1;
            $page->content = $html;
            $page->save();
            $this->info("  ✓ {$slug} geschrieben (".strlen($html).' Bytes)');
        }

        $this->newLine();
        $this->line('Stand: '.$data['stand']);
        $this->warn('⚠️  Vor dem Livegang juristisch prüfen lassen — die Vorlage ist kein Rechtsrat.');

        return self::SUCCESS;
    }

    /** @return array<string,string>|null */
    protected function loadData(): ?array
    {
        $path = (string)$this->option('from');
        if ($path === '') {
            $this->error('--from=<datei.json> fehlt.');
            $this->line('Feldliste:  php artisan famedo:legal-fill --show-fields');

            return null;
        }
        if (!is_file($path)) {
            $this->error("Datei nicht gefunden: {$path}");

            return null;
        }

        $raw = json_decode((string)file_get_contents($path), true);
        if (!is_array($raw)) {
            $this->error("Kein gültiges JSON: {$path}");

            return null;
        }

        // accept both a flat object and a Fragebogen wrapper {"legal": {...}}
        return array_map(
            fn($v) => is_scalar($v) ? trim((string)$v) : '',
            is_array($raw['legal'] ?? null) ? $raw['legal'] : $raw,
        );
    }

    /** Fill in what can be derived rather than asked. */
    protected function derive(array $data): array
    {
        if (trim((string)($data['stand'] ?? '')) === '') {
            $months = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli',
                'August', 'September', 'Oktober', 'November', 'Dezember'];
            $data['stand'] = $months[(int)date('n')].' '.date('Y');
        }

        // § 18 Abs. 2 MStV responsible defaults to the representative
        if (trim((string)($data['content_responsible'] ?? '')) === '') {
            $data['content_responsible'] = trim(($data['representative'] ?? '').', Anschrift wie oben');
        }

        // Controller for the DSGVO block defaults to "<Firma>, Inhaber <Name>" —
        // but only when the company string doesn't already name the owner.
        // Owners routinely write "El Greco, Inhaber Rafail Tsoutsoulis" into the
        // company field, and appending blindly produced that name twice.
        if (trim((string)($data['controller'] ?? '')) === '' && ($data['company'] ?? '') !== '') {
            $company = (string)$data['company'];
            $rep = trim((string)($data['representative'] ?? ''));
            $data['controller'] = ($rep !== '' && mb_stripos($company, $rep) === false)
                ? $company.', Inhaber '.$rep
                : $company;
        }

        if (trim((string)($data['restaurant_name'] ?? '')) === '') {
            $data['restaurant_name'] = (string)($data['company'] ?? '');
        }

        // supervisory authority from the postcode — the owner should not have
        // to know which Landesbehörde is competent
        if (trim((string)($data['supervisory_authority'] ?? '')) === '') {
            $plz = ltrim(preg_replace('/\D/', '', (string)($data['city'] ?? '')), '');
            $first = substr($plz, 0, 1);
            $data['supervisory_authority'] = self::AUTHORITY_BY_PLZ[$first] ?? '';
        }

        return $data;
    }

    /** Substitute every data-field span; drop data-optional blocks left empty. */
    protected function render(string $tpl, array $data): string
    {
        // 1. optional sections whose driving field is empty disappear entirely
        $tpl = preg_replace_callback(
            '#<div data-optional="(\w+)">(.*?)</div>#s',
            function(array $m) use ($data) {
                $driver = $m[1] === 'register' ? 'register_number' : $m[1];

                return trim((string)($data[$driver] ?? '')) === '' ? '' : $m[2];
            },
            $tpl,
        );

        // 2. every remaining placeholder span becomes its plain value
        return preg_replace_callback(
            '#<span class="todo" data-field="(\w+)">.*?</span>#s',
            fn(array $m) => htmlspecialchars((string)($data[$m[1]] ?? ''), ENT_QUOTES, 'UTF-8'),
            $tpl,
        );
    }

    protected function template(string $file): ?string
    {
        $path = base_path("themes/famedo/legal/{$file}");

        return is_file($path) ? (string)file_get_contents($path) : null;
    }
}
