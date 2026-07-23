<?php

declare(strict_types=1);

namespace Iamnothardcoded\TaxClasses\Listeners;

use Igniter\Admin\Widgets\Form;
use Igniter\Cart\Models\Menu;

class AddsMenuTaxClassField
{
    public function __invoke(Form $form): void
    {
        if (!$form->model instanceof Menu) {
            return;
        }

        $form->addTabFields([
            'tax_class' => [
                'tab' => 'lang:igniter.cart::default.menus.text_tab_general',
                'label' => 'lang:iamnothardcoded.taxclasses::default.label_tax_class',
                'comment' => 'lang:iamnothardcoded.taxclasses::default.help_tax_class_menu',
                'type' => 'select',
                'span' => 'left',
                // TI's select partial always renders one empty option on top — make it
                // the "inherit" choice instead of shipping a second empty-value option.
                'placeholder' => 'lang:iamnothardcoded.taxclasses::default.text_class_inherit',
                'options' => [
                    'reduced' => 'lang:iamnothardcoded.taxclasses::default.text_class_reduced',
                    'standard' => 'lang:iamnothardcoded.taxclasses::default.text_class_standard',
                ],
            ],
        ]);
    }
}
