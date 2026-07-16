{{-- jamasa/core override of igniter-orange::includes.menu.item (forked from ti-theme-orange v4.1.3) --}}
{{-- Famedo item row: text left, thumb + floating add button right.
     Contracts preserved: outer id, orange-modal trigger for option items,
     cart-box:add-item dispatch + data-control="menu-item" for simple items,
     the <i> with wire:loading.class (menu-item-list JS spins it). --}}
@php($hasThumb = $showThumb && $menuItemData->hasThumb())
@php($isAvailable = $menuItemData->mealtimeIsAvailable())
<div
    id="menu{{ $menuItemData->id }}"
    @class([
        'item',
        'item--noimg' => !$hasThumb,
        'soldout' => !$isAvailable,
        'cursor-pointer' => $isAvailable,
    ])
    @if($isAvailable)
        @if($menuItemData->hasOptions())
            data-toggle="orange-modal"
            data-component="igniter-orange::cart-item-modal"
            data-arguments='{"menuId": {{ $menuItemData->id }}}'
        @else
            wire:click="$dispatch('cart-box:add-item', {menuId: {{ $menuItemData->id }}, quantity: {{ $menuItemData->minimumQuantity }}})"
            data-control="menu-item"
        @endif
    @endif
>
    <div class="item__main">
        <div class="item__titlerow">
            <span class="item__name">{{ $menuItemData->name }}</span>
            @unless($isAvailable)
                <span class="soldout-tag">@lang('igniter.cart::default.mealtimes.text_available') {{ $menuItemData->mealtimeTitles() }}</span>
            @endunless
        </div>
        @if(strlen(strip_tags((string) $menuItemData->description)))
            <div class="item__desc">{!! $menuItemData->description !!}</div>
        @endif
        <div class="item__price">
            {!! $menuItemData->price() > 0 ? currency_format($menuItemData->price()) : lang('igniter::main.text_free') !!}
            @if ($menuItemData->specialIsActive())
                <s>{!! currency_format($menuItemData->priceBeforeSpecial) !!}</s>
                @if ($menuItemData->specialDaysRemaining())
                    <span class="text-warning small">{!! sprintf(lang('igniter.local::default.text_end_elapsed'), $menuItemData->specialDaysRemaining()) !!}</span>
                @endif
            @endif
        </div>
        @includeWhen($menuItemData->hasIngredients(), 'igniter-orange::includes.menu.ingredients', [
            'ingredients' => $menuItemData->ingredients()
        ])
    </div>
    <div class="item__side">
        @if($hasThumb)
            <div
                class="item__img"
                style="background-image:url('{{ $menuItemData->getThumb(['width' => 132, 'height' => 132]) }}')"
            ></div>
        @endif
        @if($isAvailable)
            <button type="button" @class(['addbtn', 'addbtn--float' => $hasThumb])>
                <i class="fa fa-plus" wire:loading.class="fa-spinner fa-spin"></i>
            </button>
        @endif
    </div>
</div>
