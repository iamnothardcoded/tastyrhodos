<?php

declare(strict_types=1);

namespace Iamnothardcoded\SignupDiscounts\Classes;

use Iamnothardcoded\SignupDiscounts\Models\SignupDiscountSettings;
use Igniter\Cart\CartContent;
use Igniter\User\Models\Customer;
use Illuminate\Support\Carbon;

/**
 * Picks the single best matching discount slot for a customer + cart.
 * Eligibility derives entirely from the customer's order history — no
 * redemption tables: "new customer" simply means this account has no completed
 * order yet, so the first completed order ends the eligibility by itself.
 *
 * The welcome-discount slot runs permanently (set-and-forget) — no promotion
 * period, because each customer's discount expires on its own (first order, or
 * X days). The all-registered slot has no per-customer expiry — every account
 * holder, every order — so a Von–Bis window is its ONLY stop and is mandatory:
 * without an end date it does not run (never a silent permanent margin cut).
 */
class DiscountManager
{
    /** @var array<int, array{count: int, first: ?Carbon}> per-request memo —
     *  conditions apply several times per request */
    protected static array $orderStats = [];

    /**
     * @return ?array{amount: float, label: string}
     */
    public static function bestDiscount(Customer $customer, CartContent $content): ?array
    {
        $itemsSubtotal = (float)$content->subtotal();
        if ($itemsSubtotal <= 0) {
            return null;
        }

        $best = null;
        foreach (self::campaigns() as $campaign) {
            // Same reference as the native coupon's min_total: raw item prices.
            $minTotal = (float)($campaign['min_total'] ?? 0);
            if ($minTotal > 0 && (float)$content->subtotalWithoutConditions() < $minTotal) {
                continue;
            }

            if (!self::matches($campaign, $customer)) {
                continue;
            }

            $amount = self::discountAmount($campaign, $itemsSubtotal);
            if ($amount <= 0) {
                continue;
            }

            // Best single discount wins — the slots never stack.
            if (!$best || $amount > $best['amount']) {
                $best = ['amount' => $amount, 'label' => (string)($campaign['label'] ?? '')];
            }
        }

        return $best;
    }

    /**
     * The welcome (new-customer) slot as a campaign array, or null when off.
     * Runs permanently — no promotion period (each customer's discount expires
     * on its own after the first order / X days).
     *
     * @return ?array<string, mixed>
     */
    protected static function welcomeCampaign(): ?array
    {
        if (!SignupDiscountSettings::get('new_enabled')) {
            return null;
        }

        return [
            'audience' => 'new_customers',
            'label' => (string)SignupDiscountSettings::get('new_label', ''),
            'type' => (string)SignupDiscountSettings::get('new_type', 'percent'),
            'amount' => SignupDiscountSettings::get('new_amount', 0),
            'min_total' => SignupDiscountSettings::get('new_min_total', 0),
            'mode' => (string)SignupDiscountSettings::get('new_mode', 'first_order_only'),
            'window_days' => SignupDiscountSettings::get('new_window_days', 30),
        ];
    }

    /**
     * The all-registered promo slot as a campaign array, or null when off.
     * Always date-bounded — a mandatory Von–Bis is its only stop.
     *
     * @return ?array<string, mixed>
     */
    protected static function promoCampaign(): ?array
    {
        if (!SignupDiscountSettings::get('all_enabled')) {
            return null;
        }

        return [
            'audience' => 'all_registered',
            'label' => (string)SignupDiscountSettings::get('all_label', ''),
            'type' => (string)SignupDiscountSettings::get('all_type', 'percent'),
            'amount' => SignupDiscountSettings::get('all_amount', 0),
            'date_from' => SignupDiscountSettings::get('all_date_from'),
            'date_to' => SignupDiscountSettings::get('all_date_to'),
            'min_total' => SignupDiscountSettings::get('all_min_total', 0),
        ];
    }

    /**
     * The two configured discount slots (settings tabs) as campaign arrays.
     *
     * @return array<int, array<string, mixed>>
     */
    protected static function campaigns(): array
    {
        return array_values(array_filter([
            self::welcomeCampaign(),
            self::promoCampaign(),
        ]));
    }

    protected static function matches(array $campaign, Customer $customer): bool
    {
        // All-registered slot: only runs inside its mandatory Von–Bis window.
        if (($campaign['audience'] ?? 'new_customers') === 'all_registered') {
            return self::inActivePeriod($campaign);
        }

        // New-customer slot runs permanently — eligibility is purely the
        // account's order history.
        $stats = self::orderStats($customer);

        // No completed order on this ACCOUNT yet = new customer. Prior guest
        // orders under the same email deliberately do NOT disqualify: getting
        // guests to register is the point of the feature.
        if ($stats['count'] === 0) {
            return true;
        }

        if (($campaign['mode'] ?? 'first_order_only') === 'window_days') {
            // Every order within X days of the first completed order (the first
            // order started the window and is itself discounted).
            $days = max(1, (int)($campaign['window_days'] ?? 0));

            return $stats['first'] instanceof Carbon
                && Carbon::now()->lte($stats['first']->copy()->addDays($days));
        }

        return false;
    }

