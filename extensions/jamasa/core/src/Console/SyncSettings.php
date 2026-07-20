<?php

declare(strict_types=1);

namespace Jamasa\Core\Console;

use Igniter\Local\Models\Location;
use Igniter\Local\Models\LocationSettings;
use Igniter\Local\Models\ReviewSettings;
use Igniter\Main\Classes\ThemeManager;
use Igniter\Main\Models\Theme;
use Igniter\Pages\Models\Menu;
use Igniter\Pages\Models\MenuItem;
use Igniter\Pages\Models\Page;
use Igniter\PayRegister\Models\Payment;
use Igniter\System\Models\Currency;
use Igniter\System\Models\Language;
use Igniter\User\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Converge a famedo tenant's DB-value settings to the current desired state.
 *
 * This is the idempotent replacement for the per-tenant "one-liner" bookkeeping:
 * because every setting below is a "set to X" assignment, running it against a
 * tenant that is already correct is a no-op, and running it against one that is
 * behind fixes it — so you never have to know WHICH snippet a tenant was missing.
 * Run it after every image bump (`igniter:up --force` first for schema).
 *
 * Two buckets, deliberately separated:
 *   - INVARIANTS (always): famedo-managed values a tenant should never diverge
 *     from — German site default, EUR format, cookie/GDPR texts, German payment
 *     & order-status labels, the legal-minimum footer nav, the GDPR "more info"
 *     link. Re-asserted every run.
 *   - PROVISIONING DEFAULTS (--defaults, first run only): values a tenant may
 *     legitimately override later (reservations off, reviews off, lorem pages
 *     unpublished). Only applied at provisioning so convergence never clobbers
 *     a deliberate tenant choice.
 *   - CONTENT (guarded): legal page bodies are seeded from the shipped template
 *     ONLY while still placeholder/lorem/unchanged — never over a tenant's real
 *     filled-in text (unless --force-content).
 *
 * new-tenant.sh should eventually call this with --activate --defaults instead
 * of duplicating the inline tinker; until that unification is tested end-to-end
 * on a fresh tenant, the two are kept in sync by hand.
 */
class SyncSettings extends Command
{
    protected $signature = 'famedo:sync-settings
        {--activate : Activate the famedo theme (fresh install / deliberate orange→famedo cutover)}
        {--defaults : Apply provisioning defaults (reservations off, reviews off, lorem pages unpublished) — first run only}
        {--force-content : Overwrite legal page bodies from the template even if a tenant has customized them}';

    protected $description = 'Idempotently converge a famedo tenant\'s DB settings to the current desired state.';

    public function handle(): int
    {
        $this->syncLanguageAndCurrency();
        $this->syncTheme();
        $this->syncPaymentsAndStatuses();
        $this->syncLegalPagesAndGdprLink();
        $this->syncFooterAndMainNav();

        if ($this->option('defaults')) {
            $this->applyProvisioningDefaults();
        } else {
            $this->line('  · provisioning defaults skipped (pass --defaults on first run)');
        }

        $this->info('famedo:sync-settings complete.');

        return self::SUCCESS;
    }

