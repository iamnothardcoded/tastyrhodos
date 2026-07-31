{{-- „zzgl. 0,15 € Pfand" price hint for a menu item (PAngV §7: the deposit is
     shown NEXT TO the price, never inside it). Include from your theme wherever
     an item price is displayed:

         @includeWhen($menuItem->deposit_class ?? false,
             'iamnothardcoded.bottledeposit::hint', ['menuItem' => $menuItem])

     Renders nothing for items without a deposit. --}}
@php
    $__depositAmount = \Iamnothardcoded\BottleDeposit\Classes\DepositClasses::amountFor($menuItem->deposit_class ?? null);
@endphp
@if($__depositAmount > 0)
    <span class="deposit-hint">{{ sprintf(lang('iamnothardcoded.bottledeposit::default.text_plus_deposit'), currency_format($__depositAmount)) }}</span>
@endif
