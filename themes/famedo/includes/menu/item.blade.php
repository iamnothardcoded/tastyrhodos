{{-- jamasa/core override of igniter-orange::includes.menu.item (forked from ti-theme-orange v4.1.3) --}}
{{-- Famedo item row: text left, thumb + floating add button right.
     Contracts preserved: outer id, orange-modal trigger for option items,
     cart-box:add-item dispatch + data-control="menu-item" for simple items,
     the <i> with wire:loading.class (menu-item-list JS spins it). --}}
@php($hasThumb = $showThumb && $menuItemData->hasThumb())
@php($isAvailable = $menuItemData->mealtimeIsAvailable())
@php($__dietFilter = class_exists(\Iamnothardcoded\FoodLabels\Classes\DietLabels::class)
    ? \Iamnothardcoded\FoodLabels\Classes\DietLabels::filterCodes((array)($menuItemData->model->diet_labels ?? []))
    : [])
<div
    id="menu{{ $menuItemData->id }}"
    @class([
        'item',
        'item--noimg' => !$hasThumb,
        'soldout' => !$isAvailable,
        'cursor-pointer' => $isAvailable,
    ])
    @if($__dietFilter !== [])
        data-diet="{{ implode(' ', $__dietFilter) }}"
    @endif
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
            @if(class_exists(\Iamnothardcoded\FoodLabels\Classes\FoodInfo::class))
                @include('iamnothardcoded.foodlabels::badges', ['menuItem' => $menuItemData->model])
                @include('iamnothardcoded.foodlabels::infobtn', ['menuItem' => $menuItemData->model])
            @endif
        </div>
        @if(strlen(strip_tags((string) $menuItemData->description)))
            <div class="item__desc">{!! $menuItemData->description !!}</div>
        @endif
        <div class="item__price">
            {!! $menuItemData->price() > 0 ? currency_format($menuItemData->price()) : lang('igniter::main.text_free') !!}
            {{-- Pfand hint (PAngV §7: deposit shown NEXT TO the price, never in it) --}}
            @includeWhen(
                class_exists(\Iamnothardcoded\BottleDeposit\Classes\DepositClasses::class) && ($menuItemData->model->deposit_class ?? false),
                'iamnothardcoded.bottledeposit::hint', ['menuItem' => $menuItemData->model]
            )
            @if ($menuItemData->specialIsActive())
                <s>{!! currency_format($menuItemData->priceBeforeSpecial) !!}</s>
                @if ($menuItemData->specialDaysRemaining())
                    <span class="text-warning small">{!! sprintf(lang('igniter.local::default.text_end_elapsed'), $menuItemData->specialDaysRemaining()) !!}</span>
                @endif
            @endif
        </div>
        {{-- orange's grey ingredient pills deliberately dropped: hover-tooltip
             UI is useless on phones — allergens/additives live in the
             foodlabels "i" dialog instead (explicit-unknown model). --}}
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
