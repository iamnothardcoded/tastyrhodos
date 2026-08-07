<?php

declare(strict_types=1);

namespace Jamasa\Core\Console;

use Igniter\Admin\Models\Status;
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
        $this->syncMailSender();
        $this->syncOrderMailRouting();
        $this->syncTheme();
        $this->syncPaymentsAndStatuses();
        $this->syncTaxConditions();
        $this->syncSignupDiscountConditions();
        $this->syncBottleDepositCondition();
        $this->syncTipCondition();
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

        // Distance unit: TI ships 'mi' (found on elgrecomarl 2026-08-07, while
        // laconchiglia was already 'km'). It does NOT affect the delivery-area
        // circle test — the radius stays effectively meters because both sides of
        // pointInRadius() go through convertToUserUnit(), so the unit cancels —
        // but it drives every distance the storefront/admin DISPLAYS.
        setting()->set(['distance_unit' => 'km']);

        $this->line('  ✓ language=de (admin=en), currency=EUR (de format), distance=km');
    }

    /** De-brand the email From-name. The install seeds `sender_name` = "TastyIgniter",
     *  which is the one brand string a diner reads (mail inbox From). Converge it to
     *  the tenant's own `site_name` — but ONLY when it is still the empty/install-seed
     *  value, so a restaurant that deliberately set a different From-name keeps it. */
    protected function syncMailSender(): void
    {
        $current = (string) setting('sender_name');
        if ($current === '' || $current === 'TastyIgniter') {
            $siteName = (string) setting('site_name');
            if ($siteName !== '') {
                setting()->set(['sender_name' => $siteName]);
                $this->line("  ✓ mail sender_name → '{$siteName}' (was '".($current ?: 'empty')."')");

                return;
            }
        }
        $this->line("  · mail sender_name kept ('{$current}')");
    }

    /**
     * Order-mail routing. Two separate defects were live on the whole fleet until
     * 2026-08-07 and neither was visible without reading the DB:
     *
     * 1. `order_email` shipped as [customer, ADMIN], and TI resolves the admin
     *    recipient to `site_email` — a PLATFORM setting, and it shipped as a
     *    placeholder, so the copy bounced. Target state since 2026-08-07 is
     *    **[customer] only**: the PRINTER is the restaurant's order channel, and a
     *    mail per order is noise plus shared ESP quota (which gates login). The one
     *    thing a printed slip cannot carry is a CANCELLATION — that is mailed to the
     *    location by NotifyLocationOnCancel, independently of this setting.
     * 2. `site_email` / `sender_email` / `location_email` all ship as the install
     *    placeholder `admin@domain.tld` — a domain that does not exist. So every
     *    single order fired a mail into a hard bounce: the owner got no copy, and
     *    the bounces quietly eroded our sending reputation.
     *
     * The routing is a platform convention → converged here. The ADDRESS is
     * per-tenant owner data → cannot be invented, so we WARN instead of guessing.
     */
    protected function syncOrderMailRouting(): void
    {
        $recipients = (array) setting('order_email', []);
        if ($recipients !== ['customer']) {
            setting()->set(['order_email' => ['customer']]);
            $this->line('  ✓ order_email → [customer] (was ['.implode(', ', $recipients).'])');
        } else {
            $this->line('  · order_email kept ([customer])');
        }

        $placeholder = 'admin@domain.tld';
        $location = Location::first();
        $bad = [];
        foreach ([
            'location_email' => $location?->location_email,
            'site_email' => setting('site_email'),
            'sender_email' => setting('sender_email'),
        ] as $key => $value) {
            if ($value === null || $value === '' || $value === $placeholder) {
                $bad[] = $key;
            }
        }

        if ($bad !== []) {
            $this->warn('  ⚠ '.implode(', ', $bad).' still unset/placeholder ('.$placeholder.')');
            $this->warn('    → order alerts BOUNCE and the owner gets no copy.');
            $this->warn("    → set the restaurant's real address (onboarding data, runbook §1.11a).");
        } else {
            $this->line('  ✓ order-alert addresses are real (no '.$placeholder.' left)');
        }

        $this->syncOrderStatusNotifications();
    }

    /**
     * Silence the status mails the customer does not need. Measured on a real
     * order (elgrecomarl #22): placing it produced FIVE mails for a logged-in
     * customer — confirmation to customer + restaurant, then "Eingegangen"
     * (status 1) and "In Zubereitung" (status 3) seconds later, plus the login
     * code. The two status mails arrive while the confirmation is still
     * unread — three mails in ten seconds reads as spam, trains people to
     * ignore our mail (which costs real deliverability) and burns the ESP quota
     * that gates LOGIN, because the daily cap is per ACCOUNT across all tenants.
     *
     * Dropping them takes a guest order from 4 mails to 2 and a logged-in one
     * from 5 to 3. The inverse holds for a CANCELLATION — the one state the
     * customer cannot discover on their own — so that one is forced ON.
     * "In Lieferung" stays a per-tenant choice (only meaningful once a tenant
     * actually runs its own drivers).
     */
    protected function syncOrderStatusNotifications(): void
    {
        $silenced = Status::query()
            ->where('status_for', 'order')
            ->whereIn('status_id', [1, 3])          // Eingegangen, In Zubereitung
            ->where('notify_customer', 1)
            ->update(['notify_customer' => 0]);

        // A CANCELLATION is the opposite case: the customer has no other way to
        // learn their order is off — no slip, no push, and they may already be
        // on their way to collect it. Always notify.
        $cancelled = (int) setting('canceled_order_status', 9);
        $enabled = Status::query()
            ->where('status_for', 'order')
            ->where('status_id', $cancelled)
            ->where('notify_customer', 0)
            ->update(['notify_customer' => 1]);

        $this->line($silenced > 0
            ? "  ✓ order-status mails: silenced {$silenced} redundant notification(s) (status 1/3)"
            : '  · order-status mails already quiet (status 1/3)');
        $this->line($enabled > 0
            ? "  ✓ cancellation now notifies the customer (status {$cancelled})"
            : "  · cancellation already notifies the customer (status {$cancelled})");
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

    /** With the tax-classes extension present, the split 7%/19% conditions carry
     *  the VAT lines and the core single-rate condition must be off (three tax
     *  lines otherwise). Invariant — a future §19-Kleinunternehmer tenant (no VAT
     *  shown at all) is a manual runbook exception. */
    protected function syncTaxConditions(): void
    {
        if (!class_exists(\Iamnothardcoded\TaxClasses\Extension::class)) {
            $this->line('  · tax conditions skipped (tax-classes extension not installed)');

            return;
        }

        $conditions = (array) \Igniter\Cart\Models\CartSettings::get('conditions');
        $conditions['tax']['status'] = 0;
        $conditions['tax_reduced']['status'] = 1;
        $conditions['tax_standard']['status'] = 1;
        \Igniter\Cart\Models\CartSettings::set('conditions', $conditions);

        $this->line('  ✓ tax conditions: core VAT off, tax classes 7%/19% on');
    }

    /** The tip CONDITION must mirror the tipping TOGGLE. TI's enable_tipping=0
     *  only hides the tip UI — the condition stays loaded, so a tip amount
     *  stuck in a session cart keeps adding to the total INVISIBLY (no row:
     *  the totals view skips the tip row in the loop and the tip section is
     *  gated on tippingEnabled). Found 2026-08-01: dev cart 2,50 € heavier
     *  than its rows with correct-looking VAT. */
    protected function syncTipCondition(): void
    {
        $enabled = (bool) \Igniter\Cart\Models\CartSettings::get('enable_tipping');
        $conditions = (array) \Igniter\Cart\Models\CartSettings::get('conditions');
        if (!isset($conditions['tip'])) {
            return;
        }

        $conditions['tip']['status'] = $enabled ? 1 : 0;
        foreach ($conditions as &$condition) {
            unset($condition['className'], $condition['description']);
        }
        unset($condition);
        \Igniter\Cart\Models\CartSettings::set('conditions', $conditions);

        $this->line(sprintf('  ✓ tip condition %s (mirrors enable_tipping)', $enabled ? 'on' : 'off'));
    }

    /** Bottle deposit (Pfand) runs at 108 — after the discounts (104/105; a
     *  deposit is a refundable security, never discounted) and before the
     *  tax-class conditions (110/115), which fold the deposit into the
     *  carrying drink's VAT base via the collectExtraBases event. */
    protected function syncBottleDepositCondition(): void
    {
        if (!class_exists(\Iamnothardcoded\BottleDeposit\Extension::class)) {
            $this->line('  · bottle-deposit condition skipped (bottledeposit extension not installed)');

            return;
        }

        $conditions = (array) \Igniter\Cart\Models\CartSettings::get('conditions');
        $conditions['bottle_deposit']['name'] = 'bottle_deposit';
        $conditions['bottle_deposit']['label'] = 'lang:iamnothardcoded.bottledeposit::default.text_deposit';
        $conditions['bottle_deposit']['status'] = 1;
        $conditions['bottle_deposit']['priority'] = 108;
        // Same code-owned-key strip as the signup bucket (see its comment).
        foreach ($conditions as &$condition) {
            unset($condition['className'], $condition['description']);
        }
        unset($condition);
        \Igniter\Cart\Models\CartSettings::set('conditions', $conditions);

        $this->line('  ✓ bottle_deposit @108 (after discounts, before tax)');
    }

    /** Signup discount (registered-customer discounts) runs at 104 — after
     *  delivery/tip (100), before the tax-class conditions (110/115) so the VAT
     *  bases shrink pro-rata. The native coupon is re-prioritized 200 → 105 for
     *  the same reason (also fixes coupon's breach of the order_totals tinyint
     *  priority ceiling of 127). */
    protected function syncSignupDiscountConditions(): void
    {
        if (!class_exists(\Iamnothardcoded\SignupDiscounts\Extension::class)) {
            $this->line('  · signup-discount conditions skipped (signupdiscounts extension not installed)');

            return;
        }

        $conditions = (array) \Igniter\Cart\Models\CartSettings::get('conditions');
        $conditions['signup_discount']['status'] = 1;
        $conditions['signup_discount']['priority'] = 104;
        $conditions['coupon']['priority'] = 105;
        // Pin the tax priorities too (if present) so an admin can't drag a
        // discount BELOW tax in the Cart-Conditions UI — that silently stops
        // discounts shrinking the VAT base → VAT over-reported.
        foreach (['tax_reduced' => 110, 'tax_standard' => 115] as $name => $priority) {
            if (isset($conditions[$name])) {
                $conditions[$name]['priority'] = $priority;
            }
        }
        // ⚠️ CartSettings::get() MERGES the registered className/description into
        // each row; persisting them freezes a class path in the DB, so a future
        // rename/relocate throws in CartManager::makeCondition (storefront 500).
        // Strip the code-owned keys — they re-merge from the registered condition
        // at read time. We keep only what the admin form itself stores.
        foreach ($conditions as &$condition) {
            unset($condition['className'], $condition['description']);
        }
        unset($condition);
        \Igniter\Cart\Models\CartSettings::set('conditions', $conditions);

        $this->line('  ✓ signup_discount @104, coupon @105, tax @110/115 (discounts before tax)');

        // The register→discount moment needs auto-login; an approval-required
        // default customer group breaks it. Warn only — per-tenant decision.
        $group = \Igniter\User\Models\CustomerGroup::getDefault();
        if ($group?->requiresApproval()) {
            $this->warn("  ! default customer group '{$group->group_name}' requires approval — new accounts won't auto-login, the signup discount won't appear right after registration");
        }

        // Enabled coupons are LIVE discount codes (demo data seeds some).
        if (class_exists(\Igniter\Coupons\Models\Coupon::class)) {
            $codes = \Igniter\Coupons\Models\Coupon::query()->where('status', 1)->pluck('code');
            if ($codes->isNotEmpty()) {
                $this->warn('  ! enabled coupon codes exist: '.$codes->implode(', ').' — verify these are intended (demo seeds must not reach tenants)');
            }
        }
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
