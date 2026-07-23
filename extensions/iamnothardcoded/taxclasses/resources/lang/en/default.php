<?php

return [
    'text_settings' => 'Tax Classes',
    'help_settings' => 'Configure the reduced/standard VAT rates, the default class and the delivery charge class.',

    'text_tax_reduced' => 'VAT reduced rate',
    'help_tax_reduced' => 'Inclusive VAT on items in the reduced class (e.g. food at 7% in Germany).',
    'text_tax_standard' => 'VAT standard rate',
    'help_tax_standard' => 'Inclusive VAT on items in the standard class (e.g. drinks at 19% in Germany).',

    // sprintf: %s = rate ("7", "19")
    'text_incl_vat' => 'incl. %s%% VAT',

    'label_tax_class' => 'Tax class',
    'help_tax_class_menu' => 'Leave on "Inherit" to use the category\'s tax class (or the default class).',
    'help_tax_class_category' => 'Menu items without their own tax class inherit this one.',

    'text_class_inherit' => 'Inherit (category / default)',
    'text_class_default' => 'Default class',
    'text_class_reduced' => 'Reduced (e.g. 7%)',
    'text_class_standard' => 'Standard (e.g. 19%)',

    'label_reduced_rate' => 'Reduced rate (%)',
    'help_reduced_rate' => 'VAT percentage for the reduced class. Germany: 7.',
    'label_standard_rate' => 'Standard rate (%)',
    'help_standard_rate' => 'VAT percentage for the standard class. Germany: 19.',
    'label_default_class' => 'Default tax class',
    'help_default_class' => 'Applied to menu items with no class of their own and no classed category.',
    'label_delivery_charge_class' => 'Delivery charge tax class',
    'help_delivery_charge_class' => 'Which class the delivery charge is taxed in. Ask your tax advisor; standard rate is common.',
];
