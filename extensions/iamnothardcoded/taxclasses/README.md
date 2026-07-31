# Tax Classes for TastyIgniter v4

Per-item VAT tax classes for countries with a split VAT rate — built for Germany
(7% reduced for takeaway/delivery food, 19% standard for drinks), works for any
two-rate scheme (Ireland 13.5/23, Belgium 6/21, …).

TastyIgniter core supports a single global tax rate. This extension replaces it
with **two inclusive tax lines**, each computed from the gross prices of the items
in its class:

```
Zwischensumme            33,30 €
inkl. 7% MwSt.            1,40 €
inkl. 19% MwSt.           1,90 €
GESAMT                   33,30 €
```

Prices stay gross (tax is back-calculated and never added on top), totals are
rounded once per class, and the two lines are persisted as separate order totals —
so they appear in the cart, at checkout, on invoices and in order mails
automatically.

## Features

- Two tax classes (reduced / standard), rates configurable — defaults 7% / 19%
- Assign a class per **menu item**, per **category** (items inherit), or fall back
  to a configurable default class
- Correct German gross handling: back-calculated inclusive VAT, per-class rounding
- Delivery charge taxed in a configurable class (default: standard rate)
- No mandatory theme changes — uses standard cart conditions and order totals
  (one optional one-liner for the order-confirmation page, see *Theme
  compatibility* below)

## Installation

1. Install the extension (marketplace or `extensions/iamnothardcoded/taxclasses/`),
   then run `php artisan igniter:up` to add the `tax_class` columns.
2. **Disable the core VAT condition** — otherwise you get a third, wrong tax line:
   *Admin → Settings → Cart Settings → Cart Conditions tab → toggle "VAT" off.*
   Leave "VAT reduced rate" and "VAT standard rate" enabled (they are by default).
3. Set your rates and defaults under *Admin → Settings → Tax Classes*.
4. Tag your categories: typically set the drinks category to *Standard* and leave
   everything else on the default class (*Reduced*). Individual items can override
   their category on the menu item form (General tab → Tax class).

## Theme compatibility

The tax lines are **inclusive** (they don't change the total), so they are
stored as *non-summable* order totals. Every surface that renders all totals
rows shows them automatically: cart summary, checkout, **invoice, order mails,
admin order screen** — and they are persisted in `order_totals`, so exports and
revenue/VAT queries see them regardless of theme.

One exception: the stock **Orange theme's order-confirmation (success) page**
skips non-summable rows, so the two MwSt. lines are hidden *on that page only*.
If you want them there, override `igniter-orange::includes.order.items` in your
child theme (copy the file from
`ti-theme-orange/resources/views/includes/order/items.blade.php` to
`yourtheme/includes/order/items.blade.php`) and extend the skip condition to
let `tax*` rows through:

```blade
{{-- before --}}
@continue(!$thickLine && !$orderTotal->is_summable && $orderTotal->code !== 'subtotal')
{{-- after --}}
@continue(!$thickLine && !$orderTotal->is_summable && $orderTotal->code !== 'subtotal'
    && !str_starts_with((string)$orderTotal->code, 'tax'))
```

## Notes & limitations

- **Item in several categories with different classes:** the first classed
  category wins. Set the class on the item itself to be explicit.
- **§19 UStG (Kleinunternehmer) / no VAT display:** disable both tax-class
  conditions in the Cart Conditions tab — no tax lines are shown anywhere.
- **Discounts/Coupons (since v1.1):** the tax lines are discount-aware — any
  cart-level discount condition applied *before* the tax conditions (priority
  below 110) shrinks the per-class VAT bases pro-rata (largest-remainder split,
  still one rounding per class). ⚠️ The native coupon's default priority is 200
  (after tax) — set it below 110 in *Cart Settings → Cart Conditions* to get
  discount-aware VAT. Item-scoped (menu-item) coupons are always correct (they
  reduce the item subtotals directly). Delivery-fee coupons are excluded from
  the allocation: the VAT on the delivery charge itself is not reduced
  (documented edge case).
- **PayPal:** the itemized tax field in PayPal's order breakdown (populated only
  for a condition literally named `tax`) stays empty. Charged amounts are correct.
- Your fiscal system (e.g. TSE-Kasse) remains authoritative for tax reporting;
  this extension makes the shop's displayed and recorded totals match reality.

## License

MIT
