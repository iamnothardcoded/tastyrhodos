{{-- jamasa/core override of igniter-orange::includes.cartbox.items (forked from ti-theme-orange v4.1.3) --}}
{{-- Famedo cart lines. Contracts preserved: wire:key per row, stepper
     wire:click strings + wire:loading/wire:target icon swaps, edit trigger
     (orange-modal with rowId), $previewMode branch (checkout cart-preview
     reuses this partial read-only). --}}
<div
    class="cart-items"
    wire:loading.class="opacity-75"
>
    <ul class="list-unstyled user-select-none mb-0">
        @foreach ($cart->content()->reverse() as $cartItem)
            <li class="cartline" wire:key="cart-item-{{ $cartItem->rowId }}">
                @unless($previewMode)
                    <span class="cartline__q">{{ $cartItem->qty }}</span>
                    <button
                        type="button"
                        class="cartline__main btn shadow-none text-start fw-normal p-0"
                        data-toggle="orange-modal"
                        data-component="igniter-orange::cart-item-modal"
                        data-arguments='{"menuId": {{ $cartItem->id }}, "rowId": "{{ $cartItem->rowId }}"}'
                    >
                        <span class="cartline__name d-block">{{ $cartItem->name }}</span>
                        @includeWhen($cartItem->hasOptions(), 'igniter-orange::includes.cartbox.list-item-options', ['itemOptions' => $cartItem->options])
                        @if (!empty($cartItem->comment))
                            <span class="cartline__opts d-block fst-italic">{{ $cartItem->comment }}</span>
                        @endif
                        <span class="cartline__price d-block mt-1">
                            @if ($cartItem->hasConditions())
                                <s class="text-muted">{{currency_format($cartItem->subtotalWithoutConditions())}}</s>
                            @endif
                            {{ currency_format($cartItem->subtotal) }}
                        </span>
                    </button>
                    <div class="cartline__right">
                        <div class="qty cart-qty">
                            <button
                                wire:click="onUpdateItemQuantity('{{ $cartItem->rowId }}', 'minus')"
                                wire:loading.class="disabled"
                                type="button"
                                aria-label="&minus;"
                            >
                                <i class="fa fa-minus fa-fw"
                                   wire:loading.class="fa-spinner fa-spin"
                                   wire:loading.class.remove="fa-minus"
                                   wire:target="onUpdateItemQuantity('{{ $cartItem->rowId }}', 'minus')"
                                ></i>
                            </button>
                            <button
                                wire:click="onUpdateItemQuantity('{{ $cartItem->rowId }}', 'plus')"
                                wire:loading.class="disabled"
                                type="button"
                                aria-label="+"
                            >
                                <i class="fa fa-plus fa-fw"
                                   wire:loading.class="fa-spinner fa-spin"
                                   wire:loading.class.remove="fa-plus"
                                   wire:target="onUpdateItemQuantity('{{ $cartItem->rowId }}', 'plus')"
                                ></i>
                            </button>
                        </div>
                    </div>
                @else
                    <span class="cartline__q">{{ $cartItem->qty }}</span>
                    <div class="cartline__main">
                        <span class="cartline__name d-block">{{ $cartItem->name }}</span>
                        @includeWhen($cartItem->hasOptions(), 'igniter-orange::includes.cartbox.list-item-options', ['itemOptions' => $cartItem->options])
                        @if (!empty($cartItem->comment))
                            <span class="cartline__opts d-block fst-italic">{{ $cartItem->comment }}</span>
                        @endif
                    </div>
                    <div class="cartline__price ms-3">
                        @if ($cartItem->hasConditions())
                            <s class="text-muted">{{currency_format($cartItem->subtotalWithoutConditions())}}</s>
                        @endif
                        {{ currency_format($cartItem->subtotal) }}
                    </div>
                @endunless
            </li>
        @endforeach
    </ul>
</div>
