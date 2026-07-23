<?php

declare(strict_types=1);

namespace Iamnothardcoded\TaxClasses\Listeners;

use Igniter\Admin\Widgets\Form;
use Igniter\Cart\Models\Category;

class AddsCategoryTaxClassField
{
    public function __invoke(Form $form): void
    {
        if (!$form->model instanceof Category) {
            return;
        }

        $form->addFields([
            'tax_class' => [
                'label' => 'lang:iamnothardcoded.taxclasses::default.label_tax_class',
                'comment' => 'lang:iamnothardcoded.taxclasses::default.help_tax_class_category',
                'type' => 'select',
                'span' => 'left',
                // TI's select partial always renders one empty option on top — make it
                // the "no class / default" choice instead of a second empty-value option.
                'placeholder' => 'lang:iamnothardcoded.taxclasses::default.text_class_default',
                'options' => [
                    'reduced' => 'lang:iamnothardcoded.taxclasses::default.text_class_reduced',
                    'standard' => 'lang:iamnothardcoded.taxclasses::default.text_class_standard',
                ],
            ],
        ]);
    }
}
