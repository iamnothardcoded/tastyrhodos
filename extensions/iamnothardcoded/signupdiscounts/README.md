# Signup Discounts for TastyIgniter v4

Automatic discounts for **registered customers** — built to drive account
sign-ups. No coupon codes to type: matching discounts apply themselves at the
cart, for logged-in customers only.

```
Zwischensumme            32,10 €
Willkommensrabatt        −3,21 €
Liefergebühr              2,50 €
GESAMT                   31,39 €
```

## Two discount slots (both optional, both configurable)

- **Welcome discount** — new customers get X% (or a fixed amount) off their
  first order, or off every order within N days of their first order. "New"
  means the *account* has no completed order yet, so long-time guest customers
  still get the discount when they register — which is exactly the point.
  Runs permanently (set-and-forget); no date range.
- **Registered-customer promo** — a *time-limited* discount for *all* logged-in
  customers ("10% for everyone in September"). Always bounded by a from–to date
  range: the **end date is mandatory**, so an all-customer discount can never
  silently run forever (that would just be a permanent price cut). An empty end
  date means the promo does not run.

Each slot: percent or fixed amount, optional minimum order total; the
registered-customer slot additionally has its mandatory promotion window. When
both slots match the same customer, the single best discount wins — they
never stack.

### Why the two slots differ

The welcome discount stops on its own — per customer, after the first order or
after X days — so it needs no calendar. The all-registered discount has no
per-customer expiry, so a date window is its only stop and is therefore
required. Each slot carries exactly the time control it actually needs.

## Behavior details

- Guests see no discount. Log in / register and it appears immediately — the
  cart survives registration.
- The discount is computed on the **items subtotal only**; delivery fees and
  tips are never discounted.
- Eligibility derives from the customer's order history — no redemption
  tables. Cancelled orders don't count as a "first order".
- The discount is a standard cart condition (`signup_discount`, priority 104)
  and persists as a negative order-totals row — it shows in the cart, at
  checkout, on invoices and in order mails automatically. Zero theme changes.
- Priority 104 keeps it after delivery/tip (100) and **before** tax conditions
  that want to compute VAT from the discounted total (e.g. the Tax Classes
  extension at 110/115).

## Installation

1. Install the extension (`extensions/iamnothardcoded/signupdiscounts/`), then
   enable the `signup_discount` condition:
   *Admin → Settings → Cart Settings → Cart Conditions tab.*
2. Configure the slots under *Admin → Settings → Discounts*.
3. Registration should auto-login new customers (default customer group
   without approval requirement) — otherwise the discount only appears after
   account activation, which weakens the sign-up nudge.

## Notes & limitations

- A customer creating a second account with a new email gets the welcome
  discount again. That friction plus the minimum order total caps the abuse at
  small change; there is deliberately no cross-account tracking.
- Coexists with the native Coupons extension: a coupon applied after the
  signup discount computes from the already-discounted total (sequential,
  not stacked).
- With inclusive VAT extensions, make sure this condition's priority sorts
  before the tax conditions so the tax base can shrink with the discount.

## License

MIT