    /**
     * True only if now falls inside the campaign's Von–Bis window. The end date
     * is MANDATORY: a missing "Bis" means the promo does not run (fail-closed —
     * an all-registered discount must never bleed forever). A missing "Von"
     * means "active immediately", so it only needs the end.
     */
    protected static function inActivePeriod(array $campaign): bool
    {
        $to = $campaign['date_to'] ?? null;
        if (!$to) {
            return false;
        }

        $now = Carbon::now();
        $from = $campaign['date_from'] ?? null;

        if ($from && $now->lt(Carbon::parse($from)->startOfDay())) {
            return false;
        }

        return $now->lte(Carbon::parse($to)->endOfDay());
    }

    protected static function discountAmount(array $campaign, float $itemsSubtotal): float
    {
        $amount = (float)($campaign['amount'] ?? 0);
        if ($amount <= 0) {
            return 0;
        }

        $value = ($campaign['type'] ?? 'percent') === 'percent'
            ? round($itemsSubtotal * $amount / 100, 2)
            : round($amount, 2);

        // Never discount more than the items are worth (delivery/tip untouched).
        return min($value, $itemsSubtotal);
    }

    /**
     * @return array{count: int, first: ?Carbon}
     */
    protected static function orderStats(Customer $customer): array
    {
        $id = (int)$customer->getKey();

        if (!array_key_exists($id, self::$orderStats)) {
            // "Completed" = actually placed (processed) and not cancelled —
            // mirrors the core dashboard's non-cancelled logic. The unprocessed
            // rows every checkout session creates are excluded by processed=1.
            $query = $customer->orders()->where('processed', 1);
            if ($canceledStatus = setting('canceled_order_status')) {
                $query->where('status_id', '!=', $canceledStatus);
            }

            $first = $query->min('created_at');

            self::$orderStats[$id] = [
                'count' => (int)$query->count(),
                'first' => $first ? Carbon::parse($first) : null,
            ];
        }

        return self::$orderStats[$id];
    }

    public static function flush(): void
    {
        self::$orderStats = [];
    }

    //
    // Storefront teasers (guest-facing; presentation lives in the theme).
    //

    /**
     * Wording for the "first-order offer" teaser, generated from the welcome
     * settings. Null when the welcome discount is off or has no amount.
     *
     * @return ?array{amount_label: string, headline: string, subline: string}
     */
    public static function welcomeTeaser(): ?array
    {
        $c = self::welcomeCampaign();
        if (!$c || (float)($c['amount'] ?? 0) <= 0) {
            return null;
        }

        $amountLabel = self::amountLabel($c);

        $headline = ($c['mode'] ?? 'first_order_only') === 'window_days'
            ? lang('iamnothardcoded.signupdiscounts::default.teaser_window', [
                'amount' => $amountLabel,
                'days' => max(1, (int)($c['window_days'] ?? 0)),
            ])
            : lang('iamnothardcoded.signupdiscounts::default.teaser_first', [
                'amount' => $amountLabel,
            ]);

        return [
            'amount_label' => $amountLabel,
            'headline' => $headline,
            'subline' => lang('iamnothardcoded.signupdiscounts::default.teaser_sub'),
        ];
    }

    /**
     * Wording for the order-success signup prompt ("next time you'd save X").
     * Null when the welcome discount is off. `done` is shown after the account
     * is created.
     *
     * @return ?array{amount_label: string, headline: string, subline: string, done: string}
     */
    public static function successTeaser(): ?array
    {
        if (!$t = self::welcomeTeaser()) {
            return null;
        }

        $amount = $t['amount_label'];

        return [
            'amount_label' => $amount,
            'headline' => lang('iamnothardcoded.signupdiscounts::default.teaser_success', ['amount' => $amount]),
            'subline' => lang('iamnothardcoded.signupdiscounts::default.teaser_success_sub'),
            'done' => lang('iamnothardcoded.signupdiscounts::default.teaser_success_done', ['amount' => $amount]),
        ];
    }

    /**
     * The euro amount a not-yet-registered visitor WOULD save on this cart via
     * the welcome discount — the "sign up and you'd save this" figure. Ignores
     * the login/eligibility check by design. 0 when off, empty, or below min.
     */
    public static function wouldBeWelcomeAmount(CartContent $content): float
    {
        $c = self::welcomeCampaign();
        if (!$c) {
            return 0.0;
        }

        $itemsSubtotal = (float)$content->subtotal();
        if ($itemsSubtotal <= 0) {
            return 0.0;
        }

        $minTotal = (float)($c['min_total'] ?? 0);
        if ($minTotal > 0 && (float)$content->subtotalWithoutConditions() < $minTotal) {
            return 0.0;
        }

        return self::discountAmount($c, $itemsSubtotal);
    }

