{{-- Food-info "i" button. Expects: $menuItem (Igniter\Cart\Models\Menu).
     Renders on EVERY item (explicit-unknown model: absence of data must show
     as "no data yet", never as allergen-free). The payload travels as
     Blade-escaped JSON in a data attribute — no extra roundtrip; the theme's
     JS opens a shared dialog from it (capture-phase click handler, since the
     surrounding row usually has its own click behaviour). --}}
<button
    type="button"
    class="infobtn"
    aria-haspopup="dialog"
    aria-label="{{ lang('iamnothardcoded.foodlabels::default.text_dialog_title') }}"
    data-food-info="{{ json_encode(\Iamnothardcoded\FoodLabels\Classes\FoodInfo::payload($menuItem), JSON_UNESCAPED_UNICODE) }}"
>i</button>
