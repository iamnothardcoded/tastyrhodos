# Food Labels (LMIV) for TastyIgniter v4

Dietary badges and food-information markers per menu item, built for the EU/German
legal reality of selling non-prepacked food online:

- **Diet badges** — vegetarian / vegan / spicy, rendered as small icons on the menu
  item row. Vegan implies vegetarian (only the vegan badge shows).
- **Allergens** — seeds the 14 mandatory allergen groups of Annex II, Regulation
  (EU) No 1169/2011 (LMIV) with German letter codes (A–N) into TastyIgniter's
  **native ingredients feature** (`is_allergen = 1`). You assign them through the
  stock Ingredients field on the menu form — no parallel concept, no new widget.
- **Additives** — the German § 9 ZZulV statutory disclosure formulae
  ("mit Farbstoff", "mit Konservierungsstoff", …) as a per-dish checkbox list.
- **An honest workflow state** per dish (`unknown` / `declared` / `confirmed`):
  a dish without vetted data shows an explicit "no allergen information yet —
  please ask us" fallback instead of a silent blank. A half-tagged menu must never
  imply that untagged dishes are allergen-free (Art. 7 LMIV).

## Why the tri-state matters

For distance selling of non-prepacked food, German law (§ 5 Abs. 3 LMIDV) requires
allergen information to be available **before** the purchase is concluded — the
dine-in "ask our staff" option does not extend to online ordering (LG Berlin,
16 O 304/17 and 16 O 57/20). Displaying data on *some* dishes while others are
silently blank is worse than useless: it reads as "no allergens" where there is
simply no data. This extension makes the no-data state explicit and customer-visible.

This is tooling, not legal advice — the restaurant remains responsible for the
accuracy and completeness of its food information.

## What it adds

| Where | What |
|---|---|
| `menus` table | `diet_labels` (JSON array), `additives` (JSON array), `food_info_status` (string) |
| `ingredients` table | 14 seeded LMIV allergen rows (`is_allergen = 1`), idempotent by name |
| Menu admin form | New tab **Kennzeichnung / Food labelling**: diet checkboxes, additive checkboxes, status select |
| Views | `iamnothardcoded.foodlabels::badges` and `::infobtn` — theme-neutral partials |

`Iamnothardcoded\FoodLabels\Classes\FoodInfo::payload($menu)` returns the popup
payload (`state`: `unknown` / `declared` / `none`, allergen names, additive
formulae) for themes that build their own dialog.

## Theme integration

The extension deliberately ships **data + partials only**; the theme decides the
presentation. Minimal integration in your menu item view:

```blade
@if(class_exists(\Iamnothardcoded\FoodLabels\Classes\FoodInfo::class))
    @include('iamnothardcoded.foodlabels::badges',  ['menuItem' => $menuItemData->model])
    @include('iamnothardcoded.foodlabels::infobtn', ['menuItem' => $menuItemData->model])
@endif
```

The `infobtn` partial emits a button carrying the JSON payload in
`data-food-info`; wire it to a dialog in your theme's JS. ⚠️ If your item row is
itself clickable (add-to-cart / options modal), attach the button's click handler
in the **capture phase** and `stopPropagation()`, or tapping "i" will trigger the
row action. Style hooks: `.diet-badge`, `.diet-badge--veg|vegan|hot`, `.infobtn`.

## Notes

- Additive disclosure formulae are statutory German wording and stay German in
  every locale (the EN lang file appends a translation in parentheses).
- The allergen seed matches by exact name. If you rename a seeded ingredient row,
  a later re-migration recreates the canonical one — delete the duplicate you
  don't want; seeded rows attached to dishes are never deleted by this extension.
- Uninstalling keeps the seeded allergen rows (they may be attached to dishes).

## License

MIT
