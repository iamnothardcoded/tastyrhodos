{{-- famedo theme override of igniter-orange::includes.cartbox.list-item-options (forked from ti-theme-orange v4.1.3) --}}
{{-- Compact option display (decided 2026-07-17): selected values ONLY — the
     vendor version also prints each option-GROUP name as its own line, which
     bloats the cart/checkout basket. One line per value (user preference),
     qty prefix ("2 x") and surcharge "(+…)" kept. Pure display, no JS contracts. --}}
<span class="cartline__opts d-block small text-muted">
    @foreach ($itemOptions->flatMap(fn($itemOption) => $itemOption->values) as $optionValue)
        <span class="d-block">
            @if ($optionValue->qty > 1)
                {{ $optionValue->qty }} @lang('igniter.cart::default.text_times')
            @endif
            {{ $optionValue->name }}@if ($optionValue->price > 0) ({{ currency_format($optionValue->subtotal()) }})@endif
        </span>
    @endforeach
</span>
