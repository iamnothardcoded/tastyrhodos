# Bottle Deposit (Pfand) for TastyIgniter v4

Container deposits for restaurant menus — **Pfand** 🇩🇪🇦🇹, **statiegeld** 🇳🇱,
**pant** 🇩🇰🇳🇴🇸🇪🇪🇪, **pantti** 🇫🇮, **kaucja** 🇵🇱, **záloha** 🇸🇰🇨🇿,
**consigne** 🇫🇷, **garanție** 🇷🇴, **skilagjald** 🇮🇸, **užstatas** 🇱🇹,
**depozīts** 🇱🇻, deposit 🇮🇪🇲🇹. Most of Europe runs a deposit-return scheme;
no online-ordering menu should silently swallow it.

The deposit is charged **on top of the item price, as its own order line** —
never baked into the displayed price. That's not just cleaner; in Germany it's
the law (PAngV §7: a refundable security must be shown *next to* the total
price and *not included* in it; charging the single-use deposit at all is
mandatory, VerpackG §31).

```
2× Fanta 0,5l (MEHRWEG)      10,70 €
1× Coca-Cola 0,33l (EINWEG)   3,25 €
Zwischensumme                13,95 €
Pfand                         0,55 €     ← this extension
GESAMT                       14,50 €
```

## Features

- **Deposit classes** managed in settings, seeded with the German tiers:
  `einweg` 0,25 € (single-use/DPG), `mehrweg` 0,15 € (reusable),
  `mehrweg_bier` 0,08 € (reusable beer bottle). Amounts editable, classes
  addable — adapt them to any country's scheme.
- One dropdown per **menu item** (General tab → Bottle deposit).
- One summed **"Deposit" order line** (cart condition, Σ deposit × qty). It is
  a *summable* total, so it appears in the cart, at checkout, on the
  confirmation page, on invoices and in order mails **on any theme, with zero
  theme changes** — and it is persisted per order (`order_totals`), so exports
  and revenue queries see it.
- Deposits are **never discounted**: the condition runs after discount
  conditions by default (priority 108) — a coupon shrinks the food price, not
  a refundable security.
- **[Tax Classes](https://github.com/nothardcoded-labs/ti-ext-taxclasses)
  interop** (optional, auto-detected): deposits are taxed at the carrying
  drink's rate (in Germany: an ancillary supply, UStAE 10.1 Abs. 8). With Tax
  Classes installed, the deposit amounts flow into the correct per-class VAT
  base automatically. Without it, nothing changes — the deposit is still
  charged and itemized correctly.

## Installation

1. Install the extension, then run `php artisan igniter:up` (adds the
   `deposit_class` column on menus).
2. Check *Admin → Settings → Bottle Deposit* — the German classes are
   pre-seeded; adjust amounts/labels for your scheme.
3. Make sure the **Deposit** condition is enabled under *Admin → Settings →
   Cart Settings → Cart Conditions* (priority 108 by default: after
   discounts, before tax lines).
4. Tag your drinks: menu item form → General tab → *Bottle deposit*.

## Showing „plus 0,15 € deposit" next to the price (optional, recommended)

The order line needs no theme support. The per-item hint next to the menu
price (required by German price-display law) is a theme concern — the
extension ships a ready partial. In your theme, wherever an item price is
rendered:

```blade
@includeWhen($menuItem->deposit_class ?? false,
    'iamnothardcoded.bottledeposit::hint', ['menuItem' => $menuItem])
```

Renders e.g. `<span class="deposit-hint">zzgl. 0,15 € Pfand</span>` (localized;
nothing for deposit-free items). Style `.deposit-hint` to taste.

## Notes & limitations

- **Item-level only (v1):** deposits attach to menu items, not to size/option
  values. If your 0,33 l and 0,5 l are option values of one item rather than
  separate items, both get the item's single deposit class.
- **Returns/refunds of empties** are out of scope — that's cash handling at
  the counter, like every ordering platform handles it (i.e. not at all).
- **Class codes are a stable vocabulary.** Menu items store the code; rename
  labels freely, but changing a code orphans items referencing it (they then
  simply charge no deposit).
- Your fiscal system remains authoritative for tax reporting; this extension
  makes the shop's charged and recorded totals match reality.

## License

MIT