    /** German site default + EUR German number format (invariant). */
    protected function syncLanguageAndCurrency(): void
    {
        $en = Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'status' => 1]);
        $de = Language::firstOrCreate(['code' => 'de'], ['name' => 'German', 'status' => 1]);
        if (!$de->status) {
            $de->status = 1;
            $de->save();
        }
        Language::updateDefault('de');
        Language::applySupportedLanguages();
        // Admin panel stays English for all staff (a null language_id inherits
        // the German site default → German admin, which we don't want).
        User::query()->update(['language_id' => $en->language_id]);

        Currency::where('currency_code', 'EUR')->update([
            'currency_status' => 1,
            'symbol_position' => 1,
            'thousand_sign' => '.',
            'decimal_sign' => ',',
            'currency_symbol' => "\u{00A0}€",
        ]);
        Currency::where('currency_code', 'GBP')->update(['currency_status' => 0, 'is_default' => 0]);
        Currency::where('currency_code', 'EUR')->update(['is_default' => 1]);

        $this->line('  ✓ language=de (admin=en), currency=EUR (de format)');
    }

    /** Ensure famedo theme record exists + carries the famedo GDPR texts. */
    protected function syncTheme(): void
    {
        Theme::syncAll();

        if ($this->option('activate')) {
            Theme::activateTheme('famedo');
            $this->line('  ✓ famedo theme activated');
        }

        $famedo = Theme::where('code', 'famedo')->first();
        if (!$famedo) {
            $this->warn('  ! famedo theme not found on disk — skipping theme data + nav');

            return;
        }

        $data = (array) $famedo->data;
        $data['gdpr_cookie_message'] = 'Wir verwenden nur technisch notwendige Cookies – zum Beispiel für deinen Warenkorb und deine Bestellung.';
        $data['gdpr_accept_text'] = 'Verstanden';
        $data['gdpr_more_info_text'] = 'Mehr erfahren';
        $famedo->data = $data;
        $famedo->save();

        $active = resolve(ThemeManager::class)->getActiveThemeCode();
        if ($active !== 'famedo') {
            $this->warn("  ! active theme is '{$active}', not famedo — settings staged on the famedo record but NOT live. Activate via runbook §1.8 (or re-run with --activate).");
        }

        $this->line('  ✓ cookie/GDPR texts');
    }

    /** German payment labels + order-status names (invariant). Never touches
     *  payment status (enabled/disabled) — that is per-tenant (credentials). */
    protected function syncPaymentsAndStatuses(): void
    {
        Payment::syncAll();
        $p = DB::table('payments');
        $p->where('code', 'cod')->update(['name' => 'Barzahlung', 'description' => 'Bezahle bar bei Abholung oder bei Lieferung deiner Bestellung']);
        $p->where('code', 'paypalexpress')->update(['name' => 'PayPal', 'description' => 'Bezahle bequem mit deinem PayPal-Konto']);
        $p->where('code', 'mollie')->update(['name' => 'Online bezahlen', 'description' => 'Sicher bezahlen mit Karte, Apple Pay und mehr']);

        $statuses = [
            1 => 'Eingegangen', 2 => 'Ausstehend', 3 => 'In Zubereitung', 4 => 'In Lieferung',
            5 => 'Abgeschlossen', 6 => 'Bestätigt', 7 => 'Storniert', 8 => 'Ausstehend', 9 => 'Storniert',
        ];
        foreach ($statuses as $id => $name) {
            DB::table('statuses')->where('status_id', $id)->update(['status_name' => $name]);
        }

        // "Angenommen" (id 10) — the print-queue status in the order rail. Created
        // by the jamasa migration; ensured + kept silent (notify=0) here so
        // acceptance never mails the customer (the "In Zubereitung" mail fires on
        // 10 -> 3). Idempotent: create if the migration hasn't run yet.
        if (!DB::table('statuses')->where('status_id', 10)->exists()) {
            DB::table('statuses')->insert([
                'status_id' => 10, 'status_name' => 'Angenommen', 'notify_customer' => 0,
                'status_for' => 'order', 'status_color' => '#5FA9C4',
            ]);
        } else {
            DB::table('statuses')->where('status_id', 10)->update(['status_name' => 'Angenommen', 'notify_customer' => 0]);
        }

        // Status 10 is an in-progress state for the CUSTOMER tracking page — add
        // it to processing_order_status so the progress bar lights up while an
        // order is at 10 (otherwise it renders empty). Idempotent.
        $proc = array_map('strval', (array) setting('processing_order_status', []));
        if (!in_array('10', $proc, true)) {
            $proc[] = '10';
            setting()->set(['processing_order_status' => $proc]);
        }

        $this->line('  ✓ payment labels + order-status names (German)');
    }

    /** Seed legal page bodies from the shipped template — guarded so a tenant's
     *  real filled-in text is never clobbered — and wire the GDPR "more info"
     *  link to the Datenschutz page. */
    protected function syncLegalPagesAndGdprLink(): void
    {
        $imp = $this->firstOrNewPage('impressum', 'Impressum');
        $pol = $this->firstOrNewPage('datenschutz', 'Datenschutzerklärung');

        $impTpl = $this->legalTemplate('impressum.html');
        $polTpl = $this->legalTemplate('datenschutz.html');

        foreach ([[$imp, $impTpl, 'Impressum'], [$pol, $polTpl, 'Datenschutz']] as [$page, $tpl, $label]) {
            if ($tpl === null) {
                $this->warn("  ! {$label} template missing on disk — skipped");

                continue;
            }
            if ($this->shouldSeedContent($page->content, $tpl)) {
                $page->content = $tpl;
                $page->save();
                $this->line("  ✓ {$label} page seeded from template");
            } else {
                // NOTE: once seeded, TI's HTMLPurifier strips the template's
                // class attrs on save, so the stored body no longer byte-matches
                // the raw template — we can't distinguish "seeded, untouched"
                // from "tenant-customized" here. Both are simply left alone; a
                // template change reaches an already-populated page only via
                // --force-content (deliberate).
                $this->line("  · {$label} page kept (already populated — use --force-content to reseed)");
            }
        }

        // GDPR "more info" link → Datenschutz page (resolve by slug, store id).
        $famedo = Theme::where('code', 'famedo')->first();
        if ($famedo && $pol->exists) {
            $data = (array) $famedo->data;
            $data['gdpr_more_info_link'] = (string) $pol->getKey();
            $famedo->data = $data;
            $famedo->save();
            $this->line('  ✓ cookie banner "Mehr erfahren" → Datenschutz');
        }
    }

    /** Footer = legal-minimum (Impressum · Datenschutz); main nav drops the
     *  reservation items (invariant famedo structure). */
    protected function syncFooterAndMainNav(): void
    {
        $footer = Menu::where('code', 'footer-menu')->where('theme_code', 'famedo')->first();
        if (!$footer) {
            $this->warn('  ! famedo footer-menu not found (theme not installed?) — nav skipped');

            return;
        }

        $imp = Page::where('permalink_slug', 'impressum')->first();
        $pol = Page::where('permalink_slug', 'datenschutz')->first();

        // Rebuild converges to the same 3 items every run (item ids churn, the
        // resulting structure does not).
        MenuItem::where('menu_id', $footer->getKey())->get()->each->delete();
        $header = $this->makeMenuItem($footer->getKey(), null, 'igniter.orange::default.text_information', 'header', 1);
        if ($imp) {
            $this->makeMenuItem($footer->getKey(), $header->getKey(), 'Impressum', 'static-page', 2, (string) $imp->getKey());
        }
        if ($pol) {
            $this->makeMenuItem($footer->getKey(), $header->getKey(), 'Datenschutz', 'static-page', 3, (string) $pol->getKey());
        }

        $main = Menu::where('code', 'main-menu')->where('theme_code', 'famedo')->first();
        if ($main) {
            MenuItem::where('menu_id', $main->getKey())
                ->whereIn('title', ['igniter.orange::default.menu_reservation', 'igniter.orange::default.menu_recent_reservation'])
                ->get()->each->delete();
        }

        $this->line('  ✓ footer nav (Impressum · Datenschutz), reservation nav removed');
    }

    /** First run only: values a tenant may legitimately flip later. */
    protected function applyProvisioningDefaults(): void
    {
        Page::whereIn('permalink_slug', ['about-us', 'terms-and-conditions'])->update(['status' => 0]);

        foreach (Location::all() as $location) {
            $booking = LocationSettings::instance($location, 'booking');
            $booking->is_enabled = 0;
            $booking->save();
        }

        ReviewSettings::set('allow_reviews', 0);

        $this->line('  ✓ defaults applied: reservations off, reviews off, lorem pages unpublished');
    }

    protected function firstOrNewPage(string $slug, string $title): Page
    {
        $page = Page::where('permalink_slug', $slug)->first();
        if (!$page) {
            $page = new Page;
            $page->language_id = Language::where('code', 'de')->value('language_id')
                ?? Language::query()->value('language_id');
            $page->permalink_slug = $slug;
            $page->title = $title;
            $page->layout = 'static';
            $page->status = 1;
            $page->content = '';
        }

        return $page;
    }

    protected function legalTemplate(string $file): ?string
    {
        $path = base_path("themes/famedo/legal/{$file}");
        if (!is_file($path)) {
            return null;
        }

        return preg_replace('/^<!--.*?-->\s*/s', '', (string) file_get_contents($path));
    }

    /** Seed only while the page is still empty / placeholder / lorem, or already
     *  equals the template (re-assert = harmless, lets template updates land on
     *  un-customized pages). Real tenant content (none of these) is protected. */
    protected function shouldSeedContent(?string $current, string $template): bool
    {
        if ($this->option('force-content')) {
            return true;
        }
        $current = (string) $current;
        if (trim(strip_tags($current)) === '') {
            return true;
        }
        if (str_contains($current, 'Lorem ipsum') || str_contains($current, 'folgt in Kürze')) {
            return true;
        }

        return trim($current) === trim($template);
    }

    protected function makeMenuItem(int $menuId, ?int $parentId, string $title, string $type, int $priority, string $reference = ''): MenuItem
    {
        $item = new MenuItem;
        $item->menu_id = $menuId;
        $item->parent_id = $parentId;
        $item->title = $title;
        $item->code = '';
        $item->type = $type;
        $item->reference = $reference;
        $item->priority = $priority;
        $item->save();

        return $item;
    }
}
