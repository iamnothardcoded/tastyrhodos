{{-- famedo theme override of igniter-orange::includes.cartbox.list-item-options (forked from ti-theme-orange v4.1.3) --}}
{{-- Compact option display (decided 2026-07-17): selected values ONLY, comma-joined
     on one muted line. The vendor version prints each option-GROUP name plus every
     value on its own line — bloats the cart/checkout basket. Qty prefix ("2 x")
     and surcharge "(+…)" kept per value. Pure display partial, no JS contracts. --}}
<span class="cartline__opts d-block small text-muted">
    {{
        $itemOptions->flatMap(fn($itemOption) => $itemOption->values)->map(function ($optionValue) {
            $text = $optionValue->qty > 1
                ? $optionValue->qty.' '.lang('igniter.cart::default.text_times').' '.$optionValue->name
                : $optionValue->name;

            return $optionValue->price > 0
                ? $text.' ('.currency_format($optionValue->subtotal()).')'
                : $text;
        })->implode(', ')
    }}
</span>
