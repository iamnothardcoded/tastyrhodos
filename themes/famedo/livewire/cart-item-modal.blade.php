{{-- jamasa/core override of igniter-orange::livewire.cart-item-modal (forked from ti-theme-orange v4.1.3) --}}
{{-- Famedo item sheet. Contracts preserved verbatim: root x-data="OrangeCartItem()"
     + data attrs (cart-item.js), modal-dialog class, wire:submit="onSave",
     hidden wire:model menuId/rowId, #menu-options + x-ref="item-options",
     qty stepper x-on:click/x-model, submit data-attach-loading + x-text="total". --}}
<div
    x-data="OrangeCartItem()"
    class="modal-dialog"
    data-control="cart-item"
    data-min-quantity="{{ $minQuantity }}"
    data-price-amount="{{ $price }}"
>
    <form method="POST" wire:submit="onSave">
        <div class="modal-content border-0">
            <button
                type="button"
                class="sheet__grip"
                data-bs-dismiss="modal"
                aria-label="{{ __('jamasa.core::default.ui.close') }}"
            ></button>
            <button
                type="button"
                class="btn-close famedo-sheet-close"
                data-bs-dismiss="modal"
                aria-label="{{ __('jamasa.core::default.ui.close') }}"
            ></button>
            @if ($showThumb && $menuItemData->hasThumb())
                <div class="modal-top sheet__cover" style="background-image:url('{!! $menuItemData->getThumb(['width' => 1200, 'height' => 400]) !!}')"></div>
            @endif

            <div class="modal-body sheet__scroll">
                <div class="sheet__titlerow">
                    <h4 class="sheet__title">{{ $menuItemData->name }}</h4>
                    @if(class_exists(\Iamnothardcoded\FoodLabels\Classes\FoodInfo::class))
                        @include('iamnothardcoded.foodlabels::badges', ['menuItem' => $menuItemData->model])
                        @include('iamnothardcoded.foodlabels::infobtn', ['menuItem' => $menuItemData->model])
                    @endif
                </div>
                @if (strlen($menuItemData->description))
                    <p class="sheet__desc">{!! $menuItemData->description !!}</p>
                @endif

                <input type="hidden" wire:model="menuId" />
                <input type="hidden" wire:model="rowId" />

                <div
                    id="menu-options"
                    class="menu-options"
                    x-ref="item-options"
                >
                    @include('igniter-orange::includes.cartbox.item-options')
                </div>
                <x-igniter-orange::forms.error field="menuOptions" class="text-danger mb-3"/>

                <div class="menu-comment mt-3">
                    <textarea
                        wire:model="comment"
                        name="comment"
                        class="cart-note"
                        rows="2"
                        placeholder="@lang('igniter.cart::default.label_add_comment')"
                    >{{ $cartItem ? $cartItem->comment : null }}</textarea>
                    <x-igniter-orange::forms.error field="comment" class="text-danger"/>
                </div>
            </div>

            <div class="modal-footer border-0 sheet__foot">
                <div class="qty">
                    <button
                        x-on:click="decrementQuantity()"
                        type="button"
                        aria-label="&minus;"
                    >&minus;</button>
                    <input
                        x-model="quantity"
                        type="text"
                        name="quantity"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        min="0"
                        readonly
                        autocomplete="off"
                    >
                    <button
                        x-on:click="incrementQuantity()"
                        type="button"
                        aria-label="+"
                    >+</button>
                </div>
                <button type="submit" class="btn-add" data-attach-loading>
                    <span>{!! $cartItem
                        ? lang('igniter.cart::default.button_update')
                        : lang('igniter.cart::default.button_add_to_order')
                    !!}</span>
                    <span x-text="total"></span>
                </button>
                {{-- Shown INSTEAD of qty/btn-add while body.ordering-paused/-closed
                     (lockout CSS hides them) — explains the missing add button. --}}
                <div class="sheet__locked">@lang('jamasa.core::default.overlay.sheet_locked')</div>
            </div>
        </div>
    </form>
</div>
