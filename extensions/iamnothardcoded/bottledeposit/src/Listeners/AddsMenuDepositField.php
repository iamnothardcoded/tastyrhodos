<?php

declare(strict_types=1);

namespace Iamnothardcoded\BottleDeposit\Listeners;

use Iamnothardcoded\BottleDeposit\Classes\DepositClasses;
use Igniter\Admin\Widgets\Form;
use Igniter\Cart\Models\Menu;

class AddsMenuDepositField
{
    public function __invoke(Form $form): void
    {
        if (!$form->model instanceof Menu) {
            return;
        }

        $form->addTabFields([
            'deposit_class' => [
                'tab' => 'lang:igniter.cart::default.menus.text_tab_general',
                'label' => 'lang:iamnothardcoded.bottledeposit::default.label_deposit_class',
                'comment' => 'lang:iamnothardcoded.bottledeposit::default.help_deposit_class',
                'type' => 'select',
                'span' => 'left',
                // TI's select partial always renders one empty option on top —
                // it doubles as the "no deposit" choice.
                'placeholder' => 'lang:iamnothardcoded.bottledeposit::default.text_no_deposit',
                'options' => DepositClasses::options(),
            ],
        ]);
    }
}