    /**
     * The discount a logged-in customer currently qualifies for, for a
     * reassuring "active" banner (welcome takes display precedence over promo).
     * Null when the customer qualifies for nothing right now.
     *
     * @return ?array{headline: string, subline: string}
     */
    public static function activeOfferFor(Customer $customer): ?array
    {
        $offers = [];

        $welcome = self::welcomeCampaign();
        if ($welcome && (float)($welcome['amount'] ?? 0) > 0 && self::matches($welcome, $customer)) {
            $offers[] = ['slot' => 'new', 'campaign' => $welcome];
        }

        $promo = self::promoCampaign();
        if ($promo && (float)($promo['amount'] ?? 0) > 0 && self::matches($promo, $customer)) {
            $offers[] = ['slot' => 'all', 'campaign' => $promo];
        }

        if ($offers === []) {
            return null;
        }

        // Show the offer that would actually win at checkout (best-wins). The
        // cart isn't known here, so rank by a proxy: higher percentage beats
        // lower, percentage beats fixed, higher fixed beats lower. So an active
        // promo that's a better deal DOES replace the welcome banner.
        usort($offers, fn(array $a, array $b): int => self::rank($b['campaign']) <=> self::rank($a['campaign']));
        $best = $offers[0];

        return $best['slot'] === 'new'
            ? self::welcomeOfferPayload($best['campaign'], $customer)
            : self::promoOfferPayload($best['campaign']);
    }

    protected static function rank(array $campaign): float
    {
        $amount = (float)($campaign['amount'] ?? 0);

        return ($campaign['type'] ?? 'percent') === 'percent' ? 1000 + $amount : $amount;
    }

    /** @return array{headline: string, subline: string} */
    protected static function promoOfferPayload(array $c): array
    {
        $headline = self::amountLabel($c).' '.self::displayLabel($c, 'all');

        // The promo always has a mandatory end date → always show days-left.
        if ($to = ($c['date_to'] ?? null)) {
            $left = (int)ceil(max(0.0, Carbon::now()->diffInDays(Carbon::parse($to)->endOfDay(), false)));
            $sub = $left === 1
                ? lang('iamnothardcoded.signupdiscounts::default.active_sub_promo_days_one')
                : lang('iamnothardcoded.signupdiscounts::default.active_sub_promo_days', ['days' => $left]);
        } else {
            $sub = lang('iamnothardcoded.signupdiscounts::default.active_sub_promo');
        }

        return ['headline' => $headline, 'subline' => $sub];
    }

    /** @return array{headline: string, subline: string} */
    protected static function welcomeOfferPayload(array $c, Customer $customer): array
    {
        $headline = self::amountLabel($c).' '.self::displayLabel($c, 'new');

        if (($c['mode'] ?? 'first_order_only') !== 'window_days') {
            return ['headline' => $headline, 'subline' => lang('iamnothardcoded.signupdiscounts::default.active_sub_first')];
        }

        $days = max(1, (int)($c['window_days'] ?? 0));
        $stats = self::orderStats($customer);

        // No first order yet: advertise the full window from the first order.
        if (!$stats['first'] instanceof Carbon) {
            return [
                'headline' => $headline,
                'subline' => lang('iamnothardcoded.signupdiscounts::default.active_sub_window', ['days' => $days]),
            ];
        }

        // Mid-window: show days remaining (rounded up).
        $end = $stats['first']->copy()->addDays($days);
        $left = (int)ceil(max(0.0, Carbon::now()->diffInDays($end, false)));
        $sub = $left === 1
            ? lang('iamnothardcoded.signupdiscounts::default.active_sub_days_one')
            : lang('iamnothardcoded.signupdiscounts::default.active_sub_days', ['days' => $left]);

        return ['headline' => $headline, 'subline' => $sub];
    }

    /** The customer-set label, or a sensible default per slot. */
    protected static function displayLabel(array $campaign, string $slot): string
    {
        $label = trim((string)($campaign['label'] ?? ''));

        return $label !== ''
            ? $label
            : lang('iamnothardcoded.signupdiscounts::default.'.($slot === 'all' ? 'default_label_all' : 'default_label_new'));
    }

    /** "10 %" for a percentage slot, "5,00 €" for a fixed one. */
    protected static function amountLabel(array $campaign): string
    {
        $amount = (float)($campaign['amount'] ?? 0);

        if (($campaign['type'] ?? 'percent') === 'percent') {
            return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.').' %';
        }

        return currency_format($amount);
    }
}
